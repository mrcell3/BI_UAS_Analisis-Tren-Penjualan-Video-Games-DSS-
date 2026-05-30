<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// --- LOGIKA FILTER RANGE TAHUN ---
$startYear = $_GET['start_year'] ?? '1980'; 
$endYear   = $_GET['end_year']   ?? '2020'; 

$where = " WHERE t.Year BETWEEN " . (int)$startYear . " AND " . (int)$endYear;

// 1. Top Publisher Ranking
$pubRankData = [];
$r = $db->query("
    SELECT p.Publisher, ROUND(SUM(f.Global_Sales), 1) as total,
           ROUND(SUM(f.NA_Sales), 1) as na, ROUND(SUM(f.EU_Sales), 1) as eu, ROUND(SUM(f.JP_Sales), 1) as jp
    FROM fact_sales f
    JOIN dim_publisher p ON f.publisher_id = p.publisher_id
    JOIN dim_time t ON f.time_id = t.time_id
    $where
    GROUP BY p.Publisher
    ORDER BY total DESC
    LIMIT 10
");
while ($row = $r->fetch_assoc()) $pubRankData[] = $row;

$topPub      = $pubRankData[0]['Publisher'] ?? 'N/A';
$topPubSales = $pubRankData[0]['total'] ?? 0;

// 2. Publisher Market Share
$pubTrend = [];
$targetPubs = [];
foreach (array_slice($pubRankData, 0, 3) as $pb) { $targetPubs[] = "'" . $db->real_escape_string($pb['Publisher']) . "'"; }
$pubListString = count($targetPubs) > 0 ? implode(",", $targetPubs) : "''";

$rT = $db->query("
    SELECT p.Publisher, t.Year, ROUND(SUM(f.Global_Sales), 1) as total
    FROM fact_sales f
    JOIN dim_publisher p ON f.publisher_id = p.publisher_id
    JOIN dim_time t ON f.time_id = t.time_id
    WHERE t.Year BETWEEN ".(int)$startYear." AND ".(int)$endYear."
    AND p.Publisher IN ($pubListString)
    GROUP BY p.Publisher, t.Year
    ORDER BY p.Publisher, t.Year
");
while ($row = $rT->fetch_assoc()) { $pubTrend[$row['Publisher']][$row['Year']] = (float)$row['total']; }
$trendYears = range((int)$startYear, (int)$endYear);
?>

<div class="cc" style="margin-bottom:20px; padding:20px; background:var(--surface); border-radius:var(--r); border:1px solid var(--border);">
    <form method="GET" style="display:flex; flex-wrap:wrap; align-items:center; gap:20px">
        <input type="hidden" name="page" value="analytics_publisher">
        <div style="display:flex; align-items:center; gap:10px">
            <div style="display:flex; flex-direction:column; gap:5px"><span class="fl">Dari Tahun</span>
                <input type="number" name="start_year" class="fi" value="<?= $startYear ?>" style="width:90px"></div>
            <div style="margin-top:20px; font-weight:bold; color:var(--border)">s/d</div>
            <div style="display:flex; flex-direction:column; gap:5px"><span class="fl">Sampai</span>
                <input type="number" name="end_year" class="fi" value="<?= $endYear ?>" style="width:90px"></div>
        </div>
        <div style="display:flex; align-items:flex-end; height:45px">
            <button type="submit" class="btn btn-p">Analisis Publisher</button>
            <a href="?page=analytics_publisher" class="btn btn-g" style="margin-left:10px; text-decoration:none">Reset</a>
        </div>
    </form>
</div>

<div class="kpi-row k3">
  <div class="kpi kp"><div class="kpi-tag">#1</div><span class="kpi-icon">🏢</span><div class="kpi-label">Top Publisher</div><div class="kpi-val"><?= htmlspecialchars($topPub) ?></div><div class="kpi-sub">Total: <b><?= $topPubSales ?> M</b> Sales</div></div>
  <div class="kpi ky"><span class="kpi-icon">📅</span><div class="kpi-label">Selected Range</div><div class="kpi-val"><?= $startYear ?> - <?= $endYear ?></div><div class="kpi-sub">Periode Analisis</div></div>
  <div class="kpi kt"><span class="kpi-icon">💰</span><div class="kpi-label">Top Volume Sales</div><div class="kpi-val"><?= number_format($topPubSales, 1) ?> M</div><div class="kpi-sub">Berdasarkan Filter Aktif</div></div>
</div>

<div class="cg c2">
  <div class="cc">
    <div class="cc-head"><div class="cc-title">📊 Top Publisher Performance</div><div class="cc-sub">Peringkat total unit terjual berdasarkan total global sales</div></div>
    <div class="cw tall"><canvas id="cPubRank"></canvas></div>
  </div>
  <div class="cc">
    <div class="cc-head"><div class="cc-title">📈 Publisher Sales Trend</div><div class="cc-sub">Tren perkembangan tahunan untuk 3 publisher teratas</div></div>
    <div class="cw tall"><canvas id="cPubTrend"></canvas></div>
  </div>
</div>

<script>
const colors=['#7c6aff','#00c9a7','#ff5f6d','#ffbb3b','#38bdf8','#f97316','#a78bfa','#34d399','#fb7185','#94a3b8'];
const pData = <?= json_encode($pubRankData) ?>;

new Chart(document.getElementById('cPubRank'), {
    type: 'bar',
    data: { labels: pData.map(d => d.Publisher), datasets: [{ data: pData.map(d => d.total), backgroundColor: colors, borderRadius: 5 }] },
    options: { indexAxis: 'y', plugins: { legend: { display: false } } }
});

const tY = <?= json_encode($trendYears) ?>;
const pT = <?= json_encode($pubTrend) ?>;
const activeP = Object.keys(pT);
new Chart(document.getElementById('cPubTrend'), {
    type: 'line',
    data: {
        labels: tY,
        datasets: activeP.map((p, i) => ({ label: p, data: tY.map(y => pT[p]?.[y] ?? 0), borderColor: colors[i % colors.length], fill: false, tension: 0.4 }))
    }
});
</script>