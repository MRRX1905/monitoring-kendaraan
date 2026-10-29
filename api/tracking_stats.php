<?php
/**
 * API Real-Time Statistik & Log Deteksi AI Tracking (Hanya Masuk & Keluar)
 * Bandara Malikussaleh (LSW)
 * Kementerian Perhubungan Republik Indonesia
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDB();
$today = date('Y-m-d');

// 1. Ambil data agregat Masuk & Keluar dari database MySQL
$stats_db = [
    'total_today' => 0,
    'total_masuk_today' => 0,
    'total_keluar_today' => 0,
    'by_class_masuk' => ['car' => 0, 'motorcycle' => 0, 'bus' => 0, 'truck' => 0],
    'by_class_keluar' => ['car' => 0, 'motorcycle' => 0, 'bus' => 0, 'truck' => 0],
    'recent' => []
];

try {
    // Total Masuk hari ini
    $stmt_in = $pdo->prepare("SELECT COUNT(*) FROM tracking_kendaraan WHERE DATE(waktu_event) = ? AND LOWER(direction) = 'masuk'");
    $stmt_in->execute([$today]);
    $stats_db['total_masuk_today'] = (int)$stmt_in->fetchColumn();

    // Total Keluar hari ini
    $stmt_out = $pdo->prepare("SELECT COUNT(*) FROM tracking_kendaraan WHERE DATE(waktu_event) = ? AND LOWER(direction) = 'keluar'");
    $stmt_out->execute([$today]);
    $stats_db['total_keluar_today'] = (int)$stmt_out->fetchColumn();

    $stats_db['total_today'] = $stats_db['total_masuk_today'] + $stats_db['total_keluar_today'];

    // Breakdown kelas & arah hari ini
    $stmt_cls = $pdo->prepare("
        SELECT class_name, LOWER(direction) as dir, COUNT(*) as cnt
        FROM tracking_kendaraan
        WHERE DATE(waktu_event) = ?
        GROUP BY class_name, LOWER(direction)
    ");
    $stmt_cls->execute([$today]);
    while ($row = $stmt_cls->fetch()) {
        $cls = $row['class_name'];
        $dir = $row['dir'];
        if ($dir === 'masuk') {
            $stats_db['by_class_masuk'][$cls] = (int)$row['cnt'];
        } else {
            $stats_db['by_class_keluar'][$cls] = (int)$row['cnt'];
        }
    }

    // 12 Event deteksi terakhir (Hanya Catatan Masuk & Keluar)
    $stmt_rec = $pdo->query("
        SELECT t.id, t.waktu_event, t.class_name, t.direction, t.track_id, t.confidence, t.line_label,
               j.nama_jenis, j.icon_class
        FROM tracking_kendaraan t
        LEFT JOIN jenis_kendaraan j ON t.jenis_kendaraan_id = j.id
        ORDER BY t.id DESC
        LIMIT 12
    ");
    $stats_db['recent'] = $stmt_rec->fetchAll();
} catch (Exception $e) {
    // Tangani jika tabel sedang diinisialisasi
}

// 2. Ambil status live stream dari server Python (tanpa fsockopen yang membuat TIME_WAIT socket)
$live_server = [
    'server_online' => false,
    'streaming' => false,
    'fps' => 0.0,
    'masuk' => 0,
    'keluar' => 0,
    'total' => 0,
    'counts_masuk' => ['car' => 0, 'motorcycle' => 0, 'bus' => 0, 'truck' => 0],
    'counts_keluar' => ['car' => 0, 'motorcycle' => 0, 'bus' => 0, 'truck' => 0]
];

$ctx = stream_context_create([
    'http' => [
        'timeout' => 1.5,
        'ignore_errors' => true
    ]
]);

$raw = @file_get_contents('http://127.0.0.1:5055/status', false, $ctx);
if ($raw !== false) {
    $d = json_decode($raw, true);
    if ($d && isset($d['running'])) {
        $live_server['server_online'] = true;
        $live_server['streaming'] = $d['streaming'] ?? false;
        $live_server['fps'] = $d['fps'] ?? 0.0;
        $live_server['masuk'] = $d['masuk'] ?? 0;
        $live_server['keluar'] = $d['keluar'] ?? 0;
        $live_server['total'] = $d['total'] ?? 0;
        $live_server['counts_masuk'] = $d['counts_masuk'] ?? $live_server['counts_masuk'];
        $live_server['counts_keluar'] = $d['counts_keluar'] ?? $live_server['counts_keluar'];
    }
}

echo json_encode([
    'success' => true,
    'timestamp' => date('H:i:s'),
    'db' => $stats_db,
    'server' => $live_server
]);

