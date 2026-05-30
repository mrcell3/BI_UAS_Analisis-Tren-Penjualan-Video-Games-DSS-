<?php
$page = $_GET['page'] ?? 'dashboard';
$titles = [
    'dashboard'           => ['Dashboard Overview',       'Ringkasan performa industri video game'],
    'analytics_genre'     => ['Genre Analysis',           'Analisis performa genre game'],
    'analytics_platform'  => ['Platform Analysis',        'Analisis performa platform game'],
    'analytics_regional'  => ['Regional Analysis',        'Analisis penjualan berdasarkan wilayah'],
    'analytics_publisher' => ['Publisher Analysis',       'Analisis performa publisher'],
    'analytics_rating'    => ['Rating Analysis',          'Analisis hubungan rating dan penjualan'],
    'data_management'     => ['Data Management',          'Kelola dan pantau data dalam sistem'],
    'profile'             => ['Profile',                  'Pengaturan akun pengguna'],
];

$t = $titles[$page] ?? ['Game Sales DSS', ''];

// Gunakan Null Coalescing (??) agar tidak error jika session kosong
$userName  = $_SESSION['user_name'] ?? 'Guest';
$userRole  = $_SESSION['user_role'] ?? 'guest';
$initials  = strtoupper(substr($userName, 0, 2));

$roleLabel = ($userRole === 'publisher_manager') ? 'Publisher Manager' : 'Data Integration Staff';
if ($userRole === 'guest') $roleLabel = 'Visitor';
?>

<div class="topbar">
  <div class="tb-left">
    <h1><?= $t[0] ?></h1>
    <p><?= $t[1] ?></p>
  </div>
  <div class="u-badge">
    <div class="u-av"><?= $initials ?></div>
    <div>
      <div class="u-name"><?= htmlspecialchars($userName) ?></div>
      <div class="u-role"><?= $roleLabel ?></div>
    </div>
  </div>
</div>