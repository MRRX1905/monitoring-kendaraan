<?php
/**
 * Manajemen Pengguna Sistem (Users)
 * Bandara Malikussaleh (LSW)
 * Kementerian Perhubungan Republik Indonesia
 */
$page_title = 'Manajemen Pengguna';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['admin']);

$pdo = getDB();

// 1. Tambah Pengguna Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_user'])) {
    $nama = trim($_POST['nama'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? 'petugas');
    $nip = trim($_POST['nip_nik'] ?? '');
    $hp = trim($_POST['no_hp'] ?? '');

    if (empty($nama) || empty($username) || empty($password)) {
        set_flash('danger', 'Nama, username, dan password wajib diisi.');
    } elseif (strlen($password) < 6) {
        set_flash('danger', 'Password minimal 6 karakter.');
    } else {
        try {
            // Cek apakah username sudah ada
            $stmt_cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt_cek->execute([$username]);
            if ($stmt_cek->fetchColumn() > 0) {
                set_flash('danger', 'Username sudah digunakan oleh akun lain. Gunakan username berbeda.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt_insert = $pdo->prepare("INSERT INTO users (nama, username, password, role, nip_nik, no_hp, status) VALUES (?, ?, ?, ?, ?, ?, 'aktif')");
                $stmt_insert->execute([$nama, $username, $hash, $role, $nip ?: null, $hp ?: null]);

                log_aktivitas($pdo, "Menambahkan pengguna baru: {$nama} ({$username}) role {$role}", 'master');
                set_flash('success', "Akun pengguna <strong>" . e($nama) . "</strong> berhasil dibuat.");
                header('Location: ' . BASE_URL . '/admin/users.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal menambahkan akun: ' . $e->getMessage());
        }
    }
}

// 2. Edit Pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $role = trim($_POST['role'] ?? 'petugas');
    $nip = trim($_POST['nip_nik'] ?? '');
    $hp = trim($_POST['no_hp'] ?? '');
    $status = trim($_POST['status'] ?? 'aktif');

    if ($id <= 0 || empty($nama) || empty($username)) {
        set_flash('danger', 'Data tidak valid. Nama dan username wajib diisi.');
    } else {
        try {
            // Cek keunikan username untuk akun lain
            $stmt_cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
            $stmt_cek->execute([$username, $id]);
            if ($stmt_cek->fetchColumn() > 0) {
                set_flash('danger', 'Username sudah dipakai oleh pengguna lain.');
            } else {
                $self_note = '';
                // Perlindungan jika mengedit akun sendiri yang sedang aktif
                if ($id === (int)$_SESSION['user_id']) {
                    if ($role !== 'admin') {
                        $role = 'admin';
                        $self_note = ' (Role Anda tetap Admin demi keamanan sistem)';
                    }
                    if ($status !== 'aktif') {
                        $status = 'aktif';
                        $self_note = ' (Status Anda tetap Aktif demi keamanan sistem)';
                    }
                    $_SESSION['user_nama'] = $nama;
                    $_SESSION['user_username'] = $username;
                } else {
                    // Jika menonaktifkan admin lain, pastikan masih ada minimal 1 admin aktif
                    if ($status === 'nonaktif' || $role !== 'admin') {
                        $stmt_adm = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                        $stmt_adm->execute([$id]);
                        $curr_role = $stmt_adm->fetchColumn();
                        if ($curr_role === 'admin') {
                            $active_admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'aktif' AND id != $id")->fetchColumn();
                            if ($active_admins < 1) {
                                set_flash('danger', 'Tidak dapat mengubah role/menonaktifkan admin terakhir yang aktif.');
                                header('Location: ' . BASE_URL . '/admin/users.php');
                                exit;
                            }
                        }
                    }
                }

                $stmt_update = $pdo->prepare("UPDATE users SET nama = ?, username = ?, role = ?, nip_nik = ?, no_hp = ?, status = ? WHERE id = ?");
                $stmt_update->execute([$nama, $username, $role, $nip ?: null, $hp ?: null, $status, $id]);

                log_aktivitas($pdo, "Memperbarui profil pengguna ID: {$id} ({$nama} / {$username})", 'master');
                set_flash('success', "Data pengguna <strong>" . e($nama) . "</strong> berhasil diperbarui.{$self_note}");
                header('Location: ' . BASE_URL . '/admin/users.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal memperbarui pengguna: ' . $e->getMessage());
        }
    }
}

// 3. Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $id = (int)($_POST['id'] ?? 0);
    $new_pass = trim($_POST['new_password'] ?? '');

    if ($id <= 0 || empty($new_pass)) {
        set_flash('danger', 'Password baru tidak boleh kosong.');
    } elseif (strlen($new_pass) < 6) {
        set_flash('danger', 'Password baru minimal 6 karakter.');
    } else {
        try {
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt_pw = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_pw->execute([$hash, $id]);

            log_aktivitas($pdo, "Mereset password pengguna ID: {$id}", 'master');
            set_flash('success', "Password pengguna berhasil diperbarui.");
            header('Location: ' . BASE_URL . '/admin/users.php');
            exit;
        } catch (Exception $e) {
            set_flash('danger', 'Gagal mereset password: ' . $e->getMessage());
        }
    }
}

// 4. Toggle Status (Aktif / Nonaktif Langsung)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        set_flash('danger', 'ID pengguna tidak valid.');
    } elseif ($id === (int)$_SESSION['user_id']) {
        set_flash('danger', 'Anda tidak dapat menonaktifkan akun yang sedang Anda gunakan.');
    } else {
        try {
            $stmt_u = $pdo->prepare("SELECT nama, role, status FROM users WHERE id = ?");
            $stmt_u->execute([$id]);
            $u = $stmt_u->fetch();

            if ($u) {
                $new_status = ($u['status'] === 'aktif') ? 'nonaktif' : 'aktif';
                if ($u['role'] === 'admin' && $new_status === 'nonaktif') {
                    $active_admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'aktif' AND id != $id")->fetchColumn();
                    if ($active_admins < 1) {
                        set_flash('danger', 'Tidak dapat menonaktifkan akun admin terakhir yang aktif.');
                        header('Location: ' . BASE_URL . '/admin/users.php');
                        exit;
                    }
                }

                $stmt_tog = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt_tog->execute([$new_status, $id]);

                log_aktivitas($pdo, "Mengubah status pengguna {$u['nama']} menjadi {$new_status}", 'master');
                set_flash('success', "Status akun <strong>" . e($u['nama']) . "</strong> berhasil diubah menjadi <strong>" . ucfirst($new_status) . "</strong>.");
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal mengubah status: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

// 5. Hapus Pengguna (Delete Akun)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_user'])) {
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        set_flash('danger', 'ID pengguna tidak valid.');
    } elseif ($id === (int)$_SESSION['user_id']) {
        set_flash('danger', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif login.');
    } else {
        try {
            $stmt_target = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt_target->execute([$id]);
            $target = $stmt_target->fetch();

            if (!$target) {
                set_flash('danger', 'Pengguna tidak ditemukan.');
            } else {
                // Cegah menghapus admin jika hanya tersisa 1 admin
                if ($target['role'] === 'admin') {
                    $admin_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != $id")->fetchColumn();
                    if ($admin_count < 1) {
                        set_flash('danger', 'Tidak dapat menghapus admin utama atau satu-satunya admin sistem.');
                        header('Location: ' . BASE_URL . '/admin/users.php');
                        exit;
                    }
                }

                // Cek riwayat transaksi parkir
                $stmt_cek_trx = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE petugas_masuk_id = ? OR petugas_keluar_id = ?");
                $stmt_cek_trx->execute([$id, $id]);
                $total_trx = (int)$stmt_cek_trx->fetchColumn();

                if ($total_trx > 0) {
                    // Lindungi audit trail: nonaktifkan akun secara aman
                    $stmt_deact = $pdo->prepare("UPDATE users SET status = 'nonaktif' WHERE id = ?");
                    $stmt_deact->execute([$id]);

                    log_aktivitas($pdo, "Menonaktifkan pengguna {$target['nama']} ({$target['username']}) - memiliki {$total_trx} transaksi parkir", 'master');
                    set_flash('warning', "Pengguna <strong>" . e($target['nama']) . "</strong> memiliki <strong>{$total_trx} data riwayat transaksi parkir</strong>. Demi integritas audit trail, akun <strong>dinonaktifkan</strong> secara aman (tidak dihapus permanen).");
                } else {
                    // Hapus permanen
                    $stmt_del = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt_del->execute([$id]);

                    log_aktivitas($pdo, "Menghapus akun pengguna: {$target['nama']} ({$target['username']})", 'master');
                    set_flash('success', "Akun pengguna <strong>" . e($target['nama']) . "</strong> ({$target['username']}) berhasil dihapus permanen.");
                }
                header('Location: ' . BASE_URL . '/admin/users.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal menghapus pengguna: ' . $e->getMessage());
        }
    }
}

// Ambil daftar pengguna beserta statistik transaksi yang pernah ditangani
$stmt_users = $pdo->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM parkir WHERE petugas_masuk_id = u.id OR petugas_keluar_id = u.id) as total_transaksi
    FROM users u
    ORDER BY u.role ASC, u.nama ASC
");
$users = $stmt_users->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-users-gear text-primary me-2"></i> Manajemen Akun Pengguna
        </h1>
        <div class="page-header-subtitle">
            Kelola data akun petugas pos, supervisor operasional, dan administrator sistem <?= AIRPORT_NAME ?>
        </div>
    </div>
    <button type="button" class="btn btn-primary shadow-sm" style="background: #0b2e59; border-color: #0b2e59;" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
        <i class="fa-solid fa-user-plus me-1 text-warning"></i> Tambah Pengguna Baru
    </button>
</div>

<!-- Kartu Ringkasan Akun -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center">
                <div class="bg-primary-subtle text-primary p-3 rounded-circle me-3">
                    <i class="fa-solid fa-users fa-xl"></i>
                </div>
                <div>
                    <div class="small text-muted">Total Pengguna</div>
                    <div class="fs-4 fw-bold text-dark"><?= count($users) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center">
                <div class="bg-success-subtle text-success p-3 rounded-circle me-3">
                    <i class="fa-solid fa-user-check fa-xl"></i>
                </div>
                <div>
                    <div class="small text-muted">Akun Aktif</div>
                    <div class="fs-4 fw-bold text-success">
                        <?= count(array_filter($users, fn($x) => $x['status'] === 'aktif')) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center">
                <div class="bg-danger-subtle text-danger p-3 rounded-circle me-3">
                    <i class="fa-solid fa-user-shield fa-xl"></i>
                </div>
                <div>
                    <div class="small text-muted">Administrator</div>
                    <div class="fs-4 fw-bold text-danger">
                        <?= count(array_filter($users, fn($x) => $x['role'] === 'admin')) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center">
                <div class="bg-info-subtle text-info p-3 rounded-circle me-3">
                    <i class="fa-solid fa-id-badge fa-xl"></i>
                </div>
                <div>
                    <div class="small text-muted">Petugas Pos</div>
                    <div class="fs-4 fw-bold text-info">
                        <?= count(array_filter($users, fn($x) => $x['role'] === 'petugas')) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover table-kemenhub align-middle mb-0">
            <thead>
                <tr>
                    <th>Nama Petugas</th>
                    <th>Username</th>
                    <th>NIP / NIK</th>
                    <th>No. Telepon / HP</th>
                    <th>Role</th>
                    <th>Aktivitas Transaksi</th>
                    <th>Status</th>
                    <th>Login Terakhir</th>
                    <th class="text-center" style="width: 170px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): 
                    $is_self = ((int)$u['id'] === (int)$_SESSION['user_id']);
                ?>
                    <tr class="<?= $is_self ? 'table-light' : '' ?>">
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 36px; height: 36px; font-size: 0.85rem; background: #0b2e59 !important;">
                                    <?= strtoupper(substr($u['nama'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                        <?= e($u['nama']) ?>
                                        <?php if ($is_self): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.65rem;">Anda</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">ID: #<?= $u['id'] ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <code><?= e($u['username']) ?></code>
                        </td>
                        <td><?= e($u['nip_nik'] ?? '-') ?></td>
                        <td><?= e($u['no_hp'] ?? '-') ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge bg-danger"><i class="fa-solid fa-shield-halved me-1"></i> Admin</span>
                            <?php elseif ($u['role'] === 'supervisor'): ?>
                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-user-tie me-1"></i> Supervisor</span>
                            <?php else: ?>
                                <span class="badge bg-primary"><i class="fa-solid fa-desktop me-1"></i> Petugas Pos</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <i class="fa-solid fa-receipt me-1 text-primary"></i> <?= number_format($u['total_transaksi']) ?> Transaksi
                            </span>
                        </td>
                        <td>
                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('Ubah status akun <?= e($u['nama']) ?> menjadi <?= $u['status'] === 'aktif' ? 'Nonaktif' : 'Aktif' ?>?');">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="toggle_status" value="1">
                                <?php if ($u['status'] === 'aktif'): ?>
                                    <button type="submit" class="btn btn-sm btn-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0" style="font-size: 0.75rem;" <?= $is_self ? 'disabled title="Akun Anda sendiri tidak dapat dinonaktifkan"' : 'title="Klik untuk Nonaktifkan"' ?>>
                                        <i class="fa-solid fa-circle-check me-1"></i> Aktif
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-0" style="font-size: 0.75rem;" title="Klik untuk Aktifkan">
                                        <i class="fa-solid fa-circle-xmark me-1"></i> Nonaktif
                                    </button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td>
                            <small class="text-muted"><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'Belum pernah' ?></small>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary btn-edit-user"
                                    data-id="<?= $u['id'] ?>"
                                    data-nama="<?= e($u['nama']) ?>"
                                    data-username="<?= e($u['username']) ?>"
                                    data-role="<?= e($u['role']) ?>"
                                    data-nip="<?= e($u['nip_nik'] ?? '') ?>"
                                    data-hp="<?= e($u['no_hp'] ?? '') ?>"
                                    data-status="<?= e($u['status']) ?>"
                                    title="Edit Profil Pengguna">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button type="button" class="btn btn-outline-warning text-dark btn-pw-user"
                                    data-id="<?= $u['id'] ?>"
                                    data-nama="<?= e($u['nama']) ?>"
                                    title="Ganti Kata Sandi">
                                    <i class="fa-solid fa-key"></i>
                                </button>
                                <?php if (!$is_self): ?>
                                    <button type="button" class="btn btn-outline-danger btn-hapus-user"
                                        data-id="<?= $u['id'] ?>"
                                        data-nama="<?= e($u['nama']) ?>"
                                        data-username="<?= e($u['username']) ?>"
                                        data-trx="<?= (int)$u['total_transaksi'] ?>"
                                        title="Hapus Akun Pengguna">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-secondary disabled" title="Tidak dapat menghapus akun Anda sendiri">
                                        <i class="fa-solid fa-ban"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah User -->
<div class="modal fade" id="modalTambahUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white" style="background: #0b2e59 !important; border-bottom: 3px solid #f59e0b;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus text-warning me-2"></i> Tambah Pengguna Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control" placeholder="Contoh: Rahmat Hidayat" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="Contoh: rahmat" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Role / Hak Akses <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="petugas">Petugas Pos (Operasional Masuk & Keluar)</option>
                            <option value="supervisor">Supervisor (Monitoring & Laporan)</option>
                            <option value="admin">Administrator (Akses Penuh)</option>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold">NIP / NIK</label>
                            <input type="text" name="nip_nik" class="form-control" placeholder="Opsional">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Nomor WhatsApp / HP</label>
                            <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxx">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_user" value="1" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
                        <i class="fa-solid fa-check me-1"></i> Simpan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="modalEditUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white" style="background: #0b2e59 !important; border-bottom: 3px solid #f59e0b;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-pen text-warning me-2"></i> Edit Data Pengguna</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="edit_nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Role Hak Akses</label>
                            <select name="role" id="edit_role" class="form-select">
                                <option value="petugas">Petugas Pos</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Status Akun</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold">NIP / NIK</label>
                            <input type="text" name="nip_nik" id="edit_nip" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Nomor HP</label>
                            <input type="text" name="no_hp" id="edit_hp" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_user" value="1" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ganti Password -->
<div class="modal fade" id="modalPasswordUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-warning text-dark py-3">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-key me-2"></i> Reset Password Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="pw_id">
                <div class="modal-body p-4">
                    <p class="text-muted small">Atur ulang kata sandi untuk akun <strong id="pw_nama" class="text-dark"></strong>:</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Password Baru <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="reset_password" value="1" class="btn btn-warning fw-bold text-dark">
                        <i class="fa-solid fa-lock me-1"></i> Perbarui Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus User -->
<div class="modal fade" id="modalHapusUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i> Konfirmasi Hapus Akun</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="hapus_id">
                <div class="modal-body p-4">
                    <p class="mb-2">Apakah Anda yakin ingin menghapus akun pengguna berikut?</p>
                    <div class="alert alert-light border py-2 mb-3">
                        <div class="fw-bold text-dark" id="hapus_nama">-</div>
                        <div class="small text-muted">Username: <code id="hapus_username">-</code></div>
                    </div>
                    <div id="hapus_trx_warning" class="alert alert-warning small d-none mb-0">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Pengguna ini memiliki <strong id="hapus_trx_count">0</strong> riwayat transaksi parkir. Sistem akan secara otomatis mengamankan audit trail dengan <strong>menonaktifkan akun</strong> (bukan menghapus data riwayat transaksi).
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="hapus_user" value="1" class="btn btn-danger">
                        <i class="fa-solid fa-trash-can me-1"></i> Ya, Hapus / Nonaktifkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Bootstrap Bundle dimuat oleh footer; tunggu seluruh dokumen siap sebelum membuat modal.
    document.addEventListener('DOMContentLoaded', function () {
    // Edit User Modal
    const modalEdit = new bootstrap.Modal(document.getElementById('modalEditUser'));
    document.querySelectorAll('.btn-edit-user').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_nama').value = this.getAttribute('data-nama');
            document.getElementById('edit_username').value = this.getAttribute('data-username');
            document.getElementById('edit_role').value = this.getAttribute('data-role');
            document.getElementById('edit_nip').value = this.getAttribute('data-nip');
            document.getElementById('edit_hp').value = this.getAttribute('data-hp');
            document.getElementById('edit_status').value = this.getAttribute('data-status');
            modalEdit.show();
        });
    });

    // Password User Modal
    const modalPw = new bootstrap.Modal(document.getElementById('modalPasswordUser'));
    document.querySelectorAll('.btn-pw-user').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('pw_id').value = this.getAttribute('data-id');
            document.getElementById('pw_nama').textContent = this.getAttribute('data-nama');
            modalPw.show();
        });
    });

    // Hapus User Modal
    const modalHapus = new bootstrap.Modal(document.getElementById('modalHapusUser'));
    document.querySelectorAll('.btn-hapus-user').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            const username = this.getAttribute('data-username');
            const trx = parseInt(this.getAttribute('data-trx') || 0);

            document.getElementById('hapus_id').value = id;
            document.getElementById('hapus_nama').textContent = nama;
            document.getElementById('hapus_username').textContent = username;

            const warnBox = document.getElementById('hapus_trx_warning');
            if (trx > 0) {
                document.getElementById('hapus_trx_count').textContent = trx;
                warnBox.classList.remove('d-none');
            } else {
                warnBox.classList.add('d-none');
            }
            modalHapus.show();
        });
    });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
