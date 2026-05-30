<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$msg = ''; $msgType = '';
$stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPass = $_POST['old_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($newPass !== $confirm) {
        $msg = 'Konfirmasi password tidak cocok.'; $msgType = 'alert-e';
    } elseif (strlen($newPass) < 6) {
        $msg = 'Password minimal 6 karakter.'; $msgType = 'alert-e';
    } else {
        $s2 = $db->prepare("SELECT password FROM users WHERE id = ?");
        $s2->bind_param('i', $_SESSION['user_id']);
        $s2->execute();
        $cur = $s2->get_result()->fetch_assoc();
        if (password_verify($oldPass, $cur['password'])) {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $upd  = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->bind_param('si', $hash, $_SESSION['user_id']);
            $upd->execute();
            $msg = 'Password berhasil diubah!'; $msgType = 'alert-s';
        } else {
            $msg = 'Password lama tidak sesuai.'; $msgType = 'alert-e';
        }
    }
}

$roleLabel = ($user['role'] === 'publisher_manager') ? 'Publisher Manager' : 'Data Integration Staff';
$initials  = strtoupper(substr($user['name'], 0, 2));
?>

<div class="pf-wrap">
  <div class="pf-head">
    <div class="pf-av"><?= htmlspecialchars($initials) ?></div>
    <div>
      <div class="pf-name"><?= htmlspecialchars($user['name']) ?></div>
      <div class="pf-role"><?= htmlspecialchars($roleLabel) ?></div>
      <div class="pf-email"><?= htmlspecialchars($user['email']) ?></div>
    </div>
  </div>

  <div class="pf-form">
    <h3 style="font-size:14px;font-weight:800;margin-bottom:16px">Informasi Akun</h3>
    <div class="fg"><label class="fl">Nama Pengguna</label><input class="fi" value="<?= htmlspecialchars($user['name']) ?>" disabled></div>
    <div class="fg"><label class="fl">Role</label><input class="fi" value="<?= htmlspecialchars($roleLabel) ?>" disabled></div>
    <div class="fg"><label class="fl">Email</label><input class="fi" value="<?= htmlspecialchars($user['email']) ?>" disabled></div>

    <div class="sep"></div>
    <h3 style="font-size:14px;font-weight:800;margin-bottom:16px">Ganti Password</h3>

    <?php if ($msg): ?>
    <div class="alert <?= $msgType ?> show"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="fg"><label class="fl">Password Lama</label><input class="fi" type="password" name="old_password" required></div>
      <div class="fg"><label class="fl">Password Baru</label><input class="fi" type="password" name="new_password" required></div>
      <div class="fg"><label class="fl">Konfirmasi Password</label><input class="fi" type="password" name="confirm_password" required></div>
      <div style="margin-top:12px">
        <button type="submit" class="btn btn-p">Simpan Perubahan</button>
        </div>
    </form>
  </div>
</div>