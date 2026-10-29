<?php
/**
 * Manajemen Kendaraan & Data Transaksi
 * Bandara Malikussaleh (LSW)
 * Kementerian Perhubungan Republik Indonesia
 */
$page_title = 'Manajemen Kendaraan';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['admin', 'supervisor', 'petugas']);

$pdo = getDB();
$today = date('Y-m-d');
$now_time = date('H:i:s');
$now_datetime = date('Y-m-d H:i:s');

// 1. Catat Kendaraan Masuk Manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_kendaraan'])) {
    $jenis_id = (int)($_POST['jenis_kendaraan_id'] ?? 0);
    $plat_nomor = strtoupper(trim($_POST['plat_nomor'] ?? ''));
    $ciri = trim($_POST['ciri_kendaraan'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($jenis_id <= 0) {
        set_flash('danger', 'Silakan pilih jenis kendaraan.');
    } else {
        try {
            $kode_trx = generate_kode_transaksi($pdo);
            $no_karcis = generate_nomor_karcis($pdo);
            $petugas_id = $_SESSION['user_id'];

            $stmt_in = $pdo->prepare("
                INSERT INTO parkir (
                    kode_transaksi, nomor_karcis, jenis_kendaraan_id,
                    tanggal, waktu_masuk, datetime_masuk,
                    plat_nomor, ciri_kendaraan, keterangan,
                    petugas_masuk_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_in->execute([
                $kode_trx, $no_karcis, $jenis_id,
                $today, $now_time, $now_datetime,
                $plat_nomor ?: null, $ciri ?: null, $keterangan ?: null,
                $petugas_id
            ]);

            log_aktivitas($pdo, "Input kendaraan masuk: {$kode_trx} ({$no_karcis}) Plat: " . ($plat_nomor ?: 'Tanpa Plat'), 'masuk');
            set_flash('success', "Kendaraan berhasil dicatat masuk! No. Karcis: <strong>{$no_karcis}</strong> (Kode: {$kode_trx})");
            header('Location: ' . BASE_URL . '/admin/kendaraan.php');
            exit;
        } catch (Exception $e) {
            set_flash('danger', 'Gagal mencatat kendaraan: ' . $e->getMessage());
        }
    }
}

// 2. Proses Kendaraan Keluar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_keluar'])) {
    $id = (int)($_POST['id'] ?? 0);
    $keterangan_keluar = trim($_POST['keterangan_keluar'] ?? '');

    if ($id <= 0) {
        set_flash('danger', 'Data kendaraan tidak valid.');
    } else {
        try {
            $stmt_cur = $pdo->prepare("SELECT * FROM parkir WHERE id = ?");
            $stmt_cur->execute([$id]);
            $trx = $stmt_cur->fetch();

            if (!$trx) {
                set_flash('danger', 'Transaksi tidak ditemukan.');
            } elseif (!empty($trx['datetime_keluar'])) {
                set_flash('warning', 'Kendaraan ini sudah diproses keluar sebelumnya.');
            } else {
                $t_masuk = strtotime($trx['datetime_masuk']);
                $t_keluar = time();
                $durasi_menit = max(1, (int)round(($t_keluar - $t_masuk) / 60));

                $ket_final = $trx['keterangan'];
                if ($keterangan_keluar) {
                    $ket_final = $ket_final ? $ket_final . ' | ' . $keterangan_keluar : $keterangan_keluar;
                }

                $stmt_out = $pdo->prepare("
                    UPDATE parkir SET 
                        waktu_keluar = ?,
                        datetime_keluar = ?,
                        durasi_menit = ?,
                        keterangan = ?,
                        petugas_keluar_id = ?
                    WHERE id = ?
                ");
                $stmt_out->execute([
                    date('H:i:s', $t_keluar),
                    date('Y-m-d H:i:s', $t_keluar),
                    $durasi_menit,
                    $ket_final ?: null,
                    $_SESSION['user_id'],
                    $id
                ]);

                log_aktivitas($pdo, "Memproses kendaraan keluar: {$trx['kode_transaksi']} ({$trx['nomor_karcis']}) Durasi: {$durasi_menit} menit", 'keluar');
                set_flash('success', "Kendaraan karcis <strong>{$trx['nomor_karcis']}</strong> berhasil diproses keluar. Durasi: " . format_durasi($durasi_menit));
                header('Location: ' . BASE_URL . '/admin/kendaraan.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal memproses kendaraan keluar: ' . $e->getMessage());
        }
    }
}

// 3. Edit / Koreksi Data Kendaraan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_kendaraan'])) {
    $id = (int)($_POST['id'] ?? 0);
    $jenis_id = (int)($_POST['jenis_kendaraan_id'] ?? 0);
    $plat_nomor = strtoupper(trim($_POST['plat_nomor'] ?? ''));
    $ciri = trim($_POST['ciri_kendaraan'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($id <= 0 || $jenis_id <= 0) {
        set_flash('danger', 'Data koreksi tidak valid.');
    } else {
        try {
            $stmt_old = $pdo->prepare("SELECT * FROM parkir WHERE id = ?");
            $stmt_old->execute([$id]);
            $old = $stmt_old->fetch();

            if ($old) {
                $stmt_up = $pdo->prepare("
                    UPDATE parkir SET 
                        jenis_kendaraan_id = ?,
                        plat_nomor = ?,
                        ciri_kendaraan = ?,
                        keterangan = ?
                    WHERE id = ?
                ");
                $stmt_up->execute([
                    $jenis_id,
                    $plat_nomor ?: null,
                    $ciri ?: null,
                    $keterangan ?: null,
                    $id
                ]);

                log_aktivitas($pdo, "Koreksi data kendaraan {$old['kode_transaksi']} ({$old['nomor_karcis']})", 'master');
                set_flash('success', "Data kendaraan <strong>{$old['nomor_karcis']}</strong> berhasil diperbarui.");
                header('Location: ' . BASE_URL . '/admin/kendaraan.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal memperbarui data kendaraan: ' . $e->getMessage());
        }
    }
}

// 4. Hapus Transaksi Kendaraan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_kendaraan'])) {
    $id = (int)($_POST['id'] ?? 0);
    $alasan = trim($_POST['alasan_hapus'] ?? 'Salah input');

    if ($id <= 0) {
        set_flash('danger', 'ID transaksi tidak valid.');
    } else {
        try {
            $stmt_t = $pdo->prepare("SELECT kode_transaksi, nomor_karcis, plat_nomor FROM parkir WHERE id = ?");
            $stmt_t->execute([$id]);
            $t = $stmt_t->fetch();

            if ($t) {
                $stmt_del = $pdo->prepare("DELETE FROM parkir WHERE id = ?");
                $stmt_del->execute([$id]);

                log_aktivitas($pdo, "Menghapus transaksi kendaraan {$t['kode_transaksi']} ({$t['nomor_karcis']}) - Alasan: {$alasan}", 'master');
                set_flash('success', "Transaksi kendaraan <strong>{$t['nomor_karcis']}</strong> ({$t['kode_transaksi']}) berhasil dihapus.");
                header('Location: ' . BASE_URL . '/admin/kendaraan.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }
}

// Parameter Pencarian & Filter
$q = trim($_GET['q'] ?? '');
$filter_jenis = (int)($_GET['jenis_id'] ?? 0);
$tgl_mulai = trim($_GET['tgl_mulai'] ?? date('Y-m-d', strtotime('-6 days')));
$tgl_selesai = trim($_GET['tgl_selesai'] ?? date('Y-m-d'));

// Master Jenis Kendaraan
$stmt_all_jenis = $pdo->query("SELECT * FROM jenis_kendaraan ORDER BY urutan ASC");
$jenis_options = $stmt_all_jenis->fetchAll();

// Metrik Ringkasan
$total_kendaraan = (int)$pdo->query("SELECT COUNT(*) FROM parkir")->fetchColumn();
$stmt_out_today = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE DATE(datetime_keluar) = ?");
$stmt_out_today->execute([$today]);
$total_keluar_hari_ini = (int)$stmt_out_today->fetchColumn();
$tracking_today = tracking_summary($pdo, $today, $today);

$total_ai_tracking = 0;
try {
    $total_ai_tracking = (int)$pdo->query("SELECT COUNT(*) FROM tracking_kendaraan")->fetchColumn();
} catch (Exception $ignore) {}

// Query Data Kendaraan
$sql = "
    SELECT p.*, j.nama_jenis, j.icon_class, j.warna_badge,
           u_in.nama as nama_petugas_masuk,
           u_out.nama as nama_petugas_keluar
    FROM parkir p
    JOIN jenis_kendaraan j ON p.jenis_kendaraan_id = j.id
    LEFT JOIN users u_in ON p.petugas_masuk_id = u_in.id
    LEFT JOIN users u_out ON p.petugas_keluar_id = u_out.id
    WHERE 1=1
";
$params = [];

if ($tgl_mulai && $tgl_selesai) {
    $sql .= " AND p.tanggal BETWEEN ? AND ?";
    $params[] = $tgl_mulai;
    $params[] = $tgl_selesai;
}

if ($filter_jenis > 0) {
    $sql .= " AND p.jenis_kendaraan_id = ?";
    $params[] = $filter_jenis;
}

if ($q) {
    $sql .= " AND (p.plat_nomor LIKE ? OR p.nomor_karcis LIKE ? OR p.kode_transaksi LIKE ? OR p.ciri_kendaraan LIKE ?)";
    $kw = "%{$q}%";
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
}

$sql .= " ORDER BY p.id DESC LIMIT 100";
$stmt_data = $pdo->prepare($sql);
$stmt_data->execute($params);
$kendaraan_list = $stmt_data->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-car-side text-primary me-2"></i> Manajemen Kendaraan & Transaksi
        </h1>
        <div class="page-header-subtitle">
            Pusat pengelolaan dan riwayat seluruh kendaraan di kawasan <?= AIRPORT_NAME ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary shadow-sm" style="background: #0b2e59; border-color: #0b2e59;" data-bs-toggle="modal" data-bs-target="#modalTambahKendaraan">
            <i class="fa-solid fa-plus-circle me-1 text-warning"></i> Catat Kendaraan Masuk
        </button>
        <a href="<?= BASE_URL ?>/supervisor/laporan.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-file-excel me-1 text-success"></i> Rekap Laporan
        </a>
    </div>
</div>

<!-- 4 Kartu KPI Ringkasan Kendaraan -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card stat-navy">
            <div class="stat-label">Total Riwayat Terdata</div>
            <div class="stat-value text-primary"><?= number_format($total_kendaraan) ?></div>
            <div class="small text-muted mt-2">
                <i class="fa-solid fa-database me-1"></i> Keseluruhan transaksi
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card stat-warning">
            <div class="stat-label">Tracking AI Hari Ini</div>
            <div class="stat-value" style="color: #b45309 !important;"><?= number_format($tracking_today['total']) ?></div>
            <div class="small text-muted mt-2">
                <span class="badge bg-danger text-white"><i class="fa-solid fa-circle-dot me-1"></i> Live</span> Masuk: <?= number_format($tracking_today['masuk']) ?> &bull; Keluar: <?= number_format($tracking_today['keluar']) ?>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card stat-success">
            <div class="stat-label">Kendaraan Keluar Hari Ini</div>
            <div class="stat-value text-success"><?= number_format($total_keluar_hari_ini) ?></div>
            <div class="small text-muted mt-2">
                <i class="fa-regular fa-clock me-1"></i> Tanggal <?= date('d/m/Y') ?>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card stat-info">
            <div class="stat-label">Deteksi AI CCTV Tracking</div>
            <div class="stat-value text-info"><?= number_format($total_ai_tracking) ?></div>
            <div class="small text-muted mt-2">
                <i class="fa-solid fa-video me-1"></i> Deteksi otomatis garis
            </div>
        </div>
    </div>
</div>

<!-- Filter & Pencarian -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
    <form method="GET" action="" class="row g-2 align-items-center">
        <div class="col-lg-3 col-md-6">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Cari Plat / No Karcis..." value="<?= e($q) ?>">
            </div>
        </div>
        <div class="col-lg-2 col-md-6">
            <select name="jenis_id" class="form-select form-select-sm">
                <option value="0">Semua Jenis Kendaraan</option>
                <?php foreach ($jenis_options as $jo): ?>
                    <option value="<?= $jo['id'] ?>" <?= $filter_jenis == $jo['id'] ? 'selected' : '' ?>>
                        <?= e($jo['nama_jenis']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= e($tgl_mulai) ?>" title="Tanggal Mulai">
        </div>
        <div class="col-lg-2 col-md-4">
            <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= e($tgl_selesai) ?>" title="Tanggal Selesai">
        </div>
        <div class="col-lg-1 col-md-12 d-grid">
            <button type="submit" class="btn btn-sm btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
                <i class="fa-solid fa-filter me-1"></i> Filter
            </button>
        </div>
    </form>
</div>

<!-- Tabel Kendaraan -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-list-check text-primary me-2"></i> Daftar Kendaraan (<?= count($kendaraan_list) ?> Teratas)
        </h6>
        <?php if ($q || $filter_jenis): ?>
            <a href="<?= BASE_URL ?>/admin/kendaraan.php" class="btn btn-sm btn-outline-secondary py-0">
                <i class="fa-solid fa-rotate-left me-1"></i> Reset Filter
            </a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-kemenhub align-middle mb-0">
            <thead>
                <tr>
                    <th>No. Karcis / Kode</th>
                    <th>Plat Nomor</th>
                    <th>Jenis Kendaraan</th>
                    <th>Waktu Masuk</th>
                    <th>Waktu Keluar / Durasi</th>
                    <th>Petugas / Sumber</th>
                    <th class="text-center" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($kendaraan_list)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-inbox fa-3x mb-3 text-secondary opacity-50"></i>
                            <div>Tidak ada data kendaraan yang cocok dengan filter.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($kendaraan_list as $k): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= e($k['nomor_karcis']) ?></div>
                                <small class="text-muted font-monospace"><?= e($k['kode_transaksi']) ?></small>
                            </td>
                            <td>
                                <?php if (!empty($k['plat_nomor'])): ?>
                                    <span class="badge bg-dark font-monospace px-2 py-1 fs-6">
                                        <?= e($k['plat_nomor']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Tanpa Plat</span>
                                <?php endif; ?>
                                <?php if (!empty($k['ciri_kendaraan'])): ?>
                                    <div class="small text-muted mt-1" style="max-width: 180px; font-size: 0.75rem;">
                                        <?= e($k['ciri_kendaraan']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="<?= e($k['icon_class']) ?> text-primary me-2 fa-lg"></i>
                                    <div>
                                        <div class="fw-semibold text-dark"><?= e($k['nama_jenis']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= format_waktu($k['waktu_masuk']) ?></div>
                                <small class="text-muted"><?= format_tanggal_indo($k['tanggal']) ?></small>
                            </td>
                            <td>
                                <?php if (!empty($k['datetime_keluar'])): ?>
                                    <div class="text-dark fw-semibold"><?= format_waktu($k['waktu_keluar']) ?></div>
                                    <small class="badge bg-info-subtle text-info border border-info-subtle">
                                        <i class="fa-regular fa-clock me-1"></i><?= format_durasi($k['durasi_menit']) ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted small"><i class="fa-solid fa-hourglass-half me-1 text-warning"></i> Berlangsung</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark">
                                    <i class="fa-solid fa-user me-1 text-muted"></i> <?= e($k['nama_petugas_masuk'] ?? 'Sistem AI') ?>
                                </div>
                                <?php if ($k['nama_petugas_keluar']): ?>
                                    <div class="small text-muted" style="font-size: 0.72rem;">
                                        Out: <?= e($k['nama_petugas_keluar']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <?php if (empty($k['datetime_keluar'])): ?>
                                        <button type="button" class="btn btn-outline-success btn-checkout-knd"
                                            data-id="<?= $k['id'] ?>"
                                            data-karcis="<?= e($k['nomor_karcis']) ?>"
                                            data-plat="<?= e($k['plat_nomor'] ?? '-') ?>"
                                            title="Proses Kendaraan Keluar">
                                            <i class="fa-solid fa-right-from-bracket"></i>
                                        </button>
                                    <?php endif; ?>
                                    <a href="<?= BASE_URL ?>/petugas/karcis.php?id=<?= $k['id'] ?>" target="_blank" class="btn btn-outline-secondary" title="Cetak Karcis">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-primary btn-edit-knd"
                                        data-id="<?= $k['id'] ?>"
                                        data-jenis="<?= $k['jenis_kendaraan_id'] ?>"
                                        data-plat="<?= e($k['plat_nomor'] ?? '') ?>"
                                        data-ciri="<?= e($k['ciri_kendaraan'] ?? '') ?>"
                                        data-ket="<?= e($k['keterangan'] ?? '') ?>"
                                        title="Koreksi / Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-hapus-knd"
                                        data-id="<?= $k['id'] ?>"
                                        data-karcis="<?= e($k['nomor_karcis']) ?>"
                                        data-kode="<?= e($k['kode_transaksi']) ?>"
                                        title="Hapus Transaksi">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Kendaraan Masuk -->
<div class="modal fade" id="modalTambahKendaraan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white" style="background: #0b2e59 !important; border-bottom: 3px solid #f59e0b;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-right-to-bracket text-warning me-2"></i> Catat Kendaraan Masuk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Jenis Kendaraan <span class="text-danger">*</span></label>
                        <select name="jenis_kendaraan_id" class="form-select" required>
                            <option value="">-- Pilih Jenis Kendaraan --</option>
                            <?php foreach ($jenis_options as $jo): ?>
                                <option value="<?= $jo['id'] ?>"><?= e($jo['nama_jenis']) ?> (<?= e($jo['kode_kategori']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Plat Nomor Kendaraan</label>
                        <input type="text" name="plat_nomor" class="form-control text-uppercase font-monospace" placeholder="Contoh: BL 1234 AB">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Ciri Fisik / Warna / Merk</label>
                        <input type="text" name="ciri_kendaraan" class="form-control" placeholder="Contoh: Avanza Hitam / Honda Vario Putih">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Keterangan Tambahan</label>
                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Opsional..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_kendaraan" value="1" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
                        <i class="fa-solid fa-check me-1"></i> Cetak & Simpan Masuk
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Checkout Keluar -->
<div class="modal fade" id="modalCheckoutKendaraan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-right-from-bracket me-2"></i> Proses Kendaraan Keluar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="co_id">
                <div class="modal-body p-4">
                    <p class="mb-2">Konfirmasi pelepasan kendaraan berikut dari area parkir:</p>
                    <div class="alert alert-light border py-2 mb-3">
                        <div class="fw-bold text-dark fs-5" id="co_karcis">-</div>
                        <div class="small text-muted">Plat: <span class="badge bg-dark font-monospace" id="co_plat">-</span></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Catatan Keluar (Opsional)</label>
                        <input type="text" name="keterangan_keluar" class="form-control" placeholder="Contoh: Karcis lengkap / penjemput tamu">
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="proses_keluar" value="1" class="btn btn-success">
                        <i class="fa-solid fa-check me-1"></i> Konfirmasi Selesai Keluar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Kendaraan -->
<div class="modal fade" id="modalEditKendaraan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white" style="background: #0b2e59 !important; border-bottom: 3px solid #f59e0b;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square text-warning me-2"></i> Koreksi Data Kendaraan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="edit_knd_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Jenis Kendaraan</label>
                        <select name="jenis_kendaraan_id" id="edit_knd_jenis" class="form-select" required>
                            <?php foreach ($jenis_options as $jo): ?>
                                <option value="<?= $jo['id'] ?>"><?= e($jo['nama_jenis']) ?> (<?= e($jo['kode_kategori']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Plat Nomor</label>
                        <input type="text" name="plat_nomor" id="edit_knd_plat" class="form-control text-uppercase font-monospace">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Ciri Fisik</label>
                        <input type="text" name="ciri_kendaraan" id="edit_knd_ciri" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Keterangan</label>
                        <input type="text" name="keterangan" id="edit_knd_ket" class="form-control">
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_kendaraan" value="1" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Koreksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus Kendaraan -->
<div class="modal fade" id="modalHapusKendaraan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-trash me-2"></i> Hapus Transaksi Kendaraan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="hapus_knd_id">
                <div class="modal-body p-4">
                    <p class="mb-2">Apakah Anda yakin ingin menghapus data transaksi parkir berikut?</p>
                    <div class="alert alert-light border py-2 mb-3">
                        <div class="fw-bold text-dark" id="hapus_knd_karcis">-</div>
                        <div class="small text-muted font-monospace" id="hapus_knd_kode">-</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alasan Penghapusan <span class="text-danger">*</span></label>
                        <input type="text" name="alasan_hapus" class="form-control" placeholder="Contoh: Salah input nomor plat / duplikat" required>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="hapus_kendaraan" value="1" class="btn btn-danger">
                        <i class="fa-solid fa-trash-can me-1"></i> Ya, Hapus Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Bootstrap Bundle dimuat oleh footer; tunggu seluruh dokumen siap sebelum membuat modal.
    document.addEventListener('DOMContentLoaded', function () {
    // Checkout Modal
    const modalCo = new bootstrap.Modal(document.getElementById('modalCheckoutKendaraan'));
    document.querySelectorAll('.btn-checkout-knd').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('co_id').value = this.getAttribute('data-id');
            document.getElementById('co_karcis').textContent = this.getAttribute('data-karcis');
            document.getElementById('co_plat').textContent = this.getAttribute('data-plat');
            modalCo.show();
        });
    });

    // Edit Modal
    const modalEditKnd = new bootstrap.Modal(document.getElementById('modalEditKendaraan'));
    document.querySelectorAll('.btn-edit-knd').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_knd_id').value = this.getAttribute('data-id');
            document.getElementById('edit_knd_jenis').value = this.getAttribute('data-jenis');
            document.getElementById('edit_knd_plat').value = this.getAttribute('data-plat');
            document.getElementById('edit_knd_ciri').value = this.getAttribute('data-ciri');
            document.getElementById('edit_knd_ket').value = this.getAttribute('data-ket');
            modalEditKnd.show();
        });
    });

    // Hapus Modal
    const modalHapusKnd = new bootstrap.Modal(document.getElementById('modalHapusKendaraan'));
    document.querySelectorAll('.btn-hapus-knd').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('hapus_knd_id').value = this.getAttribute('data-id');
            document.getElementById('hapus_knd_karcis').textContent = this.getAttribute('data-karcis');
            document.getElementById('hapus_knd_kode').textContent = this.getAttribute('data-kode');
            modalHapusKnd.show();
        });
    });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
