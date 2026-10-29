<?php
/**
 * Handler Proses Login Pengguna
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    set_flash('danger', 'Harap isi username dan password dengan lengkap.');
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

$pdo = getDB();

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'aktif' LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Regenerate session id untuk keamanan
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nama'] = $user['nama'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];

        // Update last login
        $stmt_update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt_update->execute([$user['id']]);

        // Catat aktivitas
        log_aktivitas($pdo, "Pengguna {$user['nama']} ({$user['username']}) berhasil login", 'auth');

        set_flash('success', "Selamat datang kembali, <strong>" . e($user['nama']) . "</strong>.");

        // Redirect sesuai role
        if ($user['role'] === 'admin') {
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
        } elseif ($user['role'] === 'supervisor') {
            header('Location: ' . BASE_URL . '/supervisor/dashboard.php');
        } else {
            header('Location: ' . BASE_URL . '/petugas/dashboard.php');
        }
        exit;
    } else {
        set_flash('danger', 'Username atau password yang Anda masukkan tidak sesuai.');
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
} catch (Exception $e) {
    set_flash('danger', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}
