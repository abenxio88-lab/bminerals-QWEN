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

    $lockUntil = (int)($_SESSION['bm_login_lock_until'] ?? 0);
    if ($lockUntil > time()) {
        $waitSecs = $lockUntil - time();
        $error = 'Too many failed attempts. Please wait ' . ceil($waitSecs / 60) . ' minute(s) before trying again.';
    } elseif (!verifyCsrfToken($token)) {
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

            $authenticated = $user && password_verify($password, $user['password_hash']);

            if ($authenticated) {
                unset($_SESSION['bm_login_fails'], $_SESSION['bm_login_lock_until']);
                session_regenerate_id(true);

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
                $fails = (int)($_SESSION['bm_login_fails'] ?? 0) + 1;
                $_SESSION['bm_login_fails'] = $fails;
                if ($fails >= 5) {
                    $_SESSION['bm_login_lock_until'] = time() + 300;
                }
                usleep(350000);
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
  <title>Executive Operations Portal | Balochistan Minerals</title>
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
      --brand-orange: #E65C1A;
      --brand-border: #E2E8F0;
      --bg-light: #F8FAFC;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background:
        linear-gradient(rgba(10, 22, 40, 0.22), rgba(10, 22, 40, 0.28)),
        url('../images/hero/golden-alpine-portal-bg.jpg') center center / cover no-repeat fixed;
      background-color: #0A1628;
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
      border-radius: 0;
      box-shadow: 0 24px 60px rgba(10, 22, 40, 0.28);
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
    .brand-header {
      text-align: center;
      margin-bottom: 10px;
    }
    .brand-title {
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      color: var(--brand-navy);
      font-size: 1.25rem;
      display: inline-block;
      margin: 0 auto;
      padding-bottom: 10px;
      border-bottom: 2.5px solid var(--brand-orange);
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
      border-radius: 0;
      padding: 10px 13px;
      font-size: 0.9rem;
      transition: all 0.15s ease;
    }
    .form-control:focus {
      border-color: var(--brand-navy);
      box-shadow: none;
      outline: none;
    }
    .btn-login {
      background: var(--brand-navy);
      color: #FFFFFF;
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      font-size: 0.88rem;
      letter-spacing: 0.02em;
      border: 1px solid var(--brand-navy);
      border-radius: 0;
      padding: 12px;
      width: 100%;
      margin-top: 10px;
      transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
    }
    .btn-login:hover,
    .btn-login:focus-visible {
      background: #F26A21;
      border-color: #F26A21;
      color: #FFFFFF;
    }
    .btn-login:active {
      background: #E65C1A !important;
      border-color: #E65C1A !important;
      color: #FFFFFF !important;
    }
    .alert {
      border-radius: 0;
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
      <picture style="display: contents;"><source media="(min-width: 769px)" srcset="../images/new-logo-dekstop.png" width="723" height="278"><img src="../images/new-logo.png" alt="Balochistan Minerals" class="brand-logo" onerror="this.style.display='none'"></picture>
    </a>
    <div class="brand-header">
      <h1 class="brand-title">Executive Operations Portal</h1>
    </div>
    <p class="brand-subtitle">Global Mineral Exports &amp; Trade Intelligence</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger py-2 px-3 mb-3 small" role="alert">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" id="username" name="username" class="form-control" placeholder="Enter authorized username" autocomplete="username" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>

      <div class="mb-4">
        <label for="password" class="form-label">Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn btn-login">
        Sign In
      </button>
    </form>

    <a href="../" class="back-link">← Back to BalochistanMinerals.com</a>
  </div>

</body>
</html>
