<?php
/**
 * Cetak Karcis Struk Masuk Kendaraan
 * Bandara Malikussaleh (LSW)
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['petugas', 'admin', 'supervisor']);

$id = (int)($_GET['id'] ?? 0);
$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] == '1';

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT p.*, j.nama_jenis, u.nama as nama_petugas
    FROM parkir p
    JOIN jenis_kendaraan j ON p.jenis_kendaraan_id = j.id
    LEFT JOIN users u ON p.petugas_masuk_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) {
    die("Data transaksi parkir tidak ditemukan.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karcis Parkir - <?= e($data['nomor_karcis']) ?> - <?= APP_NAME ?></title>
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Courier New', Courier, monospace;
            padding: 20px;
        }
        .ticket-wrapper {
            max-width: 340px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px dashed #94a3b8;
            padding: 24px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border-radius: 6px;
        }
        .ticket-header {
            text-align: center;
            border-bottom: 2px dashed #334155;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .ticket-title {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            margin-bottom: 2px;
        }
        .ticket-subtitle {
            font-size: 11px;
            color: #475569;
        }
        .ticket-number {
            font-size: 32px;
            font-weight: 900;
            letter-spacing: 2px;
            text-align: center;
            margin: 10px 0;
            padding: 6px 0;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .ticket-table {
            width: 100%;
            font-size: 12px;
            margin-bottom: 14px;
        }
        .ticket-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .ticket-barcode {
            text-align: center;
            font-size: 24px;
            letter-spacing: 6px;
            font-family: monospace;
            font-weight: bold;
            margin: 8px 0;
        }
        .ticket-footer {
            border-top: 1px dashed #94a3b8;
            padding-top: 10px;
            font-size: 10px;
            color: #475569;
            text-align: center;
            line-height: 1.4;
        }
        @media print {
            body {
                background: transparent !important;
                padding: 0 !important;
            }
            .ticket-wrapper {
                border: none !important;
                box-shadow: none !important;
                max-width: 100% !important;
                width: 100% !important;
                padding: 10px !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print text-center mb-3">
    <button onclick="window.print()" class="btn btn-primary px-4 fw-bold shadow-sm" style="background: #0b2e59; border-color: #0b2e59;">
        <i class="fa-solid fa-print me-1"></i> Cetak Karcis
    </button>
    <a href="<?= BASE_URL ?>/petugas/masuk.php" class="btn btn-outline-secondary ms-2">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="ticket-wrapper">
    <!-- Header Instansi Kemenhub -->
    <div class="ticket-header">
        <div class="ticket-title"><?= INSTANSI_NAME ?></div>
        <div class="ticket-subtitle"><?= INSTANSI_SUB ?></div>
        <div class="fw-bold mt-1" style="font-size: 12px;"><?= UPBU_NAME ?> (<?= AIRPORT_CODE ?>)</div>
        <div style="font-size: 10px;"><?= AIRPORT_LOCATION ?></div>
    </div>

    <!-- Nomor Urut Karcis -->
    <div class="text-center" style="font-size: 11px; text-transform: uppercase; font-weight: bold;">
        KARCIS TANDA MASUK PARKIR
    </div>
    <div class="ticket-number">
        <?= e($data['nomor_karcis']) ?>
    </div>

    <!-- Barcode Visual Mockup -->
    <div class="ticket-barcode">
        |||||| | |||| | ||||||
    </div>
    <div class="text-center font-monospace mb-3" style="font-size: 12px; font-weight: bold;">
        <?= e($data['kode_transaksi']) ?>
    </div>

    <!-- Rincian Data Kendaraan -->
    <table class="ticket-table">
        <tr>
            <td style="width: 35%;">Tanggal</td>
            <td style="width: 5%;">:</td>
            <td><strong><?= date('d/m/Y', strtotime($data['tanggal'])) ?></strong></td>
        </tr>
        <tr>
            <td>Waktu Masuk</td>
            <td>:</td>
            <td><strong><?= format_waktu($data['waktu_masuk']) ?></strong></td>
        </tr>
        <tr>
            <td>Jenis</td>
            <td>:</td>
            <td><?= e($data['nama_jenis']) ?></td>
        </tr>
        <?php if (!empty($data['plat_nomor'])): ?>
            <tr>
                <td>No. Plat</td>
                <td>:</td>
                <td><strong><?= e($data['plat_nomor']) ?></strong></td>
            </tr>
        <?php endif; ?>
        <?php if (!empty($data['ciri_kendaraan'])): ?>
            <tr>
                <td>Ciri/Warna</td>
                <td>:</td>
                <td><?= e($data['ciri_kendaraan']) ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td>Petugas Pos</td>
            <td>:</td>
            <td><?= e($data['nama_petugas']) ?></td>
        </tr>
    </table>

    <!-- Footer Ketentuan Bandara -->
    <div class="ticket-footer">
        <div><strong>PERHATIAN KESELAMATAN:</strong></div>
        <div>1. Harap simpan karcis ini dengan baik.</div>
        <div>2. Jangan meninggalkan barang berharga di dalam kendaraan.</div>
        <div>3. Tunjukkan karcis ini saat keluar area parkir bandara.</div>
        <div class="mt-2 text-uppercase fw-bold" style="font-size: 9px;">Terima Kasih Atas Kunjungan Anda</div>
    </div>
</div>

<?php if ($autoprint): ?>
<script>
    window.addEventListener('load', function() {
        window.print();
    });
</script>
<?php endif; ?>

</body>
</html>
