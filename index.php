<?php
/**
 * Router Utama / Entry Point
 * Sistem Informasi Monitoring Parkir Bandara Malikussaleh
 */
require_once __DIR__ . '/config/app.php';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'petugas';
    if ($role === 'admin') {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
    } elseif ($role === 'supervisor') {
        header('Location: ' . BASE_URL . '/supervisor/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/petugas/dashboard.php');
    }
    exit;
} else {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}
