<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// --- LOGIKA FILTER RANGE TAHUN ---
$startYear = $_GET['start_year'] ?? '1980'; 
$endYear   = $_GET['end_year']   ?? '2020'; 

$where = " WHERE t.Year BETWEEN " . (int)$startYear . " AND " . (int)$endYear;

// 1. Total Sales per Region
$r = $db->query("SELECT ROUND(SUM(NA_Sales),1) as na, ROUND(SUM(EU_Sales),1) as eu, ROUND(SUM(JP_Sales),1) as jp, ROUND(SUM(Other_Sales),1) as oth FROM fact_sales f JOIN dim_time t ON f.time_id = t.time_id $where");
$totals = $r->fetch_assoc();

// Hitung total kumulatif global untuk pembagi persentase dinamis
$globalSalesTotal = ($totals['na'] ?? 0) + ($totals['eu'] ?? 0) + ($totals['jp'] ?? 0) + ($totals['oth'] ?? 0);

// Mencari region tertinggi untuk insight KPI
$regSales = ['North America' => $totals['na'], 'Europe' => $totals['eu'], 'Japan' => $totals['jp']];
arsort($regSales);
$topRegion = array_key_first($regSales);
$topRegionVal = reset($regSales);

// 2. Regional Trend
$regTrend = [];
$r = $db->query("
    SELECT t.Year,
        ROUND(SUM(f.NA_Sales), 1) as na,
        ROUND(SUM(f.EU_Sales), 1) as eu,
        ROUND(SUM(f.JP_Sales), 1) as jp,
        ROUND(SUM(f.Other_Sales), 1) as oth
    FROM fact_sales f
    JOIN dim_time t ON f.time_id = t.time_id
    $where
    GROUP BY t.Year
    ORDER BY t.Year
");
while ($row = $r->fetch_assoc()) $regTrend[] = $row;
$trendYears = range((int)$startYear, (int)$endYear);

// 3. Genre per Region (SUDAH DIUBAH AGAR SINKRON DENGAN GENRE ANALYSIS)
$regGenreData = [];
$r = $db->query("
    SELECT
        CASE
            WHEN g.Genres LIKE '%Adventure%' THEN 'Adventure'
            WHEN g.Genres LIKE '%Shooter%'   THEN 'Shooter'
            WHEN g.Genres LIKE '%Platform%'  THEN 'Platform'
            WHEN g.Genres LIKE '%RPG%' OR g.Genres LIKE '%Role%' THEN 'Role-Playing'
            ELSE 'Other'
        END as genre_clean,
        ROUND(SUM(f.NA_Sales), 1) as na,
        ROUND(SUM(f.EU_Sales), 1) as eu,
        ROUND(SUM(f.JP_Sales), 1) as jp,
        ROUND(SUM(f.Other_Sales), 1) as oth
    FROM fact_sales f
    JOIN bridge_game_genre bg ON f.game_id = bg.game_id
    JOIN dim_genre g ON bg.genre_id = g.genre_id
    JOIN dim_time t ON f.time_id = t.time_id
    $where
    GROUP BY genre_clean
    HAVING genre_clean IS NOT NULL
    ORDER BY (na + eu + jp + oth) DESC
    LIMIT 5
");
while ($row = $r->fetch_assoc()) $regGenreData[] = $row;

// --- 📊 LOGIKA STATUS DSS REGIONAL BERBASIS PERSENTASE DINAMIS ---
function getRegStatus($sales, $globalTotal) {
    $percentage = $globalTotal > 0 ? ($sales / $globalTotal) * 100 : 0;
    
    if ($percentage >= 25) {
        return [
            'label' => 'Major Market', 
            'desc' => 'Wilayah kontributor utama dengan pangsa pasar masif sebesar ' . round($percentage, 1) . '% dari total penjualan global.', 
            'color' => 'dg'
        ];
    }
    return [
        'label' => 'Specialized Market', 
        'desc' => 'Memiliki pangsa pasar lebih kecil (' . round($percentage, 1) . '%), namun memiliki preferensi platform dan genre yang lebih spesifik.', 
        'color' => 'dy'
    ];
}
?>

<div class="cc" style="margin-bottom:20px; padding:20px; background:var(--surface); border-radius:var(--r);">
    <form method="GET" style="display:flex; flex-wrap:wrap; align-items:center; gap:20px">
        <input type="hidden" name="page" value="analytics_regional">
        <div style="display:flex; align-items:center; gap:10px">
            <div style="display:flex; flex-direction:column; gap:5px"><span class="fl">Dari Tahun</span>
                <input type="number" name="start_year" class="fi" value="<?= $startYear ?>" style="width:90px"></div>
            <div style="margin-top:20px; font-weight:bold; color:var(--t3)">s/d</div>
            <div style="display:flex; flex-direction:column; gap:5px"><span class="fl">Sampai</span>
                <input type="number" name="end_year" class="fi" value="<?= $endYear ?>" style="width:90px"></div>
        </div>
        <div style="display:flex; align-items:flex-end; height:45px">
            <button type="submit" class="btn btn-p">Analisis Wilayah</button>
            <a href="?page=analytics_regional" class="btn btn-g" style="margin-left:10px; text-decoration:none">Reset</a>
        </div>
    </form>
</div>

<div class="kpi-row k3">
  <div class="kpi kp"><div class="kpi-tag">#1</div><span class="kpi-icon">🌍</span><div class="kpi-label">Top Region</div><div class="kpi-val"><?= $topRegion ?></div><div class="kpi-sub">Total: <b><?= number_format($topRegionVal, 1) ?> M</b> Sales</div></div>
  <div class="kpi ky"><span class="kpi-icon">📅</span><div class="kpi-label">Analysis Period</div><div class="kpi-val"><?= $startYear ?> - <?= $endYear ?></div><div class="kpi-sub">Rentang Waktu Filter</div></div>
  <div class="kpi kt"><span class="kpi-icon">💰</span><div class="kpi-label">Global Sales</div><div class="kpi-val"><?= number_format($globalSalesTotal, 1) ?> M</div><div class="kpi-sub">Total Seluruh Wilayah</div></div>
</div>

<div class="ins-card" style="margin-bottom: 20px; background: var(--surface); padding: 20px; border-radius: var(--r); border: 1px solid var(--border);">
    <div class="ins-title" style="font-weight: 700; font-size: 13px; margin-bottom: 12px;">📊 Status DSS — Regional Overview</div>
    <?php 
    $regions = ['North America' => $totals['na'], 'Europe' => $totals['eu'], 'Japan' => $totals['jp']];
    foreach($regions as $name => $val): 
        $status = getRegStatus($val, $globalSalesTotal);
    ?>
    <div class="ins-item" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 13px;">
        <div class="dot <?= $status['color'] ?>"></div>
        <span><b><?= $name ?> — <?= $status['label'] ?>:</b> <?= $status['desc'] ?></span>
    </div>
    <?php endforeach; ?>
</div>

<div class="cg c2">
  <div class="cc">
    <div class="cc-head"><div class="cc-title">🌍 Regional Sales Distribution</div><div class="cc-sub">Total penjualan per wilayah (<?= $startYear ?>-<?= $endYear ?>)</div></div>
    <div class="cw tall"><canvas id="cRegDoughnut"></canvas></div>
  </div>
  <div class="cc">
    <div class="cc-head"><div class="cc-title">📊 Genre per Region</div><div class="cc-sub">Distribusi genre di tiap wilayah pada periode terpilih</div></div>
    <div class="cw tall"><canvas id="cRegGenre"></canvas></div>
  </div>
  <div class="cc full">
    <div class="cc-head"><div class="cc-title">📈 Regional Trend</div><div class="cc-sub">Tren penjualan tiap wilayah per tahun</div></div>
    <div class="cw sh"><canvas id="cRegTrend"></canvas></div>
  </div>
</div>

<script>
const colors = ['#7c6aff','#00c9a7','#ff5f6d','#ffbb3b'];
const totals = <?= json_encode($totals) ?>;

new Chart(document.getElementById('cRegDoughnut'), {
    type: 'doughnut',
    data: {
        labels: ['North America','Europe','Japan','Other'],
        datasets: [{ data: [parseFloat(totals.na), parseFloat(totals.eu), parseFloat(totals.jp), parseFloat(totals.oth)],
            backgroundColor: colors, borderColor: '#fff', borderWidth: 2 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'right' } } }
});

const rgRaw = <?= json_encode($regGenreData) ?>;
new Chart(document.getElementById('cRegGenre'), {
    type: 'bar',
    data: {
        labels: ['North America','Europe','Japan','Other'],
        datasets: rgRaw.map((g, i) => ({
            label: g.genre_clean,
            data: [parseFloat(g.na), parseFloat(g.eu), parseFloat(g.jp), parseFloat(g.oth)],
            backgroundColor: ['#7c6aff','#00c9a7','#ff5f6d','#ffbb3b','#38bdf8'][i],
            borderRadius: 2
        }))
    },
    options: { scales: { x: { stacked: true }, y: { stacked: true } } }
});

const rtRaw = <?= json_encode($regTrend) ?>;
new Chart(document.getElementById('cRegTrend'), {
    type: 'line',
    data: {
        labels: rtRaw.map(d => d.Year),
        datasets: [
            { label: 'North America', data: rtRaw.map(d => parseFloat(d.na)),  borderColor: '#7c6aff', fill: false, tension: 0.4 },
            { label: 'Europe',        data: rtRaw.map(d => parseFloat(d.eu)),  borderColor: '#00c9a7', fill: false, tension: 0.4 },
            { label: 'Japan',         data: rtRaw.map(d => parseFloat(d.jp)),  borderColor: '#ff5f6d', fill: false, tension: 0.4 },
            { label: 'Other',         data: rtRaw.map(d => parseFloat(d.oth)), borderColor: '#ffbb3b', fill: false, tension: 0.4 },
        ]
    }
});
</script>