<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// --- LOGIKA FILTER RANGE TAHUN & PLATFORM ---
$startYear     = $_GET['start_year'] ?? '1980'; 
$endYear       = $_GET['end_year']   ?? '2020'; 
$filterPlat    = $_GET['platform']   ?? '';

$where = " WHERE t.Year BETWEEN " . (int)$startYear . " AND " . (int)$endYear;
if ($filterPlat) $where .= " AND p.Platform = '" . $db->real_escape_string($filterPlat) . "'";

// 1. Platform ranking
$platData = [];
$totalSalesSum = 0;
$platCount = 0;

$r = $db->query("
    SELECT p.Platform,
        ROUND(SUM(f.Global_Sales), 1) as total,
        ROUND(SUM(f.NA_Sales), 1) as na,
        ROUND(SUM(f.EU_Sales), 1) as eu,
        ROUND(SUM(f.JP_Sales), 1) as jp,
        ROUND(SUM(f.Other_Sales), 1) as oth
    FROM fact_sales f
    JOIN dim_platform p ON f.platform_id = p.platform_id
    JOIN dim_time t ON f.time_id = t.time_id
    $where
    GROUP BY p.Platform
    ORDER BY total DESC
    LIMIT 10
");
while ($row = $r->fetch_assoc()) {
    $platData[] = $row;
    $totalSalesSum += $row['total'];
    $platCount++;
}

$topPlat      = $platData[0]['Platform'] ?? 'N/A';
$topPlatSales = $platData[0]['total'] ?? 0;

// 2. Platform trend per tahun (Dinamis)
$platTrend = [];
$targetPlats = $filterPlat ? ["'$filterPlat'"] : [];
if (!$filterPlat) {
    foreach (array_slice($platData, 0, 5) as $pd) { $targetPlats[] = "'" . $pd['Platform'] . "'"; }
}
$platListString = count($targetPlats) > 0 ? implode(",", $targetPlats) : "''";

$rT = $db->query("
    SELECT p.Platform, t.Year, ROUND(SUM(f.Global_Sales), 1) as total
    FROM fact_sales f
    JOIN dim_platform p ON f.platform_id = p.platform_id
    JOIN dim_time t ON f.time_id = t.time_id
    WHERE t.Year BETWEEN ".(int)$startYear." AND ".(int)$endYear."
    AND p.Platform IN ($platListString)
    GROUP BY p.Platform, t.Year
    ORDER BY p.Platform, t.Year
");
while ($row = $rT->fetch_assoc()) {
    $platTrend[$row['Platform']][$row['Year']] = (float)$row['total'];
}
$trendYears = range((int)$startYear, (int)$endYear);

// --- 📊 REVISI: LOGIKA STATUS DSS DINAMIS BERDASARKAN FILTER TAHUN ---
$averageSalesThreshold = $platCount > 0 ? ($totalSalesSum / $platCount) : 0;

function getPlatStatus($plat, $sales, $threshold) {
    // Jika penjualan platform di atas rata-rata industri pada era tersebut
    if ($sales >= $threshold) {
        return [
            'label' => 'Stable Market', 
            'desc' => 'Platform mendominasi pasar pada era ini dengan angka penjualan di atas rata-rata industri (' . number_format($threshold, 1) . ' M) serta basis pengguna yang besar.', 
            'color' => 'dg', 
            'icon' => '🟢'
        ];
    }
    // Jika di bawah rata-rata
    return [
        'label' => 'Casual Market', 
        'desc' => 'Volume penjualan di bawah rata-rata era ini (' . number_format($threshold, 1) . ' M), cenderung bergerak stabil pada segmentasi game kasual dan keluarga.', 
        'color' => 'dy', 
        'icon' => '🟡'
    ];
}
?>

<div class="cc" style="margin-bottom:20px; padding:20px; background:var(--surface); border-radius:var(--r);">
    <form method="GET" style="display:flex; flex-wrap:wrap; align-items:center; gap:20px">
        <input type="hidden" name="page" value="analytics_platform">
        <div style="display:flex; align-items:center; gap:10px">
            <div style="display:flex; flex-direction:column; gap:5px"><span class="fl">Dari Tahun</span>
                <input type="number" name="start_year" class="fi" value="<?= $startYear ?>" style="width:90px"></div>
            <div style="margin-top:20px; font-weight:bold; color:var(--t3)">s/d</div>
            <div style="display:flex; flex-direction:column; gap:5px"><span class="fl">Sampai</span>
                <input type="number" name="end_year" class="fi" value="<?= $endYear ?>" style="width:90px"></div>
        </div>
        <div style="display:flex; flex-direction:column; gap:5px"><span class="fl">Pilih Platform</span>
            <select name="platform" class="fi" style="width:150px">
                <option value="">Semua Platform</option>
                <?php 
                $pListAll = $db->query("SELECT Platform FROM dim_platform ORDER BY Platform ASC");
                while($p = $pListAll->fetch_assoc()): ?>
                    <option value="<?= $p['Platform'] ?>" <?= $filterPlat==$p['Platform']?'selected':'' ?>><?= $p['Platform'] ?></option>
                <?php endwhile; ?>
            </select></div>
        <div style="display:flex; align-items:flex-end; height:45px">
            <button type="submit" class="btn btn-p">Analisis Platform</button>
            <a href="?page=analytics_platform" class="btn btn-g" style="margin-left:10px; text-decoration:none">Reset</a>
        </div>
    </form>
</div>

<div class="kpi-row k3">
  <div class="kpi kp"><div class="kpi-tag">#1</div><span class="kpi-icon">🎮</span><div class="kpi-label"><?= $filterPlat ? 'Selected' : 'Top' ?> Platform</div><div class="kpi-val"><?= $topPlat ?></div><div class="kpi-sub">Total: <b><?= $topPlatSales ?> M</b> Sales</div></div>
  <div class="kpi ky"><span class="kpi-icon">🌍</span><div class="kpi-label">Selected Range</div><div class="kpi-val"><?= $startYear ?> - <?= $endYear ?></div><div class="kpi-sub">Periode Analisis</div></div>
  <div class="kpi kt"><span class="kpi-icon">💰</span><div class="kpi-label">Global Sales Volume</div><div class="kpi-val"><?= number_format($topPlatSales, 1) ?> M</div><div class="kpi-sub">Volume pada Filter Aktif</div></div>
</div>

<div class="ins-card" style="margin-bottom: 20px; background: var(--surface); padding: 20px; border-radius: var(--r); border: 1px solid var(--border);">
    <div class="ins-title" style="font-weight: 700; font-size: 14px; margin-bottom: 12px;">📊 Platform Status — <?= $filterPlat ?: 'Platform Overview' ?></div>
    <?php 
    $displayCount = 0;
    foreach($platData as $data): 
        if($displayCount >= ($filterPlat ? 1 : 3)) break;
        $status = getPlatStatus($data['Platform'], $data['total'], $averageSalesThreshold);
    ?>
    <div class="ins-item" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 13px;">
        <div class="dot <?= $status['color'] ?>"></div>
        <span><b><?= $data['Platform'] ?> — <?= $status['label'] ?>:</b> <?= $status['desc'] ?></span>
    </div>
    <?php $displayCount++; endforeach; ?>
</div>

<div class="cg c2">
  <div class="cc">
    <div class="cc-head">
        <div class="cc-title">📊 Platform Sales Ranking</div>
        <div class="cc-sub">Total penjualan global per platform</div>
    </div>
    <div class="cw tall"><canvas id="cPlatRank"></canvas></div>
  </div>
  <div class="cc">
    <div class="cc-head">
        <div class="cc-title">📈 Platform Trend by Year</div>
        <div class="cc-sub">Tren penjualan per tahun (<?= $startYear ?>-<?= $endYear ?>)</div>
    </div>
    <div class="cw tall"><canvas id="cPlatTrend"></canvas></div>
  </div>
  <div class="cc full">
    <div class="cc-head">
        <div class="cc-title">🌍 Platform Region Distribution</div>
        <div class="cc-sub">Distribusi penjualan per wilayah untuk platform pilihan</div>
    </div>
    <div class="cw sh"><canvas id="cPlatRegion"></canvas></div>
  </div>
</div>

<script>
const colors=['#7c6aff','#00c9a7','#ff5f6d','#ffbb3b','#38bdf8','#f97316','#a78bfa','#34d399','#fb7185','#94a3b8'];
const pData = <?= json_encode($platData) ?>;

// 1. Ranking
new Chart(document.getElementById('cPlatRank'), {
    type: 'bar',
    data: {
        labels: pData.map(d => d.Platform),
        datasets: [{ label: 'Sales (M)', data: pData.map(d => parseFloat(d.total)), backgroundColor: colors, borderRadius: 5 }]
    },
    options: { indexAxis: 'y', plugins: { legend: { display: false } } }
});

// 2. Trend
const tY = <?= json_encode($trendYears) ?>;
const pT = <?= json_encode($platTrend) ?>;
const activeP = Object.keys(pT);
new Chart(document.getElementById('cPlatTrend'), {
    type: 'line',
    data: {
        labels: tY,
        datasets: activeP.map((p, i) => ({
            label: p,
            data: tY.map(y => pT[p]?.[y] ?? 0),
            borderColor: colors[i % colors.length], fill: false, tension: 0.4, borderWidth: 3
        }))
    }
});

// 3. Region
new Chart(document.getElementById('cPlatRegion'), {
    type: 'bar',
    data: {
        labels: pData.map(d => d.Platform),
        datasets: [
            { label: 'NA', data: pData.map(d => d.na), backgroundColor: '#7c6aff' },
            { label: 'EU', data: pData.map(d => d.eu), backgroundColor: '#00c9a7' },
            { label: 'JP', data: pData.map(d => d.jp), backgroundColor: '#ff5f6d' }
        ]
    },
    options: { scales: { x: { stacked: true }, y: { stacked: true } } }
});
</script>