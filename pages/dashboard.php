<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// --- 1. FITUR FILTER RENTANG TAHUN (REVISI POIN 1) ---
$startYear = $_GET['start_year'] ?? '1980'; 
$endYear   = $_GET['end_year']   ?? '2020'; 

$whereClause = " WHERE t.Year BETWEEN " . (int)$startYear . " AND " . (int)$endYear;

// --- KPI: Peak Year ---
$peakYear = 0; $peakSales = 0;
$r = $db->query("SELECT t.Year, SUM(f.Global_Sales) as total FROM fact_sales f JOIN dim_time t ON f.time_id = t.time_id $whereClause GROUP BY t.Year ORDER BY total DESC LIMIT 1");
if ($row = $r->fetch_assoc()) {
    $peakYear  = $row['Year'];
    $peakSales = round($row['total'], 1);
}

// --- KPI: Top Platform ---
$topPlat = ''; $topPlatSales = 0;
$r = $db->query("SELECT p.Platform, SUM(f.Global_Sales) as total FROM fact_sales f JOIN dim_platform p ON f.platform_id = p.platform_id JOIN dim_time t ON f.time_id = t.time_id $whereClause GROUP BY p.Platform ORDER BY total DESC LIMIT 1");
if ($row = $r->fetch_assoc()) {
    $topPlat = $row['Platform'];
    $topPlatSales = round($row['total'], 1);
}

// --- KPI: Top Publisher ---
$topPub = ''; $topPubSales = 0;
$r = $db->query("SELECT p.Publisher, SUM(f.Global_Sales) as total FROM fact_sales f JOIN dim_publisher p ON f.publisher_id = p.publisher_id JOIN dim_time t ON f.time_id = t.time_id $whereClause GROUP BY p.Publisher ORDER BY total DESC LIMIT 1");
if ($row = $r->fetch_assoc()) {
    $topPub = $row['Publisher'];
    $topPubSales = round($row['total'], 1);
}

// --- KPI: Highest Regional Market ---
$r = $db->query("SELECT SUM(NA_Sales) as na, SUM(EU_Sales) as eu, SUM(JP_Sales) as jp, SUM(Other_Sales) as oth FROM fact_sales f JOIN dim_time t ON f.time_id = t.time_id $whereClause");
$reg = $r->fetch_assoc();
$regionMap = ['North America' => round($reg['na']??0, 1), 'Europe' => round($reg['eu']??0, 1), 'Japan' => round($reg['jp']??0, 1), 'Other' => round($reg['oth']??0, 1)];
arsort($regionMap);
$topRegion = array_key_first($regionMap);
$topRegionSales = reset($regionMap);

// --- CHART 1: Trend Data & Trend Logic ---
$trendData = [];
$r = $db->query("SELECT t.Year, ROUND(SUM(f.Global_Sales), 1) as total FROM fact_sales f JOIN dim_time t ON f.time_id = t.time_id WHERE t.Year BETWEEN " . (int)$startYear . " AND " . (int)$endYear . " GROUP BY t.Year ORDER BY t.Year");
while ($row = $r->fetch_assoc()) $trendData[] = $row;

$isTrendingUp = true;
if (count($trendData) >= 2) {
    $lastIndex = count($trendData) - 1;
    $currentYearData = $trendData[$lastIndex]['total'] ?? 0;
    $prevYearData = $trendData[$lastIndex - 1]['total'] ?? 0;
    $isTrendingUp = $currentYearData >= $prevYearData;
}

// --- CHART 2: Genre Data ---
$genreSalesData = [];
$r = $db->query("SELECT CASE WHEN g.Genres LIKE '%Shooter%' THEN 'Shooter' WHEN g.Genres LIKE '%Sport%' THEN 'Sports' WHEN g.Genres LIKE '%RPG%' OR g.Genres LIKE '%Role%' THEN 'Role-Playing' WHEN g.Genres LIKE '%Racing%' THEN 'Racing' ELSE 'Other' END as genre_clean, ROUND(SUM(f.Global_Sales), 1) as total FROM fact_sales f JOIN bridge_game_genre bg ON f.game_id = bg.game_id JOIN dim_genre g ON bg.genre_id = g.genre_id JOIN dim_time t ON f.time_id = t.time_id $whereClause GROUP BY genre_clean HAVING genre_clean != 'Other' ORDER BY total DESC LIMIT 8");
while ($row = $r->fetch_assoc()) $genreSalesData[] = $row;
$topGenre = $genreSalesData[0]['genre_clean'] ?? 'N/A';

// --- CHART 3: Regional Data ---
$regData = [];
$r = $db->query("SELECT t.Year, ROUND(SUM(f.NA_Sales), 1) as na, ROUND(SUM(f.EU_Sales), 1) as eu, ROUND(SUM(f.JP_Sales), 1) as jp, ROUND(SUM(f.Other_Sales), 1) as oth FROM fact_sales f JOIN dim_time t ON f.time_id = t.time_id WHERE t.Year BETWEEN " . (int)$startYear . " AND " . (int)$endYear . " GROUP BY t.Year ORDER BY t.Year");
while ($row = $r->fetch_assoc()) $regData[] = $row;

// --- CHART 4: Scatter Data ---
$scatterData = [];
$r = $db->query("SELECT g.Rating, ROUND(SUM(f.Global_Sales), 2) as sales FROM fact_sales f JOIN dim_game g ON f.game_id = g.game_id JOIN dim_time t ON f.time_id = t.time_id $whereClause AND g.Rating IS NOT NULL GROUP BY g.game_id, g.Rating HAVING sales > 0 ORDER BY sales DESC LIMIT 80");
while ($row = $r->fetch_assoc()) $scatterData[] = ['x' => (float)$row['Rating'], 'y' => (float)$row['sales']];
?>

<div class="yr-f" style="margin-bottom: 20px; background:var(--surface); padding:20px; border-radius:var(--r); border:1px solid var(--border);">
    <form method="GET" style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
        <input type="hidden" name="page" value="dashboard">
        <div style="display:flex; align-items:center; gap:10px;">
            <div style="display:flex; flex-direction:column; gap:5px;">
                <span class="fl" style="font-size:11px; font-weight:700; color:var(--t2);">Dari Tahun</span>
                <input type="number" name="start_year" class="fi" value="<?= $startYear ?>" style="width:90px; padding:6px;">
            </div>
            <div style="margin-top:20px; font-weight:bold; color:var(--border);">s/d</div>
            <div style="display:flex; flex-direction:column; gap:5px;">
                <span class="fl" style="font-size:11px; font-weight:700; color:var(--t2);">Sampai Tahun</span>
                <input type="number" name="end_year" class="fi" value="<?= $endYear ?>" style="width:90px; padding:6px;">
            </div>
        </div>
        <button type="submit" class="btn btn-p" style="margin-top:18px; padding:8px 16px;">Terapkan</button>
        <a href="?page=dashboard" class="btn btn-g" style="margin-top:18px; text-decoration:none; padding:8px 16px;">Reset</a>
    </form>
</div>

<div class="kpi-row k5">
  <div class="kpi kp"> <div class="kpi-tag">#1</div> <span class="kpi-icon">🎮</span> <div class="kpi-label">Top Genre</div> <div class="kpi-val"><?= htmlspecialchars($topGenre) ?></div> <div class="kpi-sub"><b><?= number_format($genreSalesData[0]['total'] ?? 0, 1) ?> M</b> Sales</div> </div>
  <div class="kpi kt"> <div class="kpi-tag">Peak</div> <span class="kpi-icon">📈</span> <div class="kpi-label">Peak Year</div> <div class="kpi-val"><?= $peakYear ?></div> <div class="kpi-sub"><b><?= $peakSales ?> M</b> Total Sales</div> </div>
  <div class="kpi ky"> <div class="kpi-tag">#1</div> <span class="kpi-icon">🖥</span> <div class="kpi-label">Top Platform</div> <div class="kpi-val"><?= htmlspecialchars($topPlat) ?></div> <div class="kpi-sub"><b><?= $topPlatSales ?> M</b> Sales</div> </div>
  <div class="kpi kr"> <div class="kpi-tag">#1</div> <span class="kpi-icon">🏢</span> <div class="kpi-label">Top Publisher</div> <div class="kpi-val"><?= htmlspecialchars($topPub) ?></div> <div class="kpi-sub"><b><?= $topPubSales ?> M</b> Sales</div> </div>
  <div class="kpi kg"> <span class="kpi-icon">🌍</span> <div class="kpi-label">Top Market</div> <div class="kpi-val"><?= htmlspecialchars($topRegion) ?></div> <div class="kpi-sub"><b><?= $topRegionSales ?> M</b> Sales</div> </div>
</div>

<div class="cg c6040">
  <div class="cc">
    <div class="cc-head"><div class="cc-title">📈 Tren Penjualan Tahunan</div><div class="cc-sub">Global sales per tahun (<?= $startYear ?>–<?= $endYear ?>)</div></div>
    <div class="cw tall"><canvas id="cTrend"></canvas></div>
    
    <div style="margin-top:15px; padding-top:15px; border-top:1px solid var(--border); font-size:12.5px;">
        <div style="margin-bottom:6px; color:var(--text);"><span style="color:#ffbb3b">💡</span> Penjualan periode ini sedang mengalami tren <b><?= $isTrendingUp ? 'meningkat' : 'menurun' ?></b> dibanding tahun sebelumnya.</div>
        <div style="color:var(--t2);"><span style="color:#00c9a7">🟢</span> <?= $isTrendingUp ? 'Luncurkan DLC atau konten tambahan untuk memaksimalkan momentum pasar.' : 'Lakukan inovasi gameplay atau promosi diskon untuk memicu kembali minat beli.' ?></div>
    </div>
  </div>
  
  <div class="cc">
    <div class="cc-head"><div class="cc-title">📊 Top Genre by Global Sales</div><div class="cc-sub">Total penjualan per kategori genre</div></div>
    <div class="cw tall"><canvas id="cGenreTop"></canvas></div>
    
    <div style="margin-top:15px; padding-top:15px; border-top:1px solid var(--border); font-size:12.5px;">
        <div style="margin-bottom:6px; color:var(--text);"><span style="color:#ffbb3b">💡</span> Genre <b><?= $topGenre ?></b> memimpin pasar dengan total volume <b><?= number_format($genreSalesData[0]['total'] ?? 0, 1) ?> M</b> unit.</div>
        <div style="color:var(--t2);"><span style="color:#00c9a7">🟢</span> Alokasikan lebih banyak sumber daya pada pengembangan proyek dengan kategori genre ini.</div>
    </div>
  </div>
</div>

<div class="cg c2">
  <div class="cc">
    <div class="cc-head"><div class="cc-title">🌍 Regional Distribution</div><div class="cc-sub">Penjualan per wilayah dari tahun ke tahun</div></div>
    <div class="cw"><canvas id="cRegion"></canvas></div>
    
    <div style="margin-top:15px; padding-top:15px; border-top:1px solid var(--border); font-size:12.5px;">
        <div style="margin-bottom:6px; color:var(--text);"><span style="color:#ffbb3b">💡</span> Wilayah <b><?= $topRegion ?></b> menjadi kontributor utama pendapatan dengan total <b><?= $topRegionSales ?> M</b>.</div>
        <div style="color:var(--t2);"><span style="color:#00c9a7">🟢</span> Tingkatkan ekspansi jangkauan pemasaran di wilayah target utama tersebut.</div>
    </div>
  </div>
  
  <div class="cc">
    <div class="cc-head"><div class="cc-title">📍 Rating vs Sales</div><div class="cc-sub">Sebaran data kualitas ulasan terhadap performa komersial game</div></div>
    <div class="cw"><canvas id="cScatter"></canvas></div>
    
    <div style="margin-top:15px; padding-top:15px; border-top:1px solid var(--border); font-size:12.5px;">
        <div style="margin-bottom:6px; color:var(--text);"><span style="color:#ffbb3b">💡</span> Korelasi antara rating ulasan dan angka penjualan global tergolong lemah.</div>
        <div style="color:var(--t2);"><span style="color:#00c9a7">🟢</span> Jangan hanya bertumpu pada kualitas game, terapkan strategi marketing agresif sejak tahap pra-rilis.</div>
    </div>
  </div>
</div>

<script>
const gc='rgba(0,0,0,0.05)', tc='#434d63', lc='#7e8aa8';
const C=['#7c6aff','#00c9a7','#ff5f6d','#ffbb3b','#38bdf8','#f97316','#a78bfa','#34d399'];

new Chart(document.getElementById('cTrend'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($trendData, 'Year')) ?>,
        datasets: [{ label: 'Sales (M)', data: <?= json_encode(array_column($trendData, 'total')) ?>, borderColor: '#7c6aff', backgroundColor: 'rgba(124,106,255,.05)', fill: true, tension: 0.4 }]
    },
    options: { responsive: true, maintainAspectRatio: true }
});

new Chart(document.getElementById('cGenreTop'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($genreSalesData, 'genre_clean')) ?>,
        datasets: [{ label: 'Sales (M)', data: <?= json_encode(array_column($genreSalesData, 'total')) ?>, backgroundColor: C, borderRadius: 5 }]
    },
    options: { indexAxis: 'y', plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('cRegion'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($regData, 'Year')) ?>,
        datasets: [
            { label: 'NA', data: <?= json_encode(array_column($regData, 'na')) ?>, backgroundColor: '#7c6aff' },
            { label: 'EU', data: <?= json_encode(array_column($regData, 'eu')) ?>, backgroundColor: '#00c9a7' },
            { label: 'JP', data: <?= json_encode(array_column($regData, 'jp')) ?>, backgroundColor: '#ff5f6d' }
        ]
    },
    options: { scales: { x: { stacked: true }, y: { stacked: true } } }
});

new Chart(document.getElementById('cScatter'), {
    type: 'scatter',
    data: { datasets: [{ label: 'Games', data: <?= json_encode($scatterData) ?>, backgroundColor: 'rgba(124,106,255,.5)' }] },
    options: { scales: { x: { title: { display: true, text: 'Rating' } }, y: { title: { display: true, text: 'Sales (M)' } } } }
});
</script>