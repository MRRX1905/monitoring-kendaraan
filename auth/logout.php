<?php
/**
 * Logout Pengguna
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

if (isset($_SESSION['user_id'])) {
    try {
        $pdo = getDB();
        log_aktivitas($pdo, "Pengguna " . ($_SESSION['user_nama'] ?? '') . " telah logout", 'auth');
    } catch (Exception $e) {
        // Abaikan jika database tidak tersedia
    }
}

// Hapus semua data sesi
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

session_start();
set_flash('info', 'Anda telah berhasil keluar dari sistem monitoring kendaraan.');
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
