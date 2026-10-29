<?php
/**
 * Navbar Utama Aplikasi
 */
$user_nama = $_SESSION['user_nama'] ?? 'Pengguna';
$user_role = $_SESSION['user_role'] ?? 'petugas';

$role_badge_class = 'bg-primary';
$role_label = 'Petugas Pos';
if ($user_role === 'admin') {
    $role_badge_class = 'bg-danger';
    $role_label = 'Administrator';
} elseif ($user_role === 'supervisor') {
    $role_badge_class = 'bg-warning text-dark';
    $role_label = 'Supervisor';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark navbar-kemenhub sticky-top py-2 px-3 px-md-4">
    <div class="container-fluid p-0">
        <!-- Logo & Nama Sistem -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>">
            <div class="bg-white rounded-circle d-flex align-items-center justify-content-center shadow-sm p-1" style="width: 38px; height: 38px;">
                <img src="<?= BASE_URL ?>/assets/img/logo-kemenhub.png" alt="Logo Kementerian Perhubungan RI" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <div>
                <span class="d-block lh-1 text-white fw-bold"><?= APP_NAME ?></span>
                <span class="brand-subtitle"><?= UPBU_NAME ?></span>
            </div>
        </a>

        <!-- Tombol Mobile Toggle -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav ms-auto align-items-center gap-2 mt-3 mt-lg-0">
                <!-- Shortcut Cepat Operasional -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/petugas/masuk.php" class="btn btn-sm btn-outline-light rounded-pill px-3">
                        <i class="fa-solid fa-right-to-bracket text-warning me-1"></i> Catat Masuk
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/petugas/keluar.php" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold text-dark shadow-sm">
                        <i class="fa-solid fa-right-from-bracket me-1"></i> Catat Keluar
                    </a>
                </li>

                <!-- User Dropdown -->
                <li class="nav-item dropdown ms-lg-3">
                    <a class="nav-link dropdown-toggle text-white d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="rounded-circle bg-light text-dark fw-bold d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">
                            <?= strtoupper(substr($user_nama, 0, 1)) ?>
                        </div>
                        <div class="d-none d-sm-block text-start lh-sm">
                            <span class="fw-semibold text-white d-block"><?= e($user_nama) ?></span>
                            <span class="badge <?= $role_badge_class ?> py-0 px-2" style="font-size: 0.68rem;"><?= $role_label ?></span>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-bold text-dark"><?= e($user_nama) ?></div>
                            <div class="small text-muted">Role: <?= ucfirst($user_role) ?></div>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="<?= BASE_URL ?>/auth/logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">
                                <i class="fa-solid fa-arrow-right-from-bracket text-danger me-2"></i> Logout / Keluar
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
