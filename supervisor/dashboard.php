<?php
/**
 * Dashboard Monitoring Eksekutif Supervisor
 * Bandara Malikussaleh (LSW)
 */
$page_title = 'Dashboard Monitoring Supervisor';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['supervisor', 'admin']);

$pdo = getDB();
$today = date('Y-m-d');
$first_day_month = date('Y-m-01');

// 1. Ringkasan Pergerakan Hari Ini (Pencatatan Manual + Otomatis AI)
$mv_today = combined_movement_summary($pdo, $today, $today);
$tracking_today = tracking_summary($pdo, $today, $today);

// 2. Metrik Bulan Ini
$stmt_month = $pdo->prepare("
    SELECT 
        COUNT(*) as total_bulan_ini,
        COUNT(CASE WHEN datetime_keluar IS NOT NULL THEN 1 END) as keluar_bulan_ini
    FROM parkir
    WHERE tanggal BETWEEN ? AND ?
");
$stmt_month->execute([$first_day_month, $today]);
$metric_month = $stmt_month->fetch();

// 3. Data Grafik Aktivitas per Jam Hari Ini (06:00 - 18:00)
$hours = ['06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];
$chart_masuk = [];
$chart_keluar = [];

foreach ($hours as $h) {
    $h_start = substr($h, 0, 2) . ':00:00';
    $h_end = substr($h, 0, 2) . ':59:59';

    // Masuk per jam
    $stmt_h_in = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE tanggal = ? AND waktu_masuk BETWEEN ? AND ?");
    $stmt_h_in->execute([$today, $h_start, $h_end]);
    $chart_masuk[] = (int)$stmt_h_in->fetchColumn();

    // Keluar per jam
    $stmt_h_out = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE DATE(datetime_keluar) = ? AND waktu_keluar BETWEEN ? AND ?");
    $stmt_h_out->execute([$today, $h_start, $h_end]);
    $chart_keluar[] = (int)$stmt_h_out->fetchColumn();
}

// 4. Komposisi Jenis Kendaraan (Untuk Donut Chart)
$stmt_pie = $pdo->prepare("
    SELECT j.nama_jenis, COUNT(p.id) as total
    FROM jenis_kendaraan j
    LEFT JOIN parkir p ON j.id = p.jenis_kendaraan_id AND p.tanggal BETWEEN ? AND ?
    GROUP BY j.id, j.nama_jenis
    ORDER BY j.urutan ASC
");
$stmt_pie->execute([$first_day_month, $today]);
$pie_data = $stmt_pie->fetchAll();

$pie_labels = [];
$pie_values = [];
foreach ($pie_data as $pd) {
    $pie_labels[] = $pd['nama_jenis'];
    $pie_values[] = (int)$pd['total'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-chart-pie text-primary me-2"></i> Dashboard Monitoring Eksekutif
        </h1>
        <div class="page-header-subtitle">
            Ringkasan data analitik & pemantauan operasional parkir <?= AIRPORT_NAME ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/supervisor/laporan.php" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
            <i class="fa-solid fa-file-invoice me-1"></i> Rekapitulasi Laporan
        </a>
    </div>
</div>

<!-- 4 Kartu Metrik Utama -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-navy">
            <div class="stat-label">Total Masuk Hari Ini</div>
            <div class="stat-value text-primary"><?= number_format($mv_today['total_masuk']) ?></div>
            <div class="small text-muted mt-2">
                <i class="fa-solid fa-keyboard me-1"></i> Manual: <strong><?= number_format($mv_today['manual_masuk']) ?></strong>
                &nbsp;&bull;&nbsp;
                <i class="fa-solid fa-video me-1 text-danger"></i> AI: <strong><?= number_format($mv_today['ai_masuk']) ?></strong>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card stat-success">
            <div class="stat-label">Total Keluar Hari Ini</div>
            <div class="stat-value text-success"><?= number_format($mv_today['total_keluar']) ?></div>
            <div class="small text-muted mt-2">
                <i class="fa-solid fa-keyboard me-1"></i> Manual: <strong><?= number_format($mv_today['manual_keluar']) ?></strong>
                &nbsp;&bull;&nbsp;
                <i class="fa-solid fa-video me-1 text-danger"></i> AI: <strong><?= number_format($mv_today['ai_keluar']) ?></strong>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <div class="stat-label">Tracking AI Hari Ini</div>
            <div class="stat-value" style="color: #b45309 !important;"><?= number_format($tracking_today['total']) ?></div>
            <div class="small text-muted mt-2">
                <span class="badge bg-danger text-white">Live</span> Masuk: <?= number_format($tracking_today['masuk']) ?> &bull; Keluar: <?= number_format($tracking_today['keluar']) ?>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card stat-info">
            <div class="stat-label">Total Lalu Lintas Hari Ini</div>
            <div class="stat-value text-info" style="font-size: 1.6rem;"><?= number_format($mv_today['total']) ?></div>
            <div class="small text-muted mt-2">
                <i class="fa-solid fa-arrows-up-down me-1 text-info"></i> Manual + Otomatis AI
            </div>
        </div>
    </div>
</div>

<!-- Kesesuaian Jumlah Masuk vs Keluar (Manual + Otomatis) -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-scale-balanced text-primary me-2"></i> Kesesuaian Jumlah Masuk vs Keluar Hari Ini (Manual + Otomatis)
        </h6>
        <?php if ($mv_today['seimbang']): ?>
            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i> Jumlah SAMA</span>
        <?php else: ?>
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="fa-solid fa-triangle-exclamation me-1"></i> Jumlah BELUM SAMA</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-4">
        <div class="row g-2 text-center mb-3">
            <div class="col-4"><div class="p-2 rounded-3 border bg-light"><div class="small text-muted">Masuk</div><div class="fw-bold fs-4 text-primary"><?= number_format($mv_today['total_masuk']) ?></div></div></div>
            <div class="col-4"><div class="p-2 rounded-3 border bg-light"><div class="small text-muted">Keluar</div><div class="fw-bold fs-4 text-success"><?= number_format($mv_today['total_keluar']) ?></div></div></div>
            <div class="col-4"><div class="p-2 rounded-3 border bg-light"><div class="small text-muted">Selisih</div><div class="fw-bold fs-4 text-danger"><?= number_format($mv_today['selisih']) ?></div></div></div>
        </div>
        <div class="d-flex justify-content-between small fw-bold mb-1">
            <span>Persentase Kesesuaian (Masuk vs Keluar)</span>
            <span class="<?= $mv_today['seimbang'] ? 'text-success' : 'text-warning' ?>"><?= $mv_today['persen_kesesuaian'] ?>%</span>
        </div>
        <div class="progress" style="height: 20px;">
            <div class="progress-bar <?= $mv_today['seimbang'] ? 'bg-success' : 'bg-warning' ?> fw-bold" role="progressbar" style="width: <?= $mv_today['persen_kesesuaian'] ?>%"><?= $mv_today['persen_kesesuaian'] ?>%</div>
        </div>
        <div class="mt-2 small text-muted">
            <?= $mv_today['seimbang'] ? 'Jumlah kendaraan masuk sudah sama dengan keluar.' : 'Jumlah masuk dan keluar belum sama (selisih ' . number_format($mv_today['selisih']) . ' unit).' ?>
        </div>
        <div class="row g-2 mt-2">
            <div class="col-md-6"><div class="p-2 border rounded-3 bg-light small"><i class="fa-solid fa-keyboard me-1"></i> Manual — Masuk: <strong><?= number_format($mv_today['manual_masuk']) ?></strong> &bull; Keluar: <strong><?= number_format($mv_today['manual_keluar']) ?></strong></div></div>
            <div class="col-md-6"><div class="p-2 border rounded-3 bg-light small"><i class="fa-solid fa-video text-danger me-1"></i> Otomatis AI — Masuk: <strong><?= number_format($mv_today['ai_masuk']) ?></strong> &bull; Keluar: <strong><?= number_format($mv_today['ai_keluar']) ?></strong></div></div>
        </div>
    </div>
</div>

<!-- 2 Grafik Pemantauan -->
<div class="row g-4 mb-4">
    <!-- Grafik Lalu Lintas per Jam -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-chart-line text-primary me-2"></i> Fluktuasi Lalu Lintas Kendaraan per Jam (Hari Ini)
                </h6>
                <span class="badge bg-light text-muted border">WIB</span>
            </div>
            <div class="card-body p-4">
                <canvas id="trafficHourlyChart" height="240"></canvas>
            </div>
        </div>
    </div>

    <!-- Grafik Donut Komposisi Jenis Kendaraan -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-chart-pie text-primary me-2"></i> Proporsi Jenis Kendaraan
                </h6>
            </div>
            <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
                <div style="max-width: 250px; width: 100%;">
                    <canvas id="pieJenisChart"></canvas>
                </div>
                <div class="mt-3 text-center small text-muted">
                    Total Aktivitas Bulan Ini: <strong><?= number_format($metric_month['total_bulan_ini'] ?? 0) ?></strong> Kendaraan
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Perhitungan Tracking Otomatis AI (Rincian Kelas) -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-video text-danger me-2"></i> Perhitungan Tracking Otomatis (AI Vision) Hari Ini
        </h6>
        <a href="<?= BASE_URL ?>/admin/tracking.php" class="btn btn-sm btn-outline-danger">
            Buka Live Tracking <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-kemenhub align-middle mb-0">
            <thead>
                <tr>
                    <th>Kelas Kendaraan (Deteksi AI)</th>
                    <th class="text-center">Terdeteksi Masuk</th>
                    <th class="text-center">Terdeteksi Keluar</th>
                    <th class="text-center">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $kelas_map = ['car' => 'Mobil (R4)', 'motorcycle' => 'Sepeda Motor (R2)', 'bus' => 'Bus / Minibus', 'truck' => 'Truk / Pick Up'];
                foreach ($kelas_map as $key => $label):
                    $in = $tracking_today['counts_masuk'][$key] ?? 0;
                    $out = $tracking_today['counts_keluar'][$key] ?? 0;
                ?>
                    <tr>
                        <td class="fw-bold text-dark"><?= e($label) ?></td>
                        <td class="text-center fw-semibold text-success"><?= number_format($in) ?></td>
                        <td class="text-center fw-semibold text-info"><?= number_format($out) ?></td>
                        <td class="text-center fw-bold"><?= number_format($in + $out) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-light">
                    <td class="fw-bold">TOTAL</td>
                    <td class="text-center fw-bold text-success"><?= number_format($tracking_today['masuk']) ?></td>
                    <td class="text-center fw-bold text-info"><?= number_format($tracking_today['keluar']) ?></td>
                    <td class="text-center fw-bold text-primary"><?= number_format($tracking_today['total']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    // 1. Chart Traffic Hourly
    const ctxTraffic = document.getElementById('trafficHourlyChart').getContext('2d');
    new Chart(ctxTraffic, {
        type: 'line',
        data: {
            labels: <?= json_encode($hours) ?>,
            datasets: [
                {
                    label: 'Kendaraan Masuk (Manual)',
                    data: <?= json_encode($chart_masuk) ?>,
                    borderColor: '#0b2e59',
                    backgroundColor: 'rgba(11, 46, 89, 0.1)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointBackgroundColor: '#0b2e59',
                    pointRadius: 4
                },
                {
                    label: 'Kendaraan Keluar (Manual)',
                    data: <?= json_encode($chart_keluar) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointBackgroundColor: '#10b981',
                    pointRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            },
            plugins: {
                legend: { position: 'top' }
            }
        }
    });

    // 2. Chart Pie Jenis Kendaraan
    const ctxPie = document.getElementById('pieJenisChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($pie_labels) ?>,
            datasets: [{
                data: <?= json_encode($pie_values) ?>,
                backgroundColor: [
                    '#0ea5e9', // Motor (Sky)
                    '#0b2e59', // Mobil (Navy)
                    '#f59e0b', // Bus (Gold)
                    '#64748b', // Truk (Slate)
                    '#10b981'  // Dinas (Green)
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            cutout: '65%'
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
