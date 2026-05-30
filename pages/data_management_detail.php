<?php
require_once __DIR__ . '/../config/db.php';
$db   = getDB();
$role = $_SESSION['user_role'] ?? '';
$isStaff = ($role === 'data_integration_staff');

if (!$isStaff) { header('Location: index.php?page=dashboard'); exit; }

$msg = ''; $msgType = '';
$tables = ['fact_sales','dim_game','dim_platform','dim_publisher','dim_genre','dim_time'];

$idColMap = [
    'fact_sales'    => 'id',
    'dim_game'      => 'game_id',
    'dim_platform'  => 'platform_id',
    'dim_publisher' => 'publisher_id',
    'dim_genre'     => 'genre_id',
    'dim_time'      => 'time_id'
];

$cols = [
    'fact_sales'    => ['id','game_id','platform_id','publisher_id','time_id','NA_Sales','EU_Sales','JP_Sales','Other_Sales','Global_Sales'],
    'dim_game'      => ['game_id','Name','Rating','Number of Reviews','Plays'],
    'dim_platform'  => ['platform_id','Platform'],
    'dim_publisher' => ['publisher_id','Publisher'],
    'dim_genre'     => ['genre_id','Genres'],
    'dim_time'      => ['time_id','Year'],
];

$searchableCols = [
    'fact_sales'    => 'id',
    'dim_game'      => 'Name',
    'dim_platform'  => 'Platform',
    'dim_publisher' => 'Publisher',
    'dim_genre'     => 'Genres',
    'dim_time'      => 'Year',
];

// Kolom-kolom yang wajib bertipe angka/integer/float agar tidak memicu Data Truncated
$numericCols = ['game_id', 'platform_id', 'publisher_id', 'time_id', 'Rating', 'Number of Reviews', 'Plays', 'Year', 'NA_Sales', 'EU_Sales', 'JP_Sales', 'Other_Sales', 'Global_Sales'];

$activeTable = $_GET['t'] ?? 'fact_sales';
if (!in_array($activeTable, $tables)) $activeTable = 'fact_sales';

// --- HANDLE POST CRUD DENGAN VALIDASI TIPE DATA (FIXED DATA TRUNCATED) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];

    // 1. ACTION DELETE
    if ($act === 'delete' && isset($_POST['table'], $_POST['row_id'])) {
        $table = $_POST['table'];
        if (isset($idColMap[$table])) {
            $idCol = $idColMap[$table];
            $id = (int)$_POST['row_id'];
            $db->query("DELETE FROM `$table` WHERE `$idCol` = $id");
            $msg = '✅ Data berhasil dihapus.'; $msgType = 'alert-s';
        }
    }

    // 2. ACTION EDIT (UPDATE)
    if ($act === 'edit' && isset($_POST['table'], $_POST['row_id'], $_POST['fields'])) {
        $table = $_POST['table'];
        $id = (int)$_POST['row_id'];
        $fields = $_POST['fields'];
        $idCol = $idColMap[$table];
        $sets = [];
        
        foreach ($fields as $col => $val) {
            $col = $db->real_escape_string($col);
            
            // Validasi proteksi Data Truncated untuk tipe angka
            if (in_array($col, $numericCols)) {
                $val = trim($val);
                if ($val === '' || !is_numeric($val)) {
                    $sets[] = "`$col` = 0"; // Ubah jadi 0 jika kosong/bukan angka bersih
                } else {
                    $sets[] = "`$col` = " . (float)$val;
                }
            } else {
                $val = $db->real_escape_string($val);
                $sets[] = "`$col` = '$val'";
            }
        }
        
        if (!empty($sets)) {
            $query = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$idCol` = $id";
            if($db->query($query)) { 
                $msg = "✅ Data berhasil diperbarui!"; 
                $msgType = "alert-s"; 
            } else {
                $msg = "❌ Gagal memperbarui data: " . $db->error;
                $msgType = "alert-e";
            }
        }
    }

    // 3. ACTION ADD (INSERT)
    if ($act === 'add' && isset($_POST['table'], $_POST['fields'])) {
        $table = $_POST['table'];
        if (isset($idColMap[$table])) {
            $fields = $_POST['fields'];
            $colsArr = []; $valsArr = [];
            
            foreach ($fields as $col => $val) {
                $colsArr[] = '`' . $db->real_escape_string($col) . '`';
                
                // Validasi proteksi Data Truncated untuk tipe angka
                if (in_array($col, $numericCols)) {
                    $val = trim($val);
                    if ($val === '' || !is_numeric($val)) {
                        $valsArr[] = "0";
                    } else {
                        $valsArr[] = (float)$val;
                    }
                } else {
                    $valsArr[] = "'" . $db->real_escape_string($val) . "'";
                }
            }
            
            $query = "INSERT INTO `$table` (" . implode(', ', $colsArr) . ") VALUES (" . implode(', ', $valsArr) . ")";
            if($db->query($query)) { 
                $msg = '✅ Data berhasil ditambahkan.'; 
                $msgType = 'alert-s'; 
            } else {
                $msg = "❌ Gagal menambahkan data: " . $db->error;
                $msgType = "alert-e";
            }
        }
    }
}

// Logika hitung total records terbaru
$stats = [];
foreach ($tables as $t) {
    $res = $db->query("SELECT COUNT(*) as cnt FROM `$t`") or die($db->error);
    $stats[$t] = (int)$res->fetch_assoc()['cnt'];
}

// Logika filter search & paginasi 50 data
$search = trim($_GET['search'] ?? '');
$searchCol = $searchableCols[$activeTable];
$where = $search ? " WHERE `$searchCol` LIKE '%" . $db->real_escape_string($search) . "%'" : '';

$countQuery = $db->query("SELECT COUNT(*) as filtered_cnt FROM `$activeTable` $where");
$totalFilteredRows = $countQuery->fetch_assoc()['filtered_cnt'] ?? 0;

$limit = 50; 
$totalPages = ceil($totalFilteredRows / $limit);
$currentPage = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($currentPage < 1) $currentPage = 1;
if ($currentPage > $totalPages && $totalPages > 0) $currentPage = $totalPages;
$offset = ($currentPage - 1) * $limit;

$rows = [];
$r = $db->query("SELECT * FROM `$activeTable` $where LIMIT $limit OFFSET $offset");
while ($row = $r->fetch_assoc()) $rows[] = $row;
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <a href="?page=data_management" class="btn btn-g" style="text-decoration:none; font-size:13px; padding:8px 14px;">⬅️ Kembali ke Portal Tabel</a>
    <div style="font-size:13px; color:var(--t3)">Total Baris Hasil Filter: <b><?= number_format($totalFilteredRows) ?></b> data</div>
</div>

<?php if ($msg): ?>
<div class="alert <?= $msgType ?> show" style="margin-bottom:16px"><?= $msg ?></div>
<?php endif; ?>

<div class="sc">
  <div class="sc-head">
    <div><h2>Dataset Detail Overview</h2><p>Isi data transaksional tabel: <b><?= $activeTable ?></b></p></div>
    <div style="display:flex; gap:10px; align-items:center">
      <form method="GET" style="display:flex; gap:8px; align-items:center">
        <input type="hidden" name="page" value="data_management_detail">
        <input type="hidden" name="t" value="<?= htmlspecialchars($activeTable) ?>">
        <div class="srch">🔍 <input name="search" placeholder="Cari..." value="<?= htmlspecialchars($search) ?>"></div>
        <button type="submit" class="btn btn-g">Cari</button>
      </form>
      <button class="btn btn-p" onclick="openAdd('<?= $activeTable ?>')">＋ Tambah Data</button>
    </div>
  </div>

  <div style="display:flex; gap:2px; padding:12px 18px 0; border-bottom:1px solid var(--border); overflow-x:auto;">
    <?php foreach ($tables as $t): ?>
    <a href="?page=data_management_detail&t=<?= $t ?>" 
       style="padding:10px 15px; font-size:11px; font-weight:700; text-decoration:none; 
              <?= $t===$activeTable ? 'border-bottom: 2px solid var(--accent); color:var(--accent)' : 'color:var(--t3)' ?>">
      <?= strtoupper($t) ?> (<?= $stats[$t] ?>)
    </a>
    <?php endforeach; ?>
  </div>

  <div style="overflow-x:auto">
  <table>
    <thead>
      <tr>
        <?php foreach ($cols[$activeTable] as $col): ?>
        <th><?= htmlspecialchars($col) ?></th>
        <?php endforeach; ?>
        <th style="width:100px">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
      <tr><td colspan="<?= count($cols[$activeTable])+1 ?>" style="text-align:center; padding:30px">Data Kosong</td></tr>
      <?php else: ?>
      <?php foreach ($rows as $row): ?>
      <tr>
        <?php foreach ($cols[$activeTable] as $col): ?>
        <td title="<?= htmlspecialchars($row[$col] ?? '') ?>">
          <?= htmlspecialchars($row[$col] ?? '-') ?>
        </td>
        <?php endforeach; ?>
        <td>
          <div class="t-acts">
            <button class="t-btn" onclick='openEdit("<?= $activeTable ?>", <?= json_encode($row) ?>)'>✏️</button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Hapus baris ini?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="table" value="<?= $activeTable ?>">
                <input type="hidden" name="row_id" value="<?= $row[$idColMap[$activeTable]] ?>">
                <button type="submit" class="t-btn">🗑</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<div style="display:flex; justify-content:center; align-items:center; margin:25px 0 10px; gap:6px;">
    <?php if ($currentPage > 1): ?>
        <a href="?page=data_management_detail&t=<?= $activeTable ?>&search=<?= urlencode($search) ?>&p=<?= $currentPage - 1 ?>" class="btn btn-g" style="text-decoration:none; padding:6px 12px; font-size:12.5px;">&lt;&lt; Prev</a>
    <?php else: ?>
        <span style="padding:6px 12px; font-size:12.5px; border:1px solid var(--border); color:var(--t3); background:var(--border); border-radius:4px; opacity:0.6; cursor:not-allowed;">&lt;&lt; Prev</span>
    <?php endif; ?>

    <?php
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $startPage + 4);
    if ($endPage - $startPage < 4) { $startPage = max(1, $endPage - 4); }
    for ($i = $startPage; $i <= $endPage; $i++):
    ?>
        <a href="?page=data_management_detail&t=<?= $activeTable ?>&search=<?= urlencode($search) ?>&p=<?= $i ?>" 
           style="padding:6px 12px; text-decoration:none; font-size:12.5px; border-radius:4px; border:1px solid <?= $i===$currentPage ? 'var(--accent)' : 'var(--border)' ?>; 
                  background:<?= $i===$currentPage ? 'var(--accent)' : 'var(--surface)' ?>; color:<?= $i===$currentPage ? '#fff' : 'var(--t1)' ?>; font-weight:bold;">
            <?= $i ?>
        </a>
    <?php endfor; ?>

    <?php if ($currentPage < $totalPages): ?>
        <a href="?page=data_management_detail&t=<?= $activeTable ?>&search=<?= urlencode($search) ?>&p=<?= $currentPage + 1 ?>" class="btn btn-g" style="text-decoration:none; padding:6px 12px; font-size:12.5px;">Next &gt;&gt;</a>
    <?php else: ?>
        <span style="padding:6px 12px; font-size:12.5px; border:1px solid var(--border); color:var(--t3); background:var(--border); border-radius:4px; opacity:0.6; cursor:not-allowed;">Next &gt;&gt;</span>
    <?php endif; ?>
</div>
<div style="text-align:center; font-size:11.5px; color:var(--t3); margin-bottom:20px;">
    Halaman <b><?= $currentPage ?></b> dari <b><?= number_format($totalPages) ?></b>
</div>
<?php endif; ?>

<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-head"><h3>✏️ Edit Record</h3><span class="modal-close" onclick="closeModal('editModal')">✕</span></div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="table" id="editTable">
      <input type="hidden" name="row_id" id="editId">
      <div id="editFields"></div>
      <div style="margin-top:20px; display:flex; gap:10px">
        <button type="submit" class="btn btn-p">Update Data</button>
        <button type="button" class="btn btn-g" onclick="closeModal('editModal')">Batal</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-head"><h3>➕ Tambah Data Baru</h3><span class="modal-close" onclick="closeModal('addModal')">✕</span></div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="table" id="addTable">
      <div id="addFields"></div>
      <div style="margin-top:20px; display:flex; gap:10px">
        <button type="submit" class="btn btn-p">Simpan Record</button>
        <button type="button" class="btn btn-g" onclick="closeModal('addModal')">Batal</button>
      </div>
    </form>
  </div>
</div>

<script>
const idColMap = <?= json_encode($idColMap) ?>;

function openEdit(table, rowData) {
    document.getElementById('editTable').value = table;
    const idKey = idColMap[table];
    document.getElementById('editId').value = rowData[idKey];
    
    let html = '';
    for (const [key, value] of Object.entries(rowData)) {
        if (key === idKey) continue;
        html += `<div class="fg">
            <label class="fl">${key.toUpperCase()}</label>
            <input type="text" name="fields[${key}]" value="${value ?? ''}" class="fi">
        </div>`;
    }
    document.getElementById('editFields').innerHTML = html;
    document.getElementById('editModal').classList.add('open');
}

const colsMap = <?= json_encode($cols) ?>;
function openAdd(table) {
    document.getElementById('addTable').value = table;
    const idKey = idColMap[table];
    let html = '';
    colsMap[table].forEach(col => {
        if (col === idKey) return;
        html += `<div class="fg">
            <label class="fl">${col.toUpperCase()}</label>
            <input type="text" name="fields[${col}]" placeholder="Input ${col}..." class="fi">
        </div>`;
    });
    document.getElementById('addFields').innerHTML = html;
    document.getElementById('addModal').classList.add('open');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}
</script>