<?php
/**
 * Setup & Inisialisasi Database Otomatis
 * Sistem Monitoring Kendaraan Bandara Malikussaleh (LSW)
 * Kementerian Perhubungan Republik Indonesia
 */

require_once __DIR__ . '/config/app.php';

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_db'])) {
    $db_host = '127.0.0.1';
    $db_port = '3306';
    $db_user = 'root';
    $db_pass = '';

    try {
        // Koneksi ke server MySQL tanpa memilih database
        $pdo_raw = new PDO("mysql:host={$db_host};port={$db_port};charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $sql_file = __DIR__ . '/database.sql';
        if (!file_exists($sql_file)) {
            throw new Exception("File database.sql tidak ditemukan.");
        }

        $sql_content = file_get_contents($sql_file);

        // Eksekusi skrip SQL pembuatan tabel dasar
        $pdo_raw->exec($sql_content);

        // Koneksikan langsung ke database parkir_malikussaleh untuk insert user dengan Bcrypt dinamis
        $pdo_app = new PDO("mysql:host={$db_host};port={$db_port};dbname=parkir_malikussaleh;charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Update password yang pasti valid dengan password_hash PHP lokal saat ini
        $hash_admin = password_hash('admin123', PASSWORD_BCRYPT);
        $hash_petugas = password_hash('petugas123', PASSWORD_BCRYPT);
        $hash_supervisor = password_hash('supervisor123', PASSWORD_BCRYPT);

        $stmt = $pdo_app->prepare("UPDATE `users` SET `password` = ? WHERE `username` = 'admin'");
        $stmt->execute([$hash_admin]);

        $stmt = $pdo_app->prepare("UPDATE `users` SET `password` = ? WHERE `username` = 'petugas'");
        $stmt->execute([$hash_petugas]);

        $stmt = $pdo_app->prepare("UPDATE `users` SET `password` = ? WHERE `username` = 'supervisor'");
        $stmt->execute([$hash_supervisor]);

        // Cek jika sample transaksi ingin ditambahkan
        if (isset($_POST['include_sample'])) {
            $today = date('Y-m-d');
            $samples = [
                ['MLK-' . date('Ymd') . '-0001', 'K-001', 2, $today, '07:30:00', $today . ' 07:30:00', '09:15:00', $today . ' 09:15:00', 105, 'BL 1422 ZC', 'Toyota Avanza Hitam', 'Antar penumpang Wings Air', 2, 2],
                ['MLK-' . date('Ymd') . '-0002', 'K-002', 1, $today, '08:05:00', $today . ' 08:05:00', '10:00:00', $today . ' 10:00:00', 115, 'BL 5890 KB', 'Honda Vario Putih', 'Jemput kerabat', 2, 2],
                ['MLK-' . date('Ymd') . '-0003', 'K-003', 2, $today, '08:45:00', $today . ' 08:45:00', NULL, NULL, NULL, 'BL 1024 AA', 'Innova Reborn Silver', 'Parkir tamu UPBU', 2, NULL],
                ['MLK-' . date('Ymd') . '-0004', 'K-004', 3, $today, '09:10:00', $today . ' 09:10:00', NULL, NULL, NULL, 'BL 7001 ZB', 'HiAce Travel Lhokseumawe', 'Rombongan dinas', 2, NULL],
                ['MLK-' . date('Ymd') . '-0005', 'K-005', 1, $today, '09:40:00', $today . ' 09:40:00', NULL, NULL, NULL, 'BL 3211 KK', 'Yamaha NMAX Hitam', 'Pengantar barang', 2, NULL],
                ['MLK-' . date('Ymd') . '-0006', 'K-006', 5, $today, '07:00:00', $today . ' 07:00:00', NULL, NULL, NULL, 'BL 1 LSW', 'Kendaraan Patroli Avsec', 'Operasional keamanan bandara', 2, NULL],
            ];

            $stmt_parkir = $pdo_app->prepare("
                INSERT IGNORE INTO `parkir` 
                (`kode_transaksi`, `nomor_karcis`, `jenis_kendaraan_id`, `tanggal`, `waktu_masuk`, `datetime_masuk`, `waktu_keluar`, `datetime_keluar`, `durasi_menit`, `plat_nomor`, `ciri_kendaraan`, `keterangan`, `petugas_masuk_id`, `petugas_keluar_id`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($samples as $s) {
                $stmt_parkir->execute($s);
            }
        }

        $success = "Database <strong>parkir_malikussaleh</strong> berhasil dipasang dan siap digunakan!";
    } catch (Exception $e) {
        $error = "Gagal inisialisasi: " . $e->getMessage() . "<br><small class='text-muted'>Pastikan Laragon sudah dalam kondisi <strong>Start All</strong> dan MySQL berjalan.</small>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_clean_data'])) {
    try {
        require_once __DIR__ . '/config/database.php';
        $pdo = getDB();
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE parkir; TRUNCATE TABLE tracking_kendaraan; TRUNCATE TABLE log_aktivitas; SET FOREIGN_KEY_CHECKS = 1;");
        $success = "Data transaksi parkir, rekaman tracking AI, dan log aktivitas berhasil di-reset menjadi kosong (0 data). Akun pengguna dan master jenis kendaraan tetap utuh!";
    } catch (Exception $e) {
        $error = "Gagal reset data: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inisialisasi Database - <?= APP_NAME ?></title>
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --kemenhub-navy: #0b2e59;
            --kemenhub-dark: #071e3d;
            --kemenhub-gold: #f59e0b;
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #071e3d 0%, #0b2e59 50%, #1e4976 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .install-card {
            max-width: 680px;
            width: 100%;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 45px rgba(0,0,0,0.3);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .install-header {
            background: #0b2e59;
            color: #ffffff;
            padding: 30px;
            text-align: center;
            border-bottom: 4px solid var(--kemenhub-gold);
            position: relative;
        }
        .account-badge {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
        }
    </style>
</head>
<body>

<div class="install-card">
    <div class="install-header">
        <div class="mb-2">
            <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                <i class="fa-solid fa-plane-departure me-1"></i> <?= INSTANSI_NAME ?>
            </span>
        </div>
        <h4 class="fw-bold mb-1"><?= APP_NAME ?></h4>
        <div class="small opacity-75"><?= UPBU_NAME ?> (<?= AIRPORT_CODE ?>)</div>
    </div>

    <div class="p-4 p-md-5">
        <?php if ($success): ?>
            <div class="alert alert-success border-0 shadow-sm p-4 text-center">
                <div class="text-success mb-2"><i class="fa-solid fa-circle-check fa-3x"></i></div>
                <h5 class="fw-bold text-success mb-2">Instalasi Berhasil Sempurna!</h5>
                <p class="mb-3"><?= $success ?></p>
                <div class="card p-3 mb-4 bg-light text-start small">
                    <div class="fw-bold mb-2 text-dark">Akun Login Bawaan:</div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <div class="account-badge">
                                <span class="badge bg-danger mb-1">Admin</span>
                                <div>User: <code>admin</code></div>
                                <div>Pass: <code>admin123</code></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="account-badge">
                                <span class="badge bg-primary mb-1">Petugas</span>
                                <div>User: <code>petugas</code></div>
                                <div>Pass: <code>petugas123</code></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="account-badge">
                                <span class="badge bg-warning text-dark mb-1">Supervisor</span>
                                <div>User: <code>supervisor</code></div>
                                <div>Pass: <code>supervisor123</code></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary btn-lg shadow" style="background: #0b2e59; border-color: #0b2e59;">
                        <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Buka Halaman Login Petugas
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 shadow-sm mb-4">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-circle-xmark fa-2x me-3"></i>
                        <div><?= $error ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <h5 class="fw-bold text-dark mb-2">Inisialisasi Database Sistem Monitoring Kendaraan</h5>
                <p class="text-muted small">
                    Skrip ini akan membuat database <code>parkir_malikussaleh</code>, 6 tabel inti (pengaturan, users multi-role, master jenis kendaraan, transaksi parkir, audit log, dan tracking otomatis AI), serta akun awal untuk operasional.
                </p>
            </div>

            <form method="POST" action="">
                <div class="mb-4 p-3 bg-light rounded-3 border">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="include_sample" id="include_sample" value="1" checked>
                        <label class="form-check-label fw-semibold text-dark" for="include_sample">
                            Isi Data Transaksi Contoh (Dummy Data)
                        </label>
                        <div class="text-muted small">
                            Menambahkan beberapa data kendaraan (mobil, motor, bus, kendaraan dinas) dengan catatan masuk & keluar agar dashboard langsung menampilkan visual statistik.
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" name="install_db" value="1" class="btn btn-primary btn-lg shadow" style="background: #0b2e59; border-color: #0b2e59;">
                        <i class="fa-solid fa-database me-2"></i> Jalankan Instalasi Database Sekarang
                    </button>
                    <a href="<?= BASE_URL ?>" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i> Buka Beranda
                    </a>
                </div>
            </form>

            <hr class="my-4">

            <div class="card border border-danger-subtle bg-danger-subtle p-3 rounded-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <div class="fw-bold text-danger"><i class="fa-solid fa-trash-can me-1"></i> Reset Data Transaksi Saja</div>
                        <div class="small text-muted">Mengosongkan tabel transaksi parkir & tracking. Akun pengguna tetap aman dan tidak dihapus.</div>
                    </div>
                    <form method="POST" action="" onsubmit="return confirm('Kosongkan semua data transaksi parkir & tracking? Akun pengguna tetap aman.');">
                        <button type="submit" name="reset_clean_data" value="1" class="btn btn-outline-danger btn-sm fw-bold">
                            Reset Transaksi (Kecuali Akun)
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
