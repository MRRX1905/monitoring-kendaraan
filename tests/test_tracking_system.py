"""
Test Suite Lengkap untuk Tracking Otomatis Kendaraan, Garis Vertikal, dan Sinkronisasi Database
Bandara Malikussaleh (LSW)
"""

import json
import sys
import time
from pathlib import Path

# Setup path
ROOT_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT_DIR / "tracking"))

from tracker_engine import VerticalLineCounter, Detection, DatabaseSync  # noqa: E402


def test_vertical_line_cross_left_to_right():
    """Uji kendaraan melintasi garis vertikal dari kiri ke kanan (arah Masuk)."""
    # Garis vertikal pada X=0.50
    counter = VerticalLineCounter(x_pos=0.50, y_start=0.05, y_end=0.95, label="Test Vertikal")

    # Frame 1: Objek di kiri garis (X=0.40)
    d1 = Detection(track_id=101, xyxy=(100, 100, 200, 200), centroid=(0.40, 0.50), name="car", class_id=2, conf=0.92)
    res1 = counter.update([d1])
    assert len(res1) == 0, f"Belum melintas, crossing harus 0 tapi dapat {len(res1)}"
    assert counter.total == 0

    # Frame 2: Objek mendekati garis (X=0.48)
    d1_f2 = Detection(track_id=101, xyxy=(120, 100, 220, 200), centroid=(0.48, 0.50), name="car", class_id=2, conf=0.92)
    res2 = counter.update([d1_f2])
    assert len(res2) == 0
    assert counter.total == 0

    # Frame 3: Objek melintasi garis ke kanan (X=0.55)
    d1_f3 = Detection(track_id=101, xyxy=(150, 100, 250, 200), centroid=(0.55, 0.50), name="car", class_id=2, conf=0.92)
    res3 = counter.update([d1_f3])
    assert len(res3) == 1, "Harus terdeteksi penyeberangan 1 kali"
    det, direction = res3[0]
    assert direction == "Masuk", f"Arah harus 'Masuk' tapi dapat '{direction}'"
    assert counter.counts["car"] == 1
    assert counter.total == 1

    # Frame 4 & 5: Objek terus bergerak ke kanan (X=0.65, 0.75) -> tidak boleh dobel hitung
    for x in [0.65, 0.75]:
        d = Detection(track_id=101, xyxy=(200, 100, 300, 200), centroid=(x, 0.50), name="car", class_id=2, conf=0.92)
        res = counter.update([d])
        assert len(res) == 0, "Objek yang sama tidak boleh dihitung ulang"

    assert counter.counts["car"] == 1
    assert counter.total == 1
    print("PASS: test_vertical_line_cross_left_to_right")


def test_vertical_line_cross_right_to_left():
    """Uji kendaraan melintasi garis vertikal dari kanan ke kiri (arah Keluar)."""
    counter = VerticalLineCounter(x_pos=0.50, y_start=0.05, y_end=0.95, label="Test Vertikal")

    # Objek di kanan garis (X=0.65)
    d2 = Detection(track_id=202, xyxy=(300, 100, 400, 200), centroid=(0.65, 0.50), name="bus", class_id=5, conf=0.88)
    counter.update([d2])
    assert counter.total == 0

    # Melintas ke kiri (X=0.45)
    d2_left = Detection(track_id=202, xyxy=(200, 100, 300, 200), centroid=(0.45, 0.50), name="bus", class_id=5, conf=0.88)
    res = counter.update([d2_left])
    assert len(res) == 1
    det, direction = res[0]
    assert direction == "Keluar", f"Arah harus 'Keluar' tapi dapat '{direction}'"
    assert counter.counts["bus"] == 1
    assert counter.total == 1
    print("PASS: test_vertical_line_cross_right_to_left")


def test_multiple_vehicle_classes_counted():
    """Uji penghitungan beragam jenis kendaraan (mobil, motor, bus, truk) pada garis vertikal."""
    counter = VerticalLineCounter(x_pos=0.50)

    # Frame 1 (di kiri garis)
    dets_f1 = [
        Detection(track_id=1, xyxy=(0, 0, 10, 10), centroid=(0.3, 0.2), name="car", class_id=2, conf=0.9),
        Detection(track_id=2, xyxy=(0, 0, 10, 10), centroid=(0.3, 0.4), name="motorcycle", class_id=3, conf=0.85),
        Detection(track_id=3, xyxy=(0, 0, 10, 10), centroid=(0.3, 0.6), name="bus", class_id=5, conf=0.95),
        Detection(track_id=4, xyxy=(0, 0, 10, 10), centroid=(0.3, 0.8), name="truck", class_id=7, conf=0.91),
    ]
    counter.update(dets_f1)
    assert counter.total == 0

    # Frame 2 (semua melintas ke kanan garis)
    dets_f2 = [
        Detection(track_id=1, xyxy=(0, 0, 10, 10), centroid=(0.65, 0.2), name="car", class_id=2, conf=0.9),
        Detection(track_id=2, xyxy=(0, 0, 10, 10), centroid=(0.65, 0.4), name="motorcycle", class_id=3, conf=0.85),
        Detection(track_id=3, xyxy=(0, 0, 10, 10), centroid=(0.65, 0.6), name="bus", class_id=5, conf=0.95),
        Detection(track_id=4, xyxy=(0, 0, 10, 10), centroid=(0.65, 0.8), name="truck", class_id=7, conf=0.91),
    ]
    res = counter.update(dets_f2)
    assert len(res) == 4, f"Harus ada 4 crossing, tapi dapat {len(res)}"
    assert counter.counts == {"car": 1, "motorcycle": 1, "bus": 1, "truck": 1}
    assert counter.total == 4
    print("PASS: test_multiple_vehicle_classes_counted")


def test_config_structure():
    """Uji file konfigurasi: sumber video tidak diatur di UI dan garis vertikal."""
    cfg_file = ROOT_DIR / "tracking" / "config.json"
    assert cfg_file.exists(), f"File {cfg_file} tidak ditemukan"
    data = json.loads(cfg_file.read_text(encoding="utf-8"))

    assert "source" in data, "Key 'source' wajib ada di config.json"
    assert data["source"] == "demo/moving_traffic.mp4" or "moving_traffic" in data["source"]
    assert "line" in data, "Key 'line' wajib ada di config.json"

    p1 = data["line"]["p1"]
    p2 = data["line"]["p2"]
    # Posisi garis vertikal: X pada p1 dan p2 harus sama
    assert abs(p1[0] - p2[0]) < 1e-4, f"Garis harus vertikal (X sama), dapat: {p1} dan {p2}"
    print(f"PASS: test_config_structure (Garis vertikal pada X: {p1[0]})")


def test_in_out_counters():
    """Uji penghitungan terpisah Masuk dan Keluar pada garis vertikal."""
    counter = VerticalLineCounter(x_pos=0.50)

    # 1. Mobil melintas Masuk (kiri ke kanan: X 0.40 -> 0.60)
    d_car1 = Detection(track_id=10, xyxy=(10, 10, 20, 20), centroid=(0.40, 0.5), name="car", class_id=2, conf=0.9)
    counter.update([d_car1])
    d_car2 = Detection(track_id=10, xyxy=(20, 10, 30, 20), centroid=(0.60, 0.5), name="car", class_id=2, conf=0.9)
    res_in = counter.update([d_car2])
    assert len(res_in) == 1
    assert res_in[0][1] == "Masuk"
    assert counter.total_masuk == 1
    assert counter.total_keluar == 0
    assert counter.total == 1

    # 2. Truk melintas Keluar (kanan ke kiri: X 0.65 -> 0.35)
    d_trk1 = Detection(track_id=20, xyxy=(30, 10, 40, 20), centroid=(0.65, 0.5), name="truck", class_id=7, conf=0.88)
    counter.update([d_trk1])
    d_trk2 = Detection(track_id=20, xyxy=(10, 10, 20, 20), centroid=(0.35, 0.5), name="truck", class_id=7, conf=0.88)
    res_out = counter.update([d_trk2])
    assert len(res_out) == 1
    assert res_out[0][1] == "Keluar"
    assert counter.total_masuk == 1
    assert counter.total_keluar == 1
    assert counter.total == 2
    assert counter.counts_masuk["car"] == 1
    assert counter.counts_keluar["truck"] == 1
    print("PASS: test_in_out_counters (Penghitungan Masuk & Keluar)")


def test_database_sync_records():
    """Uji pencatatan langsung ke MySQL database parkir_malikussaleh (hanya masuk & keluar tanpa status parkir)."""
    cfg_file = ROOT_DIR / "tracking" / "config.json"
    cfg = json.loads(cfg_file.read_text(encoding="utf-8"))

    db_sync = DatabaseSync(cfg.get("db", {}))
    test_det = Detection(
        track_id=9999,
        xyxy=(100, 100, 300, 300),
        centroid=(0.5, 0.6),
        name="car",
        class_id=2,
        conf=0.96
    )

    ev = db_sync.save_crossing(test_det, "Masuk", "Garis Test Vertikal")
    assert ev is not None, "Penyimpanan ke database harus berhasil (tidak None)"
    assert ev.direction == "Masuk"
    assert ev.track_id == 9999

    # Verifikasi data tersimpan di tabel tracking_kendaraan
    conn = db_sync.get_conn()
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*), direction FROM tracking_kendaraan WHERE track_id = 9999 GROUP BY direction")
        row = cur.fetchone()
        assert row is not None and row[0] >= 1, "Data harus tersimpan di tabel tracking_kendaraan"
        assert row[1] == "masuk", "Direction harus tersimpan sebagai 'masuk'"

        # Hapus data testing agar database tetap bersih
        cur.execute("DELETE FROM tracking_kendaraan WHERE track_id = 9999")
    conn.close()

    print("PASS: test_database_sync_records (Data tercatat ke tracking_kendaraan tanpa status parkir)")


if __name__ == "__main__":
    tests = [
        test_vertical_line_cross_left_to_right,
        test_vertical_line_cross_right_to_left,
        test_in_out_counters,
        test_multiple_vehicle_classes_counted,
        test_config_structure,
        test_database_sync_records,
    ]

    failed = 0
    print("\n=== MENJALANKAN UNIT TESTING TRACKING SISTEM (GARIS VERTIKAL) ===")
    for t in tests:
        try:
            t()
        except AssertionError as e:
            print(f"FAIL: {t.__name__} - {e}")
            failed += 1
        except Exception as e:
            print(f"ERROR: {t.__name__} - {e}")
            failed += 1

    print(f"\nHasil: {len(tests) - failed}/{len(tests)} pengujian berhasil.")
    sys.exit(1 if failed else 0)

