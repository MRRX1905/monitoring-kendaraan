"""Pengambilan screenshot asli halaman Sistem Monitoring Kendaraan (LSW).

Menjalankan Chrome sungguhan (headless) via Playwright, login sesuai role,
mengunjungi setiap halaman, membuka modal aksi penting, dan menyimpan PNG
ke folder laporan/screenshots/. Data uji yang dibuat selama pengambilan
dibersihkan otomatis di akhir skrip.
"""
import subprocess
import sys
from pathlib import Path

from playwright.sync_api import sync_playwright, expect

BASE = "http://localhost/parkir-bandara-malikussaleh"
SHOTS = Path(__file__).resolve().parent / "screenshots"
SHOTS.mkdir(parents=True, exist_ok=True)
MYSQL = r"C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe"


def sql(query, batch=True):
    out = subprocess.run(
        [MYSQL, "-uroot", "parkir_malikussaleh", "-N", "-e", query],
        capture_output=True, text=True,
    )
    return out.stdout.strip()


def shot(page, name, full_page=False):
    page.wait_for_timeout(400)
    path = SHOTS / f"{name}.png"
    page.screenshot(path=str(path), full_page=full_page)
    print("saved:", path.name)


def login(page, username, password):
    page.goto(f"{BASE}/auth/login.php", wait_until="domcontentloaded")
    page.fill('input[name="username"]', username)
    page.fill('input[name="password"]', password)
    page.click('button[type="submit"]')
    page.wait_for_load_state("domcontentloaded")
    page.wait_for_timeout(600)


def logout(page):
    page.goto(f"{BASE}/auth/logout.php", wait_until="domcontentloaded")
    page.wait_for_timeout(400)


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(channel="chrome", headless=True)
        ctx = browser.new_context(viewport={"width": 1440, "height": 900})
        page = ctx.new_page()

        # ---------- A. Publik: Login ----------
        page.goto(f"{BASE}/auth/login.php", wait_until="domcontentloaded")
        shot(page, "01_login")

        page.fill('input[name="username"]', "admin")
        page.fill('input[name="password"]', "password-salah")
        page.click('button[type="submit"]')
        page.wait_for_load_state("domcontentloaded")
        expect(page.locator(".alert-danger")).to_be_visible(timeout=5000)
        shot(page, "02_login_gagal")

        # ---------- B. Petugas Pos ----------
        login(page, "petugas", "petugas123")
        shot(page, "03_petugas_dashboard")

        page.goto(f"{BASE}/petugas/masuk.php", wait_until="domcontentloaded")
        shot(page, "04_petugas_masuk")

        # Catat kendaraan masuk lewat form asli
        page.check('input[name="jenis_kendaraan_id"][value="2"]')
        page.fill('input[name="plat_nomor"]', "BL 7777 DOC")
        page.fill('input[name="ciri_kendaraan"]', "Brio Putih")
        page.fill('input[name="keterangan"]', "Data uji dokumentasi laporan")
        page.click('button[name="simpan_masuk"]')
        page.wait_for_load_state("domcontentloaded")
        expect(page.locator(".alert-success")).to_be_visible(timeout=5000)
        shot(page, "05_masuk_sukses")

        new_id = sql("SELECT id FROM parkir WHERE plat_nomor='BL 7777 DOC' ORDER BY id DESC LIMIT 1")
        print("trx uji id:", new_id)

        # Pos keluar + modal konfirmasi
        page.goto(f"{BASE}/petugas/keluar.php", wait_until="domcontentloaded")
        shot(page, "06_petugas_keluar")
        page.click(".btn-proses-modal")
        page.wait_for_selector("#modalKeluar.show", timeout=5000)
        shot(page, "07_keluar_modal")
        page.click('#modalKeluar button[name="proses_keluar"]')
        page.wait_for_load_state("domcontentloaded")
        expect(page.locator(".alert-success")).to_be_visible(timeout=5000)
        shot(page, "08_keluar_sukses")

        # Cetak karcis
        page.goto(f"{BASE}/petugas/karcis.php?id={new_id}", wait_until="domcontentloaded")
        shot(page, "09_karcis")

        # Riwayat + modal hapus (dibatalkan)
        page.goto(f"{BASE}/petugas/data-parkir.php", wait_until="domcontentloaded")
        shot(page, "10_data_parkir")
        page.click(".btn-hapus-trx >> nth=0")
        page.wait_for_selector("#modalHapusTrx.show", timeout=5000)
        shot(page, "11_data_parkir_modal_hapus")
        page.click("#modalHapusTrx button[data-bs-dismiss='modal'] >> nth=0")
        page.wait_for_timeout(400)

        # Koreksi/edit transaksi (modal)
        page.click(".btn-edit-trx >> nth=0")
        page.wait_for_selector("#modalEditTrx.show", timeout=5000)
        shot(page, "12_data_parkir_modal_edit")
        page.click("#modalEditTrx button[data-bs-dismiss='modal'] >> nth=0")
        page.wait_for_timeout(300)
        logout(page)

        # ---------- C. Supervisor ----------
        login(page, "supervisor", "supervisor123")
        page.wait_for_timeout(1200)  # beri waktu grafik Chart.js merender
        shot(page, "13_supervisor_dashboard")

        page.goto(
            f"{BASE}/supervisor/laporan.php?tgl_mulai=2026-10-01&tgl_selesai=2026-10-31",
            wait_until="domcontentloaded",
        )
        page.wait_for_timeout(800)
        shot(page, "14_laporan_rekap")
        page.screenshot(path=str(SHOTS / "14b_laporan_rekap_full.png"), full_page=True)
        print("saved: 14b_laporan_rekap_full.png")

        page.goto(
            f"{BASE}/supervisor/laporan.php?print=1&tgl_mulai=2026-10-01&tgl_selesai=2026-10-31",
            wait_until="domcontentloaded",
        )
        page.wait_for_timeout(600)
        shot(page, "15_laporan_cetak_resmi")
        logout(page)

        # ---------- D. Administrator ----------
        login(page, "admin", "admin123")
        page.wait_for_timeout(600)
        shot(page, "16_admin_dashboard")

        page.goto(f"{BASE}/admin/kendaraan.php", wait_until="domcontentloaded")
        shot(page, "17_admin_kendaraan")
        page.click(".btn-hapus-knd >> nth=0")
        page.wait_for_selector("#modalHapusKendaraan.show", timeout=5000)
        shot(page, "18_admin_kendaraan_modal_hapus")
        page.click("#modalHapusKendaraan button[data-bs-dismiss='modal'] >> nth=0")
        page.wait_for_timeout(400)

        page.goto(f"{BASE}/admin/users.php", wait_until="domcontentloaded")
        shot(page, "19_admin_users")
        page.click(".btn-hapus-user >> nth=1")
        page.wait_for_selector("#modalHapusUser.show", timeout=5000)
        shot(page, "20_admin_users_modal_hapus")
        page.click("#modalHapusUser button[data-bs-dismiss='modal'] >> nth=0")
        page.wait_for_timeout(400)

        page.goto(f"{BASE}/admin/jenis-kendaraan.php", wait_until="domcontentloaded")
        shot(page, "21_admin_jenis_kendaraan")

        page.goto(f"{BASE}/admin/aktivitas.php", wait_until="domcontentloaded")
        page.wait_for_timeout(600)
        shot(page, "22_admin_aktivitas")

        page.goto(f"{BASE}/admin/pengaturan.php", wait_until="domcontentloaded")
        shot(page, "23_admin_pengaturan")

        # Live Tracking AI (server Python harus sedang berjalan)
        page.goto(f"{BASE}/admin/tracking.php", wait_until="domcontentloaded")
        page.wait_for_timeout(6000)  # tunggu stream MJPEG tampil
        shot(page, "24_admin_tracking_ai")

        logout(page)
        browser.close()

    # ---------- Bersihkan data uji ----------
    sql(f"DELETE FROM parkir WHERE id={new_id}")
    sql("DELETE FROM tracking_kendaraan WHERE id > 445")  # event uji dari video demo
    sql("DELETE FROM log_aktivitas WHERE user_agent LIKE '%HeadlessChrome%'")
    print("cleanup done; parkir sisa:", sql("SELECT COUNT(*) FROM parkir"))


if __name__ == "__main__":
    sys.exit(main())
