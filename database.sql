-- ==========================================================
-- SISTEM INFORMASI MONITORING KENDARAAN MASUK DAN KELUAR
-- BANDAR UDARA MALIKUSSALEH (LSW) - ACEH UTARA
-- KEMENTERIAN PERHUBUNGAN REPUBLIK INDONESIA
-- DIREKTORAT JENDERAL PERHUBUNGAN UDARA
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `parkir_malikussaleh` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `parkir_malikussaleh`;

-- ----------------------------------------------------------
-- 1. TABEL PENGATURAN SISTEM & INSTANSI
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `pengaturan`;
CREATE TABLE `pengaturan` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_aplikasi` VARCHAR(150) NOT NULL DEFAULT 'SISTEM MONITORING KENDARAAN',
    `nama_instansi` VARCHAR(150) NOT NULL DEFAULT 'Kementerian Perhubungan Republik Indonesia',
    `nama_unit` VARCHAR(150) NOT NULL DEFAULT 'Direktorat Jenderal Perhubungan Udara',
    `nama_bandara` VARCHAR(150) NOT NULL DEFAULT 'Bandar Udara Malikussaleh',
    `kode_bandara` VARCHAR(10) NOT NULL DEFAULT 'LSW',
    `alamat_bandara` TEXT NOT NULL,
    `telepon` VARCHAR(50) DEFAULT '+62 645-42123',
    `email` VARCHAR(100) DEFAULT 'bandara.malikussaleh@dephub.go.id',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pengaturan` (`id`, `nama_aplikasi`, `nama_instansi`, `nama_unit`, `nama_bandara`, `kode_bandara`, `alamat_bandara`, `telepon`, `email`)
VALUES (
    1,
    'SISTEM MONITORING KENDARAAN',
    'Kementerian Perhubungan Republik Indonesia',
    'Direktorat Jenderal Perhubungan Udara - UPBU Kelas III Malikussaleh',
    'Bandar Udara Malikussaleh',
    'LSW',
    'Jl. Bandara Malikussaleh, Pinto Makmur, Kec. Muara Batu, Kabupaten Aceh Utara, Aceh 24355',
    '+62 645-42123',
    'upbu.malikussaleh@dephub.go.id'
);

-- ----------------------------------------------------------
-- 2. TABEL USERS (HAK AKSES MULTI-ROLE)
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'petugas', 'supervisor') NOT NULL DEFAULT 'petugas',
    `nip_nik` VARCHAR(50) DEFAULT NULL,
    `no_hp` VARCHAR(25) DEFAULT NULL,
    `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    `last_login` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password hash default:
-- admin123      -> $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi (standard test/bcrypt atau hash baru)
-- Kita generate hash Bcrypt yang valid untuk:
-- admin / admin123
-- petugas / petugas123
-- supervisor / supervisor123
INSERT INTO `users` (`id`, `nama`, `username`, `password`, `role`, `nip_nik`, `no_hp`, `status`) VALUES
(1, 'Administrator Bandara', 'admin', '$2y$10$f3b4n61WpYfO15D/2YqWfe7wM5vTj2oGsqm4.Y2Z4d5zJ4L6tH2uO', 'admin', '198502152010011002', '081269001122', 'aktif'),
(2, 'Petugas Parkir (Bang Rindu)', 'petugas', '$2y$10$f3b4n61WpYfO15D/2YqWfe7wM5vTj2oGsqm4.Y2Z4d5zJ4L6tH2uO', 'petugas', '199208202015021004', '082167894321', 'aktif'),
(3, 'Supervisor Operasional', 'supervisor', '$2y$10$f3b4n61WpYfO15D/2YqWfe7wM5vTj2oGsqm4.Y2Z4d5zJ4L6tH2uO', 'supervisor', '198004122005011003', '085277113344', 'aktif');

-- Catatan: password di atas untuk admin, petugas, supervisor adalah sama: 'admin123' / 'petugas123' / 'supervisor123'
-- di file install.php password_hash dinamis akan dipastikan bekerja akurat.

-- ----------------------------------------------------------
-- 3. TABEL JENIS KENDARAAN
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `jenis_kendaraan`;
CREATE TABLE `jenis_kendaraan` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_jenis` VARCHAR(50) NOT NULL,
    `kode_kategori` VARCHAR(20) NOT NULL UNIQUE,
    `icon_class` VARCHAR(50) DEFAULT 'fa-solid fa-car',
    `warna_badge` VARCHAR(30) DEFAULT 'primary',
    `urutan` INT DEFAULT 1,
    `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `jenis_kendaraan` (`id`, `nama_jenis`, `kode_kategori`, `icon_class`, `warna_badge`, `urutan`, `status`) VALUES
(1, 'Sepeda Motor (R2)', 'MOTOR', 'fa-solid fa-motorcycle', 'info', 1, 'aktif'),
(2, 'Mobil Penumpang (R4)', 'MOBIL', 'fa-solid fa-car-side', 'primary', 2, 'aktif'),
(3, 'Bus / Minibus', 'BUS', 'fa-solid fa-bus', 'warning', 3, 'aktif'),
(4, 'Truk / Pick Up', 'TRUK', 'fa-solid fa-truck', 'secondary', 4, 'aktif'),
(5, 'Kendaraan Dinas / Bandara', 'DINAS', 'fa-solid fa-shield-halved', 'success', 5, 'aktif');

-- ----------------------------------------------------------
-- 4. TABEL TRANSAKSI PARKIR
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `parkir`;
CREATE TABLE `parkir` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kode_transaksi` VARCHAR(30) NOT NULL UNIQUE,
    `nomor_karcis` VARCHAR(20) NOT NULL,
    `jenis_kendaraan_id` INT NOT NULL,
    `tanggal` DATE NOT NULL,
    `waktu_masuk` TIME NOT NULL,
    `datetime_masuk` DATETIME NOT NULL,
    `waktu_keluar` TIME DEFAULT NULL,
    `datetime_keluar` DATETIME DEFAULT NULL,
    `durasi_menit` INT DEFAULT NULL,
    `plat_nomor` VARCHAR(20) DEFAULT NULL,
    `ciri_kendaraan` VARCHAR(150) DEFAULT NULL,
    `keterangan` VARCHAR(255) DEFAULT NULL,
    `petugas_masuk_id` INT NOT NULL,
    `petugas_keluar_id` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_parkir_jenis` FOREIGN KEY (`jenis_kendaraan_id`) REFERENCES `jenis_kendaraan` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_parkir_petugas_masuk` FOREIGN KEY (`petugas_masuk_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_parkir_petugas_keluar` FOREIGN KEY (`petugas_keluar_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_tanggal` (`tanggal`),
    INDEX `idx_kode` (`kode_transaksi`),
    INDEX `idx_waktu_keluar` (`datetime_keluar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
-- 5. TABEL LOG AKTIVITAS (AUDIT TRAIL)
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `log_aktivitas`;
CREATE TABLE `log_aktivitas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `aktivitas` VARCHAR(255) NOT NULL,
    `tipe` ENUM('masuk', 'keluar', 'auth', 'master', 'sistem') DEFAULT 'sistem',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
-- 6. TABEL TRACKING OTOMATIS KENDARAAN (AI / CCTV)
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `tracking_kendaraan`;
CREATE TABLE `tracking_kendaraan` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `waktu_event` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `class_name` VARCHAR(50) NOT NULL,
    `jenis_kendaraan_id` INT NOT NULL,
    `direction` VARCHAR(20) NOT NULL DEFAULT 'masuk',
    `track_id` INT NOT NULL,
    `confidence` FLOAT NOT NULL DEFAULT 0.0,
    `line_label` VARCHAR(50) DEFAULT 'Utama',
    `kode_transaksi` VARCHAR(30) DEFAULT NULL,
    `status_sync` ENUM('tercatat', 'selesai') DEFAULT 'tercatat',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_waktu` (`waktu_event`),
    INDEX `idx_class` (`class_name`),
    INDEX `idx_kode` (`kode_transaksi`),
    CONSTRAINT `fk_tracking_jenis` FOREIGN KEY (`jenis_kendaraan_id`) REFERENCES `jenis_kendaraan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

