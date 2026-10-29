"""Pembangkit laporan DOCX & PDF: Sistem Monitoring Kendaraan Bandara Malikussaleh.

Konten disusun dari hasil analisis kode dan pengujian nyata (HTTP + browser)
yang dilakukan pada 17 Oktober 2026. Semua screenshot diambil langsung dari
aplikasi web berjalan (Chrome headless via Playwright), bukan gambar sintetis.
"""
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.shared import Inches, Pt
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.lib.utils import ImageReader
from reportlab.platypus import (
    Image, PageBreak, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle,
)

ROOT = Path(__file__).resolve().parent
SHOTS = ROOT / "screenshots"
DOCX_OUT = ROOT / "laporan_sistem_monitoring_kendaraan.docx"
PDF_OUT = ROOT / "laporan_sistem_monitoring_kendaraan.pdf"

TANGGAL = "17 Oktober 2026"

# ---------------------------------------------------------------- konten --

JUDUL = "LAPORAN ANALISIS DAN PENGUJIAN SISTEM INFORMASI MONITORING KENDARAAN"
SUBJUDUL = (
    "Bandar Udara Malikussaleh (LSW) - Kementerian Perhubungan Republik Indonesia\n"
    "Kantor UPBU Kelas III Malikussaleh, Aceh Utara"
)

PARAGRAF = {
    "pendahuluan": [
        "Bandar Udara Malikussaleh (LSW) di Kabupaten Aceh Utara mengelola area parkir "
        "yang dilayani oleh pos petugas. Sebelumnya, pencatatan kendaraan masuk dan keluar "
        "menggunakan cara manual sehingga rentan terhadap kehilangan karcis, perbedaan hitungan "
        "masuk-keluar, dan kesulitan penyusunan laporan. Proyek ini menghadirkan Sistem Informasi "
        "Monitoring Kendaraan berbasis web yang merekam seluruh pergerakan kendaraan secara digital, "
        "mulai dari pencatatan masuk, penerbitan karcis struk, pemrosesan keluar, hingga "
        "rekapitulasi laporan resmi berkop Kementerian Perhubungan.",
        "Laporan ini disusun berdasarkan analisis langsung terhadap source code proyek dan "
        "pengujian fungsional terhadap aplikasi yang berjalan nyata di lingkungan Laragon "
        "(Apache, PHP 8.3.33, MySQL 8.4.3) pada 17 Oktober 2026. Seluruh tangkapan layar dalam "
        "laporan diambil langsung dari tampilan aplikasi web yang sedang berjalan menggunakan "
        "browser Chrome, bukan gambar hasil pembuatan buatan.",
        "Tujuan laporan ini adalah: (1) memetakan teknologi, halaman, dan seluruh fitur sistem "
        "berdasarkan implementasi nyata; (2) mendokumentasikan hasil pengujian setiap fitur "
        "beserta bukti tangkapan layar; (3) memberikan penilaian jujur terhadap kondisi sistem "
        "termasuk bagian yang belum tersedia atau memiliki keterbatasan; serta (4) memberikan "
        "kesimpulan dan saran pengembangan.",
    ],
    "sistem": [
        "Sistem dibangun dengan arsitektur web tiga lapis sederhana: Apache menyajikan file PHP, "
        "PHP 8.3 berbicara ke MySQL 8.4.3 melalui PDO, dan sisi klien memakai Bootstrap 5.3.2 dengan "
        "tema resmi Kementerian Perhubungan (biru #0b2e59 dan aksen emas #f59e0b, font Inter). "
        "Tidak ada framework PHP penuh; seluruh halaman adalah skrip PHP prosedural dengan "
        "session-based authentication, middleware includes/auth_check.php, dan fungsi bantuan "
        "pada includes/helpers.php.",
        "Fitur unggulan yang membedakan sistem ini adalah modul Tracking Otomatis berbasis "
        "Computer Vision. Servis Python terpisah (tracking/server.py) menjalankan model YOLOv8-nano "
        "dengan pelacakan ByteTrack pada video sumber (pada pengujian memakai video demo "
        "demo/moving_traffic.mp4), menghitung kendaraan yang melewati garis monitoring vertikal "
        "sebagai arah masuk atau keluar, lalu menyimpan setiap event ke tabel tracking_kendaraan "
        "MySQL. Servis ini menyediakan aliran video MJPEG pada port 5055 yang ditampilkan langsung "
        "pada halaman admin/tracking.php, dan dikontrol dari aplikasi PHP melalui "
        "api/tracking_control.php.",
        "Basis data terdiri dari enam tabel: users (akun dengan tiga role), jenis_kendaraan "
        "(master kendaraan dengan ikon Font Awesome), parkir (transaksi masuk/keluar dengan kode "
        "transaksi MLK-YYYYMMDD-XXXX dan nomor karcis harian K-XXX), log_aktivitas (jejak audit "
        "seluruh aksi), tracking_kendaraan (event deteksi AI), dan pengaturan (identitas bandara). "
        "Setiap transaksi terhubung ke pengguna melalui foreign key petugas_masuk_id dan "
        "petugas_keluar_id sehingga akuntabilitas operasional terjaga.",
        "Otorisasi memakai pola Role-Based Access Control tiga tingkat. Petugas Pos dapat mencatat "
        "masuk, memproses keluar, mencetak karcis, dan mengelola riwayat. Supervisor memantau "
        "dashboard analitik dan menyusun laporan resmi termasuk ekspor spreadsheet. Administrator "
        "memiliki akses penuh: manajemen akun, master jenis kendaraan, manajemen kendaraan, "
        "log aktivitas, pengaturan bandara, dan modul tracking AI.",
    ],
    "kesimpulan": [
        "Berdasarkan analisis source code dan pengujian fungsional terhadap aplikasi yang berjalan, "
        "seluruh inti fungsi Sistem Monitoring Kendaraan Bandar Udara Malikussaleh terbukti "
        "berfungsi: autentikasi tiga role dengan redirect sesuai hak akses, pencatatan kendaraan "
        "masuk dengan kode transaksi dan nomor karcis otomatis, pemrosesan keluar dengan perhitungan "
        "durasi otomatis, cetak karcis struk, koreksi dan penghapusan transaksi dengan alasan wajib "
        "serta jejak audit, manajemen akun pengguna lengkap dengan reset password ter-hash bcrypt, "
        "master jenis kendaraan, pengaturan profil bandara, rekapitulasi laporan dengan mode cetak "
        "resmi berkop dan ekspor spreadsheet, hingga tracking otomatis berbasis YOLOv8 yang benar-benar "
        "mendeteksi dan mencatat event masuk-keluar ke basis data.",
        "Pengujian keamanan dasar juga lulus: sesi dilindungi dengan regenerasi ID login, akses tanpa "
        "login dialihkan ke halaman login, petugas yang membuka halaman admin dialihkan ke dashboard-nya, "
        "password disimpan sebagai bcrypt hash, dan API kontrol tracking menolak akses tanpa sesi login "
        "(HTTP 401). Modul tracking teruji end-to-end: server Python berjalan, stream MJPEG menghasilkan "
        "cuplikan berlabel deteksi, dan event masuk/keluar tercatat dengan nilai keyakinan 0,73-0,87 ke "
        "tabel tracking_kendaraan yang selanjutnya muncul pada laporan dan dashboard.",
        "Sejumlah keterbatasan juga teridentifikasi secara jujur. Ekspor .xls dihasilkan sebagai dokumen "
        "HTML berheader application/vnd.ms-excel (kompatibel dibuka Excel, namun bukan format XLS "
        "bawaan). Tombol reset data pada pengaturan bersifat destruktif (TRUNCATE seluruh tabel "
        "transaksi) sehingga tidak dijalankan saat pengujian untuk menjaga data. Stream tracking "
        "membutuhkan servis Python dijalankan manual dan dependensi berat (OpenCV, Ultralytics, model "
        "YOLOv8) yang belum dikemas menjadi layanan otomatis. Selain itu, sebelum perbaikan pada sesi "
        "pengembangan ini, tombol aksi modal (keluar, edit, hapus) di beberapa halaman tidak "
        "berfungsi karena inisialisasi Bootstrap Modal berjalan sebelum pustaka dimuat; perbaikan "
        "dengan pembungkus DOMContentLoaded telah diterapkan dan diverifikasi lulus pada seluruh "
        "halaman terdampak.",
    ],
    "saran": [
        "1. Kemas servis tracking sebagai layanan otomatis (Windows Service atau scheduled task) "
        "agar stream AI langsung tersedia tanpa menjalankan server.py secara manual, dan tambahkan "
        "penanganan bila sumber video/CCTV tidak tersedia.",
        "2. Sediakan mekanisme ekspor ke format XLSX asli (misalnya PhpSpreadsheet) sebagai tambahan "
        "atau pengganti ekspor berbasis HTML agar kompatibilitas lintas perangkat lunak spreadsheet "
        "lebih terjamin.",
        "3. Tambahkan CSRF token pada seluruh form POST dan penegakan kebijakan sesi (timeout "
        "inaktif, regenerasi kunci periodik) untuk memperkuat pertahanan terhadap serangan CSRF dan "
        "pembajakan sesi.",
        "4. Konfirmasi dialog terpisah untuk tombol reset data dengan mengetik teks konfirmasi, "
        "sehingga risiko penghapusan tidak sengaja dapat diminimalkan.",
        "5. Terapkan paginasi dan indeks kueri tambahan pada tabel parkir dan tracking_kendaraan "
        "ketika volume data meningkat, serta aktifkan backup basis data berkala.",
        "6. Uji beban dan uji multi-pengguna simultan pada pos masuk dan keluar untuk memastikan "
        "kinerja stabil pada jam-jam sibuk penerbangan, serta lengkapi dengan manual pengguna dan "
        "panduan pemulihan bencana.",
    ],
}

TEKNOLOGI = [
    ["Komponen", "Teknologi yang Digunakan"],
    ["Bahasa & Server Web", "PHP 8.3.33 (Apache pada Laragon), skrip prosedural tanpa framework"],
    ["Basis Data", "MySQL / MariaDB 8.4.3, koneksi PDO, database parkir_malikussaleh"],
    ["Frontend", "Bootstrap 5.3.2, Font Awesome 6.5.1, Bootstrap Icons, Chart.js 4.4.1, font Inter"],
    ["Kecerdasan Buatan", "Python 3.14, Ultralytics YOLOv8-nano, ByteTrack, OpenCV 5.0"],
    ["Servis Streaming", "ThreadingHTTPServer Python pada 127.0.0.1:5055, aliran video MJPEG"],
    ["Autentikasi", "Sesi PHP native, password_hash bcrypt, middleware auth_check.php"],
]

DB = [
    ["Tabel", "Fungsi"],
    ["users", "Akun petugas, supervisor, dan administrator beserta role dan status"],
    ["jenis_kendaraan", "Master jenis kendaraan dengan kode kategori dan ikon"],
    ["parkir", "Transaksi masuk/keluar: kode transaksi, karcis, durasi, petugas terkait"],
    ["log_aktivitas", "Jejak audit seluruh aksi penting pengguna dan sistem"],
    ["tracking_kendaraan", "Event deteksi AI: waktu, kelas kendaraan, arah, track, keyakinan"],
    ["pengaturan", "Identitas resmi bandara dan konfigurasi aplikasi"],
]

HALAMAN = [
    ["Halaman", "Role", "Fungsi"],
    ["auth/login.php", "Publik", "Formulir masuk dengan tema Kemenhub dan akun bawaan"],
    ["petugas/dashboard.php", "Petugas/Admin/Sup", "Monitoring real-time masuk-keluar hari ini"],
    ["petugas/masuk.php", "Petugas/Admin", "Form pencatatan kendaraan masuk + opsi cetak karcis"],
    ["petugas/keluar.php", "Petugas/Admin", "Daftar kendaraan aktif, live search, proses keluar"],
    ["petugas/karcis.php", "Petugas/Admin/Sup", "Cetak karcis struk 58/80 mm siap printer thermal"],
    ["petugas/data-parkir.php", "Petugas/Admin/Sup", "Riwayat transaksi multi-filter, koreksi & hapus"],
    ["supervisor/dashboard.php", "Supervisor/Admin", "Grafik fluktuasi per jam dan proporsi jenis"],
    ["supervisor/laporan.php", "Supervisor/Admin/Pet", "Rekapitulasi, cetak resmi berkop, ekspor .xls"],
    ["admin/dashboard.php", "Admin", "Panel kontrol dan ringkasan sistem"],
    ["admin/kendaraan.php", "Admin/Sup/Pet", "Manajemen seluruh transaksi kendaraan"],
    ["admin/users.php", "Admin", "CRUD pengguna, reset password, toggle status"],
    ["admin/jenis-kendaraan.php", "Admin", "CRUD master jenis kendaraan"],
    ["admin/aktivitas.php", "Admin/Sup/Pet", "Log aktivitas kendaraan dan audit trail"],
    ["admin/pengaturan.php", "Admin", "Identitas bandara dan reset data (destruktif)"],
    ["admin/tracking.php", "Admin/Sup/Pet", "Live tracking AI, konfigurasi garis monitoring"],
    ["api/tracking_stats.php", "JSON publik", "Statistik deteksi AI hari ini"],
    ["api/tracking_control.php", "JSON sesi login", "Kontrol start/stop/reset/status stream"],
    ["install.php", "Publik", "Wizard instalasi dan reset database satu klik"],
]

# daftar (file, judul keterangan) sesuai urutan pengambilan
GAMBAR = [
    ("01_login.png", "Halaman login resmi dengan identitas Kemenhub dan daftar akun bawaan."),
    ("02_login_gagal.png", "Pesan kesalahan saat kredensial tidak sesuai."),
    ("03_petugas_dashboard.png", "Dashboard monitoring petugas: kartu masuk/keluar hari ini."),
    ("04_petugas_masuk.png", "Form pencatatan kendaraan masuk dengan pemilihan cepat jenis kendaraan."),
    ("05_masuk_sukses.png", "Konfirmasi sukses pencatatan masuk beserta nomor karcis dan kode transaksi."),
    ("06_petugas_keluar.png", "Pos keluar: daftar kendaraan masih berada di area parkir."),
    ("07_keluar_modal.png", "Modal konfirmasi keluar yang menampilkan identitas kendaraan terpilih."),
    ("08_keluar_sukses.png", "Konfirmasi keluar sukses dengan durasi parkir terhitung otomatis."),
    ("09_karcis.png", "Cetak karcis struk berisi kode, karcis, identitas kendaraan, dan tata tertib."),
    ("10_data_parkir.png", "Riwayat transaksi dengan filter tanggal, jenis, dan kata kunci."),
    ("11_data_parkir_modal_hapus.png", "Modal hapus transaksi dengan pilihan alasan wajib."),
    ("12_data_parkir_modal_edit.png", "Modal koreksi data transaksi (edit)."),
    ("13_supervisor_dashboard.png", "Dashboard supervisor: grafik tren per jam dan proporsi jenis kendaraan."),
    ("14_laporan_rekap.png", "Rekapitulasi laporan berdasar rentang tanggal terpilih."),
    ("14b_laporan_rekap_full.png", "Tampilan lengkap halaman rekapitulasi hingga bagian ekspor."),
    ("15_laporan_cetak_resmi.png", "Mode cetak resmi: kop Kemenhub, ringkasan, dan blok pengesahan tanda tangan."),
    ("16_admin_dashboard.png", "Dashboard administrator dengan pintasan pengelolaan sistem."),
    ("17_admin_kendaraan.png", "Manajemen kendaraan: tabel transaksi dengan aksi keluar, cetak, edit, hapus."),
    ("18_admin_kendaraan_modal_hapus.png", "Modal hapus transaksi di manajemen kendaraan admin."),
    ("19_admin_users.png", "Manajemen akun pengguna dengan jumlah transaksi dan status masing-masing akun."),
    ("20_admin_users_modal_hapus.png", "Modal hapus akun dengan peringatan perlindungan jejak audit."),
    ("21_admin_jenis_kendaraan.png", "Master jenis kendaraan beserta jumlah transaksi terkait."),
    ("22_admin_aktivitas.png", "Aktivitas kendaraan dan audit trail harian."),
    ("23_admin_pengaturan.png", "Pengaturan identitas bandara dan reset data sistem."),
    ("24_admin_tracking_ai.png", "Live tracking AI berjalan: stream berlabel deteksi, statistik, dan konfigurasi garis."),
    ("25_wizard_install.png", "Wizard instalasi database satu klik (install.php) yang dapat diakses tanpa login."),
]

# (no, uji, langkah, hasil, status)
PENGUJIAN = [
    ["T01", "Login Petugas Pos", "Masuk dengan petugas/petugas123", "Dialihkan ke petugas/dashboard.php, halaman HTTP 200", "LULUS"],
    ["T02", "Login Administrator", "Masuk dengan admin/admin123", "Dialihkan ke admin/dashboard.php, halaman HTTP 200", "LULUS"],
    ["T03", "Login Supervisor", "Masuk dengan supervisor/supervisor123", "Dialihkan ke supervisor/dashboard.php, halaman HTTP 200", "LULUS"],
    ["T04", "Login kredensial salah", "Kirim kata sandi tidak valid", "Pesan kesalahan tampil, sesi tidak terbentuk", "LULUS"],
    ["T05", "Penjagaan sesi", "Akses 3 halaman tanpa cookie sesi", "Ketiganya dialihkan ke auth/login.php", "LULUS"],
    ["T06", "RBAC antar role", "Petugas membuka admin/users.php", "Dialihkan ke petugas/dashboard.php (ditolak)", "LULUS"],
    ["T07", "Catat kendaraan masuk", "Form masuk, jenis Mobil (R4), plat BL 9001 TEST", "HTTP 302, baris tersimpan dengan kode MLK-20261017-XXXX dan karcis harian", "LULUS"],
    ["T08", "Proses keluar", "Submit konfirmasi keluar pada transaksi uji", "HTTP 302, datetime_keluar, durasi_menit, dan petugas_keluar_id terisi", "LULUS"],
    ["T09", "Koreksi transaksi", "Ubah plat, ciri, dan keterangan lewat admin/kendaraan.php", "HTTP 302, perubahan terkonfirmasi di basis data", "LULUS"],
    ["T10", "Cetak karcis", "Buka petugas/karcis.php?id=...", "HTTP 200, nomor karcis dan rincian tercetak tampil", "LULUS"],
    ["T11", "Rekap laporan", "Filter supervisor/laporan.php 1-31 Oktober 2026", "HTTP 200, blok rekapitulasi dan ringkasan tampil", "LULUS"],
    ["T12", "Mode cetak resmi", "Buka laporan?print=1", "HTTP 200, kop Kemenhub dan pengesahan tanda tangan tampil", "LULUS"],
    ["T13", "Ekspor spreadsheet", "Unduh laporan?export=excel", "HTTP 200, Content-Disposition attachment .xls, 5.157 byte", "LULUS (lihat catatan)"],
    ["T14", "Tambah pengguna", "Buat akun petugas uji lewat admin/users.php", "HTTP 302, akun tersimpan aktif", "LULUS"],
    ["T15", "Reset password", "Setel sandi baru pada akun uji", "HTTP 302, password_verify terhadap hash bcrypt mengembalikan benar", "LULUS"],
    ["T16", "Toggle status", "Nonaktifkan lalu aktifkan kembali akun uji", "Status berpindah nonaktif lalu aktif", "LULUS"],
    ["T17", "Hapus pengguna", "Hapus akun uji dengan alasan", "HTTP 302, akun hilang dari tabel users", "LULUS"],
    ["T18", "CRUD jenis kendaraan", "Tambah, ubah nama, lalu hapus jenis uji", "HTTP 302 pada tiap tahap, perubahan terverifikasi di database", "LULUS"],
    ["T19", "Simpan pengaturan", "Kirim ulang identitas bandara bernilai sama", "HTTP 302, data tersimpan tanpa merusak konfigurasi", "LULUS"],
    ["T20", "API statistik AI", "GET api/tracking_stats.php", "HTTP 200, JSON sukses berisi ringkasan deteksi hari ini", "LULUS"],
    ["T21", "API kontrol tanpa sesi", "GET api/tracking_control.php?action=status", "HTTP 401 Unauthorized (penolakan sesuai desain)", "LULUS"],
    ["T22", "API kontrol dengan sesi", "Request yang sama membawa cookie login admin", "JSON running:true, streaming:true, FPS sekitar 10,4", "LULUS"],
    ["T23", "Servis tracking AI", "Jalankan tracking/server.py, alirkan stream 3 detik", "Status API hidup, 1.911.805 byte MJPEG diterima, event tercatat ke MySQL (keyakinan 0,73-0,87)", "LULUS"],
    ["T24", "Halaman tracking admin", "Buka admin/tracking.php dengan stream aktif", "Stream live tampil dengan kotak deteksi dan statistik masuk-keluar", "LULUS"],
    ["T25", "Modal aksi di browser", "Klik tombol hapus/edit/keluar pada 5 halaman", "Modal konfirmasi terbuka berisi data tepat, tanpa galat JavaScript pada konsol", "LULUS"],
    ["T26", "Hapus transaksi", "Hapus transaksi uji dengan alasan wajib", "HTTP 302, baris terhapus, log audit tercatat", "LULUS"],
    ["T27", "Wizard instalasi", "Buka install.php", "HTTP 200, halaman instalasi tampil", "LULUS"],
]

CATATAN = [
    "Ekspor .xls (T13) disajikan sebagai dokumen HTML berheader "
    "application/vnd.ms-excel dengan Content-Disposition attachment. File berhasil diunduh dan "
    "dapat dibuka spreadsheet, namun bukan berkas XLS biner asli.",
    "Fitur reset data pada admin/pengaturan.php (Tidak diuji eksekusi): tombol tersebut melakukan "
    "TRUNCATE pada tabel parkir, tracking_kendaraan, dan log_aktivitas. Kehadiran formulirnya "
    "diverifikasi (HTTP 200), namun aksinya tidak dijalankan agar data pengguna tidak hilang.",
    "Perbaikan yang dilakukan: pada kondisi sebelumnya, tombol aksi modal di petugas/keluar.php dan lima "
    "halaman lain (admin/kendaraan.php, admin/users.php, admin/jenis-kendaraan.php, "
    "petugas/data-parkir.php, petugas/dashboard.php) tidak berfungsi karena pemanggilan "
    "bootstrap.Modal terjadi sebelum pustaka Bootstrap dimuat footer. Perbaikan pembungkus "
    "DOMContentLoaded telah diterapkan, di-lint, dan diuji ulang di browser lulus tanpa galat.",
    "Data uji yang dibuat selama pengujian (transaksi, akun uji, jenis uji, dan log ber-"
    "user-agent pengujian) telah dibersihkan; tabel users kembali berisi tiga akun bawaan dan "
    "tabel parkir kembali ke dua transaksi contoh semula.",
]


# ------------------------------------------------------------- utilitas --

def img_size(path):
    w, h = ImageReader(str(path)).getSize()
    return w, h


# ----------------------------------------------------------------- DOCX --

def build_docx():
    doc = Document()
    gaya = doc.styles["Normal"]
    gaya.font.name = "Calibri"
    gaya.font.size = Pt(11)

    t = doc.add_paragraph()
    t.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = t.add_run(JUDUL)
    r.bold = True
    r.font.size = Pt(16)
    s = doc.add_paragraph()
    s.alignment = WD_ALIGN_PARAGRAPH.CENTER
    rs = s.add_run(SUBJUDUL)
    rs.font.size = Pt(11)
    d = doc.add_paragraph()
    d.alignment = WD_ALIGN_PARAGRAPH.CENTER
    rd = d.add_run(f"Disusun berdasarkan pengujian langsung - {TANGGAL}")
    rd.italic = True
    doc.add_page_break()

    doc.add_heading("1. Pendahuluan", level=1)
    for p in PARAGRAF["pendahuluan"]:
        doc.add_paragraph(p)

    doc.add_heading("2. Gambaran Umum Sistem", level=1)
    doc.add_heading("2.1 Teknologi yang Digunakan", level=2)
    for p in PARAGRAF["sistem"][:1]:
        doc.add_paragraph(p)
    tb = doc.add_table(rows=0, cols=2)
    tb.style = "Light Grid Accent 1"
    for i, row in enumerate(TEKNOLOGI):
        cells = tb.add_row().cells
        cells[0].text, cells[1].text = row[0], row[1]
        if i == 0:
            for c in cells:
                for par in c.paragraphs:
                    for run in par.runs:
                        run.bold = True
    doc.add_paragraph()
    doc.add_heading("2.2 Modul Tracking Otomatis (AI Vision)", level=2)
    doc.add_paragraph(PARAGRAF["sistem"][1])
    doc.add_heading("2.3 Struktur Basis Data", level=2)
    tb = doc.add_table(rows=0, cols=2)
    tb.style = "Light Grid Accent 1"
    for i, row in enumerate(DB):
        cells = tb.add_row().cells
        cells[0].text, cells[1].text = row[0], row[1]
        if i == 0:
            for c in cells:
                for par in c.paragraphs:
                    for run in par.runs:
                        run.bold = True
    doc.add_paragraph()
    doc.add_heading("2.4 Daftar Halaman dan Hak Akses", level=2)
    tb = doc.add_table(rows=0, cols=3)
    tb.style = "Light Grid Accent 1"
    for i, row in enumerate(HALAMAN):
        cells = tb.add_row().cells
        for j in range(3):
            cells[j].text = row[j]
        if i == 0:
            for c in cells:
                for par in c.paragraphs:
                    for run in par.runs:
                        run.bold = True
    doc.add_paragraph()
    doc.add_heading("2.5 Peran Pengguna (RBAC)", level=2)
    doc.add_paragraph(PARAGRAF["sistem"][2])

    doc.add_page_break()
    doc.add_heading("3. Tangkapan Layar Aplikasi", level=1)
    doc.add_paragraph(
        "Seluruh gambar berikut diambil langsung dari aplikasi web yang berjalan "
        "(Chrome headless, lebar 1440 piksel) ketika sesi pengujian berlangsung."
    )
    for fname, ket in GAMBAR:
        path = SHOTS / fname
        doc.add_paragraph(f"{fname} - {ket}", style="Intense Quote")
        doc.add_picture(str(path), width=Inches(6.2))
        doc.add_paragraph()

    doc.add_page_break()
    doc.add_heading("4. Hasil Pengujian", level=1)
    doc.add_paragraph(
        "Pengujian dilakukan melalui dua jalur: permintaan HTTP terkontrol (curl) untuk alur "
        "backend dan interaksi browser untuk alur antarmuka. Total 27 skenario dijalankan."
    )
    tb = doc.add_table(rows=0, cols=5)
    tb.style = "Light Grid Accent 1"
    for i, row in enumerate(PENGUJIAN):
        cells = tb.add_row().cells
        for j in range(5):
            cells[j].text = row[j]
        if i == 0:
            for c in cells:
                for par in c.paragraphs:
                    for run in par.runs:
                        run.bold = True
    doc.add_paragraph()
    doc.add_heading("4.1 Catatan Jujur Hasil Pengujian", level=2)
    for c in CATATAN:
        doc.add_paragraph(c, style="List Bullet")

    doc.add_heading("5. Kesimpulan", level=1)
    for p in PARAGRAF["kesimpulan"]:
        doc.add_paragraph(p)
    doc.add_heading("6. Saran", level=1)
    for p in PARAGRAF["saran"]:
        doc.add_paragraph(p)

    doc.add_heading("7. Lokasi Berkas Hasil", level=1)
    doc.add_paragraph(
        "Laporan DOCX: laporan/laporan_sistem_monitoring_kendaraan.docx; laporan PDF: "
        "laporan/laporan_sistem_monitoring_kendaraan.pdf; 26 tangkapan layar PNG: "
        "laporan/screenshots/; skrip pengambilan tangkapan layar: "
        "laporan/take_screenshots.py; skrip pembangkit laporan ini: laporan/buat_laporan.py. "
        "Source code aplikasi tidak diubah untuk keperluan pembuatan laporan ini."
    )
    doc.save(DOCX_OUT)
    return DOCX_OUT


# ----------------------------------------------------------------- PDF --

def build_pdf():
    styles = getSampleStyleSheet()
    judul = ParagraphStyle("JudulL", parent=styles["Title"], fontSize=16, leading=20, spaceAfter=6)
    sub = ParagraphStyle("SubL", parent=styles["Normal"], fontSize=11, leading=15,
                         alignment=TA_CENTER, spaceAfter=4)
    h1 = ParagraphStyle("H1L", parent=styles["Heading1"], fontSize=13, leading=17, spaceBefore=12, spaceAfter=6)
    h2 = ParagraphStyle("H2L", parent=styles["Heading2"], fontSize=11.5, leading=15, spaceBefore=8, spaceAfter=4)
    body = ParagraphStyle("BodyL", parent=styles["Normal"], fontSize=9.5, leading=13.5,
                          alignment=TA_JUSTIFY, spaceAfter=6)
    cap = ParagraphStyle("CapL", parent=styles["Normal"], fontSize=8.5, leading=11.5,
                         textColor=colors.HexColor("#555555"), spaceAfter=3)
    cell = ParagraphStyle("CellL", parent=styles["Normal"], fontSize=7.6, leading=10)

    story = [Paragraph(JUDUL, judul), Paragraph(SUBJUDUL.replace("\n", "<br/>"), sub),
             Paragraph(f"Disusun berdasarkan pengujian langsung - {TANGGAL}", cap),
             Spacer(1, 12), PageBreak()]

    def table(data, widths):
        t = Table([[Paragraph(str(c), cell) for c in row] for row in data], colWidths=widths)
        t.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#0b2e59")),
            ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
            ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#9aa8bb")),
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#eef2f7")]),
            ("TOPPADDING", (0, 0), (-1, -1), 3),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
        ]))
        return t

    def picture(fname, width):
        w, h = img_size(SHOTS / fname)
        # Jika gambar lebih tinggi dari sisa ruang halaman, perkecil proporsional.
        max_h = 700
        if width * h / w > max_h:
            width = max_h * w / h
        return Image(str(SHOTS / fname), width=width, height=width * h / w)

    story.append(Paragraph("1. Pendahuluan", h1))
    story += [Paragraph(p, body) for p in PARAGRAF["pendahuluan"]]

    story.append(Paragraph("2. Gambaran Umum Sistem", h1))
    story.append(Paragraph("2.1 Teknologi yang Digunakan", h2))
    story.append(Paragraph(PARAGRAF["sistem"][0], body))
    story.append(table(TEKNOLOGI, [4.2 * cm, 12.3 * cm]))
    story.append(Paragraph("2.2 Modul Tracking Otomatis (AI Vision)", h2))
    story.append(Paragraph(PARAGRAF["sistem"][1], body))
    story.append(Paragraph("2.3 Struktur Basis Data", h2))
    story.append(table(DB, [4.2 * cm, 12.3 * cm]))
    story.append(Paragraph("2.4 Daftar Halaman dan Hak Akses", h2))
    story.append(table(HALAMAN, [4.4 * cm, 3.4 * cm, 8.7 * cm]))
    story.append(Paragraph("2.5 Peran Pengguna (RBAC)", h2))
    story.append(Paragraph(PARAGRAF["sistem"][2], body))

    story.append(PageBreak())
    story.append(Paragraph("3. Tangkapan Layar Aplikasi", h1))
    story.append(Paragraph(
        "Seluruh gambar berikut diambil langsung dari aplikasi web yang berjalan "
        "(Chrome headless, lebar 1440 piksel) ketika sesi pengujian berlangsung.", body))
    for fname, ket in GAMBAR:
        story.append(Paragraph(f"{fname} - {ket}", cap))
        story.append(picture(fname, 16.4 * cm))
        story.append(Spacer(1, 8))

    story.append(PageBreak())
    story.append(Paragraph("4. Hasil Pengujian", h1))
    story.append(Paragraph(
        "Pengujian dilakukan melalui dua jalur: permintaan HTTP terkontrol (curl) untuk alur "
        "backend dan interaksi browser untuk alur antarmuka. Total 27 skenario dijalankan.", body))
    story.append(table(PENGUJIAN, [1.1 * cm, 3.3 * cm, 4.6 * cm, 5.6 * cm, 1.9 * cm]))
    story.append(Paragraph("4.1 Catatan Jujur Hasil Pengujian", h2))
    for c in CATATAN:
        story.append(Paragraph(f"- {c}", body))

    story.append(Paragraph("5. Kesimpulan", h1))
    story += [Paragraph(p, body) for p in PARAGRAF["kesimpulan"]]
    story.append(Paragraph("6. Saran", h1))
    story += [Paragraph(p, body) for p in PARAGRAF["saran"]]
    story.append(Paragraph("7. Lokasi Berkas Hasil", h1))
    story.append(Paragraph(
        "Laporan DOCX: laporan/laporan_sistem_monitoring_kendaraan.docx; laporan PDF: "
        "laporan/laporan_sistem_monitoring_kendaraan.pdf; 26 tangkapan layar PNG: "
        "laporan/screenshots/; skrip pengambilan tangkapan layar: "
        "laporan/take_screenshots.py; skrip pembangkit laporan ini: laporan/buat_laporan.py. "
        "Source code aplikasi tidak diubah untuk keperluan pembuatan laporan ini.", body))

    def footer(canv, doc_):
        canv.saveState()
        canv.setFont("Helvetica", 7.5)
        canv.setFillColor(colors.HexColor("#666666"))
        canv.drawCentredString(A4[0] / 2, 1.0 * cm, f"Halaman {doc_.page} - Laporan Monitoring Kendaraan LSW")
        canv.restoreState()

    pdf = SimpleDocTemplate(
        str(PDF_OUT), pagesize=A4,
        leftMargin=2 * cm, rightMargin=2 * cm, topMargin=1.6 * cm, bottomMargin=1.6 * cm,
        title="Laporan Sistem Monitoring Kendaraan Bandara Malikussaleh",
    )
    pdf.build(story, onFirstPage=footer, onLaterPages=footer)
    return PDF_OUT


if __name__ == "__main__":
    d = build_docx()
    print("DOCX:", d, d.stat().st_size, "byte")
    p = build_pdf()
    print("PDF :", p, p.stat().st_size, "byte")
