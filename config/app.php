<?php
/**
 * Konfigurasi Aplikasi & Identitas Kemenhub
 * Sistem Informasi Monitoring Kendaraan Masuk & Keluar
 * Bandar Udara Malikussaleh (LSW)
 */

if (!ob_get_level()) {
    ob_start();
}

// Zona Waktu Indonesia Barat (WIB)
date_default_timezone_set('Asia/Jakarta');

// Inisialisasi Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Identitas Instansi & Kementerian
define('APP_NAME', 'SISTEM MONITORING KENDARAAN');
define('APP_SHORT_NAME', 'SIM-KENDARAAN');
define('APP_VERSION', '1.0.0');
define('INSTANSI_NAME', 'KEMENTERIAN PERHUBUNGAN');
define('INSTANSI_SUB', 'DIREKTORAT JENDERAL PERHUBUNGAN UDARA');
define('UPBU_NAME', 'KANTOR UPBU KELAS III MALIKUSSALEH');
define('AIRPORT_NAME', 'Bandar Udara Malikussaleh');
define('AIRPORT_CODE', 'LSW');
define('AIRPORT_LOCATION', 'Kabupaten Aceh Utara, Provinsi Aceh');
define('AIRPORT_ADDRESS', 'Jl. Bandara Malikussaleh, Pinto Makmur, Kec. Muara Batu, Kab. Aceh Utara, Aceh 24355');

// Deteksi Dinamis BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

$pos = strpos($script_dir, '/parkir-bandara-malikussaleh');
if ($pos !== false) {
    $base_path = substr($script_dir, 0, $pos + strlen('/parkir-bandara-malikussaleh'));
} else {
    $base_path = '/parkir-bandara-malikussaleh';
}

define('BASE_URL', rtrim($protocol . $host . $base_path, '/'));
