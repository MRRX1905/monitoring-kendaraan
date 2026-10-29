"""
Engine Pelacakan & Penghitungan Otomatis Kendaraan (YOLOv8 + OpenCV + MySQL)
Bandara Malikussaleh (LSW) - Kementerian Perhubungan Republik Indonesia

Mendukung Garis Monitoring VERTIKAL untuk mendeteksi kendaraan yang bergerak
melintasi lajur jalan (kiri ke kanan / kanan ke kiri).
"""

from __future__ import annotations

import json
import os
import queue
import sys
import threading
import time
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Optional, Tuple, Dict, List

import cv2
import numpy as np
import pymysql
from ultralytics import YOLO

# Pemetaan ID Kelas COCO ke Nama dan ID Tabel jenis_kendaraan
# ID Jenis di Database: 1=MOTOR, 2=MOBIL, 3=BUS, 4=TRUK
COCO_CLASS_MAP = {
    2: {"name": "car", "label": "Mobil Penumpang", "db_id": 2, "prefix": "MOBIL"},
    3: {"name": "motorcycle", "label": "Sepeda Motor", "db_id": 1, "prefix": "MOTOR"},
    5: {"name": "bus", "label": "Bus / Minibus", "db_id": 3, "prefix": "BUS"},
    7: {"name": "truck", "label": "Truk / Pick Up", "db_id": 4, "prefix": "TRUK"},
}

CLASS_COLORS = {
    "car": (0, 215, 255),        # Emas / Kuning terang
    "motorcycle": (255, 140, 0),  # Biru langit
    "bus": (0, 255, 128),        # Hijau cerah
    "truck": (255, 0, 180),      # Magenta
}


@dataclass
class Detection:
    track_id: int
    xyxy: Tuple[float, float, float, float]
    centroid: Tuple[float, float]  # Ternormalisasi 0.0 - 1.0 (cx, cy)
    name: str
    class_id: int
    conf: float


@dataclass
class CrossingEvent:
    timestamp: float
    datetime_str: str
    class_name: str
    jenis_kendaraan_id: int
    direction: str  # "Masuk" (Kiri->Kanan) atau "Keluar" (Kanan->Kiri)
    track_id: int
    confidence: float
    line_label: str


@dataclass
class TrackState:
    track_id: int
    side: float  # Sisi relatif terhadap garis vertikal (cx - x_line)
    class_name: str
    conf: float
    last_seen: float = field(default_factory=time.time)


class VerticalLineCounter:
    """
    Penghitung kendaraan dengan GARIS VERTIKAL virtual.
    Posisi garis vertikal: X tetap antara p1=(x, y1) dan p2=(x, y2).
    Objek yang melintasi garis vertikal dari kiri ke kanan (cx bergerak membesar) dihitung sebagai MASUK,
    atau dari kanan ke kiri (cx bergerak mengecil) dihitung sebagai KELUAR.
    Hanya menghitung keluar dan masuk saja, tanpa status parkir.
    """

    def __init__(self, x_pos: float = 0.50, y_start: float = 0.05, y_end: float = 0.95, label: str = "Garis Monitoring Vertikal", stale_after: float = 3.0):
        self.x_pos = x_pos
        self.y_start = y_start
        self.y_end = y_end
        self.label = label
        self.stale_after = stale_after

        self.counts_masuk: Dict[str, int] = {"car": 0, "motorcycle": 0, "bus": 0, "truck": 0}
        self.counts_keluar: Dict[str, int] = {"car": 0, "motorcycle": 0, "bus": 0, "truck": 0}
        self.events: List[CrossingEvent] = []
        self._state: Dict[int, TrackState] = {}

    def side(self, centroid: Tuple[float, float]) -> float:
        """Sisi terhadap garis vertikal: positif jika di kanan garis, negatif jika di kiri."""
        cx, cy = centroid
        return cx - self.x_pos

    def update(self, detections: List[Detection], now: Optional[float] = None) -> List[Tuple[Detection, str]]:
        """
        Memproses deteksi frame dan mendeteksi penyeberangan garis vertikal.
        """
        now = now if now is not None else time.time()
        crossings: List[Tuple[Detection, str]] = []

        for d in detections:
            cx, cy = d.centroid
            # Pastikan berada di dalam rentang Y garis vertikal
            if not (self.y_start <= cy <= self.y_end):
                continue

            current_side = self.side(d.centroid)
            if abs(current_side) < 1e-6:
                continue

            tid = d.track_id
            prev = self._state.get(tid)

            if prev is None:
                self._state[tid] = TrackState(tid, current_side, d.name, d.conf, now)
                continue

            # Deteksi penyeberangan: tanda sisi berubah (+ -> - atau - -> +)
            if (prev.side < 0 and current_side >= 0) or (prev.side > 0 and current_side <= 0):
                direction = "Masuk" if prev.side < 0 else "Keluar"
                crossings.append((d, direction))
                if direction == "Masuk":
                    self.counts_masuk[d.name] = self.counts_masuk.get(d.name, 0) + 1
                else:
                    self.counts_keluar[d.name] = self.counts_keluar.get(d.name, 0) + 1

            prev.side = current_side
            prev.class_name = d.name
            prev.conf = d.conf
            prev.last_seen = now

        # Bersihkan state objek yang sudah keluar layar / lama tidak terlihat
        dead = [k for k, v in self._state.items() if now - v.last_seen > self.stale_after]
        for k in dead:
            del self._state[k]

        return crossings

    @property
    def total_masuk(self) -> int:
        return sum(self.counts_masuk.values())

    @property
    def total_keluar(self) -> int:
        return sum(self.counts_keluar.values())

    @property
    def total(self) -> int:
        return self.total_masuk + self.total_keluar

    @property
    def counts(self) -> Dict[str, int]:
        """Total gabungan per kelas untuk kompatibilitas."""
        return {
            k: self.counts_masuk.get(k, 0) + self.counts_keluar.get(k, 0)
            for k in ["car", "motorcycle", "bus", "truck"]
        }

    def reset(self):
        self.counts_masuk = {"car": 0, "motorcycle": 0, "bus": 0, "truck": 0}
        self.counts_keluar = {"car": 0, "motorcycle": 0, "bus": 0, "truck": 0}
        self.events.clear()
        self._state.clear()


class DatabaseSync:
    """Sinkronisasi data hasil pelacakan otomatis ke database MySQL parkir_malikussaleh secara asynchronous."""

    def __init__(self, db_cfg: dict):
        self.db_cfg = db_cfg
        self._queue = queue.Queue(maxsize=1000)
        self._running = True
        self._worker_thread = threading.Thread(target=self._db_worker, daemon=True)
        self._worker_thread.start()

    def get_conn(self):
        return pymysql.connect(
            host=self.db_cfg.get("host", "127.0.0.1"),
            port=int(self.db_cfg.get("port", 3306)),
            user=self.db_cfg.get("user", "root"),
            password=self.db_cfg.get("password", ""),
            database=self.db_cfg.get("database", "parkir_malikussaleh"),
            autocommit=True,
            charset="utf8mb4",
        )

    def _db_worker(self):
        conn = None
        while self._running:
            try:
                item = self._queue.get(timeout=0.5)
            except queue.Empty:
                continue

            event, cls_info = item
            for attempt in range(2):
                try:
                    if conn is None:
                        conn = self.get_conn()
                    with conn.cursor() as cur:
                        sql_track = """
                            INSERT INTO tracking_kendaraan (
                                waktu_event, class_name, jenis_kendaraan_id, direction,
                                track_id, confidence, line_label, status_sync
                            ) VALUES (%s, %s, %s, %s, %s, %s, %s, 'tercatat')
                        """
                        cur.execute(sql_track, (
                            event.datetime_str, event.class_name, event.jenis_kendaraan_id,
                            event.direction.lower(), event.track_id, float(event.confidence),
                            event.line_label
                        ))

                        sql_log = """
                            INSERT INTO log_aktivitas (user_id, aktivitas, tipe, ip_address)
                            VALUES (1, %s, %s, '127.0.0.1')
                        """
                        log_msg = f"Deteksi AI Otomatis: {cls_info['label']} #{event.track_id} {event.direction} garis vertikal"
                        cur.execute(sql_log, (log_msg, event.direction.lower()))
                    break
                except Exception as e:
                    if conn:
                        try:
                            conn.close()
                        except Exception:
                            pass
                        conn = None
                    if attempt == 1:
                        print(f"[ERROR DatabaseSync Async] Gagal simpan crossing: {e}", file=sys.stderr)
            self._queue.task_done()

    def save_crossing(self, d: Detection, direction: str, line_label: str) -> Optional[CrossingEvent]:
        try:
            now = datetime.now()
            now_dt = now.strftime("%Y-%m-%d %H:%M:%S")

            cls_info = COCO_CLASS_MAP.get(d.class_id, {"name": d.name, "label": d.name.capitalize(), "db_id": 2, "prefix": "KND"})
            jenis_id = cls_info["db_id"]

            event = CrossingEvent(
                timestamp=time.time(),
                datetime_str=now_dt,
                class_name=d.name,
                jenis_kendaraan_id=jenis_id,
                direction=direction,
                track_id=d.track_id,
                confidence=float(d.conf),
                line_label=line_label
            )

            try:
                self._queue.put_nowait((event, cls_info))
            except queue.Full:
                pass

            return event
        except Exception as e:
            print(f"[ERROR DatabaseSync] Gagal mencatat crossing: {e}", file=sys.stderr)
            return None


class VehicleTracker:
    """Kelas utama pengelola deteksi YOLOv8, pelacakan garis vertikal, dan pencatatan keluar/masuk."""

    def __init__(self, config_path: str = "tracking/config.json"):
        self.config_path = Path(config_path).resolve()
        with open(self.config_path, "r", encoding="utf-8") as f:
            self.cfg = json.load(f)

        # Resolusi path model
        model_path = Path(self.cfg.get("model_path", "tracking/yolov8n.pt"))
        if not model_path.is_absolute():
            model_path = (self.config_path.parent.parent / model_path).resolve()

        print(f"[Tracker] Memuat model YOLO: {model_path}")
        self.model = YOLO(str(model_path))

        line_cfg = self.cfg.get("line", {})
        p1 = line_cfg.get("p1", [0.50, 0.05])
        p2 = line_cfg.get("p2", [0.50, 0.95])

        # Posisi Garis VERTIKAL: X konstan
        x_vert = float((p1[0] + p2[0]) / 2.0)
        y_start = float(min(p1[1], p2[1]))
        y_end = float(max(p1[1], p2[1]))
        line_label = line_cfg.get("label", "Garis Monitoring Vertikal")

        self.counter = VerticalLineCounter(
            x_pos=x_vert,
            y_start=y_start,
            y_end=y_end,
            label=line_label
        )
        self.db_sync = DatabaseSync(self.cfg.get("db", {}))

        self.classes = self.cfg.get("classes", [2, 3, 5, 7])
        self.conf = float(self.cfg.get("conf", 0.25))
        self.iou = float(self.cfg.get("iou", 0.45))
        self.imgsz = int(self.cfg.get("imgsz", 384))
        self.tracker_cfg = self.cfg.get("tracker", "bytetrack.yaml")
        self.device = self.cfg.get("device", "cpu")

        # Resolusi sumber video
        src = self.cfg.get("source", "demo/moving_traffic.mp4")
        if not src.startswith(("http://", "https://", "rtsp://", "rtmp://")) and not src.isdigit():
            src_path = (self.config_path.parent.parent / src).resolve()
            self.video_source = str(src_path)
        else:
            self.video_source = src

        print(f"[Tracker] Sumber video diatur: {self.video_source}")
        print(f"[Tracker] GARIS VERTIKAL aktif pada X: {x_vert:.2f} (Y: {y_start:.2f} - {y_end:.2f})")

    def detect_and_track(self, frame: np.ndarray) -> List[Detection]:
        """Menjalankan deteksi YOLOv8 dan ByteTrack untuk mendapatkan posisi kendaraan."""
        h, w = frame.shape[:2]

        kwargs = {
            "persist": True,
            "verbose": False,
            "conf": self.conf,
            "iou": self.iou,
            "imgsz": self.imgsz,
            "tracker": self.tracker_cfg,
            "classes": self.classes,
            "device": self.device
        }

        results = self.model.track(frame, **kwargs)
        detections: List[Detection] = []

        for r in results:
            if r.boxes is None or len(r.boxes) == 0 or r.boxes.id is None:
                continue

            xyxy = r.boxes.xyxy.cpu().numpy()
            cls_ids = r.boxes.cls.cpu().numpy().astype(int)
            confs = r.boxes.conf.cpu().numpy()
            ids = r.boxes.id.cpu().numpy().astype(int)

            for box, cid, cf, tid in zip(xyxy, cls_ids, confs, ids):
                x1, y1, x2, y2 = [float(v) for v in box]
                name = COCO_CLASS_MAP.get(int(cid), {}).get("name", str(cid))
                cx = (x1 + x2) / 2.0 / w
                cy = (y1 + y2) / 2.0 / h

                detections.append(
                    Detection(
                        track_id=int(tid),
                        xyxy=(x1, y1, x2, y2),
                        centroid=(cx, cy),
                        name=name,
                        class_id=int(cid),
                        conf=float(cf)
                    )
                )
        return detections

    def process_frame(self, frame: np.ndarray) -> Tuple[np.ndarray, List[Detection]]:
        """Mendeteksi, melacak objek, dan memperbarui garis vertikal."""
        detections = self.detect_and_track(frame)

        # Update counter penyeberangan garis vertikal
        crossings = self.counter.update(detections)
        for det, direction in crossings:
            event = self.db_sync.save_crossing(det, direction, self.counter.label)
            if event:
                self.counter.events.append(event)
                print(f"[AI TRACK] +1 {det.name.upper()} #{det.track_id} arah: {direction} (Masuk: {self.counter.total_masuk}, Keluar: {self.counter.total_keluar})")

        # Gambar visualisasi overlay ke frame
        rendered = self.draw_overlay(frame, detections)
        return rendered, detections

    def draw_overlay(self, frame: np.ndarray, detections: List[Detection], fps: float = 0.0) -> np.ndarray:
        """Menggambar GARIS VERTIKAL, kotak pembatas objek, dan HUD informasi MASUK/KELUAR."""
        h, w = frame.shape[:2]

        # 1. Gambar GARIS VERTIKAL (kuning cerah dengan border luar)
        x_px = int(self.counter.x_pos * w)
        y1_px = int(self.counter.y_start * h)
        y2_px = int(self.counter.y_end * h)

        # Garis bayangan gelap untuk kontras
        cv2.line(frame, (x_px, y1_px), (x_px, y2_px), (0, 0, 0), 5, cv2.LINE_AA)
        # Garis utama neon kuning/emas
        cv2.line(frame, (x_px, y1_px), (x_px, y2_px), (0, 230, 255), 3, cv2.LINE_AA)
        cv2.circle(frame, (x_px, y1_px), 7, (0, 165, 255), -1, cv2.LINE_AA)
        cv2.circle(frame, (x_px, y2_px), 7, (0, 165, 255), -1, cv2.LINE_AA)
        cv2.circle(frame, (x_px, (y1_px + y2_px) // 2), 5, (0, 255, 255), -1, cv2.LINE_AA)

        # Indikator Arah di sisi kiri dan kanan garis
        cv2.putText(frame, "< KELUAR", (max(10, x_px - 85), 36),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.45, (100, 200, 255), 1, cv2.LINE_AA)
        cv2.putText(frame, "MASUK >", (min(w - 75, x_px + 10), 36),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.45, (100, 255, 100), 1, cv2.LINE_AA)

        # Label Garis Vertikal
        label_text = f"GARIS VERTIKAL (X: {int(self.counter.x_pos * 100)}%)"
        cv2.putText(frame, label_text, (max(10, x_px - 80), 20),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.50, (0, 240, 255), 2, cv2.LINE_AA)

        # 2. Gambar Kotak Deteksi Kendaraan
        for d in detections:
            x1, y1, x2, y2 = [int(v) for v in d.xyxy]
            color = CLASS_COLORS.get(d.name, (0, 255, 0))

            cv2.rectangle(frame, (x1, y1), (x2, y2), color, 2, cv2.LINE_AA)
            cx, cy = int(d.centroid[0] * w), int(d.centroid[1] * h)
            cv2.circle(frame, (cx, cy), 4, (0, 0, 255), -1, cv2.LINE_AA)

            tag = f"#{d.track_id} {d.name.upper()} {round(d.conf * 100)}%"
            (tw, th), _ = cv2.getTextSize(tag, cv2.FONT_HERSHEY_SIMPLEX, 0.45, 1)
            cv2.rectangle(frame, (x1, max(0, y1 - th - 6)), (x1 + tw + 6, y1), color, -1)
            cv2.putText(frame, tag, (x1 + 3, max(12, y1 - 4)),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.45, (0, 0, 0), 1, cv2.LINE_AA)

        # 3. HUD Display (Atas Kiri) - Ringkasan Masuk & Keluar
        overlay = frame.copy()
        cv2.rectangle(overlay, (10, 10), (320, 145), (15, 20, 32), -1)
        cv2.addWeighted(overlay, 0.80, frame, 0.20, 0, frame)

        # Header Total Masuk & Keluar
        cv2.putText(frame, f"MASUK: {self.counter.total_masuk}", (20, 34),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.60, (50, 255, 120), 2, cv2.LINE_AA)
        cv2.putText(frame, f"KELUAR: {self.counter.total_keluar}", (160, 34),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.60, (100, 200, 255), 2, cv2.LINE_AA)
        cv2.line(frame, (20, 44), (310, 44), (60, 75, 95), 1)

        # Breakdown per Jenis Kendaraan (Masuk / Keluar)
        y_offset = 64
        for cname in ["car", "motorcycle", "bus", "truck"]:
            c_in = self.counter.counts_masuk.get(cname, 0)
            c_out = self.counter.counts_keluar.get(cname, 0)
            c_label = COCO_CLASS_MAP.get(
                [k for k, v in COCO_CLASS_MAP.items() if v["name"] == cname][0]
            )["label"]
            text_line = f"{c_label:<14} : In {c_in} | Out {c_out}"
            cv2.putText(frame, text_line, (20, y_offset),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.42, (220, 225, 235), 1, cv2.LINE_AA)
            y_offset += 20

        # FPS Indicator
        if fps > 0:
            cv2.putText(frame, f"FPS: {fps:.1f}", (w - 110, 30),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.55, (0, 255, 0), 2, cv2.LINE_AA)

        # Status Bar di Bagian Bawah
        cv2.putText(frame, "MONITORING KENDARAAN (VERTIKAL: MASUK & KELUAR)", (10, h - 12),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.48, (120, 240, 160), 1, cv2.LINE_AA)

        return frame
