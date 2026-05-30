<?php
session_start();

// Jika tidak ada session id, tendang ke login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page = $_GET['page'] ?? 'dashboard';
$role = $_SESSION['user_role'] ?? ''; 

// HALAMAN YANG BOLEH DIAKSES (Menambahkan halaman detail baru)
$allowed = [
    'dashboard',
    'analytics_genre',
    'analytics_platform',
    'analytics_regional',
    'analytics_publisher',
    'analytics_rating',
    'profile',
    'data_management',
    'data_management_detail' // Sub-halaman baru terdaftar
];

// REVISI POIN 1: Proteksi Mutlak. Manager/Selain Staff tidak diberi akses melihat isi manajemen data sama sekali.
if (($page === 'data_management' || $page === 'data_management_detail') && $role !== 'data_integration_staff') {
    header('Location: index.php?page=dashboard');
    exit;
}

if (!in_array($page, $allowed)) {
    $page = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Game Sales DSS — Cupcorn Entertainment</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main" id="main">
  <?php include 'includes/topbar.php'; ?>
  <div class="content">
    <?php include 'pages/' . $page . '.php'; ?>
  </div>
</div>

<script>
function toggleSB() {
  document.getElementById('sb').classList.toggle('col');
  document.getElementById('main').classList.toggle('col');
}
</script>
</body>
</html>