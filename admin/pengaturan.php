<?php
/**
 * Pengaturan Profil Instansi & Sistem
 * Bandara Malikussaleh (LSW)
 */
$page_title = 'Pengaturan Bandara';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['admin']);

$pdo = getDB();

// Simpan Perubahan Pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_pengaturan'])) {
    $nama_aplikasi = trim($_POST['nama_aplikasi'] ?? '');
    $nama_instansi = trim($_POST['nama_instansi'] ?? '');
    $nama_unit = trim($_POST['nama_unit'] ?? '');
    $nama_bandara = trim($_POST['nama_bandara'] ?? '');
    $kode_bandara = strtoupper(trim($_POST['kode_bandara'] ?? 'LSW'));
    $alamat_bandara = trim($_POST['alamat_bandara'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $email = trim($_POST['email'] ?? '');

    try {
        $stmt = $pdo->prepare("
            UPDATE pengaturan SET 
                nama_aplikasi = ?,
                nama_instansi = ?,
                nama_unit = ?,
                nama_bandara = ?,
                kode_bandara = ?,
                alamat_bandara = ?,
                telepon = ?,
                email = ?
            WHERE id = 1
        ");
        $stmt->execute([
            $nama_aplikasi, $nama_instansi, $nama_unit, $nama_bandara, $kode_bandara, $alamat_bandara, $telepon, $email
        ]);

        log_aktivitas($pdo, "Memperbarui konfigurasi profil bandara", 'master');
        set_flash('success', "Konfigurasi instansi dan profil bandara berhasil disimpan.");
        header('Location: ' . BASE_URL . '/admin/pengaturan.php');
        exit;
    } catch (Exception $e) {
        set_flash('danger', 'Gagal menyimpan pengaturan: ' . $e->getMessage());
    }
}

// Reset Data Transaksi & Tracking (Kecuali Akun User)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_data_transaksi'])) {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE parkir; TRUNCATE TABLE tracking_kendaraan; TRUNCATE TABLE log_aktivitas; SET FOREIGN_KEY_CHECKS = 1;");
        log_aktivitas($pdo, "Melakukan Reset Data Transaksi & Tracking (Akun Pengguna Tetap Utuh)", 'sistem');
        set_flash('success', "Basis data transaksi, tracking, dan log berhasil di-reset bersih! Akun pengguna tetap aman.");
        header('Location: ' . BASE_URL . '/admin/pengaturan.php');
        exit;
    } catch (Exception $e) {
        set_flash('danger', 'Gagal mereset database: ' . $e->getMessage());
    }
}

// Ambil data pengaturan saat ini
$stmt_get = $pdo->query("SELECT * FROM pengaturan WHERE id = 1");
$pengaturan = $stmt_get->fetch();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-sliders text-primary me-2"></i> Pengaturan & Profil Bandara
        </h1>
        <div class="page-header-subtitle">
            Konfigurasi identitas Kementerian Perhubungan, alamat operasional, dan data sistem
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-building-columns text-primary me-2"></i> Identitas Instansi & Bandara
                </h6>
            </div>
            <div class="card-body p-4">
                <form action="" method="POST">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Nama Aplikasi Sistem</label>
                            <input type="text" name="nama_aplikasi" class="form-control" value="<?= e($pengaturan['nama_aplikasi'] ?? APP_NAME) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Kode Bandara (IATA)</label>
                            <input type="text" name="kode_bandara" class="form-control text-uppercase font-monospace" value="<?= e($pengaturan['kode_bandara'] ?? AIRPORT_CODE) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kementerian / Instansi Induk</label>
                        <input type="text" name="nama_instansi" class="form-control" value="<?= e($pengaturan['nama_instansi'] ?? INSTANSI_NAME) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Unit Pelaksana Teknis / Kantor UPBU</label>
                        <input type="text" name="nama_unit" class="form-control" value="<?= e($pengaturan['nama_unit'] ?? INSTANSI_SUB) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Resmi Bandara</label>
                        <input type="text" name="nama_bandara" class="form-control" value="<?= e($pengaturan['nama_bandara'] ?? AIRPORT_NAME) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alamat Lengkap Bandara</label>
                        <textarea name="alamat_bandara" class="form-control" rows="3" required><?= e($pengaturan['alamat_bandara'] ?? AIRPORT_ADDRESS) ?></textarea>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nomor Telepon Operasional</label>
                            <input type="text" name="telepon" class="form-control" value="<?= e($pengaturan['telepon'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Email Resmi Bandara</label>
                            <input type="email" name="email" class="form-control" value="<?= e($pengaturan['email'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" name="simpan_pengaturan" value="1" class="btn btn-primary px-4 shadow-sm" style="background: #0b2e59; border-color: #0b2e59;">
                            <i class="fa-solid fa-floppy-disk me-1 text-warning"></i> Simpan Konfigurasi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Informasi Sistem -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-server text-primary me-2"></i> Lingkungan Sistem
                </h6>
            </div>
            <div class="card-body p-4 small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Web Server:</span>
                    <span class="fw-bold">Laragon (Apache/Nginx)</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Versi PHP:</span>
                    <span class="fw-bold"><?= phpversion() ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Zona Waktu:</span>
                    <span class="fw-bold">Asia/Jakarta (WIB)</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Database Engine:</span>
                    <span class="fw-bold">MySQL InnoDB</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Status Koneksi DB:</span>
                    <span class="badge bg-success">Terhubung</span>
                </div>
            </div>
        </div>

        <!-- Tombol Reset / Setup Database -->
        <div class="card border-0 shadow-sm rounded-4 border-start border-warning border-4 mb-3">
            <div class="card-body p-4">
                <h6 class="fw-bold text-dark mb-2">
                    <i class="fa-solid fa-wrench text-warning me-1"></i> Setup / Inisialisasi Ulang
                </h6>
                <p class="small text-muted mb-3">
                    Jalankan wizard instalasi jika Anda ingin menyetel ulang skema dasar sistem.
                </p>
                <a href="<?= BASE_URL ?>/install.php" class="btn btn-outline-warning text-dark btn-sm w-100 fw-bold" onclick="return confirm('Membuka halaman setup/reset database?');">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Buka Wizard Instalasi
                </a>
            </div>
        </div>

        <!-- Tombol Reset Transaksi (Kecuali Akun User) -->
        <div class="card border-0 shadow-sm rounded-4 border-start border-danger border-4">
            <div class="card-body p-4">
                <h6 class="fw-bold text-danger mb-2">
                    <i class="fa-solid fa-database text-danger me-1"></i> Reset Data (Kecuali Akun User)
                </h6>
                <p class="small text-muted mb-3">
                    Mengosongkan seluruh riwayat transaksi parkir, rekaman tracking AI, dan log aktivitas. Akun pengguna, jenis kendaraan, dan pengaturan profil tetap utuh.
                </p>
                <form method="POST" action="" onsubmit="return confirm('APAKAH ANDA YAKIN? Seluruh data transaksi parkir dan tracking akan dikosongkan. Akun pengguna tetap aman.');">
                    <button type="submit" name="reset_data_transaksi" class="btn btn-outline-danger btn-sm w-100 fw-bold">
                        <i class="fa-solid fa-trash-can me-1"></i> Reset Transaksi & Tracking
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
