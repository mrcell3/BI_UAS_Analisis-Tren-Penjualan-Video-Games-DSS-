<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// --- LOGIKA FILTER RANGE TAHUN ---
$startYear = $_GET['start_year'] ?? '1980'; 
$endYear   = $_GET['end_year']   ?? '2020'; 

$where = " WHERE t.Year BETWEEN " . (int)$startYear . " AND " . (int)$endYear;

// 1. Scatter Data (Hubungan Rating & Sales)
$scatterData = [];
$rS = $db->query("
    SELECT dgm.Rating, ROUND(SUM(f.Global_Sales), 2) as sales
    FROM fact_sales f
    JOIN dim_game dgm ON f.game_id = dgm.game_id
    JOIN dim_time t ON f.time_id = t.time_id
    $where AND dgm.Rating IS NOT NULL
    GROUP BY f.game_id, dgm.Rating
    HAVING sales > 0 ORDER BY sales DESC LIMIT 100
");
while ($row = $rS->fetch_assoc()) {
    $scatterData[] = ['x' => (float)$row['Rating'], 'y' => (float)$row['sales']];
}

// 2. Avg Rating per Year (Trend)
$ratTrend = [];
$rT = $db->query("
    SELECT t.Year, ROUND(AVG(dgm.Rating), 2) as avg_rating
    FROM fact_sales f
    JOIN dim_game dgm ON f.game_id = dgm.game_id
    JOIN dim_time t ON f.time_id = t.time_id
    WHERE dgm.Rating IS NOT NULL AND t.Year BETWEEN ".(int)$startYear." AND ".(int)$endYear."
    GROUP BY t.Year ORDER BY t.Year
");
while ($row = $rT->fetch_assoc()) $ratTrend[] = $row;

// 3. Avg Rating per Genre
$ratGenre = [];
$totalMainGenreSales = 0; // Menghitung total sales genre utama saja
$mainGenreCount = 0;      // Menghitung jumlah genre utama saja

$rG = $db->query("
    SELECT 
        CASE 
            WHEN dg.Genres LIKE '%Shooter%' THEN 'Shooter'
            WHEN dg.Genres LIKE '%Sport%' THEN 'Sports'
            WHEN dg.Genres LIKE '%RPG%' OR dg.Genres LIKE '%Role%' THEN 'Role-Playing'
            WHEN dg.Genres LIKE '%Racing%' THEN 'Racing'
            ELSE 'Other'
        END as gc,
        ROUND(AVG(dgm.Rating), 2) as avg_rating,
        ROUND(SUM(f.Global_Sales), 1) as total_sales
    FROM fact_sales f
    JOIN dim_game dgm ON f.game_id = dgm.game_id
    JOIN bridge_game_genre bg ON dgm.game_id = bg.game_id
    JOIN dim_genre dg ON bg.genre_id = dg.genre_id
    JOIN dim_time t ON f.time_id = t.time_id
    $where AND dgm.Rating IS NOT NULL
    GROUP BY gc
    ORDER BY avg_rating DESC
");
while ($row = $rG->fetch_assoc()) {
    $ratGenre[] = $row;
    
    // Saring agar data akumulasi 'Other' tidak merusak rata-rata batas penjualan
    if ($row['gc'] !== 'Other') {
        $totalMainGenreSales += $row['total_sales'];
        $mainGenreCount++;
    }
}

// 4. KPI Data
$rAvgFiltered = $db->query("SELECT ROUND(AVG(dgm.Rating), 2) as avg FROM fact_sales f JOIN dim_game dgm ON f.game_id = dgm.game_id JOIN dim_time t ON f.time_id = t.time_id $where AND dgm.Rating IS NOT NULL");
$avgRatingRange = $rAvgFiltered->fetch_assoc()['avg'] ?? 0;
$topRatedGenre  = $ratGenre[0]['gc'] ?? 'N/A';

// --- 📊 LOGIKA MATRIKS KUADRAN DSS DINAMIS SINKRON TOTAL ---
$ratingThreshold = 3.65; // Batas kualitas rating tinggi sesuai data grafikmu
$salesThreshold  = $mainGenreCount > 0 ? ($totalMainGenreSales / $mainGenreCount) : 0; // Batas penjualan rata-rata dinamis

$groups = [
    'high_rating_high_sales' => [],
    'high_rating_low_sales'  => [],
    'low_rating_high_sales'  => [],
    'low_rating_low_sales'   => []
];

foreach ($ratGenre as $rg) {
    $isHighRating = ($rg['avg_rating'] >= $ratingThreshold);
    
    // Kategori 'Other' otomatis High Sales karena gabungan sisa seluruh database
    $isHighSales  = ($rg['gc'] === 'Other') ? true : ($rg['total_sales'] >= $salesThreshold);

    if ($isHighRating && $isHighSales) {
        $groups['high_rating_high_sales'][] = $rg['gc'];
    } elseif ($isHighRating && !$isHighSales) {
        $groups['high_rating_low_sales'][] = $rg['gc'];
    } elseif (!$isHighRating && $isHighSales) {
        $groups['low_rating_high_sales'][] = $rg['gc'];
    } else {
        $groups['low_rating_low_sales'][] = $rg['gc'];
    }
}
?>

<div class="cc" style="margin-bottom:20px; padding:20px; background:var(--surface); border-radius:var(--r); border:1px solid var(--border);">
    <form method="GET" style="display:flex; flex-wrap:wrap; align-items:center; gap:20px">
        <input type="hidden" name="page" value="analytics_rating">
        <div style="display:flex; align-items:center; gap:10px">
            <div style="display:flex; flex-direction:column; gap:5px"><span class="fl">Dari Tahun</span>
                <input type="number" name="start_year" class="fi" value="<?= $startYear ?>" style="width:90px"></div>
            <div style="margin-top:20px; font-weight:bold; color:var(--border)">s/d</div>
            <div style="display:flex; flex-direction:column; gap:5px"><span class="fl">Sampai</span>
                <input type="number" name="end_year" class="fi" value="<?= $endYear ?>" style="width:90px"></div>
        </div>
        <div style="display:flex; align-items:flex-end; height:45px">
            <button type="submit" class="btn btn-p">Update Analisis</button>
            <a href="?page=analytics_rating" class="btn btn-g" style="margin-left:10px; text-decoration:none">Reset</a>
        </div>
    </form>
</div>

<div class="kpi-row k3">
  <div class="kpi ky"><span class="kpi-icon">⭐</span><div class="kpi-label">Highest Rated Genre</div><div class="kpi-val"><?= htmlspecialchars($topRatedGenre) ?></div><div class="kpi-sub">Top Genre di Periode Ini</div></div>
  <div class="kpi kp"><span class="kpi-icon">📊</span><div class="kpi-label">Avg Rating (Range)</div><div class="kpi-val"><?= $avgRatingRange ?></div><div class="kpi-sub">Rata-rata Thn <?= $startYear ?>-<?= $endYear ?></div></div>
  <div class="kpi kt"><span class="kpi-icon">📅</span><div class="kpi-label">Period</div><div class="kpi-val"><?= $startYear ?>-<?= $endYear ?></div><div class="kpi-sub">Rentang Waktu Analisis</div></div>
</div>

<div class="ins-card" style="margin-bottom: 20px; background: var(--surface); padding: 20px; border-radius: var(--r); border: 1px solid var(--border);">
    <div class="ins-title" style="font-weight: 700; font-size: 13px; margin-bottom: 12px;">📊 Status DSS — Rating & Sales Kuadran</div>
    
    <?php if (!empty($groups['high_rating_high_sales'])): ?>
    <div class="ins-item" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 13px;">
        <div class="dot dg"></div>
        <span><b><?= implode(', ', $groups['high_rating_high_sales']) ?> — High Rating High Sales:</b> Genre dengan kualitas rating tinggi dan sukses besar di pasar pada periode ini.</span>
    </div>
    <?php endif; ?>

    <?php if (!empty($groups['high_rating_low_sales'])): ?>
    <div class="ins-item" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 13px;">
        <div class="dot dy"></div>
        <span><b><?= implode(', ', $groups['high_rating_low_sales']) ?> — High Rating Low Sales:</b> Game berkualitas tinggi tetapi kurang optimal dalam komersial/penjualan pada era ini.</span>
    </div>
    <?php endif; ?>

    <?php if (!empty($groups['low_rating_high_sales'])): ?>
    <div class="ins-item" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 13px;">
        <div class="dot db" style="background: #38bdf8;"></div>
        <span><b><?= implode(', ', $groups['low_rating_high_sales']) ?> — Low Rating High Sales:</b> Rating genre cenderung biasa/rendah, namun performa volume penjualannya di pasar sangat tinggi.</span>
    </div>
    <?php endif; ?>

    <?php if (!empty($groups['low_rating_low_sales'])): ?>
    <div class="ins-item" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 13px;">
        <div class="dot dr" style="background: #ff5f6d;"></div>
        <span><b><?= implode(', ', $groups['low_rating_low_sales']) ?> — Low Rating Low Sales:</b> Genre dengan kualitas rating maupun tingkat penjualan yang berada di bawah standar performa era ini.</span>
    </div>
    <?php endif; ?>
</div>

<div class="cg c3">
  <div class="cc">
    <div class="cc-head"><div class="cc-title">📍 Rating vs Sales</div><div class="cc-sub">Sebaran kualitas game vs total unit terjual</div></div>
    <div class="cw"><canvas id="cRatScatter"></canvas></div>
  </div>
  <div class="cc">
    <div class="cc-head"><div class="cc-title">📊 Average Rating per Genre</div><div class="cc-sub">Perbandingan kepuasan pemain antar genre</div></div>
    <div class="cw tall"><canvas id="cRatGenre"></canvas></div>
  </div>
  <div class="cc">
    <div class="cc-head"><div class="cc-title">📈 Rating Trend</div><div class="cc-sub">Perkembangan kualitas game per tahun</div></div>
    <div class="cw tall"><canvas id="cRatTrend"></canvas></div>
  </div>
</div>

<script>
const colors=['#7c6aff','#00c9a7','#ff5f6d','#ffbb3b','#38bdf8'];

new Chart(document.getElementById('cRatScatter'), {
    type: 'scatter',
    data: { datasets: [{ label: 'Games', data: <?= json_encode($scatterData) ?>, backgroundColor: 'rgba(124,106,255,0.6)', pointRadius: 5 }] },
    options: { scales: { x: { title: { display: true, text: 'Rating' } }, y: { title: { display: true, text: 'Sales (M)' } } } }
});

const rgRaw = <?= json_encode($ratGenre) ?>;
new Chart(document.getElementById('cRatGenre'), {
    type: 'bar',
    data: { labels: rgRaw.map(d => d.gc), datasets: [{ label: 'Avg Rating', data: rgRaw.map(d => d.avg_rating), backgroundColor: colors, borderRadius: 5 }] },
    options: { indexAxis: 'y', scales: { x: { min: 2, max: 5 } }, plugins: { legend: { display: false } } }
});

const rtRaw = <?= json_encode($ratTrend) ?>;
new Chart(document.getElementById('cRatTrend'), {
    type: 'line',
    data: { labels: rtRaw.map(d => d.Year), datasets: [{ label: 'Avg Rating', data: rtRaw.map(d => d.avg_rating), borderColor: '#ffbb3b', backgroundColor: 'rgba(255,187,59,0.1)', fill: true, tension: 0.4 }] },
    options: { scales: { y: { min: 1, max: 5 } } }
});
</script>