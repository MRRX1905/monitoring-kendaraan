# Sistem Informasi Monitoring Kendaraan Masuk & Keluar Area Parkir
### Bandar Udara Malikussaleh (LSW) - Aceh Utara
**Kementerian Perhubungan Republik Indonesia &bull; Direktorat Jenderal Perhubungan Udara**

---

## 🛫 Gambaran Umum
Sistem Informasi ini dirancang khusus untuk memodernisasi dan mendigitalisasi proses pencatatan kendaraan masuk dan keluar pada area parkir **Bandar Udara Malikussaleh**. Sistem beroperasi tanpa kewajiban plat nomor (menggunakan **Kode Transaksi Unik** dan **Nomor Karcis Harian** otomatis sesuai kondisi eksisting di lapangan), dilengkapi dengan *live dashboard monitoring*, modul pos masuk cepat, modul pos keluar interaktif, cetak karcis struk, dan rekapitulasi laporan resmi ber-Kop Kementerian Perhubungan.

---

## 🏛️ Identitas & Tema Kementerian Perhubungan
- **Warna Utama:** Biru Kemenhub (*Deep Navy Blue* `#0b2e59` & `#071d38`)
- **Aksen:** Emas / Kuning Resmi Perhubungan (*Amber Gold* `#f59e0b`)
- **Background & Card:** Bersih, modern, kontras tinggi (*Clean Aviation UI* `#f3f6fa` & `#ffffff`)
- **Tipografi:** Google Fonts *Inter*
- **Kop Surat Cetak:** Standar kedinasan Kementerian Perhubungan Republik Indonesia - Kantor UPBU Kelas III Malikussaleh

---

## ⚡ Cara Menjalankan di Laragon

1. **Nyalakan Laragon:**
   - Buka aplikasi **Laragon**.
   - Klik tombol **Start All** (memastikan Apache & MySQL berjalan).

2. **Inisialisasi Database (1-Klik):**
   - Buka browser dan akses alamat instalasi otomatis:
     ```
     http://localhost/parkir-bandara-malikussaleh/install.php
     ```
   - Klik tombol **"Jalankan Instalasi Database Sekarang"**.
   - Database `parkir_malikussaleh`, 5 tabel inti, master data, dan contoh transaksi dummy akan langsung terpasang secara otomatis!

3. **Buka Aplikasi Utama:**
   - Akses:
     ```
     http://localhost/parkir-bandara-malikussaleh
     ```
   - Halaman login resmi Kemenhub akan langsung terbuka.

---

## 🔑 Akun Login Bawaan (Default Credentials)

| Role / Hak Akses | Username | Password | Deskripsi Tugas |
| :--- | :--- | :--- | :--- |
| **Petugas Pos** | `petugas` | `petugas123` | Mencatat kendaraan masuk, mencatat keluar, cetak karcis, melihat riwayat |
| **Administrator** | `admin` | `admin123` | Akses penuh: kelola pengguna, master jenis kendaraan, profil bandara, log audit |
| **Supervisor** | `supervisor` | `supervisor123` | Monitoring manajerial, grafik tren per jam, rekapitulasi laporan, cetak resmi & Excel |

---

## 🚗 Fitur Utama Sistem

1. **Pos Kendaraan Masuk:**
   - Shortcut pemilihan cepat jenis kendaraan (Sepeda Motor, Mobil Pribadi, Bus/Minibus, Truk, Kendaraan Dinas).
   - Penomoran karcis urut harian otomatis (`K-001`, `K-002`, ...).
   - Kode transaksi unik sistem (`MLK-YYYYMMDD-XXXX`).
   - Jam masuk tersinkronisasi otomatis dengan server (WIB).
   - Input plat nomor dan ciri fisik (bersifat opsional, tidak menghambat antrean).
   - Opsi langsung cetak karcis tanda masuk struk mini untuk pengendara.

2. **Pos Kendaraan Keluar:**
   - Menampilkan daftar kendaraan yang belum diproses keluar secara *real-time*.
   - Filter pencarian cepat (*live search*) berdasarkan nomor karcis, kode transaksi, jenis kendaraan, atau warna/ciri fisik.
   - Modal konfirmasi keluar 1-klik yang otomatis menghitung durasi parkir dalam jam dan menit.

3. **Cetak Karcis Struk Thermal:**
   - Layout struk 58mm / 80mm siap cetak ke printer thermal loket pos parkir.
   - Dilengkapi identitas bandara, barcode visual, nomor karcis besar, dan tata tertib keselamatan.

4. **Dashboard Monitoring Real-Time:**
   - Dua kartu indikator utama: *Masuk Hari Ini* dan *Keluar Hari Ini*.
   - Panel *Perhitungan Tracking Otomatis (AI Vision)*: jumlah kendaraan terdeteksi masuk, keluar, dan total lalu lintas.
   - Tabel 10 aktivitas transaksi terbaru hari ini.

5. **Dashboard Supervisor & Grafik Analitik:**
   - Grafik fluktuasi volume kendaraan per jam (06:00 - 18:00 WIB).
   - Diagram proporsi jenis kendaraan (Donut chart).
   - Total volume pergerakan lalu lintas kendaraan di area bandara.

6. **Rekapitulasi Laporan & Export:**
   - Filter fleksibel: Harian, Mingguan, Bulanan, atau rentang tanggal bebas.
   - Rekap total volume per jenis kendaraan.
   - Rekap *Perhitungan Tracking Otomatis (AI Vision)*: deteksi masuk & keluar per kelas kendaraan.
   - **Mode Cetak Resmi:** Tampilan siap cetak lengkap dengan Kop Surat Kemenhub dan kolom pengesahan tanda tangan Supervisor & Petugas Pelaksana.
   - **Export Excel:** Unduh rekapitulasi data lengkap ke dalam format spreadsheet (.xls).

7. **Manajemen Master Data & Pengguna (Admin):**
   - Tambah, edit, dan reset password petugas / supervisor.
   - Tambah dan atur master jenis kendaraan serta icon yang tampil di loket.
   - Pengaturan identitas dan alamat resmi bandara.

---

## 📂 Struktur Direktori Proyek

```text
C:\laragon\www\parkir-bandara-malikussaleh\
├── config/
│   ├── app.php                # Konstanta instansi Kemenhub, timezone WIB, BASE_URL
│   └── database.php           # Koneksi PDO MySQL & penanganan error
├── includes/
│   ├── auth_check.php         # Middleware otentikasi & RBAC
│   ├── helpers.php            # Format tanggal, kode karcis, durasi, audit log
│   ├── header.php             # Template header & CSS Kemenhub
│   ├── navbar.php             # Topbar navigasi & profil petugas
│   ├── sidebar.php            # Menu dinamis sesuai role pengguna
│   └── footer.php             # Template footer & script jam live
├── auth/
│   ├── login.php              # Halaman login tema Kemenhub
│   ├── proses-login.php       # Validasi Bcrypt & sesi
│   └── logout.php             # Destruksi sesi aman
├── petugas/
│   ├── dashboard.php          # Monitoring real-time petugas pos
│   ├── masuk.php              # Form pencatatan kendaraan masuk
│   ├── keluar.php             # Pos keluar & filter kendaraan aktif
│   ├── karcis.php             # Cetak struk karcis mini untuk pengendara
│   └── data-parkir.php        # Riwayat pencatatan lengkap multi-filter
├── supervisor/
│   ├── dashboard.php          # Dashboard analitik grafik fluktuasi & proporsi
│   └── laporan.php            # Rekapitulasi, cetak resmi ber-Kop & export Excel
├── admin/
│   ├── dashboard.php          # Panel kontrol admin & audit log
│   ├── users.php              # Manajemen akun & reset password
│   ├── jenis-kendaraan.php    # Master data jenis kendaraan
│   └── pengaturan.php         # Konfigurasi profil bandara
├── assets/
│   └── css/
│       └── kemenhub-theme.css # Stylesheet tema resmi Kementerian Perhubungan
├── database.sql               # Skrip DDL MySQL & data awal
├── install.php                # Wizard setup database otomatis 1-klik
├── index.php                  # Entry point router aplikasi
└── README.md                  # Dokumentasi proyek
```

---
*Dikembangkan untuk Unit Pengelola Bandar Udara (UPBU) Kelas III Malikussaleh - Lhokseumawe, Aceh Utara.*
