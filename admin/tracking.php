<?php
/**
 * Sistem Tracking Otomatis Kendaraan (AI Vision) - Garis Vertikal (Masuk & Keluar)
 * Bandara Malikussaleh (LSW)
 * Kementerian Perhubungan Republik Indonesia
 */
$page_title = 'Tracking Otomatis Kendaraan';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['admin', 'supervisor', 'petugas']);

$pdo = getDB();
$today = date('Y-m-d');

// Baca konfigurasi tracking (Read-only di UI)
$config_file = __DIR__ . '/../tracking/config.json';
$cfg = [];
if (file_exists($config_file)) {
    $cfg = json_decode(file_get_contents($config_file), true);
}

$video_source = $cfg['source'] ?? 'demo/moving_traffic.mp4';
$line_cfg = $cfg['line'] ?? [];
$line_label = $line_cfg['label'] ?? 'Garis Monitoring Vertikal';
$line_x = isset($line_cfg['p1'][0]) ? ($line_cfg['p1'][0] * 100) : 50;

// Agregat perhitungan Masuk & Keluar hari ini dari database
$total_masuk = (int)$pdo->query("SELECT COUNT(*) FROM tracking_kendaraan WHERE DATE(waktu_event) = '$today' AND LOWER(direction) = 'masuk'")->fetchColumn();
$total_keluar = (int)$pdo->query("SELECT COUNT(*) FROM tracking_kendaraan WHERE DATE(waktu_event) = '$today' AND LOWER(direction) = 'keluar'")->fetchColumn();
$total_today = $total_masuk + $total_keluar;

// Breakdown kelas (Masuk & Keluar)
$stmt_cls = $pdo->prepare("
    SELECT class_name, LOWER(direction) as dir, COUNT(*) as cnt
    FROM tracking_kendaraan
    WHERE DATE(waktu_event) = ?
    GROUP BY class_name, LOWER(direction)
");
$stmt_cls->execute([$today]);

$counts_masuk = ['car' => 0, 'motorcycle' => 0, 'bus' => 0, 'truck' => 0];
$counts_keluar = ['car' => 0, 'motorcycle' => 0, 'bus' => 0, 'truck' => 0];

while ($row = $stmt_cls->fetch()) {
    $c = $row['class_name'];
    $d = $row['dir'];
    if ($d === 'masuk') {
        $counts_masuk[$c] = (int)$row['cnt'];
    } else {
        $counts_keluar[$c] = (int)$row['cnt'];
    }
}

// 10 Event deteksi terakhir (Hanya Masuk & Keluar)
$stmt_rec = $pdo->query("
    SELECT t.*, j.nama_jenis, j.icon_class
    FROM tracking_kendaraan t
    LEFT JOIN jenis_kendaraan j ON t.jenis_kendaraan_id = j.id
    ORDER BY t.id DESC
    LIMIT 10
");
$recent_events = $stmt_rec->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-box mb-4">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-video text-danger me-2"></i> Sistem Tracking Otomatis Kendaraan (AI Vision)
        </h1>
        <div class="page-header-subtitle">
            Penghitungan otomatis lalu lintas kendaraan (Masuk & Keluar) berbasis YOLOv8 dengan Garis Monitoring Vertikal
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span id="badgeStreamStatus" class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 fs-6">
            <i class="fa-solid fa-circle fa-2xs me-1"></i> Memeriksa Stream...
        </span>
        <button id="btnToggleStream" type="button" class="btn btn-primary shadow-sm" style="background: #0b2e59; border-color: #0b2e59;">
            <i class="fa-solid fa-play me-1 text-warning"></i> Hidupkan Stream
        </button>
    </div>
</div>

<!-- 6 Kartu Ringkasan Perhitungan Masuk & Keluar Real-Time -->
<div class="row g-3 mb-4">
    <!-- 1. Total Terhitung -->
    <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-card stat-navy text-center p-3 h-100">
            <div class="stat-label">TOTAL HARI INI</div>
            <div class="stat-value text-primary fs-3" id="statTotal"><?= $total_today ?></div>
            <div class="small text-muted mt-1"><i class="fa-solid fa-calculator me-1"></i> Masuk + Keluar</div>
        </div>
    </div>

    <!-- 2. Kendaraan Masuk -->
    <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-card stat-success text-center p-3 h-100" style="border-top-color: #10b981;">
            <div class="stat-label">KENDARAAN MASUK</div>
            <div class="stat-value text-success fs-3" id="statMasuk"><?= $total_masuk ?></div>
            <div class="small text-muted mt-1"><i class="fa-solid fa-arrow-right-to-bracket text-success me-1"></i> Arah Masuk</div>
        </div>
    </div>

    <!-- 3. Kendaraan Keluar -->
    <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-card stat-info text-center p-3 h-100" style="border-top-color: #0ea5e9;">
            <div class="stat-label">KENDARAAN KELUAR</div>
            <div class="stat-value text-info fs-3" id="statKeluar"><?= $total_keluar ?></div>
            <div class="small text-muted mt-1"><i class="fa-solid fa-arrow-right-from-bracket text-info me-1"></i> Arah Keluar</div>
        </div>
    </div>

    <!-- 4. Mobil Penumpang -->
    <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-card stat-warning text-center p-3 h-100">
            <div class="stat-label">MOBIL (R4)</div>
            <div class="stat-value text-warning fs-3" id="statCarTotal"><?= ($counts_masuk['car'] + $counts_keluar['car']) ?></div>
            <div class="small text-muted mt-1">
                In: <b id="statCarIn" class="text-success"><?= $counts_masuk['car'] ?></b> | Out: <b id="statCarOut" class="text-info"><?= $counts_keluar['car'] ?></b>
            </div>
        </div>
    </div>

    <!-- 5. Sepeda Motor -->
    <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-card stat-navy text-center p-3 h-100" style="border-top-color: #8b5cf6;">
            <div class="stat-label">MOTOR (R2)</div>
            <div class="stat-value fs-3" style="color: #8b5cf6;" id="statMotoTotal"><?= ($counts_masuk['motorcycle'] + $counts_keluar['motorcycle']) ?></div>
            <div class="small text-muted mt-1">
                In: <b id="statMotoIn" class="text-success"><?= $counts_masuk['motorcycle'] ?></b> | Out: <b id="statMotoOut" class="text-info"><?= $counts_keluar['motorcycle'] ?></b>
            </div>
        </div>
    </div>

    <!-- 6. Bus & Truk -->
    <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-card stat-navy text-center p-3 h-100" style="border-top-color: #ec4899;">
            <div class="stat-label">BUS & TRUK</div>
            <div class="stat-value fs-3" style="color: #ec4899;" id="statHeavyTotal"><?= ($counts_masuk['bus'] + $counts_keluar['bus'] + $counts_masuk['truck'] + $counts_keluar['truck']) ?></div>
            <div class="small text-muted mt-1">
                In: <b id="statHeavyIn" class="text-success"><?= ($counts_masuk['bus'] + $counts_masuk['truck']) ?></b> | Out: <b id="statHeavyOut" class="text-info"><?= ($counts_keluar['bus'] + $counts_keluar['truck']) ?></b>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Kolom Layar Video Stream -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-dot text-danger fa-beat-fade"></i> Live Monitoring Area Parkir
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.72rem;">
                        <i class="fa-solid fa-arrows-left-right me-1"></i> Garis Vertikal (X: <?= $line_x ?>%)
                    </span>
                </h6>
                <div class="d-flex align-items-center gap-2">
                    <span id="fpsCounter" class="badge bg-dark font-monospace">FPS: --</span>
                    <button id="btnResetCounter" type="button" class="btn btn-sm btn-outline-secondary py-0" title="Reset Counter">
                        <i class="fa-solid fa-rotate me-1"></i> Reset
                    </button>
                </div>
            </div>
            <div class="card-body p-0 position-relative bg-dark d-flex align-items-center justify-content-center" style="min-height: 480px;">
                <!-- Video Stream Element (Multi-threaded MJPEG dengan auto-reconnect stabil) -->
                <img id="streamImg" src="" class="w-100 h-auto d-none" alt="Live Stream Kamera">

                <!-- Placeholder Jika Stream Dimatikan (OFF) -->
                <div id="streamOffPlaceholder" class="text-center p-5 text-white">
                    <div class="mb-3">
                        <i class="fa-solid fa-video-slash fa-4x text-secondary opacity-50"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Stream Kamera Dinonaktifkan</h5>
                    <p class="text-secondary small mb-4" style="max-width: 420px; margin: auto;">
                        Fitur streaming sedang dimatikan untuk menghemat sumber daya CPU. Klik tombol di bawah untuk mengaktifkan pemantauan live garis vertikal.
                    </p>
                    <button id="btnStartStreamPlaceholder" type="button" class="btn btn-warning fw-bold text-dark px-4 py-2 shadow">
                        <i class="fa-solid fa-play me-2"></i> Hidupkan Stream Kamera
                    </button>
                </div>

                <!-- Indikator Loading / Menghubungkan -->
                <div id="streamLoading" class="text-center p-5 text-white d-none">
                    <div class="spinner-border text-warning mb-3" role="status"></div>
                    <div class="fw-semibold">Menghubungkan ke Server AI Vision...</div>
                    <small class="text-secondary">Memuat model YOLOv8 & video sumber...</small>
                </div>
            </div>
            <div class="card-footer bg-light p-3 small text-muted d-flex justify-content-between align-items-center">
                <div>
                    <i class="fa-solid fa-server me-1"></i>
                    <span id="streamFooterText">Mode Siaga (Klik tombol untuk memulai stream)</span>
                </div>
                <div>
                    <span class="badge bg-dark-subtle text-dark border">
                        <i class="fa-solid fa-file-video me-1"></i> <?= htmlspecialchars(basename($video_source)) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Panel Kontrol & Informasi Deteksi -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-sliders text-warning me-2"></i> Konfigurasi Monitoring
                </h6>
            </div>
            <div class="card-body p-3">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Arah Deteksi:</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="fa-solid fa-arrows-left-right me-1"></i> Vertikal (Kiri <-> Kanan)
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Posisi Garis:</span>
                        <span class="fw-semibold font-monospace">X: <?= $line_x ?>% (Tengah Layar)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Kiri ke Kanan:</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Dihitung MASUK
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Kanan ke Kiri:</span>
                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                            <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Dihitung KELUAR
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Model AI:</span>
                        <span class="fw-semibold">YOLOv8 Nano (CPU Optimized)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Mode Sinkronisasi:</span>
                        <span class="badge bg-success text-white">
                            <i class="fa-solid fa-database me-1"></i> Masuk & Keluar Otomatis
                        </span>
                    </li>
                </ul>

                <div class="alert alert-info py-2 px-3 mt-3 mb-0 small rounded-3">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Sistem hanya mencatat statistik kendaraan <b>Masuk</b> dan <b>Keluar</b> secara otomatis tanpa mengubah status transaksi parkir.
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-bolt text-danger me-2"></i> Kontrol Cepat Stream
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="d-grid gap-2">
                    <button id="btnQuickStart" class="btn btn-outline-success text-start py-2">
                        <i class="fa-solid fa-play me-2"></i> Hidupkan Stream Kamera
                    </button>
                    <button id="btnQuickStop" class="btn btn-outline-danger text-start py-2">
                        <i class="fa-solid fa-stop me-2"></i> Matikan Stream (Standby)
                    </button>
                    <button id="btnQuickReset" class="btn btn-outline-secondary text-start py-2">
                        <i class="fa-solid fa-rotate me-2"></i> Reset Counter Sementara
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Riwayat Deteksi Kendaraan (Masuk & Keluar Saja) -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-database text-primary me-2"></i> Riwayat Kendaraan Melintas (Masuk & Keluar)
        </h6>
        <span class="badge bg-light text-muted border">Pencatatan Otomatis Real-Time</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-kemenhub align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Waktu Deteksi</th>
                    <th>ID Kendaraan (Track ID)</th>
                    <th>Jenis Kendaraan</th>
                    <th>Arah Pergerakan</th>
                    <th>Tingkat Akurasi</th>
                    <th>Posisi Garis</th>
                    <th>Status Pencatatan</th>
                </tr>
            </thead>
            <tbody id="tblRecentEvents">
                <?php if (empty($recent_events)): ?>
                    <tr id="rowEmptyEvents">
                        <td colspan="8" class="text-center py-4 text-muted">
                            Belum ada kendaraan yang melintasi garis vertikal hari ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($recent_events as $ev): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= date('H:i:s', strtotime($ev['waktu_event'])) ?> WIB</div>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($ev['waktu_event'])) ?></small>
                            </td>
                            <td><code>#<?= $ev['track_id'] ?></code></td>
                            <td>
                                <span class="badge bg-primary font-monospace text-uppercase"><?= e($ev['class_name']) ?></span>
                            </td>
                            <td>
                                <?php if (strtolower($ev['direction']) === 'masuk'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1">
                                        <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> MASUK
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1">
                                        <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> KELUAR
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="fw-semibold text-success"><?= round($ev['confidence'] * 100) ?>%</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="fa-solid fa-grip-lines-vertical me-1"></i> <?= e($ev['line_label'] ?? 'Garis Vertikal') ?>
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

<script>
    const streamImg = document.getElementById('streamImg');
    const streamPlaceholder = document.getElementById('streamOffPlaceholder');
    const streamLoading = document.getElementById('streamLoading');
    const btnToggle = document.getElementById('btnToggleStream');
    const btnPlaceholder = document.getElementById('btnStartStreamPlaceholder');
    const btnReset = document.getElementById('btnResetCounter');
    const btnQuickStart = document.getElementById('btnQuickStart');
    const btnQuickStop = document.getElementById('btnQuickStop');
    const btnQuickReset = document.getElementById('btnQuickReset');
    const badgeStatus = document.getElementById('badgeStreamStatus');
    const footerText = document.getElementById('streamFooterText');
    const fpsBadge = document.getElementById('fpsCounter');

    let isStreamActive = false;
    let pollInterval = null;
    let reconnectTimeout = null;
    let consecutiveFailures = 0;

    // Auto-reconnect stream jika terjadi diskoneksi sementara
    streamImg.onerror = function() {
        if (isStreamActive) {
            clearTimeout(reconnectTimeout);
            reconnectTimeout = setTimeout(() => {
                if (isStreamActive) {
                    console.log("Reconnecting stream...");
                    streamImg.src = "http://127.0.0.1:5055/stream?t=" + Date.now();
                }
            }, 1500);
        }
    };

    // Fungsi update status UI
    function updateUIStreamState(active, fps = 0) {
        isStreamActive = active;
        if (active) {
            streamImg.classList.remove('d-none');
            streamPlaceholder.classList.add('d-none');
            streamLoading.classList.add('d-none');

            // Set sumber stream MJPEG hanya jika belum terpasang
            const currentSrc = streamImg.src || '';
            if (!currentSrc.includes('127.0.0.1:5055/stream')) {
                streamImg.src = "http://127.0.0.1:5055/stream?t=" + Date.now();
            }

            badgeStatus.className = "badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6";
            badgeStatus.innerHTML = '<i class="fa-solid fa-circle text-success fa-beat-fade me-1"></i> STREAM LIVE: AKTIF';

            btnToggle.className = "btn btn-danger shadow-sm";
            btnToggle.innerHTML = '<i class="fa-solid fa-stop me-1"></i> Matikan Stream';

            footerText.textContent = "Live Monitoring Aktif (Deteksi Garis Vertikal Masuk & Keluar)";
            fpsBadge.textContent = "FPS: " + (fps > 0 ? fps.toFixed(1) : "--");
        } else {
            streamImg.classList.add('d-none');
            streamImg.src = "";
            streamPlaceholder.classList.remove('d-none');
            streamLoading.classList.add('d-none');

            badgeStatus.className = "badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 fs-6";
            badgeStatus.innerHTML = '<i class="fa-solid fa-circle fa-2xs me-1"></i> STREAM STANDBY: DIMATIKAN';

            btnToggle.className = "btn btn-primary shadow-sm";
            btnToggle.style.background = "#0b2e59";
            btnToggle.style.borderColor = "#0b2e59";
            btnToggle.innerHTML = '<i class="fa-solid fa-play me-1 text-warning"></i> Hidupkan Stream';

            footerText.textContent = "Stream Kamera Dimatikan (Mode Siaga)";
            fpsBadge.textContent = "FPS: 0.0";
        }
    }

    // Toggle Stream (Start / Stop)
    async function setStreamState(targetAction) {
        if (targetAction === 'start') {
            streamPlaceholder.classList.add('d-none');
            streamLoading.classList.remove('d-none');
        }

        try {
            const formData = new FormData();
            formData.append('action', targetAction);

            const res = await fetch('<?= BASE_URL ?>/api/tracking_control.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                updateUIStreamState(data.streaming);
            } else {
                alert(data.message || 'Gagal mengubah status stream.');
                updateUIStreamState(false);
            }
        } catch (err) {
            console.error('Error toggling stream:', err);
            updateUIStreamState(false);
        }
    }

    btnToggle.addEventListener('click', () => setStreamState(isStreamActive ? 'stop' : 'start'));
    btnPlaceholder.addEventListener('click', () => setStreamState('start'));
    btnQuickStart.addEventListener('click', () => setStreamState('start'));
    btnQuickStop.addEventListener('click', () => setStreamState('stop'));

    // Reset Counter
    async function resetCounterAction() {
        if (!confirm('Reset hitungan counter sementara pada kamera? (Data riwayat di database tetap tersimpan)')) return;
        try {
            const formData = new FormData();
            formData.append('action', 'reset');
            await fetch('<?= BASE_URL ?>/api/tracking_control.php', { method: 'POST', body: formData });
            fetchStats();
        } catch (e) {}
    }

    btnReset.addEventListener('click', resetCounterAction);
    btnQuickReset.addEventListener('click', resetCounterAction);

    // Polling Statistik Real-Time (Masuk, Keluar, dan Breakdown)
    async function fetchStats() {
        try {
            const res = await fetch('<?= BASE_URL ?>/api/tracking_stats.php');
            const data = await res.json();

            if (data.success) {
                consecutiveFailures = 0;
                const db = data.db;
                const srv = data.server;

                // Update counter kartu KPI
                document.getElementById('statTotal').textContent = db.total_today;
                document.getElementById('statMasuk').textContent = db.total_masuk_today;
                document.getElementById('statKeluar').textContent = db.total_keluar_today;

                // Mobil
                const carIn = db.by_class_masuk.car || 0;
                const carOut = db.by_class_keluar.car || 0;
                document.getElementById('statCarTotal').textContent = carIn + carOut;
                document.getElementById('statCarIn').textContent = carIn;
                document.getElementById('statCarOut').textContent = carOut;

                // Motor
                const motoIn = db.by_class_masuk.motorcycle || 0;
                const motoOut = db.by_class_keluar.motorcycle || 0;
                document.getElementById('statMotoTotal').textContent = motoIn + motoOut;
                document.getElementById('statMotoIn').textContent = motoIn;
                document.getElementById('statMotoOut').textContent = motoOut;

                // Bus & Truk
                const heavyIn = (db.by_class_masuk.bus || 0) + (db.by_class_masuk.truck || 0);
                const heavyOut = (db.by_class_keluar.bus || 0) + (db.by_class_keluar.truck || 0);
                document.getElementById('statHeavyTotal').textContent = heavyIn + heavyOut;
                document.getElementById('statHeavyIn').textContent = heavyIn;
                document.getElementById('statHeavyOut').textContent = heavyOut;

                // Update status stream jika status server berubah
                if (srv.server_online) {
                    if (srv.streaming !== isStreamActive) {
                        updateUIStreamState(srv.streaming, srv.fps);
                    } else if (isStreamActive) {
                        fpsBadge.textContent = "FPS: " + (srv.fps ? srv.fps.toFixed(1) : "--");
                    }
                }

                // Update tabel event deteksi terbaru (Masuk & Keluar)
                if (db.recent && db.recent.length > 0) {
                    const tbody = document.getElementById('tblRecentEvents');
                    let html = '';
                    db.recent.forEach((ev, idx) => {
                        const timeStr = ev.waktu_event ? ev.waktu_event.split(' ')[1] : '--:--:--';
                        const dateStr = ev.waktu_event ? ev.waktu_event.split(' ')[0] : '--';
                        const isMasuk = (ev.direction || '').toLowerCase() === 'masuk';
                        const dirBadge = isMasuk
                            ? `<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1"><i class="fa-solid fa-arrow-right-to-bracket me-1"></i> MASUK</span>`
                            : `<span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i> KELUAR</span>`;

                        html += `
                            <tr>
                                <td>${idx + 1}</td>
                                <td>
                                    <div class="fw-semibold text-dark">${timeStr} WIB</div>
                                    <small class="text-muted">${dateStr}</small>
                                </td>
                                <td><code>#${ev.track_id}</code></td>
                                <td>
                                    <span class="badge bg-primary font-monospace text-uppercase">${ev.class_name}</span>
                                </td>
                                <td>${dirBadge}</td>
                                <td>
                                    <span class="fw-semibold text-success">${Math.round(ev.confidence * 100)}%</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <i class="fa-solid fa-grip-lines-vertical me-1"></i> ${ev.line_label || 'Garis Vertikal'}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="fa-solid fa-circle-check me-1"></i> Tercatat Masuk & Keluar
                                    </span>
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                }
            }
        } catch (err) {
            consecutiveFailures++;
            if (consecutiveFailures >= 5 && isStreamActive) {
                updateUIStreamState(false);
            }
        }
    }

    // Inisialisasi awal saat halaman dibuka
    document.addEventListener('DOMContentLoaded', () => {
        fetch('<?= BASE_URL ?>/api/tracking_control.php?action=status')
            .then(r => r.json())
            .then(d => {
                updateUIStreamState(d.running && d.streaming, d.fps || 0);
            })
            .catch(() => updateUIStreamState(false));

        // Mulai polling berkala setiap 1.5 detik
        pollInterval = setInterval(fetchStats, 1500);
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
