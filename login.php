<?php
session_start();
require 'config/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password, role, full_name FROM users WHERE username = ? AND is_active = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();

    // Support both bcrypt (production) and plain-text (dev seed fallback)
    $valid = $u && (password_verify($password, $u['password']) || hash_equals($u['password'], $password));

    if ($valid) {
        $_SESSION['user'] = [
            'id'        => $u['id'],
            'username'  => $u['username'],
            'role'      => $u['role'],
            'full_name' => $u['full_name'],
        ];
        // Update last_login
        $conn->query("UPDATE users SET last_login = NOW() WHERE id = {$u['id']}");
        header("Location: dashboard.php");
        exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CareTrack HMS — Login</title>
<meta name="description" content="Login to CareTrack Hospital Management System">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #1e3a8a 0%, #0ea5e9 100%); min-height: 100vh; display: grid; place-items: center; }
  .box { width: 420px; max-width: 95vw; }
  .card { border: none; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.25); }
  .brand { font-size: 1.75rem; font-weight: 700; color: #1e3a8a; }
  .btn-primary { background: #1e3a8a; border: none; border-radius: 8px; padding: .65rem; font-weight: 600; }
  .btn-primary:hover { background: #1e40af; }
  .form-control { border-radius: 8px; padding: .65rem .9rem; }
  .badge-role { font-size: .7rem; }
  .demo-creds { background: #f0f9ff; border-radius: 8px; font-size: .78rem; }
</style>
</head>
<body>
<div class="box">
  <div class="card p-4 p-md-5">
    <div class="text-center mb-4">
      <div class="brand">🏥 CareTrack HMS</div>
      <p class="text-muted mb-0 mt-1">Hospital Management System</p>
    </div>
    <?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label class="form-label fw-semibold">Username</label>
        <input class="form-control" name="username" required autocomplete="username">
      </div>
      <div class="mb-4">
        <label class="form-label fw-semibold">Password</label>
        <input type="password" class="form-control" name="password" required autocomplete="current-password">
      </div>
      <button class="btn btn-primary w-100">Login →</button>
    </form>
    <div class="demo-creds p-3 mt-4">
      <div class="fw-semibold mb-1 text-primary">Demo Credentials</div>
      <div>Admin: <code>admin</code> / <code>admin123</code></div>
      <div>Doctor: <code>dr_anu</code> / <code>doctor123</code></div>
      <div>Reception: <code>reception</code> / <code>reception123</code></div>
    </div>
  </div>
</div>
</body>
</html>