<?php
require_once 'includes/config.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $db = getDB();
    $stmt = $db->prepare("SELECT id, full_name, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        header('Location: dashboard.php'); exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – Canteen Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <style>
        :root {
            --maroon: #7B1416; --maroon-dark: #5C0E10;
        }
        body {
            font-family: 'DM Sans', sans-serif;
            background: #F0EEEC; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
        }
        .login-wrap {
            background: #fff; border-radius: 16px; padding: 48px 44px;
            width: 100%; max-width: 420px;
            box-shadow: 0 4px 30px rgba(0,0,0,.08);
            border: 1px solid #E4E0DC;
        }
        .login-logo {
            width: 54px; height: 54px; background: var(--maroon);
            border-radius: 14px; display: flex; align-items: center; justify-content: center;
            margin-bottom: 24px;
        }
        .login-logo i { color: #fff; font-size: 1.5rem; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 4px; }
        .sub { color: #888; font-size: .875rem; margin-bottom: 32px; }
        .form-label { font-weight: 600; font-size: .875rem; }
        .form-control {
            border: 1.5px solid #D9D4CF; border-radius: 8px; padding: 10px 14px;
        }
        .form-control:focus {
            border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(123,20,22,.08);
        }
        .btn-login {
            width: 100%; background: var(--maroon); color: #fff; border: none;
            border-radius: 8px; padding: 11px; font-weight: 600; font-size: .95rem;
            margin-top: 8px; transition: background .15s;
        }
        .btn-login:hover { background: var(--maroon-dark); color: #fff; }
        .hint { font-size: .78rem; color: #aaa; text-align: center; margin-top: 16px; }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-logo"><i class="bi bi-shop"></i></div>
    <h1>Canteen System</h1>
    <p class="sub">Sign in to manage your canteen</p>
    <?php if ($error): ?>
    <div class="alert alert-danger py-2 px-3 mb-3" style="border-radius:8px;font-size:.875rem;"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" placeholder="Enter username" required autocomplete="username">
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Enter password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn-login">Sign In</button>
    </form>
</div>
</body>
</html>
