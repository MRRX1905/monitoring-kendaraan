<?php
/**
 * Laporan & Rekapitulasi Aktivitas Parkir
 * Bandara Malikussaleh (LSW)
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['supervisor', 'admin', 'petugas']);

$pdo = getDB();

// Parameter Filter
$tgl_mulai = trim($_GET['tgl_mulai'] ?? date('Y-m-01'));
$tgl_selesai = trim($_GET['tgl_selesai'] ?? date('Y-m-d'));
$jenis_id = (int)($_GET['jenis_id'] ?? 0);
$is_export = isset($_GET['export']) && $_GET['export'] === 'excel';
$is_print = isset($_GET['print']) && $_GET['print'] === '1';

// Query dasar data
$sql = "
    SELECT p.*, j.nama_jenis, j.icon_class,
           u_masuk.nama as nama_petugas_masuk,
           u_keluar.nama as nama_petugas_keluar
    FROM parkir p
    JOIN jenis_kendaraan j ON p.jenis_kendaraan_id = j.id
    LEFT JOIN users u_masuk ON p.petugas_masuk_id = u_masuk.id
    LEFT JOIN users u_keluar ON p.petugas_keluar_id = u_keluar.id
    WHERE p.tanggal BETWEEN ? AND ?
";
$params = [$tgl_mulai, $tgl_selesai];

if ($jenis_id > 0) {
    $sql .= " AND p.jenis_kendaraan_id = ?";
    $params[] = $jenis_id;
}

$sql .= " ORDER BY p.datetime_masuk ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Hitung Rekapitulasi per Jenis Kendaraan
$rekap_jenis = [];
$total_masuk = count($data);
$total_keluar = 0;
$total_durasi = 0;

foreach ($data as $d) {
    $j_name = $d['nama_jenis'];
    if (!isset($rekap_jenis[$j_name])) {
        $rekap_jenis[$j_name] = ['masuk' => 0, 'keluar' => 0];
    }
    $rekap_jenis[$j_name]['masuk']++;

    if (!empty($d['datetime_keluar'])) {
        $total_keluar++;
        $rekap_jenis[$j_name]['keluar']++;
        if ($d['durasi_menit'] !== null) {
            $total_durasi += $d['durasi_menit'];
        }
    }
}

$rata_durasi = $total_keluar > 0 ? (int)round($total_durasi / $total_keluar) : 0;

// Perhitungan Tracking Otomatis (AI Vision) pada periode terpilih.
// Filter jenis kendaraan diterapkan pada sumber Manual dan AI agar semua rekap konsisten.
$tracking = tracking_summary($pdo, $tgl_mulai, $tgl_selesai, $jenis_id);
$kelas_map = ['car' => 'Mobil (R4)', 'motorcycle' => 'Sepeda Motor (R2)', 'bus' => 'Bus / Minibus', 'truck' => 'Truk / Pick Up'];

// Ringkasan gabungan Periode Ini: Pencatatan Manual + Otomatis AI
$mv = combined_movement_summary($pdo, $tgl_mulai, $tgl_selesai, $jenis_id);

// Rincian seluruh transaksi (gabungan Pencatatan Manual + Otomatis AI)
$rincian = [];
if (!$is_export && !$is_print) {
    foreach ($data as $r) {
        $identitas = trim($r['nomor_karcis'] . ' • ' . ($r['plat_nomor'] ?: ($r['ciri_kendaraan'] ?: '-')));
        $rincian[] = [
            'waktu' => $r['datetime_masuk'],
            'sumber' => 'Manual',
            'arah' => 'masuk',
            'jenis' => $r['nama_jenis'],
            'identitas' => $identitas,
        ];
        if (!empty($r['datetime_keluar'])) {
            $rincian[] = [
                'waktu' => $r['datetime_keluar'],
                'sumber' => 'Manual',
                'arah' => 'keluar',
                'jenis' => $r['nama_jenis'],
                'identitas' => $identitas,
            ];
        }
    }

    try {
        $stmt_rincian_ai = $pdo->prepare("
            SELECT t.waktu_event, t.class_name, t.direction, t.track_id, t.line_label,
                   j.nama_jenis
            FROM tracking_kendaraan t
            LEFT JOIN jenis_kendaraan j ON t.jenis_kendaraan_id = j.id
            WHERE DATE(t.waktu_event) BETWEEN ? AND ?
            " . ($jenis_id > 0 ? " AND t.jenis_kendaraan_id = ?" : "")
        );
        $params_rincian_ai = [$tgl_mulai, $tgl_selesai];
        if ($jenis_id > 0) {
            $params_rincian_ai[] = $jenis_id;
        }
        $stmt_rincian_ai->execute($params_rincian_ai);
        foreach ($stmt_rincian_ai->fetchAll() as $ar) {
            $rincian[] = [
                'waktu' => $ar['waktu_event'],
                'sumber' => 'Otomatis AI',
                'arah' => strtolower($ar['direction']) === 'masuk' ? 'masuk' : 'keluar',
                'jenis' => $ar['nama_jenis'] ?: label_kelas_tracking($ar['class_name']),
                'identitas' => 'Track #' . $ar['track_id'] . ' • ' . strtoupper($ar['class_name']),
            ];
        }
    } catch (Exception $e) {
        // tabel tracking belum tersedia
    }

    usort($rincian, function ($a, $b) {
        return strcmp($b['waktu'], $a['waktu']);
    });
}

// Batasi rincian tampilan web dengan paginasi agar halaman tetap ringkas.
$per_halaman = 25;
$total_rincian = count($rincian);
$total_halaman = max(1, (int)ceil($total_rincian / $per_halaman));
$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$halaman = min($halaman, $total_halaman);
$offset_rincian = ($halaman - 1) * $per_halaman;
$rincian_halaman = array_slice($rincian, $offset_rincian, $per_halaman);

// Export ke Excel / Spreadsheet
if ($is_export) {
    $filename = "Laporan_Parkir_Malikussaleh_{$tgl_mulai}_sd_{$tgl_selesai}.xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    ?>
    <table border="1">
        <tr>
            <th colspan="12" style="font-size: 16px; font-weight: bold; text-align: center;">
                LAPORAN AKTIVITAS KENDARAAN MASUK & KELUAR<br>
                <?= INSTANSI_NAME ?> - <?= UPBU_NAME ?> (<?= AIRPORT_CODE ?>)<br>
                Periode: <?= format_tanggal_indo($tgl_mulai) ?> s/d <?= format_tanggal_indo($tgl_selesai) ?>
            </th>
        </tr>
        <tr><td colspan="12"></td></tr>
        <tr style="background-color: #0b2e59; color: #ffffff;">
            <th>No</th>
            <th>No. Karcis</th>
            <th>Kode Transaksi</th>
            <th>Tanggal</th>
            <th>Jenis Kendaraan</th>
            <th>Plat Nomor</th>
            <th>Ciri Kendaraan</th>
            <th>Waktu Masuk</th>
            <th>Waktu Keluar</th>
            <th>Durasi (Menit)</th>
            <th>Petugas Masuk</th>
            <th>Petugas Keluar</th>
        </tr>
        <?php $no = 1; foreach ($data as $r): ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><?= e($r['nomor_karcis']) ?></td>
            <td><?= e($r['kode_transaksi']) ?></td>
            <td><?= $r['tanggal'] ?></td>
            <td><?= e($r['nama_jenis']) ?></td>
            <td><?= e($r['plat_nomor'] ?? '-') ?></td>
            <td><?= e($r['ciri_kendaraan'] ?? '-') ?></td>
            <td><?= $r['waktu_masuk'] ?></td>
            <td><?= $r['waktu_keluar'] ?? '-' ?></td>
            <td><?= $r['durasi_menit'] ?? '-' ?></td>
            <td><?= e($r['nama_petugas_masuk']) ?></td>
            <td><?= e($r['nama_petugas_keluar'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <br>
    <table border="1">
        <tr style="background-color: #0b2e59; color: #ffffff;">
            <th colspan="4">RINGKASAN PERBANDINGAN MASUK VS KELUAR (MANUAL + OTOMATIS)</th>
        </tr>
        <tr>
            <th>Sumber Pencatatan</th>
            <th>Masuk</th>
            <th>Keluar</th>
            <th>Subtotal</th>
        </tr>
        <tr>
            <td>Manual (Pos Petugas)</td>
            <td><?= $mv['manual_masuk'] ?></td>
            <td><?= $mv['manual_keluar'] ?></td>
            <td><?= $mv['manual_masuk'] + $mv['manual_keluar'] ?></td>
        </tr>
        <tr>
            <td>Otomatis (AI Vision)</td>
            <td><?= $mv['ai_masuk'] ?></td>
            <td><?= $mv['ai_keluar'] ?></td>
            <td><?= $mv['ai_total'] ?></td>
        </tr>
        <tr>
            <td><b>TOTAL</b></td>
            <td><b><?= $mv['total_masuk'] ?></b></td>
            <td><b><?= $mv['total_keluar'] ?></b></td>
            <td><b><?= $mv['total'] ?></b></td>
        </tr>
        <tr>
            <td colspan="4" style="text-align: center;"><b>Kesesuaian Masuk &amp; Keluar: Selisih <?= $mv['selisih'] ?> unit - Persentase Kesesuaian <?= $mv['persen_kesesuaian'] ?>% (<?= $mv['seimbang'] ? 'SAMA' : 'BELUM SAMA' ?>)</b></td>
        </tr>
    </table>
    <br>
    <table border="1">
        <tr style="background-color: #0b2e59; color: #ffffff;">
            <th colspan="4">REKAPITULASI PER JENIS KENDARAAN (PENCATATAN MANUAL)</th>
        </tr>
        <tr>
            <th>Jenis Kendaraan</th>
            <th>Total Masuk</th>
            <th>Total Keluar</th>
            <th>Persentase Kesesuaian</th>
        </tr>
        <?php foreach ($rekap_jenis as $j_nama => $counts):
            $maks_jenis = max($counts['masuk'], $counts['keluar']);
            $pct_kesesuaian = $maks_jenis > 0 ? round(min($counts['masuk'], $counts['keluar']) / $maks_jenis * 100, 1) : 100;
        ?>
        <tr>
            <td><?= e($j_nama) ?></td>
            <td><?= $counts['masuk'] ?></td>
            <td><?= $counts['keluar'] ?></td>
            <td><?= $pct_kesesuaian ?>%</td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <td><b>TOTAL</b></td>
            <td><b><?= $total_masuk ?></b></td>
            <td><b><?= $total_keluar ?></b></td>
            <td><b><?= max($total_masuk, $total_keluar) > 0 ? round(min($total_masuk, $total_keluar) / max($total_masuk, $total_keluar) * 100, 1) : 100 ?>%</b></td>
        </tr>
    </table>
    <br>
    <table border="1">
        <tr style="background-color: #0b2e59; color: #ffffff;">
            <th colspan="4">PERHITUNGAN TRACKING OTOMATIS (AI VISION)</th>
        </tr>
        <tr>
            <th>Kelas Kendaraan (Deteksi AI)</th>
            <th>Terdeteksi Masuk</th>
            <th>Terdeteksi Keluar</th>
            <th>Subtotal</th>
            <th>Persentase Kesesuaian</th>
        </tr>
        <?php foreach ($kelas_map as $key => $label):
            $in = $tracking['counts_masuk'][$key] ?? 0;
            $out = $tracking['counts_keluar'][$key] ?? 0;
            $maks_kelas = max($in, $out);
            $pct_kesesuaian = $maks_kelas > 0 ? round(min($in, $out) / $maks_kelas * 100, 1) : 100;
        ?>
        <tr>
            <td><?= e($label) ?></td>
            <td><?= $in ?></td>
            <td><?= $out ?></td>
            <td><?= $in + $out ?></td>
            <td><?= $pct_kesesuaian ?>%</td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <td><b>TOTAL</b></td>
            <td><b><?= $tracking['masuk'] ?></b></td>
            <td><b><?= $tracking['keluar'] ?></b></td>
            <td><b><?= $tracking['total'] ?></b></td>
            <td><b><?= $tracking['total'] > 0 ? round(min($tracking['masuk'], $tracking['keluar']) / max($tracking['masuk'], $tracking['keluar']) * 100, 1) : 100 ?>%</b></td>
        </tr>
    </table>
    <?php
    exit;
}

// Mode Print Khusus (Halaman Cetak Tanpa Sidebar/Navbar)
if ($is_print) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Cetak Laporan Parkir - <?= AIRPORT_NAME ?></title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            /* Kunci cetak: ukuran kertas A4 dengan margin 10mm, tinggi area cetak
               yang tersedia = 277mm (~1046px @96dpi). Seluruh isi laporan dibuat
               kompak dan jumlah barisnya dibatasi supaya tidak pernah > 1 lembar. */
            @page { size: A4 portrait; margin: 10mm; }
            * { box-sizing: border-box; }
            html, body { margin: 0; padding: 0; }
            body { font-family: 'Times New Roman', Times, serif; color: #000; font-size: 9.5pt; line-height: 1.25; background: #fff; }
            .container { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .row { margin-left: 0 !important; margin-right: 0 !important; }
            .row > * { padding-left: 3px !important; padding-right: 3px !important; }
            .kop-surat { border-bottom: 2px double #000; padding-bottom: 3px; margin-bottom: 6px; text-align: center; }
            .kop-surat h4 { margin: 0; font-size: 11.5pt; font-weight: bold; text-transform: uppercase; }
            .kop-surat h5 { margin: 1px 0; font-size: 10.5pt; font-weight: bold; text-transform: uppercase; }
            .kop-surat p { margin: 0; font-size: 8pt; }
            table { width: 100%; border-collapse: collapse; font-size: 8pt; margin-bottom: 4px; }
            table th, table td { border: 1px solid #000; padding: 2px 3px; line-height: 1.2; }
            table th { background-color: #f2f2f2; text-align: center; }
            .no-print { margin: 0 0 4px; }
            .mb-3, .mb-4 { margin-bottom: 4px !important; }
            .mt-4, .mt-5 { margin-top: 4px !important; }
            .pt-4 { padding-top: 2px !important; }
            .p-2 { padding: 2px 4px !important; }
            .small, small { font-size: 8pt !important; }
            h4, h5 { font-size: inherit; }
            tr, .card { page-break-inside: avoid; }
            table { page-break-inside: auto; }
            @media print {
                .no-print { display: none !important; }
                body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            }
        </style>
    </head>
    <body onload="window.print()">
        <div class="container my-4">
            <div class="no-print text-end">
                <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa-solid fa-print me-1"></i> Cetak</button>
                <button onclick="window.close()" class="btn btn-secondary btn-sm ms-1">Tutup</button>
            </div>

            <!-- Kop Surat Resmi Kemenhub -->
            <div class="kop-surat">
                <h4><?= INSTANSI_NAME ?></h4>
                <h5><?= INSTANSI_SUB ?></h5>
                <h5><?= UPBU_NAME ?></h5>
                <p><?= AIRPORT_ADDRESS ?></p>
            </div>

            <div class="text-center mb-4">
                <h5 class="fw-bold text-uppercase mb-1" style="text-decoration: underline;">REKAPITULASI AKTIVITAS PARKIR KENDARAAN</h5>
                <div>Periode: <strong><?= format_tanggal_indo($tgl_mulai) ?></strong> s/d <strong><?= format_tanggal_indo($tgl_selesai) ?></strong></div>
            </div>

            <!-- Ringkasan Singkat -->
            <div class="row g-2 mb-3">
                <div class="col-4">
                    <div class="p-2 border text-center">
                        <small class="d-block text-muted">Total Kendaraan Masuk</small>
                        <strong><?= $mv['total_masuk'] ?> Unit</strong>
                        <div class="small text-muted">Manual <?= $mv['manual_masuk'] ?> + AI <?= $mv['ai_masuk'] ?></div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-2 border text-center">
                        <small class="d-block text-muted">Total Kendaraan Keluar</small>
                        <strong><?= $mv['total_keluar'] ?> Unit</strong>
                        <div class="small text-muted">Manual <?= $mv['manual_keluar'] ?> + AI <?= $mv['ai_keluar'] ?></div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-2 border text-center">
                        <small class="d-block text-muted">Tracking AI (Masuk + Keluar)</small>
                        <strong><?= $tracking['total'] ?> Unit</strong>
                        <div class="small text-muted">Masuk <?= $tracking['masuk'] ?> / Keluar <?= $tracking['keluar'] ?></div>
                    </div>
                </div>
            </div>
            <div class="border p-2 mb-4 text-center">
                <b>Kesesuaian Jumlah Masuk &amp; Keluar:</b>
                Masuk <b><?= $mv['total_masuk'] ?></b> &bull; Keluar <b><?= $mv['total_keluar'] ?></b> &bull;
                Selisih <b><?= $mv['selisih'] ?></b> unit &mdash;
                Persentase Kesesuaian <b><?= $mv['persen_kesesuaian'] ?>%</b>
                (<?= $mv['seimbang'] ? 'SAMA' : 'BELUM SAMA' ?>)
            </div>

            <!-- Rincian Transaksi (Manual + Otomatis AI), dibatasi agar laporan tetap 1 lembar -->
            <?php
            $cetak_maks_rincian = 12;
            $cetak_rincian = array_slice($rincian, 0, $cetak_maks_rincian);
            $cetak_sisa = max(0, count($rincian) - count($cetak_rincian));
            ?>
            <table>
                <thead>
                    <tr>
                        <th colspan="6" style="background-color: #0b2e59; color: #ffffff;">A. RINCIAN TRANSAKSI PERIODE TERPILIH (MANUAL + OTOMATIS AI)</th>
                    </tr>
                    <tr>
                        <th style="width: 26px;">No</th>
                        <th style="width: 92px;">Waktu</th>
                        <th style="width: 82px;">Sumber</th>
                        <th style="width: 62px;">Arah</th>
                        <th>Jenis / Kelas Kendaraan</th>
                        <th style="width: 160px;">Kode / Identitas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rincian)): ?>
                        <tr><td colspan="6" class="text-center py-2">Tidak ada data transaksi pada periode ini.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($cetak_rincian as $r): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td class="text-center"><?= date('d/m/Y H:i', strtotime($r['waktu'])) ?></td>
                                <td class="text-center"><?= e($r['sumber']) ?></td>
                                <td class="text-center"><?= $r['arah'] === 'masuk' ? 'Masuk' : 'Keluar' ?></td>
                                <td><?= e($r['jenis']) ?></td>
                                <td class="text-center"><?= e($r['identitas']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="6" class="text-center" style="font-style: italic;">
                                Hanya menampilkan <?= count($cetak_rincian) ?> dari <?= number_format(count($rincian)) ?> transaksi periode ini.
                                <?php if ($cetak_sisa > 0): ?>Sisa <?= number_format($cetak_sisa) ?> transaksi dapat dilihat pada rincian web atau Export Excel.<?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Rekapitulasi Per Jenis Kendaraan -->
            <div class="mt-4">
                <div class="fw-bold mb-2 text-uppercase">B. Rekapitulasi Per Jenis Kendaraan (Pencatatan Manual)</div>
                <table>
                    <thead>
                        <tr>
                            <th>Jenis Kendaraan</th>
                            <th>Total Masuk</th>
                            <th>Total Keluar</th>
                            <th>Persentase Kesesuaian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rekap_jenis as $j_nama => $counts):
                            $maks_jenis = max($counts['masuk'], $counts['keluar']);
                            $pct_kesesuaian = $maks_jenis > 0 ? round(min($counts['masuk'], $counts['keluar']) / $maks_jenis * 100, 1) : 100;
                        ?>
                            <tr>
                                <td><?= e($j_nama) ?></td>
                                <td class="text-center"><?= $counts['masuk'] ?></td>
                                <td class="text-center"><?= $counts['keluar'] ?></td>
                                <td class="text-center"><?= $pct_kesesuaian ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td class="fw-bold">TOTAL</td>
                            <td class="text-center fw-bold"><?= $total_masuk ?></td>
                            <td class="text-center fw-bold"><?= $total_keluar ?></td>
                            <td class="text-center fw-bold"><?= max($total_masuk, $total_keluar) > 0 ? round(min($total_masuk, $total_keluar) / max($total_masuk, $total_keluar) * 100, 1) : 100 ?>%</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Rekapitulasi Tracking Otomatis AI -->
            <div class="mt-4">
                <div class="fw-bold mb-2 text-uppercase">C. Perhitungan Tracking Otomatis (AI Vision)</div>
                <table>
                    <thead>
                        <tr>
                            <th>Kelas Kendaraan (Deteksi AI)</th>
                            <th>Terdeteksi Masuk</th>
                            <th>Terdeteksi Keluar</th>
                            <th>Subtotal</th>
                            <th>Persentase Kesesuaian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kelas_map as $key => $label):
                            $in = $tracking['counts_masuk'][$key] ?? 0;
                            $out = $tracking['counts_keluar'][$key] ?? 0;
                            $maks_kelas = max($in, $out);
                            $pct_kesesuaian = $maks_kelas > 0 ? round(min($in, $out) / $maks_kelas * 100, 1) : 100;
                        ?>
                            <tr>
                                <td><?= e($label) ?></td>
                                <td class="text-center"><?= $in ?></td>
                                <td class="text-center"><?= $out ?></td>
                                <td class="text-center"><?= $in + $out ?></td>
                                <td class="text-center"><?= $pct_kesesuaian ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td class="fw-bold">TOTAL</td>
                            <td class="text-center fw-bold"><?= $tracking['masuk'] ?></td>
                            <td class="text-center fw-bold"><?= $tracking['keluar'] ?></td>
                            <td class="text-center fw-bold"><?= $tracking['total'] ?></td>
                            <td class="text-center fw-bold"><?= $tracking['total'] > 0 ? round(min($tracking['masuk'], $tracking['keluar']) / max($tracking['masuk'], $tracking['keluar']) * 100, 1) : 100 ?>%</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Tanda Tangan Pengesahan -->
            <div class="row mt-5 pt-4" style="page-break-inside: avoid;">
                <div class="col-6 text-center">
                    <div>Mengetahui,</div>
                    <div class="fw-bold">Supervisor Operasional Parkir</div>
                    <div style="height: 34px;"></div>
                    <div class="fw-bold" style="text-decoration: underline;">( ............................................ )</div>
                    <div class="small">NIP. ........................................</div>
                </div>
                <div class="col-6 text-center">
                    <div>Aceh Utara, <?= format_tanggal_indo(date('Y-m-d')) ?></div>
                    <div class="fw-bold">Petugas Pencatat Pos</div>
                    <div style="height: 34px;"></div>
                    <div class="fw-bold" style="text-decoration: underline;">( <?= e($_SESSION['user_nama']) ?> )</div>
                    <div class="small">Petugas Pelaksana Parkir</div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Tampilan Normal Web
$page_title = 'Rekapitulasi Laporan Parkir';
require_once __DIR__ . '/../includes/header.php';

// Ambil master jenis kendaraan
$stmt_j = $pdo->query("SELECT * FROM jenis_kendaraan ORDER BY urutan ASC");
$jenis_opts = $stmt_j->fetchAll();
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-file-invoice text-primary me-2"></i> Rekapitulasi & Laporan Aktivitas Parkir
        </h1>
        <div class="page-header-subtitle">
            Pelaporan operasional berkala kendaraan masuk dan keluar Bandara Malikussaleh
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="?<?= http_build_query(array_merge($_GET, ['print' => 1])) ?>" target="_blank" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
            <i class="fa-solid fa-print me-1"></i> Cetak Laporan Resmi
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="btn btn-success">
            <i class="fa-solid fa-file-excel me-1"></i> Export Excel
        </a>
    </div>
</div>

<!-- Filter Card -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <form action="" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="tgl_mulai" class="form-label small fw-semibold text-dark">Dari Tanggal</label>
                <input type="date" class="form-control" id="tgl_mulai" name="tgl_mulai" value="<?= e($tgl_mulai) ?>" required>
            </div>
            <div class="col-md-3">
                <label for="tgl_selesai" class="form-label small fw-semibold text-dark">Sampai Tanggal</label>
                <input type="date" class="form-control" id="tgl_selesai" name="tgl_selesai" value="<?= e($tgl_selesai) ?>" required>
            </div>
            <div class="col-md-3">
                <label for="jenis_id" class="form-label small fw-semibold text-dark">Jenis Kendaraan</label>
                <select class="form-select" id="jenis_id" name="jenis_id">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($jenis_opts as $jo): ?>
                        <option value="<?= $jo['id'] ?>" <?= $jenis_id == $jo['id'] ? 'selected' : '' ?>>
                            <?= e($jo['nama_jenis']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 text-end pt-2">
                <a href="<?= BASE_URL ?>/supervisor/laporan.php" class="btn btn-outline-secondary me-2">Reset</a>
                <button type="submit" class="btn btn-primary px-4" style="background: #0b2e59; border-color: #0b2e59;">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Tampilkan Rekapitulasi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 4 Kotak Ringkasan Periode Terpilih -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-navy">
            <div class="stat-label">Total Masuk Periode Ini</div>
            <div class="stat-value text-primary"><?= number_format($mv['total_masuk']) ?></div>
            <div class="small text-muted mt-2">Manual <?= number_format($mv['manual_masuk']) ?> &bull; AI <?= number_format($mv['ai_masuk']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <div class="stat-label">Total Keluar Periode Ini</div>
            <div class="stat-value text-success"><?= number_format($mv['total_keluar']) ?></div>
            <div class="small text-muted mt-2">Manual <?= number_format($mv['manual_keluar']) ?> &bull; AI <?= number_format($mv['ai_keluar']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <div class="stat-label">Tracking AI Periode Ini</div>
            <div class="stat-value" style="color: #b45309 !important;"><?= number_format($tracking['total']) ?></div>
            <div class="small text-muted mt-2">Masuk: <?= number_format($tracking['masuk']) ?> &bull; Keluar: <?= number_format($tracking['keluar']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info">
            <div class="stat-label">Total Lalu Lintas Periode Ini</div>
            <div class="stat-value text-info" style="font-size: 1.5rem;"><?= number_format($mv['total']) ?></div>
            <div class="small text-muted mt-2">Manual + Otomatis AI</div>
        </div>
    </div>
</div>

<!-- Kesesuaian Jumlah Masuk vs Keluar (Manual + Otomatis) -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-scale-balanced text-primary me-2"></i> Kesesuaian Jumlah Masuk vs Keluar Periode Ini (Manual + Otomatis)
        </h6>
        <?php if ($mv['seimbang']): ?>
            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i> Jumlah SAMA</span>
        <?php else: ?>
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="fa-solid fa-triangle-exclamation me-1"></i> Jumlah BELUM SAMA</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-4">
        <div class="row g-2 text-center mb-3">
            <div class="col-4"><div class="p-2 rounded-3 border bg-light"><div class="small text-muted">Masuk</div><div class="fw-bold fs-4 text-primary"><?= number_format($mv['total_masuk']) ?></div></div></div>
            <div class="col-4"><div class="p-2 rounded-3 border bg-light"><div class="small text-muted">Keluar</div><div class="fw-bold fs-4 text-success"><?= number_format($mv['total_keluar']) ?></div></div></div>
            <div class="col-4"><div class="p-2 rounded-3 border bg-light"><div class="small text-muted">Selisih</div><div class="fw-bold fs-4 text-danger"><?= number_format($mv['selisih']) ?></div></div></div>
        </div>
        <div class="d-flex justify-content-between small fw-bold mb-1">
            <span>Persentase Kesesuaian (Masuk vs Keluar)</span>
            <span class="<?= $mv['seimbang'] ? 'text-success' : 'text-warning' ?>"><?= $mv['persen_kesesuaian'] ?>%</span>
        </div>
        <div class="progress" style="height: 20px;">
            <div class="progress-bar <?= $mv['seimbang'] ? 'bg-success' : 'bg-warning' ?> fw-bold" role="progressbar" style="width: <?= $mv['persen_kesesuaian'] ?>%"><?= $mv['persen_kesesuaian'] ?>%</div>
        </div>
        <div class="mt-2 small text-muted">
            <?= $mv['seimbang'] ? 'Jumlah kendaraan masuk sudah sama dengan keluar.' : 'Jumlah masuk dan keluar belum sama (selisih ' . number_format($mv['selisih']) . ' unit).' ?>
        </div>
        <div class="row g-2 mt-2">
            <div class="col-md-6"><div class="p-2 border rounded-3 bg-light small"><i class="fa-solid fa-keyboard me-1"></i> Manual — Masuk: <strong><?= number_format($mv['manual_masuk']) ?></strong> &bull; Keluar: <strong><?= number_format($mv['manual_keluar']) ?></strong></div></div>
            <div class="col-md-6"><div class="p-2 border rounded-3 bg-light small"><i class="fa-solid fa-video text-danger me-1"></i> Otomatis AI — Masuk: <strong><?= number_format($mv['ai_masuk']) ?></strong> &bull; Keluar: <strong><?= number_format($mv['ai_keluar']) ?></strong></div></div>
        </div>
    </div>
</div>

<!-- Tabel Rekap Per Jenis Kendaraan -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-chart-column text-primary me-2"></i> Rekapitulasi Berdasarkan Jenis Kendaraan (Pencatatan Manual)
        </h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-kemenhub align-middle mb-0">
            <thead>
                <tr>
                    <th>Jenis Kendaraan</th>
                    <th class="text-center">Total Masuk</th>
                    <th class="text-center">Total Keluar</th>
                    <th class="text-center">Persentase Kesesuaian</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rekap_jenis)): ?>
                    <tr><td colspan="4" class="text-center py-3 text-muted">Belum ada data.</td></tr>
                <?php else: ?>
                    <?php foreach ($rekap_jenis as $j_nama => $counts):
                        $maks_jenis = max($counts['masuk'], $counts['keluar']);
                        $pct_kesesuaian = $maks_jenis > 0 ? round(min($counts['masuk'], $counts['keluar']) / $maks_jenis * 100, 1) : 100;
                    ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= e($j_nama) ?></td>
                            <td class="text-center fw-semibold text-primary"><?= $counts['masuk'] ?> Unit</td>
                            <td class="text-center fw-semibold text-success"><?= $counts['keluar'] ?> Unit</td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; max-width: 120px;">
                                        <div class="progress-bar <?= $pct_kesesuaian == 100 ? 'bg-success' : 'bg-warning' ?>" role="progressbar" style="width: <?= $pct_kesesuaian ?>%"></div>
                                    </div>
                                    <small class="text-muted fw-bold"><?= $pct_kesesuaian ?>%</small>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Tabel Rekap Tracking Otomatis AI -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-video text-danger me-2"></i> Perhitungan Tracking Otomatis (AI Vision) Periode Ini
        </h6>
        <a href="<?= BASE_URL ?>/admin/tracking.php" class="btn btn-sm btn-outline-danger">
            Buka Live Tracking <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-kemenhub align-middle mb-0">
            <thead>
                <tr>
                    <th>Kelas Kendaraan (Deteksi AI)</th>
                    <th class="text-center">Terdeteksi Masuk</th>
                    <th class="text-center">Terdeteksi Keluar</th>
                    <th class="text-center">Subtotal</th>
                    <th class="text-center">Persentase Kesesuaian</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kelas_map as $key => $label):
                    $in = $tracking['counts_masuk'][$key] ?? 0;
                    $out = $tracking['counts_keluar'][$key] ?? 0;
                    $maks_kelas = max($in, $out);
                    $pct_kesesuaian = $maks_kelas > 0 ? round(min($in, $out) / $maks_kelas * 100, 1) : 100;
                ?>
                    <tr>
                        <td class="fw-bold text-dark"><?= e($label) ?></td>
                        <td class="text-center fw-semibold text-success"><?= number_format($in) ?></td>
                        <td class="text-center fw-semibold text-info"><?= number_format($out) ?></td>
                        <td class="text-center fw-bold"><?= number_format($in + $out) ?></td>
                        <td class="text-center fw-bold <?= $pct_kesesuaian == 100 ? 'text-success' : 'text-warning' ?>"><?= $pct_kesesuaian ?>%</td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-light">
                    <td class="fw-bold">TOTAL</td>
                    <td class="text-center fw-bold text-success"><?= number_format($tracking['masuk']) ?></td>
                    <td class="text-center fw-bold text-info"><?= number_format($tracking['keluar']) ?></td>
                    <td class="text-center fw-bold text-primary"><?= number_format($tracking['total']) ?></td>
                    <td class="text-center fw-bold <?= $tracking['masuk'] === $tracking['keluar'] ? 'text-success' : 'text-warning' ?>"><?= $tracking['total'] > 0 ? round(min($tracking['masuk'], $tracking['keluar']) / max($tracking['masuk'], $tracking['keluar']) * 100, 1) : 100 ?>%</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Tabel Rincian Seluruh Transaksi (Manual + Otomatis AI) -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-list text-primary me-2"></i> Rincian Seluruh Transaksi Periode Terpilih (Manual + Otomatis AI)
        </h6>
        <span class="badge bg-light text-secondary border">Total: <?= number_format($total_rincian) ?> Data</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-kemenhub align-middle mb-0">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Waktu</th>
                    <th>Sumber Pencatatan</th>
                    <th>Arah</th>
                    <th>Jenis / Kelas Kendaraan</th>
                    <th>Kode / Identitas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rincian)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">Tidak ada data pada periode ini.</td></tr>
                <?php else: ?>
                    <?php $no = $offset_rincian + 1; foreach ($rincian_halaman as $r): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div><?= format_waktu($r['waktu']) ?></div>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($r['waktu'])) ?></small>
                            </td>
                            <td>
                                <?php if ($r['sumber'] === 'Manual'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="fa-solid fa-keyboard me-1"></i> Manual</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-video me-1"></i> Otomatis AI</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['arah'] === 'masuk'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-right-to-bracket me-1"></i> Masuk</span>
                                <?php else: ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fa-solid fa-right-from-bracket me-1"></i> Keluar</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($r['jenis']) ?></td>
                            <td class="font-monospace small"><?= e($r['identitas']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_halaman > 1): ?>
        <?php
        $query_halaman = $_GET;
        unset($query_halaman['halaman'], $query_halaman['print'], $query_halaman['export']);
        ?>
        <div class="card-footer bg-white py-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <small class="text-muted">
                Menampilkan <?= number_format($offset_rincian + 1) ?>–<?= number_format(min($offset_rincian + $per_halaman, $total_rincian)) ?> dari <?= number_format($total_rincian) ?> transaksi
            </small>
            <nav aria-label="Navigasi rincian transaksi">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $halaman <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($query_halaman, ['halaman' => max(1, $halaman - 1)])) ?>">Sebelumnya</a>
                    </li>
                    <?php for ($p = max(1, $halaman - 2); $p <= min($total_halaman, $halaman + 2); $p++): ?>
                        <li class="page-item <?= $p === $halaman ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($query_halaman, ['halaman' => $p])) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $halaman >= $total_halaman ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($query_halaman, ['halaman' => min($total_halaman, $halaman + 1)])) ?>">Berikutnya</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
