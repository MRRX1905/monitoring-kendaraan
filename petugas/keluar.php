<?php
/**
 * Pencatatan Kendaraan Keluar
 * Pos Keluar Bandara Malikussaleh
 */
$page_title = 'Pencatatan Kendaraan Keluar';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['petugas', 'admin']);

$pdo = getDB();

// 1. Handler Edit Transaksi (Koreksi salah input di Pos Keluar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_transaksi'])) {
    $id = (int)($_POST['id'] ?? 0);
    $jenis_kendaraan_id = (int)($_POST['jenis_kendaraan_id'] ?? 0);
    $plat_nomor = trim($_POST['plat_nomor'] ?? '');
    $ciri_kendaraan = trim($_POST['ciri_kendaraan'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $waktu_masuk = trim($_POST['waktu_masuk'] ?? '');

    if ($id <= 0 || $jenis_kendaraan_id <= 0) {
        set_flash('danger', 'Data koreksi tidak valid.');
    } else {
        try {
            $stmt_old = $pdo->prepare("SELECT * FROM parkir WHERE id = ?");
            $stmt_old->execute([$id]);
            $old = $stmt_old->fetch();

            if ($old) {
                $tanggal = $old['tanggal'];
                $datetime_masuk = $tanggal . ' ' . (strlen($waktu_masuk) == 5 ? $waktu_masuk . ':00' : $waktu_masuk);

                $stmt_up = $pdo->prepare("
                    UPDATE parkir SET 
                        jenis_kendaraan_id = ?,
                        plat_nomor = ?,
                        ciri_kendaraan = ?,
                        keterangan = ?,
                        waktu_masuk = ?,
                        datetime_masuk = ?
                    WHERE id = ?
                ");
                $stmt_up->execute([
                    $jenis_kendaraan_id,
                    $plat_nomor ? strtoupper($plat_nomor) : null,
                    $ciri_kendaraan ?: null,
                    $keterangan ?: null,
                    $waktu_masuk,
                    $datetime_masuk,
                    $id
                ]);

                log_aktivitas($pdo, "Koreksi data transaksi di Pos Keluar #{$id} ({$old['kode_transaksi']})", 'master');
                set_flash('success', "Data kendaraan <strong>{$old['nomor_karcis']}</strong> ({$old['kode_transaksi']}) berhasil dikoreksi.");
                header('Location: ' . BASE_URL . '/petugas/keluar.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal memperbarui transaksi: ' . $e->getMessage());
        }
    }
}

// 2. Handler Hapus Transaksi (Jika batal / salah input di Pos Keluar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_transaksi'])) {
    $id = (int)($_POST['id'] ?? 0);
    $alasan = trim($_POST['alasan_hapus'] ?? 'Salah input data');

    if ($id <= 0) {
        set_flash('danger', 'ID transaksi tidak valid.');
    } else {
        try {
            $stmt_check = $pdo->prepare("SELECT kode_transaksi, nomor_karcis FROM parkir WHERE id = ?");
            $stmt_check->execute([$id]);
            $trx = $stmt_check->fetch();

            if ($trx) {
                $stmt_del = $pdo->prepare("DELETE FROM parkir WHERE id = ?");
                $stmt_del->execute([$id]);

                log_aktivitas($pdo, "Menghapus transaksi di Pos Keluar {$trx['kode_transaksi']} ({$trx['nomor_karcis']}) - Alasan: {$alasan}", 'master');
                set_flash('success', "Transaksi <strong>{$trx['kode_transaksi']}</strong> ({$trx['nomor_karcis']}) berhasil dihapus.");
                header('Location: ' . BASE_URL . '/petugas/keluar.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }
}

// 3. Proses Submit Keluar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_keluar'])) {
    $parkir_id = (int)($_POST['parkir_id'] ?? 0);

    if ($parkir_id <= 0) {
        set_flash('danger', 'ID transaksi kendaraan tidak valid.');
    } else {
        try {
            // Ambil data transaksi yang belum diproses keluar
            $stmt_check = $pdo->prepare("SELECT * FROM parkir WHERE id = ? AND datetime_keluar IS NULL");
            $stmt_check->execute([$parkir_id]);
            $transaksi = $stmt_check->fetch();

            if (!$transaksi) {
                set_flash('warning', 'Data kendaraan tidak ditemukan atau kendaraan ini sudah dicatat keluar sebelumnya.');
            } else {
                $waktu_keluar = date('H:i:s');
                $datetime_keluar = date('Y-m-d H:i:s');
                $datetime_masuk = $transaksi['datetime_masuk'];

                // Hitung durasi dalam menit
                $t1 = new DateTime($datetime_masuk);
                $t2 = new DateTime($datetime_keluar);
                $interval = $t1->diff($t2);
                $durasi_menit = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

                $petugas_keluar_id = $_SESSION['user_id'];

                $stmt_update = $pdo->prepare("
                    UPDATE parkir SET 
                        waktu_keluar = ?, 
                        datetime_keluar = ?, 
                        durasi_menit = ?, 
                        petugas_keluar_id = ? 
                    WHERE id = ?
                ");
                $stmt_update->execute([$waktu_keluar, $datetime_keluar, $durasi_menit, $petugas_keluar_id, $parkir_id]);

                // Catat log
                log_aktivitas($pdo, "Mencatat kendaraan keluar: {$transaksi['kode_transaksi']} ({$transaksi['nomor_karcis']}) - Durasi: {$durasi_menit} menit", 'keluar');

                $durasi_text = format_durasi($durasi_menit);
                set_flash('success', "Kendaraan <strong>{$transaksi['nomor_karcis']}</strong> ({$transaksi['kode_transaksi']}) berhasil dicatat KELUAR! Durasi Parkir: <strong>{$durasi_text}</strong>.");
                header('Location: ' . BASE_URL . '/petugas/keluar.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal memproses kendaraan keluar: ' . $e->getMessage());
        }
    }
}

// Master jenis kendaraan untuk modal koreksi
$stmt_all_jenis = $pdo->query("SELECT * FROM jenis_kendaraan ORDER BY urutan ASC");
$jenis_options = $stmt_all_jenis->fetchAll();

// Ambil semua kendaraan yang belum diproses keluar (belum memiliki waktu keluar)
$stmt_parkir = $pdo->query("
    SELECT p.*, j.nama_jenis, j.icon_class, j.warna_badge, u_masuk.nama as nama_petugas_masuk
    FROM parkir p
    JOIN jenis_kendaraan j ON p.jenis_kendaraan_id = j.id
    LEFT JOIN users u_masuk ON p.petugas_masuk_id = u_masuk.id
    WHERE p.datetime_keluar IS NULL
    ORDER BY p.datetime_masuk DESC
");
$parkir_aktif = $stmt_parkir->fetchAll();

// Jika ada parameter kode pencarian cepat di URL
$preselect_kode = trim($_GET['kode'] ?? '');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-right-from-bracket text-warning me-2"></i> Pos Kendaraan Keluar
        </h1>
        <div class="page-header-subtitle">
            Verifikasi, penyelesaian aktivitas kendaraan keluar, serta koreksi atau hapus data jika salah input
        </div>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-warning text-dark fs-6 px-3 py-2 d-flex align-items-center">
            <i class="fa-solid fa-car-side me-2"></i> Belum Diproses Keluar: <strong><?= count($parkir_aktif) ?></strong> Unit
        </span>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <!-- Header Filter & Pencarian -->
    <div class="card-header bg-white py-3 border-bottom">
        <div class="row g-3 align-items-center">
            <div class="col-md-6 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="text" id="liveSearchInput" class="form-control bg-light border-start-0 ps-0" placeholder="Ketik No. Karcis, Kode Transaksi, Plat Nomor, atau Ciri Kendaraan..." value="<?= e($preselect_kode) ?>" autocomplete="off" autofocus>
                </div>
            </div>
            <div class="col-md-6 col-lg-7 text-md-end">
                <div class="btn-group btn-group-sm" role="group" id="filterButtonGroup">
                    <button type="button" class="btn btn-outline-secondary active" data-filter="all">Semua Jenis</button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="Motor">Motor</button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="Mobil">Mobil</button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="Bus">Bus</button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="Lainnya">Lainnya</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 1. Tabel Kendaraan Belum Diproses Keluar (Desktop & Tablet Layar Lebar >= 768px) -->
    <div class="table-responsive d-none d-md-block">
        <table class="table table-hover table-kemenhub align-middle mb-0" id="tableKeluar">
            <thead>
                <tr>
                    <th style="width: 100px;">No. Karcis</th>
                    <th>Kode Transaksi</th>
                    <th>Jenis Kendaraan</th>
                    <th>Plat / Identitas Fisik</th>
                    <th>Waktu Masuk</th>
                    <th>Petugas Masuk</th>
                    <th class="text-center" style="width: 180px;">Aksi & Koreksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($parkir_aktif)): ?>
                    <tr id="rowEmpty">
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-square-parking fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                            <h6 class="fw-bold text-dark">Tidak Ada Kendaraan yang Menunggu Proses Keluar</h6>
                            <p class="small text-muted mb-0">Semua kendaraan telah tercatat keluar atau belum ada kendaraan tercatat masuk.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($parkir_aktif as $row): ?>
                        <tr class="parkir-row" 
                            data-karcis="<?= strtolower($row['nomor_karcis']) ?>" 
                            data-kode="<?= strtolower($row['kode_transaksi']) ?>" 
                            data-plat="<?= strtolower($row['plat_nomor'] ?? '') ?>" 
                            data-ciri="<?= strtolower($row['ciri_kendaraan'] ?? '') ?>" 
                            data-jenis="<?= strtolower($row['nama_jenis']) ?>"
                            data-kategori="<?= strtolower($row['nama_jenis']) ?>">
                            <td>
                                <span class="badge bg-warning text-dark fw-bold px-2 py-1 fs-6"><?= e($row['nomor_karcis']) ?></span>
                            </td>
                            <td>
                                <span class="fw-semibold text-primary font-monospace"><?= e($row['kode_transaksi']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="<?= e($row['icon_class']) ?> me-1 text-primary"></i> <?= e($row['nama_jenis']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($row['plat_nomor'])): ?>
                                    <span class="badge bg-dark text-white me-1"><?= e($row['plat_nomor']) ?></span>
                                <?php endif; ?>
                                <span class="fw-medium text-dark"><?= e($row['ciri_kendaraan'] ?? '-') ?></span>
                                <?php if (!empty($row['keterangan'])): ?>
                                    <small class="text-muted d-block italic"><?= e($row['keterangan']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><i class="fa-regular fa-clock me-1 text-muted"></i><?= format_waktu($row['waktu_masuk']) ?></div>
                                <small class="text-muted"><?= format_tanggal_indo($row['tanggal']) ?></small>
                            </td>
                            <td>
                                <small class="text-muted"><i class="fa-solid fa-user me-1"></i> <?= e($row['nama_petugas_masuk']) ?></small>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-success fw-bold px-2 btn-proses-modal" 
                                        data-id="<?= $row['id'] ?>"
                                        data-karcis="<?= e($row['nomor_karcis']) ?>"
                                        data-kode="<?= e($row['kode_transaksi']) ?>"
                                        data-jenis="<?= e($row['nama_jenis']) ?>"
                                        data-plat="<?= e($row['plat_nomor'] ?: '-') ?>"
                                        data-ciri="<?= e($row['ciri_kendaraan'] ?: '-') ?>"
                                        data-masuk="<?= format_tanggal_indo($row['tanggal']) . ' ' . format_waktu($row['waktu_masuk']) ?>"
                                        title="Proses Keluar">
                                        <i class="fa-solid fa-right-from-bracket me-1"></i> Keluar
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-edit-trx"
                                        data-id="<?= $row['id'] ?>"
                                        data-karcis="<?= e($row['nomor_karcis']) ?>"
                                        data-kode="<?= e($row['kode_transaksi']) ?>"
                                        data-jenis="<?= $row['jenis_kendaraan_id'] ?>"
                                        data-plat="<?= e($row['plat_nomor'] ?? '') ?>"
                                        data-ciri="<?= e($row['ciri_kendaraan'] ?? '') ?>"
                                        data-waktu="<?= substr($row['waktu_masuk'], 0, 5) ?>"
                                        data-keterangan="<?= e($row['keterangan'] ?? '') ?>"
                                        title="Koreksi Data (Salah Input)">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-hapus-trx"
                                        data-id="<?= $row['id'] ?>"
                                        data-karcis="<?= e($row['nomor_karcis']) ?>"
                                        data-kode="<?= e($row['kode_transaksi']) ?>"
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

    <!-- 2. Tampilan Kartu Mobile Simpel (Khusus Layar Smartphone Petugas < 768px) -->
    <div class="d-block d-md-none p-3" id="mobileCardsContainer">
        <?php if (empty($parkir_aktif)): ?>
            <div class="text-center py-4 text-muted">
                <i class="fa-solid fa-square-parking fa-3x mb-2 text-secondary opacity-50"></i>
                <div class="fw-bold">Tidak ada kendaraan yang menunggu proses keluar</div>
            </div>
        <?php else: ?>
            <?php foreach ($parkir_aktif as $row): ?>
                <div class="mobile-vehicle-card parkir-card"
                    data-karcis="<?= strtolower($row['nomor_karcis']) ?>" 
                    data-kode="<?= strtolower($row['kode_transaksi']) ?>" 
                    data-plat="<?= strtolower($row['plat_nomor'] ?? '') ?>" 
                    data-ciri="<?= strtolower($row['ciri_kendaraan'] ?? '') ?>" 
                    data-jenis="<?= strtolower($row['nama_jenis']) ?>"
                    data-kategori="<?= strtolower($row['nama_jenis']) ?>">
                    
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-warning text-dark fw-bold fs-6 px-2 py-1"><?= e($row['nomor_karcis']) ?></span>
                        <span class="badge bg-light text-dark border">
                            <i class="<?= e($row['icon_class']) ?> me-1 text-primary"></i> <?= e($row['nama_jenis']) ?>
                        </span>
                    </div>

                    <div class="mb-2">
                        <?php if (!empty($row['plat_nomor'])): ?>
                            <span class="badge bg-dark text-white fs-6 me-1"><?= e($row['plat_nomor']) ?></span>
                        <?php endif; ?>
                        <span class="fw-bold text-dark"><?= e($row['ciri_kendaraan'] ?: 'Tanpa keterangan ciri') ?></span>
                        <div class="small text-muted font-monospace mt-1">Kode: <?= e($row['kode_transaksi']) ?></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center small text-muted mb-3 pt-2 border-top">
                        <span><i class="fa-regular fa-clock me-1"></i> Masuk: <strong><?= format_waktu($row['waktu_masuk']) ?></strong></span>
                        <span>Petugas: <?= e($row['nama_petugas_masuk']) ?></span>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success flex-grow-1 fw-bold py-2 btn-proses-modal"
                            data-id="<?= $row['id'] ?>"
                            data-karcis="<?= e($row['nomor_karcis']) ?>"
                            data-kode="<?= e($row['kode_transaksi']) ?>"
                            data-jenis="<?= e($row['nama_jenis']) ?>"
                            data-plat="<?= e($row['plat_nomor'] ?: '-') ?>"
                            data-ciri="<?= e($row['ciri_kendaraan'] ?: '-') ?>"
                            data-masuk="<?= format_tanggal_indo($row['tanggal']) . ' ' . format_waktu($row['waktu_masuk']) ?>">
                            <i class="fa-solid fa-right-from-bracket me-1"></i> KELUAR
                        </button>
                        <button type="button" class="btn btn-outline-primary px-3 btn-edit-trx"
                            data-id="<?= $row['id'] ?>"
                            data-karcis="<?= e($row['nomor_karcis']) ?>"
                            data-kode="<?= e($row['kode_transaksi']) ?>"
                            data-jenis="<?= $row['jenis_kendaraan_id'] ?>"
                            data-plat="<?= e($row['plat_nomor'] ?? '') ?>"
                            data-ciri="<?= e($row['ciri_kendaraan'] ?? '') ?>"
                            data-waktu="<?= substr($row['waktu_masuk'], 0, 5) ?>"
                            data-keterangan="<?= e($row['keterangan'] ?? '') ?>"
                            title="Edit">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger px-3 btn-hapus-trx"
                            data-id="<?= $row['id'] ?>"
                            data-karcis="<?= e($row['nomor_karcis']) ?>"
                            data-kode="<?= e($row['kode_transaksi']) ?>"
                            title="Hapus">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal 1: Konfirmasi Keluar -->
<div class="modal fade" id="modalKeluar" tabindex="-1" aria-labelledby="modalKeluarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3" style="background: #0b2e59 !important; border-bottom: 3px solid #f59e0b;">
                <h5 class="modal-title fw-bold" id="modalKeluarLabel">
                    <i class="fa-solid fa-right-from-bracket text-warning me-2"></i> Konfirmasi Kendaraan Keluar
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="parkir_id" id="modal_parkir_id" value="">
                
                <div class="modal-body p-4">
                    <div class="alert alert-light border d-flex align-items-center mb-3">
                        <div class="me-3">
                            <span class="badge bg-warning text-dark fs-5 p-2 rounded-3" id="modal_karcis">K-000</span>
                        </div>
                        <div>
                            <div class="fw-bold text-primary font-monospace" id="modal_kode">MLK-00000000-0000</div>
                            <div class="small text-muted" id="modal_jenis">Mobil</div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Plat Nomor</small>
                                <span class="fw-bold text-dark" id="modal_plat">-</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Warna / Ciri</small>
                                <span class="fw-bold text-dark" id="modal_ciri">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Waktu Masuk:</span>
                            <span class="fw-semibold text-dark small" id="modal_masuk">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Waktu Keluar:</span>
                            <span class="fw-semibold text-dark small"><i class="fa-regular fa-clock text-warning me-1"></i> Saat Ini (<?= date('H:i') ?> WIB)</span>
                        </div>
                    </div>

                    <div class="small text-muted text-center">
                        <i class="fa-solid fa-circle-info text-primary me-1"></i> Petugas keluar yang mencatat: <strong><?= e($_SESSION['user_nama']) ?></strong>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="proses_keluar" value="1" class="btn btn-success px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-check-circle me-1"></i> Proses Selesai Keluar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Edit / Koreksi Transaksi -->
<div class="modal fade" id="modalEditTrx" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3" style="background: #0b2e59 !important; border-bottom: 3px solid #f59e0b;">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-pen-to-square text-warning me-2"></i> Koreksi Data Kendaraan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="edit_trx_id">
                
                <div class="modal-body p-4">
                    <div class="alert alert-light border d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="small text-muted d-block">Nomor Karcis</span>
                            <span class="badge bg-warning text-dark fs-6" id="edit_trx_karcis">K-000</span>
                        </div>
                        <div class="text-end">
                            <span class="small text-muted d-block">Kode Transaksi</span>
                            <span class="font-monospace fw-bold text-primary" id="edit_trx_kode">MLK-00000000-0000</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Jenis Kendaraan <span class="text-danger">*</span></label>
                        <select name="jenis_kendaraan_id" id="edit_trx_jenis" class="form-select" required>
                            <?php foreach ($jenis_options as $jo): ?>
                                <option value="<?= $jo['id'] ?>"><?= e($jo['nama_jenis']) ?> (<?= e($jo['kode_kategori']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Plat Nomor</label>
                            <input type="text" name="plat_nomor" id="edit_trx_plat" class="form-control text-uppercase" placeholder="Contoh: BL 1234 AA">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Warna / Ciri Kendaraan</label>
                            <input type="text" name="ciri_kendaraan" id="edit_trx_ciri" class="form-control" placeholder="Contoh: Avanza Putih">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Waktu Masuk</label>
                            <input type="time" name="waktu_masuk" id="edit_trx_waktu" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Keterangan</label>
                            <input type="text" name="keterangan" id="edit_trx_keterangan" class="form-control" placeholder="Keperluan masuk">
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light p-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_transaksi" value="1" class="btn btn-primary px-4 fw-bold" style="background: #0b2e59; border-color: #0b2e59;">
                        <i class="fa-solid fa-floppy-disk me-1 text-warning"></i> Simpan Koreksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Hapus Transaksi -->
<div class="modal fade" id="modalHapusTrx" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> Hapus Transaksi Parkir
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="hapus_trx_id">

                <div class="modal-body p-4 text-center">
                    <div class="text-danger mb-3">
                        <i class="fa-solid fa-trash-can fa-3x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Yakin Ingin Menghapus Data Ini?</h5>
                    <p class="text-muted small mb-3">
                        Transaksi <strong id="hapus_trx_karcis" class="text-dark"></strong> (<span id="hapus_trx_kode" class="font-monospace text-primary"></span>) akan dihapus secara permanen dari basis data.
                    </p>

                    <div class="text-start bg-light p-3 rounded-3 border mb-2">
                        <label class="form-label small fw-bold text-dark">Alasan Penghapusan <span class="text-danger">*</span></label>
                        <select name="alasan_hapus" class="form-select mb-2" required>
                            <option value="Salah input jenis kendaraan">Salah input jenis kendaraan</option>
                            <option value="Input ganda / transaksi duplikat">Input ganda / transaksi duplikat</option>
                            <option value="Kendaraan langsung putar balik (batal masuk)">Kendaraan langsung putar balik (batal masuk)</option>
                            <option value="Pengujian sistem / data percobaan">Pengujian sistem / data percobaan</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                        <small class="text-muted">Aktivitas penghapusan akan tercatat pada log audit keamanan sistem.</small>
                    </div>
                </div>

                <div class="modal-footer bg-light p-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="hapus_transaksi" value="1" class="btn btn-danger px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-trash-can me-1"></i> Ya, Hapus Permanen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Bootstrap Bundle dimuat oleh footer; tunggu seluruh dokumen siap sebelum membuat modal.
    document.addEventListener('DOMContentLoaded', function () {
    // Live Search & Filter
    const searchInput = document.getElementById('liveSearchInput');
    const tableRows = document.querySelectorAll('.parkir-row');
    const mobileCards = document.querySelectorAll('.parkir-card');
    const filterBtns = document.querySelectorAll('#filterButtonGroup button');

    let currentFilter = 'all';

    function doFilter() {
        const query = searchInput.value.toLowerCase().trim();

        // Filter tabel desktop
        tableRows.forEach(row => {
            const karcis = row.getAttribute('data-karcis');
            const kode = row.getAttribute('data-kode');
            const plat = row.getAttribute('data-plat');
            const ciri = row.getAttribute('data-ciri');
            const jenis = row.getAttribute('data-jenis');

            const matchSearch = karcis.includes(query) || kode.includes(query) || plat.includes(query) || ciri.includes(query) || jenis.includes(query);

            let matchFilter = true;
            if (currentFilter !== 'all') {
                if (currentFilter === 'Lainnya') {
                    matchFilter = !jenis.includes('motor') && !jenis.includes('mobil') && !jenis.includes('bus');
                } else {
                    matchFilter = jenis.includes(currentFilter.toLowerCase());
                }
            }

            row.style.display = (matchSearch && matchFilter) ? '' : 'none';
        });

        // Filter mobile cards
        mobileCards.forEach(card => {
            const karcis = card.getAttribute('data-karcis');
            const kode = card.getAttribute('data-kode');
            const plat = card.getAttribute('data-plat');
            const ciri = card.getAttribute('data-ciri');
            const jenis = card.getAttribute('data-jenis');

            const matchSearch = karcis.includes(query) || kode.includes(query) || plat.includes(query) || ciri.includes(query) || jenis.includes(query);

            let matchFilter = true;
            if (currentFilter !== 'all') {
                if (currentFilter === 'Lainnya') {
                    matchFilter = !jenis.includes('motor') && !jenis.includes('mobil') && !jenis.includes('bus');
                } else {
                    matchFilter = jenis.includes(currentFilter.toLowerCase());
                }
            }

            card.style.display = (matchSearch && matchFilter) ? '' : 'none';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', doFilter);
    }

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.getAttribute('data-filter');
            doFilter();
        });
    });

    // Modal Trigger Keluar
    const modalKeluarEl = new bootstrap.Modal(document.getElementById('modalKeluar'));
    document.querySelectorAll('.btn-proses-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modal_parkir_id').value = this.getAttribute('data-id');
            document.getElementById('modal_karcis').textContent = this.getAttribute('data-karcis');
            document.getElementById('modal_kode').textContent = this.getAttribute('data-kode');
            document.getElementById('modal_jenis').textContent = this.getAttribute('data-jenis');
            document.getElementById('modal_plat').textContent = this.getAttribute('data-plat');
            document.getElementById('modal_ciri').textContent = this.getAttribute('data-ciri');
            document.getElementById('modal_masuk').textContent = this.getAttribute('data-masuk');
            modalKeluarEl.show();
        });
    });

    // Modal Trigger Edit / Koreksi
    const modalEditEl = new bootstrap.Modal(document.getElementById('modalEditTrx'));
    document.querySelectorAll('.btn-edit-trx').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_trx_id').value = this.getAttribute('data-id');
            document.getElementById('edit_trx_karcis').textContent = this.getAttribute('data-karcis');
            document.getElementById('edit_trx_kode').textContent = this.getAttribute('data-kode');
            document.getElementById('edit_trx_jenis').value = this.getAttribute('data-jenis');
            document.getElementById('edit_trx_plat').value = this.getAttribute('data-plat');
            document.getElementById('edit_trx_ciri').value = this.getAttribute('data-ciri');
            document.getElementById('edit_trx_waktu').value = this.getAttribute('data-waktu');
            document.getElementById('edit_trx_keterangan').value = this.getAttribute('data-keterangan');
            modalEditEl.show();
        });
    });

    // Modal Trigger Hapus
    const modalHapusEl = new bootstrap.Modal(document.getElementById('modalHapusTrx'));
    document.querySelectorAll('.btn-hapus-trx').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('hapus_trx_id').value = this.getAttribute('data-id');
            document.getElementById('hapus_trx_karcis').textContent = this.getAttribute('data-karcis');
            document.getElementById('hapus_trx_kode').textContent = this.getAttribute('data-kode');
            modalHapusEl.show();
        });
    });

    // Auto filter jika ada parameter URL
    if (searchInput && searchInput.value) {
        doFilter();
    }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
