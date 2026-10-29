<?php
/**
 * Data Transaksi Parkir Lengkap & Riwayat
 * Bandara Malikussaleh (LSW)
 */
$page_title = 'Data Transaksi Parkir';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['petugas', 'admin', 'supervisor']);

$pdo = getDB();

// 1. Handler Edit Transaksi (Koreksi salah input)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_transaksi'])) {
    $id = (int)($_POST['id'] ?? 0);
    $jenis_kendaraan_id = (int)($_POST['jenis_kendaraan_id'] ?? 0);
    $plat_nomor = trim($_POST['plat_nomor'] ?? '');
    $ciri_kendaraan = trim($_POST['ciri_kendaraan'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $waktu_masuk = trim($_POST['waktu_masuk'] ?? '');

    if ($id <= 0 || $jenis_kendaraan_id <= 0) {
        set_flash('danger', 'Data koreksi tidak valid.');
    } else {
        try {
            // Ambil data transaksi eksisting
            $stmt_old = $pdo->prepare("SELECT * FROM parkir WHERE id = ?");
            $stmt_old->execute([$id]);
            $old = $stmt_old->fetch();

            if (!$old) {
                set_flash('danger', 'Transaksi tidak ditemukan.');
            } else {
                $tanggal = $old['tanggal'];
                $datetime_masuk = $tanggal . ' ' . (strlen($waktu_masuk) == 5 ? $waktu_masuk . ':00' : $waktu_masuk);

                // Update data
                $stmt_up = $pdo->prepare("
                    UPDATE parkir SET 
                        jenis_kendaraan_id = ?,
                        plat_nomor = ?,
                        ciri_kendaraan = ?,
                        keterangan = ?,
                        waktu_masuk = ?,
                        datetime_masuk = ?
                    WHERE id = ?
                ");
                $stmt_up->execute([
                    $jenis_kendaraan_id,
                    $plat_nomor ? strtoupper($plat_nomor) : null,
                    $ciri_kendaraan ?: null,
                    $keterangan ?: null,
                    $waktu_masuk,
                    $datetime_masuk,
                    $id
                ]);

                log_aktivitas($pdo, "Koreksi data transaksi #{$id} ({$old['kode_transaksi']}) oleh " . $_SESSION['user_nama'], 'master');
                set_flash('success', "Transaksi <strong>{$old['kode_transaksi']}</strong> ({$old['nomor_karcis']}) berhasil diperbarui/dikoreksi.");
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal memperbarui transaksi: ' . $e->getMessage());
        }
    }
}

// 2. Handler Hapus Transaksi (Jika salah input / batal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_transaksi'])) {
    $id = (int)($_POST['id'] ?? 0);
    $alasan = trim($_POST['alasan_hapus'] ?? 'Salah input data');

    if ($id <= 0) {
        set_flash('danger', 'ID transaksi tidak valid.');
    } else {
        try {
            $stmt_check = $pdo->prepare("SELECT kode_transaksi, nomor_karcis FROM parkir WHERE id = ?");
            $stmt_check->execute([$id]);
            $trx = $stmt_check->fetch();

            if (!$trx) {
                set_flash('danger', 'Transaksi tidak ditemukan atau sudah dihapus.');
            } else {
                $stmt_del = $pdo->prepare("DELETE FROM parkir WHERE id = ?");
                $stmt_del->execute([$id]);

                log_aktivitas($pdo, "Menghapus transaksi {$trx['kode_transaksi']} ({$trx['nomor_karcis']}) - Alasan: {$alasan}", 'master');
                set_flash('success', "Transaksi <strong>{$trx['kode_transaksi']}</strong> ({$trx['nomor_karcis']}) berhasil dihapus dari sistem.");
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }
}

// Filter parameters
$filter_tanggal_mulai = trim($_GET['tgl_mulai'] ?? date('Y-m-d'));
$filter_tanggal_selesai = trim($_GET['tgl_selesai'] ?? date('Y-m-d'));
$filter_jenis = (int)($_GET['jenis_id'] ?? 0);
$filter_keyword = trim($_GET['q'] ?? '');

// Master jenis kendaraan untuk dropdown
$stmt_jenis = $pdo->query("SELECT * FROM jenis_kendaraan ORDER BY urutan ASC");
$jenis_options = $stmt_jenis->fetchAll();

// Query builder
$query = "
    SELECT p.*, j.nama_jenis, j.icon_class, j.warna_badge,
           u_masuk.nama as nama_petugas_masuk,
           u_keluar.nama as nama_petugas_keluar
    FROM parkir p
    JOIN jenis_kendaraan j ON p.jenis_kendaraan_id = j.id
    LEFT JOIN users u_masuk ON p.petugas_masuk_id = u_masuk.id
    LEFT JOIN users u_keluar ON p.petugas_keluar_id = u_keluar.id
    WHERE 1=1
";
$params = [];

if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) {
    $query .= " AND p.tanggal BETWEEN ? AND ?";
    $params[] = $filter_tanggal_mulai;
    $params[] = $filter_tanggal_selesai;
} elseif (!empty($filter_tanggal_mulai)) {
    $query .= " AND p.tanggal = ?";
    $params[] = $filter_tanggal_mulai;
}

if ($filter_jenis > 0) {
    $query .= " AND p.jenis_kendaraan_id = ?";
    $params[] = $filter_jenis;
}

if (!empty($filter_keyword)) {
    $kw = "%{$filter_keyword}%";
    $query .= " AND (p.kode_transaksi LIKE ? OR p.nomor_karcis LIKE ? OR p.plat_nomor LIKE ? OR p.ciri_kendaraan LIKE ? OR p.keterangan LIKE ?)";
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
}

$query .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$data_parkir = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Data Riwayat Transaksi Parkir
        </h1>
        <div class="page-header-subtitle">
            Pencarian, pemantauan, koreksi edit, dan hapus transaksi jika terjadi kesalahan input
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/petugas/masuk.php" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
            <i class="fa-solid fa-plus me-1 text-warning"></i> Input Masuk Baru
        </a>
    </div>
</div>

<!-- Filter Card -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <form action="" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="tgl_mulai" class="form-label small fw-semibold text-dark">Tanggal Mulai</label>
                <input type="date" class="form-control form-control-sm" id="tgl_mulai" name="tgl_mulai" value="<?= e($filter_tanggal_mulai) ?>">
            </div>

            <div class="col-md-3">
                <label for="tgl_selesai" class="form-label small fw-semibold text-dark">Tanggal Selesai</label>
                <input type="date" class="form-control form-control-sm" id="tgl_selesai" name="tgl_selesai" value="<?= e($filter_tanggal_selesai) ?>">
            </div>

            <div class="col-md-2">
                <label for="jenis_id" class="form-label small fw-semibold text-dark">Jenis Kendaraan</label>
                <select class="form-select form-select-sm" id="jenis_id" name="jenis_id">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($jenis_options as $opt): ?>
                        <option value="<?= $opt['id'] ?>" <?= $filter_jenis == $opt['id'] ? 'selected' : '' ?>>
                            <?= e($opt['nama_jenis']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="q" class="form-label small fw-semibold text-dark">Kata Kunci</label>
                <input type="text" class="form-control form-control-sm" id="q" name="q" placeholder="Cari karcis/plat..." value="<?= e($filter_keyword) ?>">
            </div>

            <div class="col-12 text-end pt-2">
                <a href="<?= BASE_URL ?>/petugas/data-parkir.php" class="btn btn-sm btn-outline-secondary me-2">
                    <i class="fa-solid fa-rotate-left me-1"></i> Reset
                </a>
                <button type="submit" class="btn btn-sm btn-primary px-4" style="background: #0b2e59; border-color: #0b2e59;">
                    <i class="fa-solid fa-filter me-1"></i> Terapkan Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Hasil Data -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-table-list text-primary me-2"></i> Daftar Kendaraan (Ditemukan: <?= count($data_parkir) ?> Data)
        </h6>
        <span class="badge bg-light text-muted border">Koreksi & Hapus Aktif</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-kemenhub align-middle mb-0">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>No. Karcis</th>
                    <th>Kode Transaksi</th>
                    <th>Tanggal</th>
                    <th>Jenis Kendaraan</th>
                    <th>Plat / Ciri Kendaraan</th>
                    <th>Waktu Masuk</th>
                    <th>Waktu Keluar</th>
                    <th>Durasi</th>
                    <th class="text-center" style="min-width: 150px;">Aksi & Koreksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data_parkir)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-circle-exclamation fa-2x mb-2 d-block text-secondary opacity-50"></i>
                            Tidak ditemukan data parkir yang sesuai dengan kriteria filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($data_parkir as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-dark fw-bold px-2 py-1"><?= e($row['nomor_karcis']) ?></span>
                            </td>
                            <td>
                                <span class="font-monospace text-primary fw-semibold"><?= e($row['kode_transaksi']) ?></span>
                            </td>
                            <td>
                                <small><?= date('d/m/Y', strtotime($row['tanggal'])) ?></small>
                            </td>
                            <td>
                                <i class="<?= e($row['icon_class']) ?> me-1 text-secondary"></i>
                                <?= e($row['nama_jenis']) ?>
                            </td>
                            <td>
                                <?php if (!empty($row['plat_nomor'])): ?>
                                    <span class="badge bg-dark text-white me-1"><?= e($row['plat_nomor']) ?></span>
                                <?php endif; ?>
                                <span class="small text-muted"><?= e($row['ciri_kendaraan'] ?? '-') ?></span>
                                <?php if (!empty($row['keterangan'])): ?>
                                    <div class="small text-muted" style="font-size: 0.72rem;"><em><?= e($row['keterangan']) ?></em></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= format_waktu($row['waktu_masuk']) ?></div>
                                <small class="text-muted" style="font-size: 0.72rem;"><?= e($row['nama_petugas_masuk']) ?></small>
                            </td>
                            <td>
                                <?php if ($row['waktu_keluar']): ?>
                                    <div><?= format_waktu($row['waktu_keluar']) ?></div>
                                    <small class="text-muted" style="font-size: 0.72rem;"><?= e($row['nama_petugas_keluar'] ?? '-') ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= format_durasi($row['durasi_menit']) ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <!-- Cetak Karcis -->
                                    <a href="<?= BASE_URL ?>/petugas/karcis.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-outline-secondary" title="Cetak Karcis">
                                        <i class="fa-solid fa-print"></i>
                                    </a>

                                    <!-- Tombol Edit (Koreksi) -->
                                    <button type="button" class="btn btn-outline-primary btn-edit-trx"
                                        data-id="<?= $row['id'] ?>"
                                        data-karcis="<?= e($row['nomor_karcis']) ?>"
                                        data-kode="<?= e($row['kode_transaksi']) ?>"
                                        data-jenis="<?= $row['jenis_kendaraan_id'] ?>"
                                        data-plat="<?= e($row['plat_nomor'] ?? '') ?>"
                                        data-ciri="<?= e($row['ciri_kendaraan'] ?? '') ?>"
                                        data-waktu="<?= substr($row['waktu_masuk'], 0, 5) ?>"
                                        data-keterangan="<?= e($row['keterangan'] ?? '') ?>"
                                        title="Koreksi / Edit Data">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <!-- Tombol Hapus -->
                                    <button type="button" class="btn btn-outline-danger btn-hapus-trx"
                                        data-id="<?= $row['id'] ?>"
                                        data-karcis="<?= e($row['nomor_karcis']) ?>"
                                        data-kode="<?= e($row['kode_transaksi']) ?>"
                                        title="Hapus Transaksi (Salah Input)">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>

                                    <!-- Tombol Keluar (Jika Belum Diproses) -->
                                    <?php if (empty($row['datetime_keluar'])): ?>
                                        <a href="<?= BASE_URL ?>/petugas/keluar.php?kode=<?= urlencode($row['kode_transaksi']) ?>" class="btn btn-success" title="Proses Keluar">
                                            <i class="fa-solid fa-right-from-bracket"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal 1: Edit / Koreksi Transaksi -->
<div class="modal fade" id="modalEditTrx" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3" style="background: #0b2e59 !important; border-bottom: 3px solid #f59e0b;">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-pen-to-square text-warning me-2"></i> Koreksi Transaksi Parkir
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="edit_trx_id">
                
                <div class="modal-body p-4">
                    <div class="alert alert-light border d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="small text-muted d-block">Nomor Karcis</span>
                            <span class="badge bg-warning text-dark fs-6" id="edit_trx_karcis">K-000</span>
                        </div>
                        <div class="text-end">
                            <span class="small text-muted d-block">Kode Transaksi</span>
                            <span class="font-monospace fw-bold text-primary" id="edit_trx_kode">MLK-00000000-0000</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Jenis Kendaraan <span class="text-danger">*</span></label>
                        <select name="jenis_kendaraan_id" id="edit_trx_jenis" class="form-select" required>
                            <?php foreach ($jenis_options as $jo): ?>
                                <option value="<?= $jo['id'] ?>"><?= e($jo['nama_jenis']) ?> (<?= e($jo['kode_kategori']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Plat Nomor</label>
                            <input type="text" name="plat_nomor" id="edit_trx_plat" class="form-control text-uppercase" placeholder="Contoh: BL 1234 AA">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Warna / Ciri Kendaraan</label>
                            <input type="text" name="ciri_kendaraan" id="edit_trx_ciri" class="form-control" placeholder="Contoh: Avanza Putih">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Waktu Masuk</label>
                            <input type="time" name="waktu_masuk" id="edit_trx_waktu" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Keterangan / Keperluan</label>
                        <input type="text" name="keterangan" id="edit_trx_keterangan" class="form-control" placeholder="Contoh: Antar Tamu / Jemput Keluarga">
                    </div>
                </div>

                <div class="modal-footer bg-light p-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_transaksi" value="1" class="btn btn-primary px-4 fw-bold" style="background: #0b2e59; border-color: #0b2e59;">
                        <i class="fa-solid fa-floppy-disk me-1 text-warning"></i> Simpan Koreksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Hapus Transaksi (Salah Input / Duplikat) -->
<div class="modal fade" id="modalHapusTrx" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> Hapus Transaksi Parkir
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="hapus_trx_id">

                <div class="modal-body p-4 text-center">
                    <div class="text-danger mb-3">
                        <i class="fa-solid fa-trash-can fa-3x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Yakin Ingin Menghapus Data Ini?</h5>
                    <p class="text-muted small mb-3">
                        Transaksi <strong id="hapus_trx_karcis" class="text-dark"></strong> (<span id="hapus_trx_kode" class="font-monospace text-primary"></span>) akan dihapus secara permanen dari basis data.
                    </p>

                    <div class="text-start bg-light p-3 rounded-3 border mb-2">
                        <label class="form-label small fw-bold text-dark">Alasan Penghapusan <span class="text-danger">*</span></label>
                        <select name="alasan_hapus" class="form-select mb-2" required>
                            <option value="Salah input jenis kendaraan">Salah input jenis kendaraan</option>
                            <option value="Input ganda / transaksi duplikat">Input ganda / transaksi duplikat</option>
                            <option value="Kendaraan langsung putar balik (batal masuk)">Kendaraan langsung putar balik (batal masuk)</option>
                            <option value="Pengujian sistem / data percobaan">Pengujian sistem / data percobaan</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                        <small class="text-muted">Aktivitas penghapusan akan tercatat pada log audit keamanan sistem.</small>
                    </div>
                </div>

                <div class="modal-footer bg-light p-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="hapus_transaksi" value="1" class="btn btn-danger px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-trash-can me-1"></i> Ya, Hapus Permanen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Bootstrap Bundle dimuat oleh footer; tunggu seluruh dokumen siap sebelum membuat modal.
    document.addEventListener('DOMContentLoaded', function () {
    // Inisialisasi Modal Edit
    const modalEdit = new bootstrap.Modal(document.getElementById('modalEditTrx'));
    document.querySelectorAll('.btn-edit-trx').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_trx_id').value = this.getAttribute('data-id');
            document.getElementById('edit_trx_karcis').textContent = this.getAttribute('data-karcis');
            document.getElementById('edit_trx_kode').textContent = this.getAttribute('data-kode');
            document.getElementById('edit_trx_jenis').value = this.getAttribute('data-jenis');
            document.getElementById('edit_trx_plat').value = this.getAttribute('data-plat');
            document.getElementById('edit_trx_ciri').value = this.getAttribute('data-ciri');
            document.getElementById('edit_trx_waktu').value = this.getAttribute('data-waktu');
            document.getElementById('edit_trx_keterangan').value = this.getAttribute('data-keterangan');
            modalEdit.show();
        });
    });

    // Inisialisasi Modal Hapus
    const modalHapus = new bootstrap.Modal(document.getElementById('modalHapusTrx'));
    document.querySelectorAll('.btn-hapus-trx').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('hapus_trx_id').value = this.getAttribute('data-id');
            document.getElementById('hapus_trx_karcis').textContent = this.getAttribute('data-karcis');
            document.getElementById('hapus_trx_kode').textContent = this.getAttribute('data-kode');
            modalHapus.show();
        });
    });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
