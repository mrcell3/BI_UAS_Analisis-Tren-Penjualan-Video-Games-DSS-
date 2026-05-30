<?php
$page     = $_GET['page'] ?? 'dashboard';
$role     = $_SESSION['user_role'] ?? '';
$subPages = ['analytics_genre','analytics_platform','analytics_regional','analytics_publisher','analytics_rating'];
$subOpen  = in_array($page, $subPages);
?>
<aside class="sidebar" id="sb">
  <div class="s-logo">
    <div class="s-logo-icon">🎮</div>
    <div class="s-logo-text">Game Sales<br>DSS</div>
  </div>

  <nav class="s-nav">
    <a href="?page=dashboard" class="ni <?= $page==='dashboard' ? 'active' : '' ?>">
      <span class="ni-icon">⊞</span><span class="nl">Dashboard</span>
    </a>

    <div class="ni <?= $subOpen ? 'active' : '' ?>" onclick="toggleSub()" style="cursor:pointer">
      <span class="ni-icon">📊</span><span class="nl">Analytics</span>
    </div>
    
    <div class="sub" id="subMenu" style="display:<?= $subOpen ? 'block' : 'none' ?>">
      <a href="?page=analytics_genre"     class="si <?= $page==='analytics_genre'     ? 'active':'' ?>">🎭 Genre Analysis</a>
      <a href="?page=analytics_platform"  class="si <?= $page==='analytics_platform'  ? 'active':'' ?>">🖥 Platform Analysis</a>
      <a href="?page=analytics_regional"  class="si <?= $page==='analytics_regional'  ? 'active':'' ?>">🌍 Regional Analysis</a>
      <a href="?page=analytics_publisher" class="si <?= $page==='analytics_publisher' ? 'active':'' ?>">🏢 Publisher Analysis</a>
      <a href="?page=analytics_rating"    class="si <?= $page==='analytics_rating'    ? 'active':'' ?>">⭐ Rating Analysis</a>
    </div>

    <?php if ($role === 'data_integration_staff'): ?>
    <a href="?page=data_management" class="ni <?= ($page==='data_management' || $page==='data_management_detail') ? 'active':'' ?>">
      <span class="ni-icon">🗄</span><span class="nl">Data Management</span>
    </a>
    <?php endif; ?>

    <a href="?page=profile" class="ni <?= $page==='profile' ? 'active':'' ?>">
      <span class="ni-icon">👤</span><span class="nl">Profile</span>
    </a>
  </nav>

  <div class="s-foot">
    <a href="auth/logout.php" class="ni" style="color: var(--a3); margin-bottom: 10px;" onclick="return confirm('Yakin ingin keluar?')">
      <span class="ni-icon">⏻</span><span class="nl">Logout</span>
    </a>

    <div class="cb" onclick="toggleSB()">
      <span class="cb-icon">◀</span><span class="ct nl">Collapse</span>
    </div>
  </div>
</aside>

<script>
function toggleSub() {
    const m = document.getElementById('subMenu');
    if (m.style.display === "none" || m.style.display === "") {
        m.style.display = "block";
    } else {
        m.style.display = "none";
    }
}
</script>