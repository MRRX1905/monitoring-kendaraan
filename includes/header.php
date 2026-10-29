<?php
/**
 * Header Layout Sistem
 * Kemenhub & UPBU Malikussaleh
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/helpers.php';

$page_title = isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME . ' (LSW)';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/logo-kemenhub.svg">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/logo-kemenhub.png">

    <!-- Meta Tags -->
    <meta name="description" content="Sistem Informasi Monitoring Kendaraan Masuk dan Keluar Area Parkir Bandar Udara Malikussaleh (LSW) - Kementerian Perhubungan RI">
    <meta name="author" content="Kantor UPBU Kelas III Malikussaleh">

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Bootstrap Icons (BI) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Chart.js untuk Monitoring Visual -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- Tema Kemenhub Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/kemenhub-theme.css">
</head>
<body>

<!-- 1. Topbar Resmi Instansi Pemerintah -->
<div class="top-bar-instansi">
    <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
        <div>
            <i class="fa-solid fa-plane-departure text-warning me-2"></i>
            <span><?= INSTANSI_NAME ?> &bull; <strong><?= INSTANSI_SUB ?></strong></span>
        </div>
        <div class="d-none d-md-flex align-items-center gap-3">
            <span><i class="fa-solid fa-location-dot me-1 text-warning"></i> <?= AIRPORT_NAME ?> (<?= AIRPORT_CODE ?>)</span>
            <span>&bull;</span>
            <span id="liveClock" class="fw-semibold text-white"><i class="fa-regular fa-clock me-1 text-warning"></i> --:--:-- WIB</span>
        </div>
    </div>
</div>

<!-- 2. Navbar Utama Aplikasi -->
<?php require_once __DIR__ . '/navbar.php'; ?>

<!-- 3. Wrapper Layout Sidebar + Konten -->
<div class="app-wrapper">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="app-content">
        <?php render_flash(); ?>
