<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// --- LOGIKA FILTER RANGE TAHUN & GENRE ---
$startYear   = $_GET['start_year'] ?? '1980'; 
$endYear     = $_GET['end_year']   ?? '2020'; 
$filterGenre = $_GET['genre'] ?? '';

$where = " WHERE t.Year BETWEEN " . (int)$startYear . " AND " . (int)$endYear;
if ($filterGenre) $where .= " AND dg.Genres LIKE '%" . $db->real_escape_string($filterGenre) . "%'";

// 1. Genre sales ranking (MURNI KODE ASLI KAMU)
$genreRankData = [];
$r = $db->query("
    SELECT
        CASE
            WHEN dg.Genres LIKE '%Shooter%'      THEN 'Shooter'
            WHEN dg.Genres LIKE '%Sport%'        THEN 'Sports'
            WHEN dg.Genres LIKE '%RPG%' OR dg.Genres LIKE '%Role%' THEN 'Role-Playing'
            WHEN dg.Genres LIKE '%Racing%'       THEN 'Racing'
            WHEN dg.Genres LIKE '%Platform%'     THEN 'Platform'
            WHEN dg.Genres LIKE '%Fighting%'     THEN 'Fighting'
            WHEN dg.Genres LIKE '%Simulation%'   THEN 'Simulation'
            WHEN dg.Genres LIKE '%Puzzle%'       THEN 'Puzzle'
            WHEN dg.Genres LIKE '%Adventure%'    THEN 'Adventure'
            WHEN dg.Genres LIKE '%Strategy%'     THEN 'Strategy'
            ELSE 'Other'
        END as genre_clean,
        ROUND(SUM(f.Global_Sales), 1) as total,
        ROUND(SUM(f.NA_Sales), 1) as na,
        ROUND(SUM(f.EU_Sales), 1) as eu,
        ROUND(SUM(f.JP_Sales), 1) as jp
    FROM fact_sales f
    JOIN bridge_game_genre bg ON f.game_id = bg.game_id
    JOIN dim_genre dg ON bg.genre_id = dg.genre_id
    JOIN dim_time t ON f.time_id = t.time_id
    $where
    GROUP BY genre_clean
    HAVING genre_clean != 'Other'
    ORDER BY total DESC
    LIMIT 10
");
while ($row = $r->fetch_assoc()) $genreRankData[] = $row;

$topGenre      = $genreRankData[0]['genre_clean'] ?? 'N/A';
$topGenreSales = $genreRankData[0]['total'] ?? 0;

// 2. Genre trend per tahun (LOGIKA DINAMIS: Menampilkan Top 3 atau 1 Pilihan User)
$targetGenres = $filterGenre ? ["'$filterGenre'"] : [];
if (!$filterGenre) {
    // Otomatis mengambil Top 3 teratas dari hasil ranking agar grafik rapi
    foreach (array_slice($genreRankData, 0, 3) as $gd) { 
        $targetGenres[] = "'" . $gd['genre_clean'] . "'"; 
    }
}
$genreListString = implode(",", $targetGenres);

// (DI SINI LANGSUNG DITAMBAHKAN ADVENTURE & PLATFORM AGAR SQL TIDAK MEMBUANG DATANYA)
$genreTrend = [];
$rT = $db->query("
    SELECT
        CASE
            WHEN dg.Genres LIKE '%Shooter%'      THEN 'Shooter'
            WHEN dg.Genres LIKE '%Sport%'        THEN 'Sports'
            WHEN dg.Genres LIKE '%RPG%' OR dg.Genres LIKE '%Role%' THEN 'Role-Playing'
            WHEN dg.Genres LIKE '%Racing%'       THEN 'Racing'
            WHEN dg.Genres LIKE '%Platform%'     THEN 'Platform'
            WHEN dg.Genres LIKE '%Fighting%'     THEN 'Fighting'
            WHEN dg.Genres LIKE '%Simulation%'   THEN 'Simulation'
            WHEN dg.Genres LIKE '%Puzzle%'       THEN 'Puzzle'
            WHEN dg.Genres LIKE '%Adventure%'    THEN 'Adventure'
            WHEN dg.Genres LIKE '%Strategy%'     THEN 'Strategy'
            ELSE 'Other'
        END as gc,
        t.Year, ROUND(SUM(f.Global_Sales), 1) as total
    FROM fact_sales f
    JOIN bridge_game_genre bg ON f.game_id = bg.game_id
    JOIN dim_genre dg ON bg.genre_id = dg.genre_id
    JOIN dim_time t ON f.time_id = t.time_id
    WHERE t.Year BETWEEN ".(int)$startYear." AND ".(int)$endYear."
    GROUP BY gc, t.Year
    HAVING gc IN ($genreListString)
    ORDER BY gc, t.Year
");
while ($row = $rT->fetch_assoc()) { $genreTrend[$row['gc']][$row['Year']] = (float)$row['total']; }
$trendYears = range((int)$startYear, (int)$endYear);

// 3. Avg rating (MURNI KODE ASLI KAMU)
$rR = $db->query("SELECT AVG(Rating) as avg FROM dim_game WHERE Rating IS NOT NULL");
$avgRating = round($rR->fetch_assoc()['avg'] ?? 0, 2);

// 4. Scatter data (MURNI KODE ASLI KAMU)
$scatterData = [];
$rS = $db->query("
    SELECT dgm.Rating, ROUND(SUM(f.Global_Sales), 2) as sales
    FROM fact_sales f
    JOIN dim_game dgm ON f.game_id = dgm.game_id
    JOIN bridge_game_genre bg ON f.game_id = bg.game_id
    JOIN dim_genre dg ON bg.genre_id = dg.genre_id
    JOIN dim_time t ON f.time_id = t.time_id
    $where AND dgm.Rating IS NOT NULL
    GROUP BY f.game_id, dgm.Rating
    HAVING sales > 0 ORDER BY sales DESC LIMIT 80
");
while ($row = $rS->fetch_assoc()) { $scatterData[] = ['x' => (float)$row['Rating'], 'y' => (float)$row['sales']]; }

// --- LOGIKA STATUS DSS (MURNI KODE ASLI KAMU) ---
function getDssStatus($genre, $sales, $trendArray) {
    if ($sales < 50 && $sales > 0) return ['label' => 'Niche Market', 'desc' => 'Memiliki pasar terbatas dengan total sales rendah.', 'color' => 'dr', 'icon' => '🔴'];
    if (isset($trendArray[$genre])) {
        $data = $trendArray[$genre];
        $years = array_keys($data);
        if (count($years) >= 2) {
            $last = end($data); $prev = prev($data);
            if ($last < $prev) return ['label' => 'Declining', 'desc' => 'Mengalami penurunan tren penjualan dalam beberapa tahun terakhir.', 'color' => 'dy', 'icon' => '🟡'];
        }
    }
    return ['label' => 'High Potential', 'desc' => 'Penjualan tinggi dan stabil di berbagai wilayah.', 'color' => 'dg', 'icon' => '🟢'];
}
?>

<div class="cc" style="margin-bottom:20px; padding:20px; background:var(--surface); border-radius:var(--r);">
    <form method="GET" style="display:flex; flex-wrap:wrap; align-items:center; gap:20px">
        <input type="hidden" name="page" value="analytics_genre">
        <div style="display:flex; align-items:center; gap:10px">
            <div style="display:flex; flex-direction:column; gap:5px"><span class="fl">Dari Tahun</span><input type="number" name="start_year" class="fi" value="<?= $startYear ?>" style="width:90px"></div>
            <div style="margin-top:20px; font-weight:bold; color:var(--t3)">s/d</div>
            <div style="display:flex; flex-direction:column; gap:5px"><span class="fl">Sampai</span><input type="number" name="end_year" class="fi" value="<?= $endYear ?>" style="width:90px"></div>
        </div>
        <div style="display:flex; flex-direction:column; gap:5px"><span class="fl">Pilih Genre</span><select name="genre" class="fi" style="width:160px"><option value="">Semua Genre</option>
            <?php foreach(['Shooter','Sports','Role-Playing','Adventure','Racing','Platform','Simulation','Puzzle','Strategy','Fighting'] as $gItem): ?>
                <option value="<?= $gItem ?>" <?= ($filterGenre == $gItem) ? 'selected' : '' ?>><?= $gItem ?></option>
            <?php endforeach; ?>
        </select></div>
        <div style="display:flex; align-items:flex-end; height:45px"><button type="submit" class="btn btn-p">Analisis Genre</button><a href="?page=analytics_genre" class="btn btn-g" style="margin-left:10px; text-decoration:none">Reset</a></div>
    </form>
</div>

<div class="kpi-row k3">
  <div class="kpi kp"><div class="kpi-tag">#1</div><span class="kpi-icon">🎭</span><div class="kpi-label"><?= $filterGenre ? 'Selected Genre' : 'Top Genre' ?></div><div class="kpi-val"><?= htmlspecialchars($topGenre) ?></div><div class="kpi-sub">Total: <b><?= number_format($topGenreSales, 1) ?> M</b> Sales</div></div>
  <div class="kpi ky"><span class="kpi-icon">⭐</span><div class="kpi-label">Avg Rating</div><div class="kpi-val"><?= $avgRating ?></div><div class="kpi-sub">Kualitas Industri</div></div>
  <div class="kpi kt"><span class="kpi-icon">💰</span><div class="kpi-label">Range Sales</div><div class="kpi-val"><?= number_format($topGenreSales, 1) ?> M </div><div class="kpi-sub"><?= $startYear ?> - <?= $endYear ?></div></div>
</div>

<div class="ins-card" style="margin-bottom: 20px; background: var(--surface); padding: 20px; border-radius: var(--r); border: 1px solid var(--border);">
    <div class="ins-title" style="font-weight: 700; font-size: 14px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">📊 Status DSS — <?= $filterGenre ?: 'Genre Overview' ?></div>
    <?php 
    $displayCount = 0;
    foreach($genreRankData as $data): 
        if($displayCount >= ($filterGenre ? 1 : 3)) break;
        $status = getDssStatus($data['genre_clean'], $data['total'], $genreTrend);
    ?>
    <div class="ins-item" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 13px;">
        <div class="dot <?= $status['color'] ?>"></div>
        <span><b><?= $data['genre_clean'] ?> — <?= $status['label'] ?>:</b> <?= $status['desc'] ?></span>
    </div>
    <?php $displayCount++; endforeach; ?>
</div>

<div class="cg c2">
  <div class="cc">
    <div class="cc-head"><div class="cc-title">📊 Genre Sales Ranking</div><div class="cc-sub">Total penjualan global per genre</div></div>
    <div class="cw tall"><canvas id="cGenreRank"></canvas></div>
  </div>
  <div class="cc">
    <div class="cc-head"><div class="cc-title">📈 Genre Sales Trend per Year</div><div class="cc-sub">Tren perkembangan genre dari waktu ke waktu</div></div>
    <div class="cw tall"><canvas id="cGenreTrend"></canvas></div>
  </div>
  <div class="cc">
    <div class="cc-head"><div class="cc-title">🌍 Genre Distribution by Region</div><div class="cc-sub">Melihat genre dominan di tiap wilayah</div></div>
    <div class="cw"><canvas id="cGenreRegion"></canvas></div>
  </div>
  <div class="cc">
    <div class="cc-head"><div class="cc-title">📍 Genre Rating vs Sales</div><div class="cc-sub">Hubungan kualitas genre dan penjualan</div></div>
    <div class="cw"><canvas id="cGenreScatter"></canvas></div>
  </div>
</div>

<script>
const colors=['#7c6aff','#00c9a7','#ff5f6d','#ffbb3b','#38bdf8','#f97316','#a78bfa','#34d399','#fb7185','#94a3b8'];
const gData = <?= json_encode($genreRankData) ?>;
new Chart(document.getElementById('cGenreRank'), { type: 'bar', data: { labels: gData.map(d => d.genre_clean), datasets: [{ data: gData.map(d => d.total), backgroundColor: colors, borderRadius: 5 }] }, options: { indexAxis: 'y', plugins: { legend: { display: false } } } });
const tY = <?= json_encode($trendYears) ?>;
const gT = <?= json_encode($genreTrend) ?>;
const activeG = Object.keys(gT);
new Chart(document.getElementById('cGenreTrend'), { type: 'line', data: { labels: tY, datasets: activeG.map((g, i) => ({ label: g, data: tY.map(y => gT[g]?.[y] ?? 0), borderColor: colors[i % colors.length], fill: false, tension: 0.4, borderWidth: 3 })) } });
new Chart(document.getElementById('cGenreRegion'), { type: 'bar', data: { labels: gData.slice(0, 5).map(d => d.genre_clean), datasets: [{ label: 'NA', data: gData.slice(0, 5).map(d => d.na), backgroundColor: '#7c6aff' },{ label: 'EU', data: gData.slice(0, 5).map(d => d.eu), backgroundColor: '#00c9a7' },{ label: 'JP', data: gData.slice(0, 5).map(d => d.jp), backgroundColor: '#ff5f6d' }] }, options: { scales: { x: { stacked: true }, y: { stacked: true } } } });
new Chart(document.getElementById('cGenreScatter'), { type: 'scatter', data: { datasets: [{ label: 'Games', data: <?= json_encode($scatterData) ?>, backgroundColor: 'rgba(124,106,255,0.5)' }] }, options: { scales: { x: { title: { display: true, text: 'Rating' } }, y: { title: { display: true, text: 'Sales' } } } } });
</script>