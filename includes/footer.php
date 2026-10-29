<?php
/**
 * Footer Layout Sistem & Navigasi Mobile Responsif
 */
$user_role = $_SESSION['user_role'] ?? 'petugas';
$current_page = basename($_SERVER['PHP_SELF']);
?>
    </main>
</div>

<!-- Navigasi Bawah Khusus Smartphone / Tablet (Mobile Bottom Nav) -->
<?php if (isset($_SESSION['user_id'])): ?>
    <nav class="mobile-bottom-nav d-lg-none">
        <?php if ($user_role === 'admin'): ?>
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="mobile-nav-item <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Beranda</span>
            </a>
            <a href="<?= BASE_URL ?>/petugas/masuk.php" class="mobile-nav-item mobile-nav-btn-highlight <?= ($current_page === 'masuk.php') ? 'active' : '' ?>">
                <div class="mobile-nav-circle">
                    <i class="fa-solid fa-plus"></i>
                </div>
                <span>Masuk</span>
            </a>
            <a href="<?= BASE_URL ?>/petugas/keluar.php" class="mobile-nav-item <?= ($current_page === 'keluar.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Keluar</span>
            </a>
            <a href="<?= BASE_URL ?>/petugas/data-parkir.php" class="mobile-nav-item <?= ($current_page === 'data-parkir.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-list-check"></i>
                <span>Riwayat</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/users.php" class="mobile-nav-item <?= ($current_page === 'users.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-users"></i>
                <span>Petugas</span>
            </a>
        <?php elseif ($user_role === 'supervisor'): ?>
            <a href="<?= BASE_URL ?>/supervisor/dashboard.php" class="mobile-nav-item <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Monitoring</span>
            </a>
            <a href="<?= BASE_URL ?>/petugas/keluar.php" class="mobile-nav-item <?= ($current_page === 'keluar.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-car"></i>
                <span>Area Parkir</span>
            </a>
            <a href="<?= BASE_URL ?>/petugas/data-parkir.php" class="mobile-nav-item <?= ($current_page === 'data-parkir.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Riwayat</span>
            </a>
            <a href="<?= BASE_URL ?>/supervisor/laporan.php" class="mobile-nav-item <?= ($current_page === 'laporan.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-file-invoice"></i>
                <span>Laporan</span>
            </a>
            <a href="<?= BASE_URL ?>/auth/logout.php" onclick="return confirm('Keluar dari akun?');" class="mobile-nav-item text-danger">
                <i class="fa-solid fa-power-off text-danger"></i>
                <span>Keluar</span>
            </a>
        <?php else: ?>
            <!-- Role Petugas Pos Lapangan -->
            <a href="<?= BASE_URL ?>/petugas/dashboard.php" class="mobile-nav-item <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Beranda</span>
            </a>
            <a href="<?= BASE_URL ?>/petugas/keluar.php" class="mobile-nav-item <?= ($current_page === 'keluar.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-right-from-bracket text-warning"></i>
                <span>Keluar</span>
            </a>
            <!-- Tombol Tambah Masuk Menonjol di Tengah -->
            <a href="<?= BASE_URL ?>/petugas/masuk.php" class="mobile-nav-item mobile-nav-btn-highlight <?= ($current_page === 'masuk.php') ? 'active' : '' ?>">
                <div class="mobile-nav-circle">
                    <i class="fa-solid fa-plus"></i>
                </div>
                <span>Catat Masuk</span>
            </a>
            <a href="<?= BASE_URL ?>/petugas/data-parkir.php" class="mobile-nav-item <?= ($current_page === 'data-parkir.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-list-check"></i>
                <span>Riwayat</span>
            </a>
            <a href="<?= BASE_URL ?>/auth/logout.php" onclick="return confirm('Keluar dari sesi petugas?');" class="mobile-nav-item text-danger">
                <i class="fa-solid fa-power-off text-danger"></i>
                <span>Logout</span>
            </a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<!-- Footer Aplikasi (Desktop View) -->
<footer class="app-footer d-none d-lg-block">
    <div class="container-fluid px-4">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                <span>&copy; <?= date('Y') ?> <strong><?= INSTANSI_NAME ?></strong> &bull; <?= UPBU_NAME ?></span>
            </div>
            <div class="col-md-6 text-center text-md-end text-muted">
                <span><?= APP_NAME ?> v<?= APP_VERSION ?> (<?= AIRPORT_CODE ?>)</span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS (termasuk Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Live Clock Script -->
<script>
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const clockEl = document.getElementById('liveClock');
        if (clockEl) {
            clockEl.innerHTML = `<i class="fa-regular fa-clock me-1 text-warning"></i> ${hours}:${minutes}:${seconds} WIB`;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>

</body>
</html>
