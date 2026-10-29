<?php
/**
 * Master Data Jenis Kendaraan
 * Bandara Malikussaleh (LSW)
 * Kementerian Perhubungan Republik Indonesia
 */
$page_title = 'Master Jenis Kendaraan';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['admin']);

$pdo = getDB();

// 1. Tambah Jenis Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_jenis'])) {
    $nama = trim($_POST['nama_jenis'] ?? '');
    $kode = strtoupper(trim($_POST['kode_kategori'] ?? ''));
    $icon = trim($_POST['icon_class'] ?? 'fa-solid fa-car');
    $urutan = (int)($_POST['urutan'] ?? 1);

    if (empty($nama) || empty($kode)) {
        set_flash('danger', 'Nama jenis dan kode kategori wajib diisi.');
    } else {
        try {
            // Cek keunikan kode kategori
            $stmt_cek = $pdo->prepare("SELECT COUNT(*) FROM jenis_kendaraan WHERE kode_kategori = ?");
            $stmt_cek->execute([$kode]);
            if ($stmt_cek->fetchColumn() > 0) {
                set_flash('danger', "Kode kategori <strong>{$kode}</strong> sudah terdaftar.");
            } else {
                $stmt = $pdo->prepare("INSERT INTO jenis_kendaraan (nama_jenis, kode_kategori, icon_class, urutan, status) VALUES (?, ?, ?, ?, 'aktif')");
                $stmt->execute([$nama, $kode, $icon, $urutan]);

                log_aktivitas($pdo, "Menambahkan jenis kendaraan baru: {$nama} ({$kode})", 'master');
                set_flash('success', "Jenis kendaraan <strong>" . e($nama) . "</strong> berhasil ditambahkan.");
                header('Location: ' . BASE_URL . '/admin/jenis-kendaraan.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal menambahkan jenis kendaraan: ' . $e->getMessage());
        }
    }
}

// 2. Edit Jenis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_jenis'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama_jenis'] ?? '');
    $kode = strtoupper(trim($_POST['kode_kategori'] ?? ''));
    $icon = trim($_POST['icon_class'] ?? 'fa-solid fa-car');
    $urutan = (int)($_POST['urutan'] ?? 1);
    $status = trim($_POST['status'] ?? 'aktif');

    if ($id <= 0 || empty($nama) || empty($kode)) {
        set_flash('danger', 'Data tidak valid.');
    } else {
        try {
            // Cek kode kategori tidak duplikat dengan record lain
            $stmt_cek = $pdo->prepare("SELECT COUNT(*) FROM jenis_kendaraan WHERE kode_kategori = ? AND id != ?");
            $stmt_cek->execute([$kode, $id]);
            if ($stmt_cek->fetchColumn() > 0) {
                set_flash('danger', "Kode kategori <strong>{$kode}</strong> sudah dipakai jenis lain.");
            } else {
                $stmt = $pdo->prepare("UPDATE jenis_kendaraan SET nama_jenis = ?, kode_kategori = ?, icon_class = ?, urutan = ?, status = ? WHERE id = ?");
                $stmt->execute([$nama, $kode, $icon, $urutan, $status, $id]);

                log_aktivitas($pdo, "Memperbarui jenis kendaraan ID: {$id} ({$nama})", 'master');
                set_flash('success', "Jenis kendaraan <strong>" . e($nama) . "</strong> berhasil diperbarui.");
                header('Location: ' . BASE_URL . '/admin/jenis-kendaraan.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal memperbarui jenis kendaraan: ' . $e->getMessage());
        }
    }
}

// 3. Toggle Status (Aktif / Nonaktif)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status_jenis'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        set_flash('danger', 'ID jenis kendaraan tidak valid.');
    } else {
        try {
            $stmt_get = $pdo->prepare("SELECT nama_jenis, status FROM jenis_kendaraan WHERE id = ?");
            $stmt_get->execute([$id]);
            $j = $stmt_get->fetch();

            if ($j) {
                $new_status = ($j['status'] === 'aktif') ? 'nonaktif' : 'aktif';
                $stmt_up = $pdo->prepare("UPDATE jenis_kendaraan SET status = ? WHERE id = ?");
                $stmt_up->execute([$new_status, $id]);

                log_aktivitas($pdo, "Mengubah status jenis kendaraan {$j['nama_jenis']} menjadi {$new_status}", 'master');
                set_flash('success', "Status jenis <strong>" . e($j['nama_jenis']) . "</strong> berhasil diubah menjadi <strong>" . ucfirst($new_status) . "</strong>.");
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal mengubah status: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . '/admin/jenis-kendaraan.php');
    exit;
}

// 4. Hapus Jenis Kendaraan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_jenis'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        set_flash('danger', 'ID jenis kendaraan tidak valid.');
    } else {
        try {
            $stmt_j = $pdo->prepare("SELECT nama_jenis, kode_kategori FROM jenis_kendaraan WHERE id = ?");
            $stmt_j->execute([$id]);
            $j = $stmt_j->fetch();

            if (!$j) {
                set_flash('danger', 'Jenis kendaraan tidak ditemukan.');
            } else {
                // Cek relasi transaksi parkir
                $stmt_p = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE jenis_kendaraan_id = ?");
                $stmt_p->execute([$id]);
                $count_p = (int)$stmt_p->fetchColumn();

                // Cek relasi tracking
                $count_t = 0;
                try {
                    $stmt_t = $pdo->prepare("SELECT COUNT(*) FROM tracking_kendaraan WHERE jenis_kendaraan_id = ?");
                    $stmt_t->execute([$id]);
                    $count_t = (int)$stmt_t->fetchColumn();
                } catch (Exception $ignore) {}

                $total_usage = $count_p + $count_t;

                if ($total_usage > 0) {
                    // Nonaktifkan otomatis agar histori transaksi tetap terjaga
                    $stmt_dis = $pdo->prepare("UPDATE jenis_kendaraan SET status = 'nonaktif' WHERE id = ?");
                    $stmt_dis->execute([$id]);

                    log_aktivitas($pdo, "Menonaktifkan jenis kendaraan {$j['nama_jenis']} ({$j['kode_kategori']}) karena memiliki {$total_usage} data terkait", 'master');
                    set_flash('warning', "Jenis kendaraan <strong>" . e($j['nama_jenis']) . "</strong> memiliki <strong>{$total_usage} data transaksi/tracking terkait</strong>. Demi integritas data, sistem <strong>menonaktifkan</strong> statusnya (tidak dihapus permanen).");
                } else {
                    $stmt_del = $pdo->prepare("DELETE FROM jenis_kendaraan WHERE id = ?");
                    $stmt_del->execute([$id]);

                    log_aktivitas($pdo, "Menghapus jenis kendaraan {$j['nama_jenis']} ({$j['kode_kategori']})", 'master');
                    set_flash('success', "Jenis kendaraan <strong>" . e($j['nama_jenis']) . "</strong> berhasil dihapus permanen.");
                }
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal menghapus jenis kendaraan: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . '/admin/jenis-kendaraan.php');
    exit;
}

// Ambil daftar jenis kendaraan beserta jumlah transaksi
$stmt_list = $pdo->query("
    SELECT j.*, COUNT(p.id) as total_transaksi
    FROM jenis_kendaraan j
    LEFT JOIN parkir p ON j.id = p.jenis_kendaraan_id
    GROUP BY j.id
    ORDER BY j.urutan ASC
");
$jenis_list = $stmt_list->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-car-tunnel text-primary me-2"></i> Master Data Jenis Kendaraan
        </h1>
        <div class="page-header-subtitle">
            Klasifikasi jenis kendaraan yang diizinkan memasuki area parkir <?= AIRPORT_NAME ?>
        </div>
    </div>
    <button type="button" class="btn btn-primary shadow-sm" style="background: #0b2e59; border-color: #0b2e59;" data-bs-toggle="modal" data-bs-target="#modalTambahJenis">
        <i class="fa-solid fa-plus-circle me-1 text-warning"></i> Tambah Jenis Kendaraan
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover table-kemenhub align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 70px;" class="text-center">Urutan</th>
                    <th style="width: 60px;">Icon</th>
                    <th>Nama Jenis Kendaraan</th>
                    <th>Kode Kategori</th>
                    <th>Total Transaksi Terdata</th>
                    <th>Status</th>
                    <th class="text-center" style="width: 170px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jenis_list as $j): ?>
                    <tr>
                        <td class="text-center fw-bold text-muted"><?= $j['urutan'] ?></td>
                        <td>
                            <div class="bg-light rounded p-2 text-center d-inline-block border" style="width: 42px;">
                                <i class="<?= e($j['icon_class']) ?> fa-lg text-primary"></i>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($j['nama_jenis']) ?></div>
                        </td>
                        <td>
                            <span class="badge bg-secondary font-monospace"><?= e($j['kode_kategori']) ?></span>
                        </td>
                        <td>
                            <span class="fw-semibold text-primary">
                                <i class="fa-solid fa-car me-1"></i> <?= number_format($j['total_transaksi']) ?> Transaksi
                            </span>
                        </td>
                        <td>
                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('Ubah status <?= e($j['nama_jenis']) ?>?');">
                                <input type="hidden" name="id" value="<?= $j['id'] ?>">
                                <input type="hidden" name="toggle_status_jenis" value="1">
                                <?php if ($j['status'] === 'aktif'): ?>
                                    <button type="submit" class="btn btn-sm btn-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0" style="font-size: 0.75rem;" title="Klik untuk Nonaktifkan">
                                        <i class="fa-solid fa-circle-check me-1"></i> Aktif
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-0" style="font-size: 0.75rem;" title="Klik untuk Aktifkan">
                                        <i class="fa-solid fa-circle-xmark me-1"></i> Nonaktif
                                    </button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary btn-edit-jenis"
                                    data-id="<?= $j['id'] ?>"
                                    data-nama="<?= e($j['nama_jenis']) ?>"
                                    data-kode="<?= e($j['kode_kategori']) ?>"
                                    data-icon="<?= e($j['icon_class']) ?>"
                                    data-urutan="<?= $j['urutan'] ?>"
                                    data-status="<?= e($j['status']) ?>"
                                    title="Edit Jenis Kendaraan">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-hapus-jenis"
                                    data-id="<?= $j['id'] ?>"
                                    data-nama="<?= e($j['nama_jenis']) ?>"
                                    data-kode="<?= e($j['kode_kategori']) ?>"
                                    data-trx="<?= (int)$j['total_transaksi'] ?>"
                                    title="Hapus Jenis Kendaraan">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Jenis -->
<div class="modal fade" id="modalTambahJenis" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white" style="background: #0b2e59 !important; border-bottom: 3px solid #f59e0b;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle text-warning me-2"></i> Tambah Jenis Kendaraan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Jenis Kendaraan <span class="text-danger">*</span></label>
                        <input type="text" name="nama_jenis" class="form-control" placeholder="Contoh: Sepeda Listrik / VIP" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Kode Kategori <span class="text-danger">*</span></label>
                            <input type="text" name="kode_kategori" class="form-control text-uppercase" placeholder="Contoh: VIP" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">No. Urut Tampil</label>
                            <input type="number" name="urutan" class="form-control" value="<?= count($jenis_list) + 1 ?>" min="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Icon FontAwesome</label>
                        <input type="text" name="icon_class" class="form-control" value="fa-solid fa-car">
                        <small class="text-muted">Contoh: <code>fa-solid fa-motorcycle</code>, <code>fa-solid fa-car-side</code>, <code>fa-solid fa-bus</code>, <code>fa-solid fa-truck</code></small>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_jenis" value="1" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
                        <i class="fa-solid fa-check me-1"></i> Simpan Jenis Kendaraan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Jenis -->
<div class="modal fade" id="modalEditJenis" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white" style="background: #0b2e59 !important; border-bottom: 3px solid #f59e0b;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square text-warning me-2"></i> Edit Jenis Kendaraan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Jenis Kendaraan <span class="text-danger">*</span></label>
                        <input type="text" name="nama_jenis" id="edit_nama" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Kode Kategori <span class="text-danger">*</span></label>
                            <input type="text" name="kode_kategori" id="edit_kode" class="form-control text-uppercase" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Urutan</label>
                            <input type="number" name="urutan" id="edit_urutan" class="form-control" min="1">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label small fw-bold">Icon FontAwesome</label>
                            <input type="text" name="icon_class" id="edit_icon" class="form-control" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_jenis" value="1" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus Jenis -->
<div class="modal fade" id="modalHapusJenis" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i> Konfirmasi Hapus Jenis Kendaraan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="hapus_id">
                <div class="modal-body p-4">
                    <p class="mb-2">Apakah Anda yakin ingin menghapus jenis kendaraan berikut?</p>
                    <div class="alert alert-light border py-2 mb-3">
                        <div class="fw-bold text-dark" id="hapus_nama">-</div>
                        <div class="small text-muted">Kode: <code id="hapus_kode">-</code></div>
                    </div>
                    <div id="hapus_trx_box" class="alert alert-warning small d-none mb-0">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Jenis kendaraan ini sudah memiliki <strong id="hapus_trx_count">0</strong> data transaksi/tracking. Sistem akan secara otomatis <strong>menonaktifkan</strong> statusnya demi integritas data laporan bandara.
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="hapus_jenis" value="1" class="btn btn-danger">
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
    // Edit Modal
    const modalEdit = new bootstrap.Modal(document.getElementById('modalEditJenis'));
    document.querySelectorAll('.btn-edit-jenis').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_nama').value = this.getAttribute('data-nama');
            document.getElementById('edit_kode').value = this.getAttribute('data-kode');
            document.getElementById('edit_icon').value = this.getAttribute('data-icon');
            document.getElementById('edit_urutan').value = this.getAttribute('data-urutan');
            document.getElementById('edit_status').value = this.getAttribute('data-status');
            modalEdit.show();
        });
    });

    // Hapus Modal
    const modalHapus = new bootstrap.Modal(document.getElementById('modalHapusJenis'));
    document.querySelectorAll('.btn-hapus-jenis').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            const kode = this.getAttribute('data-kode');
            const trx = parseInt(this.getAttribute('data-trx') || 0);

            document.getElementById('hapus_id').value = id;
            document.getElementById('hapus_nama').textContent = nama;
            document.getElementById('hapus_kode').textContent = kode;

            const box = document.getElementById('hapus_trx_box');
            if (trx > 0) {
                document.getElementById('hapus_trx_count').textContent = trx;
                box.classList.remove('d-none');
            } else {
                box.classList.add('d-none');
            }
            modalHapus.show();
        });
    });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
