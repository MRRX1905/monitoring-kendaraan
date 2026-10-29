"""
Streaming Server HTTP & MJPEG Multi-Threaded untuk Pelacakan Kendaraan Otomatis
Bandara Malikussaleh (LSW) - Kementerian Perhubungan RI

Optimasi performa:
  - ThreadingHTTPServer (multi-threaded, tidak saling memblokir antara stream dan status/control API)
  - Frame Synchronization via Condition Variable (tidak mengirim frame duplikat)
  - Explicit flush() per frame untuk mencegah buffering stuttering pada browser
  - Garis Monitoring VERTIKAL
"""

from __future__ import annotations

import json
import os
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

import cv2
import numpy as np

# Tambahkan direktori root tracking ke sys.path
sys.path.insert(0, str(Path(__file__).resolve().parent))
from tracker_engine import VehicleTracker  # noqa: E402


class StreamState:
    def __init__(self):
        self.is_running = True              # Server background aktif
        self.is_streaming = True            # Fitur streaming kamera dihidupkan (True/False)
        self.current_frame: np.ndarray | None = None
        self.current_jpeg: bytes | None = None
        self.frame_id: int = 0              # ID frame untuk sinkronisasi unik
        self.fps: float = 0.0
        self.lock = threading.Lock()
        self.frame_condition = threading.Condition(self.lock)
        self.tracker: VehicleTracker | None = None
        self.recent_events: list = []


state = StreamState()


def video_processing_worker(tracker: VehicleTracker):
    """Worker background untuk membaca video, deteksi YOLOv8, dan encoding JPEG."""
    src_path = tracker.video_source
    print(f"[Worker] Membuka video source: {src_path}")

    cap = cv2.VideoCapture(src_path)
    if not cap.isOpened():
        print(f"[Worker ERROR] Gagal membuka video source: {src_path}", file=sys.stderr)
        return

    frame_count = cap.get(cv2.CAP_PROP_FRAME_COUNT)
    fps_video = cap.get(cv2.CAP_PROP_FPS) or 25.0
    frame_delay = 1.0 / max(10.0, min(fps_video, 30.0))

    prev_time = time.perf_counter()

    while state.is_running:
        # Jika streaming sedang dimatikan (OFF): jeda sejenak untuk hemat CPU
        if not state.is_streaming:
            time.sleep(0.15)
            # Buat frame standby beresolusi ringan
            standby = np.zeros((400, 720, 3), dtype=np.uint8)
            cv2.rectangle(standby, (30, 30), (690, 370), (20, 24, 35), -1)
            cv2.putText(standby, "STREAM KAMERA DINONAKTIFKAN", (150, 190),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.8, (120, 130, 150), 2, cv2.LINE_AA)
            cv2.putText(standby, "Klik 'Hidupkan Stream' pada panel kontrol untuk memulai monitoring",
                        (110, 230), cv2.FONT_HERSHEY_SIMPLEX, 0.48, (90, 100, 120), 1, cv2.LINE_AA)
            _, buf = cv2.imencode(".jpg", standby, [cv2.IMWRITE_JPEG_QUALITY, 70])
            with state.frame_condition:
                state.current_jpeg = buf.tobytes()
                state.frame_id += 1
                state.frame_condition.notify_all()
            continue

        t_loop_start = time.perf_counter()
        ret, frame = cap.read()

        # Looping otomatis video demo secara mulus tanpa freeze
        if not ret or frame is None:
            cap.set(cv2.CAP_PROP_POS_FRAMES, 0)
            ret, frame = cap.read()
            if not ret or frame is None:
                cap.release()
                cap = cv2.VideoCapture(src_path)
                ret, frame = cap.read()
            if not ret or frame is None:
                time.sleep(0.04)
                continue

        # Resize ke lebar optimal 768 untuk performa tinggi & FPS halus
        h, w = frame.shape[:2]
        target_w = int(tracker.cfg.get("max_display_width", 768))
        if w > target_w:
            new_h = int(h * (target_w / float(w)))
            frame = cv2.resize(frame, (target_w, new_h))

        # Proses frame melalui tracker YOLOv8 (Garis Vertikal Masuk/Keluar)
        try:
            rendered, dets = tracker.process_frame(frame)
        except Exception as e:
            print(f"[Worker Exception] {e}", file=sys.stderr)
            rendered = frame

        # Simpan event terbaru untuk status JSON (Hanya Masuk & Keluar)
        if tracker.counter.events:
            with state.lock:
                evs = tracker.counter.events[-15:]
                state.recent_events = [
                    {
                        "time": e.datetime_str,
                        "class": e.class_name,
                        "track_id": e.track_id,
                        "direction": e.direction,
                        "conf": round(e.confidence * 100)
                    }
                    for e in reversed(evs)
                ]

        # Encode ke JPEG berkualitas baik namun ringan (quality 72)
        _, jpeg_buf = cv2.imencode(".jpg", rendered, [cv2.IMWRITE_JPEG_QUALITY, 72])
        jpeg_bytes = jpeg_buf.tobytes()

        # Hitung FPS aktual loop pemrosesan
        t_loop_end = time.perf_counter()
        loop_duration = t_loop_end - t_loop_start
        inst_fps = 1.0 / max(1e-4, loop_duration)

        # Update state dan notifikasi thread stream konsumen
        with state.frame_condition:
            state.current_frame = rendered
            state.current_jpeg = jpeg_bytes
            state.fps = inst_fps
            state.frame_id += 1
            state.frame_condition.notify_all()

        # Pacing sesuai FPS video asli agar tidak lag/stutter
        to_sleep = frame_delay - loop_duration
        if to_sleep > 0.001:
            time.sleep(to_sleep)

    cap.release()
    print("[Worker] Video Capture dihentikan.")


class StreamingHandler(BaseHTTPRequestHandler):
    """Handler HTTP Multi-Threaded untuk MJPEG stream stabil, status API, dan kontrol start/stop."""

    # Gunakan HTTP/1.0 agar MJPEG stream tidak diputus/dibuffer oleh browser (Chrome/Edge/Firefox)
    protocol_version = "HTTP/1.0"

    def log_message(self, format, *args):
        # Hindari output logging request berlebihan di console
        return

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")
        self.end_headers()

    def do_GET(self):
        parsed = urlparse(self.path)
        path = parsed.path
        query = parse_qs(parsed.query)

        # 1. Endpoint /stream (MJPEG Stabil, Single Packet Write, Tanpa Stutter)
        if path == "/stream":
            self.send_response(200)
            self.send_header("Access-Control-Allow-Origin", "*")
            self.send_header("Cache-Control", "no-cache, private, no-store, must-revalidate, max-age=0")
            self.send_header("Pragma", "no-cache")
            self.send_header("Expires", "0")
            self.send_header("Connection", "close")
            self.send_header("Content-Type", "multipart/x-mixed-replace; boundary=frame")
            self.end_headers()

            last_sent_id = -1
            try:
                while state.is_running:
                    # Tunggu hingga frame baru dihasilkan (Condition Variable)
                    with state.frame_condition:
                        if state.frame_id == last_sent_id:
                            state.frame_condition.wait(timeout=0.1)
                        if state.frame_id == last_sent_id:
                            continue
                        last_sent_id = state.frame_id
                        frame_bytes = state.current_jpeg

                    if frame_bytes is not None:
                        # Tulis seluruh frame dalam 1 payload atomik untuk mencegah socket lag/flicker
                        packet = (
                            b"--frame\r\n"
                            b"Content-Type: image/jpeg\r\n"
                            b"Content-Length: " + str(len(frame_bytes)).encode("ascii") + b"\r\n\r\n"
                            + frame_bytes + b"\r\n"
                        )
                        self.wfile.write(packet)
                        self.wfile.flush()
            except (ConnectionResetError, BrokenPipeError, TimeoutError, OSError):
                pass
            return

        # 2. Endpoint /status (JSON Ringkasan Masuk, Keluar, dan Breakdown Realtime)
        elif path == "/status":
            with state.lock:
                tracker = state.tracker
                masuk = tracker.counter.total_masuk if tracker else 0
                keluar = tracker.counter.total_keluar if tracker else 0
                total = tracker.counter.total if tracker else 0
                counts_masuk = tracker.counter.counts_masuk if tracker else {}
                counts_keluar = tracker.counter.counts_keluar if tracker else {}
                counts = tracker.counter.counts if tracker else {}
                fps = round(state.fps, 1)
                is_stream = state.is_streaming
                events = list(state.recent_events)

            data = {
                "running": True,
                "streaming": is_stream,
                "fps": fps,
                "masuk": masuk,
                "keluar": keluar,
                "total": total,
                "counts_masuk": counts_masuk,
                "counts_keluar": counts_keluar,
                "counts": counts,
                "line": {
                    "type": "vertical",
                    "label": tracker.counter.label if tracker else "Garis Monitoring Vertikal",
                    "x_pos": tracker.counter.x_pos if tracker else 0.50
                },
                "source": tracker.video_source if tracker else "",
                "recent_events": events
            }
            body = json.dumps(data, indent=2).encode("utf-8")
            self.send_response(200)
            self.send_header("Access-Control-Allow-Origin", "*")
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.send_header("Connection", "close")
            self.end_headers()
            self.wfile.write(body)
            return

        # 3. Endpoint /control (Start, Stop, Reset)
        elif path == "/control":
            action = query.get("action", [""])[0].lower()
            resp = {"status": "ok", "action": action}

            if action == "start":
                state.is_streaming = True
                resp["message"] = "Stream dihidupkan (Active)"
                resp["streaming"] = True
            elif action == "stop":
                state.is_streaming = False
                resp["message"] = "Stream dimatikan (Paused / Standby)"
                resp["streaming"] = False
            elif action == "reset":
                if state.tracker:
                    state.tracker.counter.reset()
                with state.lock:
                    state.recent_events.clear()
                resp["message"] = "Counter berhasil di-reset"
            elif action == "shutdown":
                state.is_running = False
                resp["message"] = "Server dimatikan"

            body = json.dumps(resp).encode("utf-8")
            self.send_response(200)
            self.send_header("Access-Control-Allow-Origin", "*")
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.send_header("Connection", "close")
            self.end_headers()
            self.wfile.write(body)
            return

        # 4. Endpoint /snapshot (Single JPEG)
        elif path == "/snapshot":
            with state.lock:
                frame_bytes = state.current_jpeg

            if frame_bytes is not None:
                self.send_response(200)
                self.send_header("Access-Control-Allow-Origin", "*")
                self.send_header("Content-Type", "image/jpeg")
                self.send_header("Content-Length", str(len(frame_bytes)))
                self.send_header("Connection", "close")
                self.end_headers()
                self.wfile.write(frame_bytes)
            else:
                self.send_response(503)
                self.send_header("Connection", "close")
                self.end_headers()
            return

        # Default 404
        self.send_response(404)
        self.send_header("Connection", "close")
        self.end_headers()
        self.wfile.write(b"Not Found")


def run_server(config_path: str = "tracking/config.json"):
    """Inisialisasi tracker, luncurkan worker pemrosesan, dan jalankan ThreadingHTTPServer."""
    tracker = VehicleTracker(config_path)
    state.tracker = tracker

    host = tracker.cfg.get("server", {}).get("host", "127.0.0.1")
    port = int(tracker.cfg.get("server", {}).get("port", 5055))

    # Jalankan worker video di thread background
    t = threading.Thread(target=video_processing_worker, args=(tracker,), daemon=True)
    t.start()

    # Gunakan ThreadingHTTPServer untuk menangani banyak request simultan tanpa lag/stutter
    server_address = (host, port)
    httpd = ThreadingHTTPServer(server_address, StreamingHandler)
    httpd.daemon_threads = True

    print(f"[Streaming Server] Multi-threaded berjalan pada http://{host}:{port}/stream")
    print(f"[Streaming Server] Status API : http://{host}:{port}/status")

    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\n[Streaming Server] Menghentikan server...")
    finally:
        state.is_running = False
        httpd.server_close()


if __name__ == "__main__":
    cfg_file = sys.argv[1] if len(sys.argv) > 1 else "tracking/config.json"
    run_server(cfg_file)
