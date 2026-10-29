<?php
/**
 * Konfigurasi Database PDO
 * Sistem Monitoring Kendaraan Bandara Malikussaleh
 */

require_once __DIR__ . '/app.php';

$db_host = '127.0.0.1';
$db_port = '3306';
$db_name = 'parkir_malikussaleh';
$db_user = 'root';
$db_pass = ''; // Default password MySQL Laragon

$pdo = null;
$db_error_message = null;

try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    $db_error_message = $e->getMessage();
    $pdo = null;
}

/**
 * Mengambil koneksi PDO aktif atau menampilkan panduan install jika belum ada database
 * @return PDO
 */
function getDB() {
    global $pdo, $db_error_message;
    if ($pdo === null) {
        $install_url = BASE_URL . '/install.php';
        die("
        <!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='UTF-8'>
            <title>Koneksi Database Belum Siap - Sistem Monitoring Kendaraan</title>
            <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css'>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'>
            <style>
                body { background-color: #f1f5f9; font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; min-height: 100vh; }
                .db-box { max-width: 600px; margin: auto; background: #fff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); overflow: hidden; border-top: 5px solid #0b2e59; }
            </style>
        </head>
        <body>
            <div class='container p-4'>
                <div class='db-box p-4 p-md-5'>
                    <div class='text-center mb-4'>
                        <div class='text-warning mb-2'><i class='fa-solid fa-triangle-exclamation fa-3x'></i></div>
                        <h4 class='fw-bold text-dark'>Database Belum Siap atau Belum Dibuat</h4>
                        <p class='text-muted small'>Kementerian Perhubungan &bull; UPBU Malikussaleh (LSW)</p>
                    </div>
                    <div class='alert alert-danger py-2 small'>
                        <strong>Pesan Error:</strong> {$db_error_message}
                    </div>
                    <p class='small text-secondary'>Sistem mendeteksi database <code>parkir_malikussaleh</code> belum terkonfigurasi pada MySQL Laragon Anda.</p>
                    <div class='d-grid gap-2'>
                        <a href='{$install_url}' class='btn btn-primary btn-lg shadow-sm' style='background: #0b2e59; border-color: #0b2e59;'>
                            <i class='fa-solid fa-wand-magic-sparkles me-2'></i> Klik di Sini untuk Setup Database Otomatis
                        </a>
                    </div>
                    <div class='text-center mt-3'>
                        <small class='text-muted'>Pastikan Laragon sudah dalam kondisi <strong>Start All</strong>.</small>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ");
    }
    return $pdo;
}
