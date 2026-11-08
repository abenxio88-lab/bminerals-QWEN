<?php
/**
 * Balochistan Minerals - Owner & Executive Login
 * Light Luxury Corporate Theme
 */

require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $token    = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $pdo = getDB();
        if ($pdo === null) {
            $error = 'Database connection not yet configured in admin/db.php. Please add Hostinger credentials.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user) {
                $authenticated = false;

                // 1. Standard secure bcrypt verification
                if (password_verify($password, $user['password_hash'])) {
                    $authenticated = true;
                }
                // 2. Initial setup password fallback ('Admin@BM2026') -> auto-upgrades to secure bcrypt!
                elseif ($user['password_hash'] === 'Admin@BM2026' && $password === 'Admin@BM2026') {
                    $authenticated = true;
                    $secureHash = password_hash($password, PASSWORD_DEFAULT);
                    $up = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
                    $up->execute([$secureHash, $user['id']]);
                }

                if ($authenticated) {
                    $_SESSION['bm_admin_id']    = $user['id'];
                    $_SESSION['bm_admin_user']  = $user['username'];
                    $_SESSION['bm_admin_name']  = $user['full_name'];
                    $_SESSION['bm_admin_email'] = $user['email'];
                    $_SESSION['bm_admin_role']  = $user['role'];

                    // Update last login
                    $pdo->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid username or password.';
                }
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Executive Portal Login | Balochistan Minerals</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <!-- Bootstrap 5 CSS via CDN (Light Luxury Styling) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --brand-navy: #0F172A;
      --brand-slate: #1E293B;
      --brand-gold: #B48C36;
      --brand-border: #E2E8F0;
      --bg-light: #F8FAFC;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background-color: var(--bg-light);
      color: var(--brand-navy);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      -webkit-font-smoothing: antialiased;
    }
    .login-card {
      background: #FFFFFF;
      border: 1px solid var(--brand-border);
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);
      max-width: 420px;
      width: 100%;
      padding: 38px 34px;
    }
    .brand-logo {
      max-height: 48px;
      width: auto;
      display: block;
      margin: 0 auto 16px;
    }
    .brand-title {
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      color: var(--brand-navy);
      font-size: 1.25rem;
      text-align: center;
      margin-bottom: 4px;
      letter-spacing: -0.01em;
    }
    .brand-subtitle {
      font-size: 0.8rem;
      color: #64748B;
      text-align: center;
      margin-bottom: 24px;
    }
    .form-label {
      font-family: 'Montserrat', sans-serif;
      font-weight: 600;
      font-size: 0.76rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #475569;
      margin-bottom: 6px;
    }
    .form-control {
      border: 1px solid #CBD5E1;
      border-radius: 6px;
      padding: 10px 13px;
      font-size: 0.9rem;
      transition: all 0.15s ease;
    }
    .form-control:focus {
      border-color: var(--brand-gold);
      box-shadow: 0 0 0 3px rgba(180, 140, 54, 0.15);
    }
    .btn-login {
      background: var(--brand-navy);
      color: #FFFFFF;
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      font-size: 0.88rem;
      border: none;
      border-radius: 6px;
      padding: 11px;
      width: 100%;
      margin-top: 10px;
      transition: all 0.15s ease;
    }
    .btn-login:hover {
      background: var(--brand-slate);
      color: #FFFFFF;
    }
    .gold-bar {
      height: 2px;
      background: var(--brand-gold);
      border-radius: 2px;
      width: 44px;
      margin: 0 auto 16px;
    }
    .back-link {
      font-size: 0.8rem;
      color: #64748B;
      text-align: center;
      margin-top: 22px;
      display: block;
      text-decoration: none;
    }
    .back-link:hover {
      color: var(--brand-navy);
    }
  </style>
</head>
<body>

  <div class="login-card">
    <a href="../" title="Return to Website">
      <img src="../images/new-logo.png" alt="Balochistan Minerals" class="brand-logo" onerror="this.style.display='none'">
    </a>
    <h1 class="brand-title">Trade Desk Portal</h1>
    <div class="gold-bar"></div>
    <p class="brand-subtitle">Commercial Operations & Lead Management</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger py-2 px-3 mb-3 small" role="alert">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" id="username" name="username" class="form-control" placeholder="admin" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>

      <div class="mb-4">
        <label for="password" class="form-label">Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn btn-login">
        Secure Sign In
      </button>
    </form>

    <a href="../" class="back-link">← Back to BalochistanMinerals.com</a>
  </div>

</body>
</html>
