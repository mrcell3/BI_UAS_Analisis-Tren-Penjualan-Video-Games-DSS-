<?php
require_once __DIR__ . '/../config/db.php';
$db   = getDB();
$role = $_SESSION['user_role'] ?? '';
$isStaff = ($role === 'data_integration_staff');

// Proteksi tingkat halaman
if (!$isStaff) { header('Location: index.php?page=dashboard'); exit; }

$msg = ''; $msgType = '';

// Handle POST — Upload SQL ETL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_etl') {
    if (isset($_FILES['sql_file'])) {
        $file = $_FILES['sql_file'];
        if ($file['error'] === 0 && strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'sql') {
            $sql = file_get_contents($file['tmp_name']);
            $sql = preg_replace('/--.*?\n/', '', $sql);
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
            
            if ($db->multi_query($sql)) {
                do {
                    if ($res = $db->store_result()) { $res->free(); }
                } while ($db->more_results() && $db->next_result());
                $msg = "✅ Data ETL berhasil diupload ke data warehouse!";
                $msgType = "alert-s";
            } else {
                $msg = "❌ Gagal eksekusi SQL: " . $db->error;
                $msgType = "alert-e";
            }
        } else {
            $msg = '❌ File harus berformat .sql'; $msgType = 'alert-e';
        }
    }
}

// Pengambilan Data untuk Statistik & List Informasi Tabel
$tablesInfo = [
    'fact_sales'    => ['type' => 'Fact Table', 'desc' => 'Tabel utama pusat transaksi metrik penjualan game global.'],
    'dim_game'      => ['type' => 'Dimension Table', 'desc' => 'Metadata detail judul game, rating, jumlah ulasan, dan total plays.'],
    'dim_platform'  => ['type' => 'Dimension Table', 'desc' => 'Master rilis platform hardware / tipe konsol komersial.'],
    'dim_publisher' => ['type' => 'Dimension Table', 'desc' => 'Data direktori korporasi publisher penerbit video game.'],
    'dim_genre'     => ['type' => 'Dimension Table', 'desc' => 'Data master nama kategori genre klasifikasi game.'],
    'dim_time'      => ['type' => 'Dimension Table', 'desc' => 'Dimensi waktu kronologis rekam jejak tahun rilis game.']
];

$stats = [];
foreach (array_keys($tablesInfo) as $t) {
    $res = $db->query("SELECT COUNT(*) as cnt FROM `$t`") or die($db->error);
    $stats[$t] = (int)$res->fetch_assoc()['cnt'];
}
$totalRecords = array_sum($stats);
?>

<div class="dm-kpi-row">
  <div class="dm-kpi">
    <div class="dmki p">🗄</div>
    <div><div class="dm-val"><?= count($tablesInfo) ?></div><div class="dm-lbl">Total Tabel</div></div>
  </div>
  <div class="dm-kpi">
    <div class="dmki t">📄</div>
    <div><div class="dm-val"><?= number_format($totalRecords) ?></div><div class="dm-lbl">Total Records</div></div>
  </div>
  <div class="dm-kpi">
    <div class="dmki y">🔄</div>
    <div><div class="dm-val"><?= date('d M Y') ?></div><div class="dm-lbl">Terakhir Sinkronisasi</div></div>
  </div>
</div>

<?php if ($msg): ?>
<div class="alert <?= $msgType ?> show" style="margin-bottom:16px"><?= $msg ?></div>
<?php endif; ?>

<div class="sc">
  <div class="sc-head">
    <div>
      <h2>📤 Upload Hasil ETL</h2>
      <p>Sinkronisasi berkas data SQL langsung ke Data Warehouse</p>
    </div>
  </div>
  <div style="padding:18px">
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload_etl">
      <div class="upload-zone" onclick="document.getElementById('sqlFile').click()" id="dropZone">
        <div class="upload-zone-icon">📂</div>
        <div class="upload-zone-text">Klik atau drag & drop file SQL hasil ETL di sini</div>
        <div class="upload-zone-sub">Format: .sql</div>
      </div>
      <input type="file" id="sqlFile" name="sql_file" accept=".sql" style="display:none" onchange="showFileName(this)">
      <div id="fileNameDisplay" style="font-size:12.5px;color:var(--t2);margin-bottom:12px;display:none"></div>
      <button type="submit" class="btn btn-p">⬆ Upload & Sinkronisasi</button>
    </form>
  </div>
</div>

<div class="sc" style="margin-top:20px">
  <div class="sc-head">
    <div><h2>Data Warehouse Architecture</h2><p>Pilih tabel star schema untuk mengelola baris data internal.</p></div>
  </div>
  
  <div style="overflow-x:auto; padding:0 10px 15px;">
    <table style="width:100%; border-collapse:collapse; text-align:left;">
      <thead>
        <tr style="border-bottom:2px solid var(--border); color:var(--t1);">
          <th style="padding:12px">Nama Tabel Data Warehouse</th>
          <th style="padding:12px">Jenis Tabel</th>
          <th style="padding:12px">Jumlah Record</th>
          <th style="padding:12px">Deskripsi Skema</th>
          <th style="padding:12px; text-align:center;">Manajemen</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tablesInfo as $tName => $info): ?>
        <tr style="border-bottom:1px solid var(--border); color:var(--t2);" onmouseover="this.style.background='rgba(0,0,0,0.01)'" onmouseout="this.style.background='transparent'">
          <td style="padding:14px 12px; font-family:'JetBrains Mono', monospace; font-weight:700; color:var(--accent);"><?= $tName ?></td>
          <td style="padding:14px 12px;">
             <span style="font-size:11px; padding:4px 8px; border-radius:4px; font-weight:700; 
                          background:<?= $info['type']=='Fact Table' ? 'rgba(124,106,255,0.1)':'rgba(0,201,167,0.1)' ?>;
                          color:<?= $info['type']=='Fact Table' ? 'var(--accent)':'#00c9a7' ?>;">
                <?= $info['type'] ?>
             </span>
          </td>
          <td style="padding:14px 12px; font-weight:600;"><?= number_format($stats[$tName]) ?> baris</td>
          <td style="padding:14px 12px; color:var(--t3); font-size:13px;"><?= $info['desc'] ?></td>
          <td style="padding:14px 12px; text-align:center;">
            <a href="?page=data_management_detail&t=<?= $tName ?>" class="btn btn-g" style="text-decoration:none; font-size:12px; padding:6px 12px; display:inline-block;">Buka & Kelola Data</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function showFileName(input) {
    const d = document.getElementById('fileNameDisplay');
    if (input.files[0]) { d.style.display = 'block'; d.textContent = '📄 ' + input.files[0].name; }
}
</script>