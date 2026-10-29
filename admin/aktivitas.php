<?php
/**
 * Monitoring Aktivitas Kendaraan & Audit Trail
 * Bandara Malikussaleh (LSW)
 * Kementerian Perhubungan Republik Indonesia
 */
$page_title = 'Aktivitas Kendaraan';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['admin', 'supervisor', 'petugas']);

$pdo = getDB();
$today = date('Y-m-d');

// Filter
$tab = trim($_GET['tab'] ?? 'semua');
$tgl = trim($_GET['tgl'] ?? $today);

// 1. Ambil Pergerakan Parkir Hari Ini (Masuk & Keluar)
$stmt_parkir = $pdo->prepare("
    SELECT p.*, j.nama_jenis, j.icon_class,
           u_in.nama as nama_petugas_masuk,
           u_out.nama as nama_petugas_keluar
    FROM parkir p
    JOIN jenis_kendaraan j ON p.jenis_kendaraan_id = j.id
    LEFT JOIN users u_in ON p.petugas_masuk_id = u_in.id
    LEFT JOIN users u_out ON p.petugas_keluar_id = u_out.id
    WHERE p.tanggal = ? OR DATE(p.datetime_keluar) = ?
    ORDER BY p.updated_at DESC, p.id DESC
    LIMIT 60
");
$stmt_parkir->execute([$tgl, $tgl]);
$aktivitas_parkir = $stmt_parkir->fetchAll();

// 2. Ambil Riwayat Deteksi AI Tracking Otomatis
$aktivitas_ai = [];
try {
    $stmt_ai = $pdo->prepare("
        SELECT t.*, j.nama_jenis, j.icon_class
        FROM tracking_kendaraan t
        LEFT JOIN jenis_kendaraan j ON t.jenis_kendaraan_id = j.id
        WHERE DATE(t.waktu_event) = ?
        ORDER BY t.id DESC
        LIMIT 60
    ");
    $stmt_ai->execute([$tgl]);
    $aktivitas_ai = $stmt_ai->fetchAll();
} catch (Exception $e) {}

// 3. Ambil Log Audit Petugas / Sistem
$stmt_log = $pdo->prepare("
    SELECT l.*, u.nama, u.role
    FROM log_aktivitas l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE DATE(l.created_at) = ?
    ORDER BY l.id DESC
    LIMIT 60
");
$stmt_log->execute([$tgl]);
$log_audit = $stmt_log->fetchAll();

// Metrik Hari Ini
$stmt_m = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE tanggal = ?");
$stmt_m->execute([$tgl]);
$cnt_masuk = (int)$stmt_m->fetchColumn();

$stmt_k = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE DATE(datetime_keluar) = ?");
$stmt_k->execute([$tgl]);
$cnt_keluar = (int)$stmt_k->fetchColumn();

$cnt_ai = count($aktivitas_ai);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Aktivitas Kendaraan & Audit Trail
        </h1>
        <div class="page-header-subtitle">
            Log pemantauan seluruh aktivitas pergerakan kendaraan masuk, keluar, dan deteksi otomatis AI
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/kendaraan.php" class="btn btn-outline-primary">
            <i class="fa-solid fa-car me-1"></i> Manajemen Kendaraan
        </a>
        <a href="<?= BASE_URL ?>/admin/tracking.php" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
            <i class="fa-solid fa-video me-1 text-warning"></i> Live Tracking AI
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Aktivitas -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center">
                <div class="bg-primary-subtle text-primary p-3 rounded-circle me-3">
                    <i class="fa-solid fa-right-to-bracket fa-xl"></i>
                </div>
                <div>
                    <div class="small text-muted">Aktivitas Masuk (<?= date('d/m', strtotime($tgl)) ?>)</div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format($cnt_masuk) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center">
                <div class="bg-success-subtle text-success p-3 rounded-circle me-3">
                    <i class="fa-solid fa-right-from-bracket fa-xl"></i>
                </div>
                <div>
                    <div class="small text-muted">Aktivitas Keluar</div>
                    <div class="fs-4 fw-bold text-success"><?= number_format($cnt_keluar) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center">
                <div class="bg-info-subtle text-info p-3 rounded-circle me-3">
                    <i class="fa-solid fa-camera-rotate fa-xl"></i>
                </div>
                <div>
                    <div class="small text-muted">Deteksi AI Hari Ini</div>
                    <div class="fs-4 fw-bold text-info"><?= number_format($cnt_ai) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center">
                <div class="bg-warning-subtle text-warning p-3 rounded-circle me-3">
                    <i class="fa-solid fa-list-check fa-xl"></i>
                </div>
                <div>
                    <div class="small text-muted">Log Audit Sistem</div>
                    <div class="fs-4 fw-bold text-warning"><?= count($log_audit) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Nav Tabs & Filter Tanggal -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <ul class="nav nav-pills" id="aktivitasTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link <?= $tab === 'semua' ? 'active' : '' ?>" id="pills-semua-tab" data-bs-toggle="pill" data-bs-target="#pills-semua" type="button" style="<?= $tab === 'semua' ? 'background: #0b2e59;' : '' ?>">
                    <i class="fa-solid fa-layer-group me-1"></i> Semua Aktivitas (<?= count($aktivitas_parkir) + count($aktivitas_ai) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?= $tab === 'ai' ? 'active' : '' ?>" id="pills-ai-tab" data-bs-toggle="pill" data-bs-target="#pills-ai" type="button" style="<?= $tab === 'ai' ? 'background: #0b2e59;' : '' ?>">
                    <i class="fa-solid fa-video me-1"></i> Deteksi AI Tracking (<?= count($aktivitas_ai) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?= $tab === 'audit' ? 'active' : '' ?>" id="pills-audit-tab" data-bs-toggle="pill" data-bs-target="#pills-audit" type="button" style="<?= $tab === 'audit' ? 'background: #0b2e59;' : '' ?>">
                    <i class="fa-solid fa-shield-halved me-1"></i> Audit Trail Sistem (<?= count($log_audit) ?>)
                </button>
            </li>
        </ul>
        <form method="GET" action="" class="d-flex align-items-center gap-2">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <label class="small fw-semibold text-muted mb-0">Tanggal:</label>
            <input type="date" name="tgl" class="form-control form-control-sm" value="<?= e($tgl) ?>" onchange="this.form.submit()">
        </form>
    </div>

    <div class="card-body p-0">
        <div class="tab-content" id="aktivitasTabContent">
            <!-- TAB 1: Semua Aktivitas Pergerakan -->
            <div class="tab-pane fade <?= $tab === 'semua' ? 'show active' : '' ?>" id="pills-semua" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 140px;">Waktu</th>
                                <th>Arah Pergerakan</th>
                                <th>Nomor Karcis / Plat</th>
                                <th>Jenis Kendaraan</th>
                                <th>Petugas / Operator</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($aktivitas_parkir)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Belum ada aktivitas kendaraan pada tanggal ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($aktivitas_parkir as $ap): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= format_waktu($ap['waktu_masuk']) ?></div>
                                            <small class="text-muted"><?= format_tanggal_indo($ap['tanggal']) ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($ap['datetime_keluar'])): ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                    <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Keluar
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Masuk Area
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= e($ap['nomor_karcis']) ?></div>
                                            <?php if ($ap['plat_nomor']): ?>
                                                <span class="badge bg-dark font-monospace"><?= e($ap['plat_nomor']) ?></span>
                                            <?php else: ?>
                                                <small class="text-muted">Tanpa Plat</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <i class="<?= e($ap['icon_class']) ?> text-primary me-1"></i>
                                            <?= e($ap['nama_jenis']) ?>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= e($ap['nama_petugas_masuk'] ?? 'Sistem AI') ?></div>
                                            <?php if ($ap['nama_petugas_keluar']): ?>
                                                <div class="small text-muted">Keluar: <?= e($ap['nama_petugas_keluar']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small text-muted" style="max-width: 250px;">
                                                <?= e($ap['keterangan'] ?? ($ap['ciri_kendaraan'] ?? '-')) ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: Deteksi AI Tracking (Masuk & Keluar) -->
            <div class="tab-pane fade <?= $tab === 'ai' ? 'show active' : '' ?>" id="pills-ai" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu Event</th>
                                <th>Kelas Deteksi</th>
                                <th>Kategori Sistem</th>
                                <th>ID Track</th>
                                <th>Arah Lintasan</th>
                                <th>Tingkat Akurasi (Conf)</th>
                                <th>Posisi Garis</th>
                                <th>Status Pencatatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($aktivitas_ai)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-video-slash fa-2x mb-2 text-secondary opacity-50 d-block"></i>
                                        Belum ada event tracking AI pada tanggal ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($aktivitas_ai as $ai): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= date('H:i:s', strtotime($ai['waktu_event'])) ?> WIB</div>
                                            <small class="text-muted"><?= date('d/m/Y', strtotime($ai['waktu_event'])) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary text-uppercase font-monospace"><?= e($ai['class_name']) ?></span>
                                        </td>
                                        <td>
                                            <i class="<?= e($ai['icon_class'] ?? 'fa-solid fa-car') ?> text-primary me-1"></i>
                                            <?= e($ai['nama_jenis'] ?? '-') ?>
                                        </td>
                                        <td><code>#<?= $ai['track_id'] ?></code></td>
                                        <td>
                                            <?php if (strtolower($ai['direction']) === 'masuk'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1">
                                                    <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> MASUK (Garis Vertikal)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1">
                                                    <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> KELUAR (Garis Vertikal)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 16px; width: 100px;">
                                                <div class="progress-bar bg-success" style="width: <?= round($ai['confidence'] * 100) ?>%;">
                                                    <?= round($ai['confidence'] * 100) ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <i class="fa-solid fa-grip-lines-vertical me-1"></i> <?= e($ai['line_label'] ?? 'Garis Vertikal') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="fa-solid fa-circle-check me-1"></i> Tercatat Masuk & Keluar
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 3: Audit Trail -->
            <div class="tab-pane fade <?= $tab === 'audit' ? 'show active' : '' ?>" id="pills-audit" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Pengguna / Akun</th>
                                <th>Role</th>
                                <th>Tipe</th>
                                <th>Aktivitas Dilakukan</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($log_audit)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Belum ada log audit untuk tanggal ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($log_audit as $la): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= date('H:i:s', strtotime($la['created_at'])) ?> WIB</div>
                                            <small class="text-muted"><?= date('d/m/Y', strtotime($la['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($la['nama'] ?? 'Sistem Otomatis') ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= e(ucfirst($la['role'] ?? 'System')) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= e(strtoupper($la['tipe'])) ?></span>
                                        </td>
                                        <td><?= e($la['aktivitas']) ?></td>
                                        <td><code><?= e($la['ip_address']) ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
