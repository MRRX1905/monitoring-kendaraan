<?php
/**
 * API Kontrol Stream Tracking Otomatis Kendaraan (Start, Stop, Reset, Status)
 * Bandara Malikussaleh (LSW)
 * Kementerian Perhubungan Republik Indonesia
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';

// Pastikan login untuk kontrol
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = trim($_POST['action'] ?? ($_GET['action'] ?? 'status'));
$python_exe = 'C:\\Users\\mrafi\\AppData\\Local\\Programs\\Python\\Python314\\python.exe';
$script_path = str_replace('\\', '/', realpath(__DIR__ . '/../tracking/server.py'));
$work_dir = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$server_host = '127.0.0.1';
$server_port = 5055;

/**
 * Cek apakah socket server streaming sedang aktif
 */
function is_stream_server_alive($host, $port) {
    $fp = @fsockopen($host, $port, $errno, $errstr, 0.3);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

/**
 * Kirim request HTTP ke server tracking Python
 */
function request_tracker($path) {
    global $server_host, $server_port;
    $url = "http://{$server_host}:{$server_port}{$path}";
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 2.0,
            'ignore_errors' => true
        ]
    ]);
    $res = @file_get_contents($url, false, $ctx);
    if ($res !== false) {
        return json_decode($res, true);
    }
    return null;
}

// 1. Action: STATUS
if ($action === 'status') {
    $alive = is_stream_server_alive($server_host, $server_port);
    if ($alive) {
        $status_data = request_tracker('/status');
        if ($status_data) {
            echo json_encode([
                'success' => true,
                'running' => true,
                'streaming' => $status_data['streaming'] ?? false,
                'fps' => $status_data['fps'] ?? 0,
                'masuk' => $status_data['masuk'] ?? 0,
                'keluar' => $status_data['keluar'] ?? 0,
                'total' => $status_data['total'] ?? 0,
                'counts_masuk' => $status_data['counts_masuk'] ?? [],
                'counts_keluar' => $status_data['counts_keluar'] ?? [],
                'counts' => $status_data['counts'] ?? [],
                'source' => $status_data['source'] ?? '',
                'events' => $status_data['recent_events'] ?? []
            ]);
            exit;
        }
    }
    echo json_encode([
        'success' => true,
        'running' => false,
        'streaming' => false,
        'fps' => 0,
        'masuk' => 0,
        'keluar' => 0,
        'total' => 0,
        'counts_masuk' => ['car' => 0, 'motorcycle' => 0, 'bus' => 0, 'truck' => 0],
        'counts_keluar' => ['car' => 0, 'motorcycle' => 0, 'bus' => 0, 'truck' => 0],
        'counts' => ['car' => 0, 'motorcycle' => 0, 'bus' => 0, 'truck' => 0],
        'events' => []
    ]);
    exit;
}

// 2. Action: START
if ($action === 'start') {
    $alive = is_stream_server_alive($server_host, $server_port);

    if (!$alive) {
        // Luncurkan server Python di background
        $cmd = "powershell -WindowStyle Hidden -Command \"Start-Process '{$python_exe}' -ArgumentList '\"{$script_path}\"' -WorkingDirectory '\"{$work_dir}\"' -WindowStyle Hidden\"";
        pclose(popen($cmd, "r"));

        // Tunggu socket siap (maksimal 3 detik)
        for ($i = 0; $i < 6; $i++) {
            usleep(500000); // 0.5 detik
            if (is_stream_server_alive($server_host, $server_port)) {
                $alive = true;
                break;
            }
        }
    }

    if ($alive) {
        $res = request_tracker('/control?action=start');
        echo json_encode([
            'success' => true,
            'running' => true,
            'streaming' => true,
            'message' => 'Stream kamera AI tracking berhasil dihidupkan.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'running' => false,
            'streaming' => false,
            'message' => 'Gagal memulai background server tracking.'
        ]);
    }
    exit;
}

// 3. Action: STOP
if ($action === 'stop') {
    if (is_stream_server_alive($server_host, $server_port)) {
        request_tracker('/control?action=stop');
        echo json_encode([
            'success' => true,
            'running' => true,
            'streaming' => false,
            'message' => 'Stream kamera AI tracking berhasil dimatikan (Standby).'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'running' => false,
            'streaming' => false,
            'message' => 'Stream sudah dalam status tidak aktif.'
        ]);
    }
    exit;
}

// 4. Action: RESET
if ($action === 'reset') {
    if (is_stream_server_alive($server_host, $server_port)) {
        request_tracker('/control?action=reset');
    }
    echo json_encode([
        'success' => true,
        'message' => 'Counter tracking berhasil direset.'
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
