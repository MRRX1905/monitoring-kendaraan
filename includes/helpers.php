<?php
/**
 * Helper & Utility Functions
 * Sistem Informasi Monitoring Parkir Bandara Malikussaleh
 */

require_once __DIR__ . '/../config/app.php';

/**
 * Escape string untuk pencegahan XSS
 */
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Menyimpan Flash Message di Session
 */
function set_flash($type, $message) {
    $_SESSION['flash_msg'] = [
        'type' => $type, // success, danger, warning, info
        'text' => $message
    ];
}

/**
 * Menampilkan Flash Message jika ada
 */
function render_flash() {
    if (isset($_SESSION['flash_msg'])) {
        $msg = $_SESSION['flash_msg'];
        unset($_SESSION['flash_msg']);
        $icon = 'fa-circle-info';
        if ($msg['type'] === 'success') $icon = 'fa-circle-check';
        if ($msg['type'] === 'danger') $icon = 'fa-circle-xmark';
        if ($msg['type'] === 'warning') $icon = 'fa-triangle-exclamation';

        echo "
        <div class='alert alert-{$msg['type']} alert-dismissible fade show d-flex align-items-center shadow-sm border-0 mb-4' role='alert'>
            <i class='fa-solid {$icon} me-2 fs-5'></i>
            <div>{$msg['text']}</div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    }
}

/**
 * Format tanggal Indonesia: 30 September 2026
 */
function format_tanggal_indo($date_str) {
    if (!$date_str) return '-';
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $timestamp = strtotime($date_str);
    $d = date('j', $timestamp);
    $m = (int)date('n', $timestamp);
    $y = date('Y', $timestamp);
    return "{$d} " . ($bulan[$m] ?? '') . " {$y}";
}

/**
 * Format waktu: 08:30 WIB
 */
function format_waktu($time_str) {
    if (!$time_str) return '-';
    return date('H:i', strtotime($time_str)) . ' WIB';
}

/**
 * Format durasi dari menit ke jam dan menit
 */
function format_durasi($total_menit) {
    if ($total_menit === null || $total_menit < 0) return '-';
    $jam = floor($total_menit / 60);
    $sisa_menit = $total_menit % 60;

    if ($jam > 0 && $sisa_menit > 0) {
        return "{$jam} Jam {$sisa_menit} Menit";
    } elseif ($jam > 0) {
        return "{$jam} Jam";
    } else {
        return "{$sisa_menit} Menit";
    }
}

/**
 * Ringkasan perhitungan Tracking Otomatis (AI Vision)
 * pada rentang tanggal tertentu.
 *
 * @return array{masuk:int, keluar:int, total:int, counts_masuk:array, counts_keluar:array}
 */
function tracking_summary($pdo, $dari, $sampai, $jenis_kendaraan_id = 0) {
    $kelas = ['car', 'motorcycle', 'bus', 'truck'];
    $summary = [
        'masuk' => 0,
        'keluar' => 0,
        'total' => 0,
        'counts_masuk' => array_fill_keys($kelas, 0),
        'counts_keluar' => array_fill_keys($kelas, 0),
    ];

    try {
        $sql = "
            SELECT LOWER(direction) AS dir, class_name, COUNT(*) AS cnt
            FROM tracking_kendaraan
            WHERE DATE(waktu_event) BETWEEN ? AND ?
        ";
        $params = [$dari, $sampai];

        if ((int)$jenis_kendaraan_id > 0) {
            $sql .= " AND jenis_kendaraan_id = ?";
            $params[] = (int)$jenis_kendaraan_id;
        }

        $sql .= " GROUP BY LOWER(direction), class_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch()) {
            $dir = $row['dir'];
            $cls = $row['class_name'];
            $cnt = (int)$row['cnt'];

            if ($dir === 'masuk') {
                $summary['masuk'] += $cnt;
                if (isset($summary['counts_masuk'][$cls])) {
                    $summary['counts_masuk'][$cls] += $cnt;
                }
            } else {
                $summary['keluar'] += $cnt;
                if (isset($summary['counts_keluar'][$cls])) {
                    $summary['counts_keluar'][$cls] += $cnt;
                }
            }
        }

        $summary['total'] = $summary['masuk'] + $summary['keluar'];
    } catch (Exception $e) {
        // Tabel tracking belum tersedia pada instalasi lama
    }

    return $summary;
}

/**
 * Label Indonesia untuk kelas deteksi YOLO
 */
function label_kelas_tracking($class_name) {
    $map = [
        'car' => 'Mobil (R4)',
        'motorcycle' => 'Sepeda Motor (R2)',
        'bus' => 'Bus / Minibus',
        'truck' => 'Truk / Pick Up',
    ];
    return $map[strtolower($class_name)] ?? ucfirst($class_name);
}

/**
 * Ringkasan pergerakan gabungan: Pencatatan Manual (tabel parkir)
 * + Pencatatan Otomatis AI (tracking_kendaraan), beserta persentase
 * perbandingan masuk vs keluar pada rentang tanggal tertentu.
 *
 * @return array
 */
function combined_movement_summary($pdo, $dari, $sampai, $jenis_kendaraan_id = 0) {
    $manual_masuk = 0;
    $manual_keluar = 0;

    try {
        $filter_jenis = (int)$jenis_kendaraan_id > 0 ? " AND jenis_kendaraan_id = ?" : "";
        $params_masuk = [$dari, $sampai];
        $params_keluar = [$dari, $sampai];
        if ((int)$jenis_kendaraan_id > 0) {
            $params_masuk[] = (int)$jenis_kendaraan_id;
            $params_keluar[] = (int)$jenis_kendaraan_id;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE tanggal BETWEEN ? AND ?{$filter_jenis}");
        $stmt->execute($params_masuk);
        $manual_masuk = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE DATE(datetime_keluar) BETWEEN ? AND ?{$filter_jenis}");
        $stmt->execute($params_keluar);
        $manual_keluar = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // Tabel parkir belum tersedia
    }

    $ai = tracking_summary($pdo, $dari, $sampai, $jenis_kendaraan_id);

    $total_masuk = $manual_masuk + (int)$ai['masuk'];
    $total_keluar = $manual_keluar + (int)$ai['keluar'];
    $total = $total_masuk + $total_keluar;

    // Kesesuaian: seberapa sama jumlah masuk dengan jumlah keluar (100% = sama persis)
    $selisih = abs($total_masuk - $total_keluar);
    $maks = max($total_masuk, $total_keluar);
    $persen_kesesuaian = $maks > 0 ? round(min($total_masuk, $total_keluar) / $maks * 100, 1) : 100.0;

    return [
        'manual_masuk' => $manual_masuk,
        'manual_keluar' => $manual_keluar,
        'ai_masuk' => (int)$ai['masuk'],
        'ai_keluar' => (int)$ai['keluar'],
        'ai_total' => (int)$ai['total'],
        'counts_masuk' => $ai['counts_masuk'],
        'counts_keluar' => $ai['counts_keluar'],
        'total_masuk' => $total_masuk,
        'total_keluar' => $total_keluar,
        'total' => $total,
        'selisih' => $selisih,
        'persen_kesesuaian' => $persen_kesesuaian,
        'seimbang' => $total_masuk === $total_keluar,
        'persen_masuk' => $total > 0 ? round($total_masuk / $total * 100, 1) : 0,
        'persen_keluar' => $total > 0 ? round($total_keluar / $total * 100, 1) : 0,
    ];
}

/**
 * Menghasilkan kode transaksi otomatis berformat: MLK-YYYYMMDD-XXXX
 */
function generate_kode_transaksi($pdo) {
    $today = date('Ymd');
    $prefix = "MLK-{$today}-";

    $stmt = $pdo->prepare("SELECT kode_transaksi FROM parkir WHERE kode_transaksi LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $last_num = (int)substr($last, -4);
        $next_num = $last_num + 1;
    } else {
        $next_num = 1;
    }

    return $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

/**
 * Menghasilkan nomor karcis urutan harian: K-001, K-002
 */
function generate_nomor_karcis($pdo) {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE tanggal = ?");
    $stmt->execute([$today]);
    $count = (int)$stmt->fetchColumn();
    $next_num = $count + 1;
    return 'K-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

/**
 * Catat aktivitas pengguna ke audit log
 */
function log_aktivitas($pdo, $aktivitas, $tipe = 'sistem') {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);

        $stmt = $pdo->prepare("INSERT INTO log_aktivitas (user_id, aktivitas, tipe, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $aktivitas, $tipe, $ip, $ua]);
    } catch (Exception $e) {
        // Abaikan kegagalan logging agar tidak mengganggu flow utama
    }
}
