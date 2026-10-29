<?php
/**
 * Halaman Login Sistem
 * Kemenhub - Sistem Monitoring Kendaraan Bandara Malikussaleh
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';

// Jika sudah login, redirect ke dashboard yang sesuai
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
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Petugas - <?= APP_NAME ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/logo-kemenhub.svg">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/logo-kemenhub.png">

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --kemenhub-navy: #0b2e59;
            --kemenhub-gold: #f59e0b;
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #071d38 0%, #0b2e59 40%, #15427d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            max-width: 460px;
            width: 100%;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .login-header {
            background: #0b2e59;
            color: #ffffff;
            padding: 30px 24px 26px;
            text-align: center;
            border-bottom: 4px solid var(--kemenhub-gold);
            position: relative;
        }
        .login-header::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: #ffffff;
        }
        .logo-circle {
            width: 64px;
            height: 64px;
            background: #ffffff;
            border: 2px solid var(--kemenhub-gold);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            padding: 8px;
        }
        .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .form-control:focus {
            border-color: #0b2e59;
            box-shadow: 0 0 0 3px rgba(11, 46, 89, 0.15);
        }
        .btn-kemenhub {
            background: #0b2e59;
            border-color: #0b2e59;
            color: #ffffff;
            font-weight: 600;
            padding: 12px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        .btn-kemenhub:hover {
            background: #082142;
            border-color: #082142;
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(11, 46, 89, 0.3);
        }
        .account-helper {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 12px;
            font-size: 0.78rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="logo-circle">
            <img src="<?= BASE_URL ?>/assets/img/logo-kemenhub.png" alt="Logo Kementerian Perhubungan RI">
        </div>
        <div class="small text-warning text-uppercase fw-bold" style="letter-spacing: 1px; font-size: 0.72rem;">
            <?= INSTANSI_NAME ?>
        </div>
        <h5 class="fw-bold mb-1 text-white"><?= APP_NAME ?></h5>
        <div class="small opacity-75"><?= UPBU_NAME ?> (<?= AIRPORT_CODE ?>)</div>
    </div>

    <div class="p-4 p-md-5">
        <?php render_flash(); ?>

        <form action="<?= BASE_URL ?>/auth/proses-login.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label small fw-bold text-dark">
                    <i class="fa-solid fa-user me-1 text-secondary"></i> Username Petugas
                </label>
                <input type="text" class="form-control form-control-lg fs-6" id="username" name="username" placeholder="Masukkan username" required autofocus autocomplete="username">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label small fw-bold text-dark">
                    <i class="fa-solid fa-lock me-1 text-secondary"></i> Password
                </label>
                <div class="input-group">
                    <input type="password" class="form-control form-control-lg fs-6" id="password" name="password" placeholder="Masukkan password" required autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="fa-solid fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <div class="d-grid gap-2 mb-4 pt-2">
                <button type="submit" class="btn btn-kemenhub">
                    <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Masuk Sistem Monitoring Kendaraan
                </button>
            </div>
        </form>

        <!-- Petunjuk Akun Bawaan (Memudahkan Pengujian) -->
        <div class="account-helper mb-3">
            <div class="fw-bold text-dark mb-1"><i class="fa-solid fa-key text-warning me-1"></i> Akun Bawaan Sistem:</div>
            <div class="d-flex justify-content-between mb-1">
                <span>Petugas Pos:</span>
                <code>petugas / petugas123</code>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span>Administrator:</span>
                <code>admin / admin123</code>
            </div>
            <div class="d-flex justify-content-between">
                <span>Supervisor:</span>
                <code>supervisor / supervisor123</code>
            </div>
        </div>

        <div class="text-center">
            <a href="<?= BASE_URL ?>/install.php" class="text-muted small text-decoration-none">
                <i class="fa-solid fa-wrench me-1"></i> Setup / Reset Database Sistem
            </a>
        </div>
    </div>
</div>

<script>
    // Toggle Password Visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');

    togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        toggleIcon.classList.toggle('fa-eye');
        toggleIcon.classList.toggle('fa-eye-slash');
    });
</script>

</body>
</html>
