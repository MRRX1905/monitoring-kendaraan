<?php
/**
 * Pencatatan Kendaraan Masuk
 * Pos Masuk Bandara Malikussaleh
 */
$page_title = 'Pencatatan Kendaraan Masuk';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['petugas', 'admin']);

$pdo = getDB();
$today = date('Y-m-d');
$now_time = date('H:i');

// Ambil master jenis kendaraan aktif
$stmt_jenis = $pdo->query("SELECT * FROM jenis_kendaraan WHERE status = 'aktif' ORDER BY urutan ASC");
$jenis_list = $stmt_jenis->fetchAll();

// Generate perkiraan kode transaksi & karcis berikutnya untuk preview
$next_kode = generate_kode_transaksi($pdo);
$next_karcis = generate_nomor_karcis($pdo);

// Proses Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_masuk'])) {
    $jenis_kendaraan_id = (int)($_POST['jenis_kendaraan_id'] ?? 0);
    $plat_nomor = trim($_POST['plat_nomor'] ?? '');
    $ciri_kendaraan = trim($_POST['ciri_kendaraan'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $cetak = isset($_POST['cetak_karcis']) && $_POST['cetak_karcis'] == '1';

    if ($jenis_kendaraan_id <= 0) {
        set_flash('danger', 'Harap pilih jenis kendaraan terlebih dahulu.');
    } else {
        try {
            // Generate kode transaksi & karcis final saat simpan
            $kode_final = generate_kode_transaksi($pdo);
            $karcis_final = generate_nomor_karcis($pdo);
            $petugas_id = $_SESSION['user_id'];
            $waktu_sekarang = date('H:i:s');
            $datetime_sekarang = date('Y-m-d H:i:s');

            $stmt_insert = $pdo->prepare("
                INSERT INTO parkir 
                (kode_transaksi, nomor_karcis, jenis_kendaraan_id, tanggal, waktu_masuk, datetime_masuk, plat_nomor, ciri_kendaraan, keterangan, petugas_masuk_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_insert->execute([
                $kode_final,
                $karcis_final,
                $jenis_kendaraan_id,
                $today,
                $waktu_sekarang,
                $datetime_sekarang,
                $plat_nomor ? strtoupper($plat_nomor) : null,
                $ciri_kendaraan ?: null,
                $keterangan ?: null,
                $petugas_id
            ]);

            $new_id = $pdo->lastInsertId();

            // Catat log
            log_aktivitas($pdo, "Mencatat kendaraan masuk: {$kode_final} ({$karcis_final})", 'masuk');

            if ($cetak) {
                header('Location: ' . BASE_URL . "/petugas/karcis.php?id={$new_id}&autoprint=1");
                exit;
            } else {
                set_flash('success', "Kendaraan berhasil dicatat masuk! Nomor Karcis: <strong>{$karcis_final}</strong> (Kode: <code>{$kode_final}</code>).");
                header('Location: ' . BASE_URL . '/petugas/masuk.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal menyimpan transaksi: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-right-to-bracket text-primary me-2"></i> Pos Kendaraan Masuk
        </h1>
        <div class="page-header-subtitle">
            Pencatatan digital kendaraan masuk area parkir Bandara Malikussaleh
        </div>
    </div>
    <a href="<?= BASE_URL ?>/petugas/dashboard.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <!-- Header Kartu Kemenhub -->
            <div class="bg-primary text-white p-3 px-4 d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #071d38 0%, #0b2e59 100%) !important; border-bottom: 3px solid #f59e0b;">
                <div>
                    <div class="small text-warning text-uppercase fw-bold" style="letter-spacing: 0.5px;">Loket Pintu Masuk</div>
                    <h5 class="mb-0 fw-bold">Form Registrasi Kendaraan Masuk</h5>
                </div>
                <div class="text-end">
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold">
                        <i class="fa-solid fa-ticket me-1"></i> No. Karcis: <?= $next_karcis ?>
                    </span>
                </div>
            </div>

            <div class="card-body p-4 p-md-5">
                <form action="" method="POST" id="formMasuk">
                    <!-- Info Header Otomatis -->
                    <div class="row g-3 p-3 bg-light rounded-3 border mb-4">
                        <div class="col-md-4">
                            <label class="small text-muted d-block">Kode Transaksi Sistem</label>
                            <span class="fw-bold text-primary font-monospace"><?= $next_kode ?></span>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted d-block">Tanggal & Waktu Masuk</label>
                            <span class="fw-bold text-dark"><i class="fa-regular fa-clock me-1 text-warning"></i> <?= format_tanggal_indo($today) ?> - <?= $now_time ?> WIB</span>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted d-block">Petugas Pencatat</label>
                            <span class="fw-bold text-dark"><i class="fa-solid fa-user-check me-1 text-success"></i> <?= e($_SESSION['user_nama']) ?></span>
                        </div>
                    </div>

                    <!-- 1. Pemilihan Jenis Kendaraan (Quick Cards) -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark fs-6 mb-2">
                            1. Pilih Jenis Kendaraan <span class="text-danger">*</span>
                        </label>
                        <div class="row g-3">
                            <?php foreach ($jenis_list as $index => $j): ?>
                                <div class="col-6 col-md-4 col-lg">
                                    <label class="w-100 mb-0">
                                        <input type="radio" name="jenis_kendaraan_id" value="<?= $j['id'] ?>" class="vehicle-radio" <?= $index === 0 ? 'checked' : '' ?> required>
                                        <div class="btn-vehicle-select <?= $index === 0 ? 'selected' : '' ?>">
                                            <i class="<?= e($j['icon_class']) ?> v-icon"></i>
                                            <span class="v-name"><?= e($j['nama_jenis']) ?></span>
                                            <small class="text-muted mt-1" style="font-size: 0.7rem;"><?= e($j['kode_kategori']) ?></small>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 2. Detail & Ciri Kendaraan -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="plat_nomor" class="form-label fw-semibold text-dark">
                                Plat Nomor Kendaraan <span class="text-muted small fw-normal">(Opsional jika ada)</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-id-card text-muted"></i></span>
                                <input type="text" class="form-control text-uppercase" id="plat_nomor" name="plat_nomor" placeholder="Contoh: BL 1234 AB" autocomplete="off">
                            </div>
                            <div class="form-text small text-muted">Sistem tidak mewajibkan plat nomor apabila tidak dicatat di lapangan.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="ciri_kendaraan" class="form-label fw-semibold text-dark">
                                Warna / Ciri Fisik Kendaraan <span class="text-muted small fw-normal">(Opsional)</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-palette text-muted"></i></span>
                                <input type="text" class="form-control" id="ciri_kendaraan" name="ciri_kendaraan" placeholder="Contoh: Avanza Hitam / Vario Putih" autocomplete="off">
                            </div>
                            <div class="form-text small text-muted">Membantu identifikasi saat kendaraan keluar tanpa nomor karcis.</div>
                        </div>
                    </div>

                    <!-- 3. Keterangan / Keperluan Masuk -->
                    <div class="mb-4">
                        <label for="keterangan" class="form-label fw-semibold text-dark">
                            Keterangan / Keperluan <span class="text-muted small fw-normal">(Opsional)</span>
                        </label>
                        <input type="text" class="form-control" id="keterangan" name="keterangan" placeholder="Contoh: Antar Penumpang Wings Air / Jemput Tamu / Dinas Bandara" autocomplete="off">
                    </div>

                    <hr class="my-4">

                    <!-- Tombol Aksi Simpan -->
                    <div class="d-flex flex-column flex-sm-row justify-content-between gap-3">
                        <div class="form-check d-flex align-items-center">
                            <input class="form-check-input me-2" type="checkbox" name="cetak_karcis" id="cetak_karcis" value="1">
                            <label class="form-check-label fw-semibold text-dark" for="cetak_karcis">
                                <i class="fa-solid fa-print me-1 text-primary"></i> Langsung cetak struk karcis untuk pengendara
                            </label>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="reset" class="btn btn-light border px-4">
                                <i class="fa-solid fa-rotate-left me-1"></i> Reset
                            </button>
                            <button type="submit" name="simpan_masuk" value="1" class="btn btn-primary btn-lg px-4 shadow-sm" style="background: #0b2e59; border-color: #0b2e59;">
                                <i class="fa-solid fa-floppy-disk me-2 text-warning"></i> Simpan Kendaraan Masuk
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Radio button style visual highlighter
    document.querySelectorAll('.vehicle-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.btn-vehicle-select').forEach(card => card.classList.remove('selected'));
            if (this.checked) {
                this.nextElementSibling.classList.add('selected');
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
