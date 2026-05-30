<?php
session_start();

// Jika sudah login, langsung lempar ke index
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/db.php';
    $db    = getDB();
    
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    // Ambil data user berdasarkan email
    $stmt = $db->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Verifikasi password (Demo: 'password')
    if ($user && $pass === 'password') {
        // Set session
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        
        header('Location: index.php');
        exit;
    } else {
        $error = 'Email atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login — Game Sales DSS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{
            /* Warna Light Mode */
            --bg:#f4f7fa;
            --surface:#ffffff;
            --s2:#f8fafc;
            --border:#e2e8f0;
            --accent:#7c6aff;
            --a2:#00c9a7;
            --text:#1e293b;
            --t2:#64748b;
            --t3:#94a3b8;
            --red:#ef4444;
            --r:16px;
            --r2:10px;
            --font:'Plus Jakarta Sans',sans-serif;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }
        body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center}
        .wrap{width:100%;max-width:420px;padding:24px}
        .logo{text-align:center;margin-bottom:32px}
        .logo-icon{width:64px;height:64px;background:linear-gradient(135deg,var(--accent),var(--a2));border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 16px;box-shadow: 0 10px 15px -3px rgba(124, 106, 255, 0.3);}
        .logo-title{font-size:26px;font-weight:800;margin-bottom:6px;letter-spacing:-0.02em;color:var(--text)}
        .logo-sub{font-size:14px;color:var(--t2);font-weight:500}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:32px;box-shadow: var(--shadow)}
        .fg{margin-bottom:20px}
        .fl{display:block;font-size:11px;font-weight:700;color:var(--t2);text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}
        .fi{width:100%;background:var(--s2);border:1px solid var(--border);border-radius:var(--r2);padding:12px 16px;color:var(--text);font-family:var(--font);font-size:14.5px;transition:all .2s;outline:none}
        .fi:focus{border-color:var(--accent);background:#fff;box-shadow: 0 0 0 4px rgba(124, 106, 255, 0.1)}
        .fi::placeholder{color:var(--t3)}
        .btn-login{width:100%;background:var(--accent);color:#fff;border:none;border-radius:var(--r2);padding:14px;font-family:var(--font);font-size:15px;font-weight:700;cursor:pointer;transition:all .2s;margin-top:8px;box-shadow: 0 4px 6px -1px rgba(124, 106, 255, 0.2)}
        .btn-login:hover{background:#6352e0;transform:translateY(-1px);box-shadow: 0 10px 15px -3px rgba(124, 106, 255, 0.3)}
        .btn-login:active{transform:translateY(0)}
        .err{background:#fff1f2;border:1px solid #fecdd3;border-radius:var(--r2);padding:12px 16px;font-size:13px;color:var(--red);margin-bottom:20px;font-weight:500;display:flex;align-items:center;gap:8px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="logo">
        <div class="logo-icon">📊</div>
        <div class="logo-title">Game Sales DSS</div>
        <div class="logo-sub">Cupcorn Entertainment</div>
    </div>
    <div class="card">
        <?php if ($error): ?>
            <div class="err">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="fg">
                <label class="fl">Email Address</label>
                <input type="email" name="email" class="fi" placeholder="nama@email.com" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="fg">
                <label class="fl">Password</label>
                <input type="password" name="password" class="fi" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-login">Sign In</button>
        </form>
    </div>
</div>
</body>
</html>