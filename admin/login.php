<?php
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['admin'])) redirect(ADMIN_URL . 'index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (!$u || !$p) {
        $error = 'Please enter username and password.';
    } elseif (admin_login($u, $p)) {
        redirect(ADMIN_URL . 'index.php');
    } else {
        $error = 'Invalid username or password.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | Sharan Foundation</title>
<link rel="icon" href="<?= BASE_URL ?>images/logo.png">
<link rel="stylesheet" href="<?= ADMIN_URL ?>assets/css/admin.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="logo">
      <img src="<?= BASE_URL ?>images/logo.png" alt="Sharan Foundation">
      <h1>Sharan Foundation</h1>
      <p class="sub">Admin Control Panel</p>
    </div>

    <?php if ($error): ?>
      <div style="background:#fdecea;color:#c0392b;padding:.7rem 1rem;border-left:3px solid #c0392b;border-radius:6px;margin-bottom:1rem;font-size:.88rem"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <div class="form-group">
        <label>Username or Email <span class="req">*</span></label>
        <input type="text" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password <span class="req">*</span></label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;padding:.85rem">Sign In →</button>
    </form>

    <div class="demo">
      <strong>🔑 Default Login:</strong><br>
      Username: <code>admin</code> &nbsp; • &nbsp; Password: <code>admin123</code><br>
      <small>(Change the password after first login!)</small>
    </div>

    <p style="text-align:center;margin-top:1.5rem;font-size:.82rem;color:#888">
      <a href="<?= BASE_URL ?>">← Back to Website</a>
    </p>
  </div>
</div>
</body>
</html>
