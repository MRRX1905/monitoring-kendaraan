<?php
/**
 * Middleware Autentikasi & Otorisasi Hak Akses (RBAC)
 * Sistem Informasi Monitoring Parkir Bandara Malikussaleh
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/helpers.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    set_flash('danger', 'Silakan login terlebih dahulu untuk mengakses sistem.');
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

/**
 * Validasi Role Pengguna
 * @param array|string $allowed_roles Contoh: ['admin'] atau ['admin', 'petugas']
 */
function check_role($allowed_roles) {
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }

    $current_role = $_SESSION['user_role'] ?? '';

    if (!in_array($current_role, $allowed_roles)) {
        set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk membuka halaman tersebut.');
        // Redirect ke dashboard masing-masing
        if ($current_role === 'admin') {
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
        } elseif ($current_role === 'supervisor') {
            header('Location: ' . BASE_URL . '/supervisor/dashboard.php');
        } else {
            header('Location: ' . BASE_URL . '/petugas/dashboard.php');
        }
        exit;
    }
}
