<?php
/**
 * Balochistan Minerals - Owner & Staff Executive Login (with TOTP 2FA & Security Logging)
 * Light Luxury Corporate Theme
 */

require_once __DIR__ . '/auth.php';

function resolvePostLoginLandingPage(array $perms) {
    if (!empty($perms['view_rfqs'])) return 'dashboard.php';
    if (!empty($perms['view_deals'])) return 'deals.php';
    if (!empty($perms['view_stockpiles'])) return 'stockpiles.php';
    return 'deals.php';
}

function completeStaffLogin($pdo, array $user) {
    unset($_SESSION['bm_login_fails'], $_SESSION['bm_login_lock_until'], $_SESSION['bm_pending_2fa']);
    session_regenerate_id(true);

    $tier = normalizeRoleTier($user['role'] ?? 'Owner');
    $_SESSION['bm_admin_id']    = (int)$user['id'];
    $_SESSION['bm_admin_user']  = $user['username'];
    $_SESSION['bm_admin_name']  = $user['full_name'];
    $_SESSION['bm_admin_email'] = $user['email'] ?? '';
    $_SESSION['bm_admin_role']  = $tier;
    unset($GLOBALS['bm_auth_verified_user']);

    $perms = getPermissionsForUser($user);
    $landing = resolvePostLoginLandingPage($perms);

    $pdo->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')->execute([(int)$user['id']]);
    upsertAdminSession($pdo, (int)$user['id'], $landing);
    recordLoginEvent($pdo, (int)$user['id'], $user['username'], 'Success', 'Role: ' . $tier);

    header('Location: ' . $landing);
    exit;
}

// Clear pending 2FA if user clicked "Use another account"
if (isset($_GET['reset_2fa'])) {
    unset($_SESSION['bm_pending_2fa']);
    header('Location: login.php');
    exit;
}

// Check existing session cleanly without redirect loops
if (isLoggedIn()) {
    $verified = refreshSessionAuth(getDB());
    if ($verified && empty($verified['kicked'])) {
        header('Location: ' . resolvePostLoginLandingPage($verified['permissions'] ?? []));
        exit;
    }
}

$error = '';
$info  = '';
$revokedReason = trim((string)($_GET['revoked'] ?? ''));
if ($revokedReason === 'frozen') {
    $error = 'Your staff account has been suspended by the administrator.';
} elseif ($revokedReason === 'revoked') {
    $info = 'Your previous session was signed out remotely or expired. Please sign in again.';
}

$pdo = getDB();
if ($pdo !== null) {
    ensureTeamSecuritySchema($pdo);
}

$pending2fa = $_SESSION['bm_pending_2fa'] ?? null;
if ($pending2fa && (int)($pending2fa['expires'] ?? 0) < time()) {
    unset($_SESSION['bm_pending_2fa']);
    $pending2fa = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $step  = $_POST['step'] ?? 'credentials';
    $lockUntil = (int)($_SESSION['bm_login_lock_until'] ?? 0);

    if ($lockUntil > time()) {
        $waitSecs = $lockUntil - time();
        $error = 'Too many failed attempts. Please wait ' . ceil($waitSecs / 60) . ' minute(s) before trying again.';
    } elseif (!verifyCsrfToken($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } elseif ($pdo === null) {
        $error = 'Database connection not yet configured in admin/db.php.';
    } elseif ($step === 'verify_2fa') {
        $pending2fa = $_SESSION['bm_pending_2fa'] ?? null;
        if (!$pending2fa || (int)($pending2fa['expires'] ?? 0) < time()) {
            unset($_SESSION['bm_pending_2fa']);
            $pending2fa = null;
            $error = '2FA challenge timed out. Please sign in again.';
        } else {
            $code = trim((string)($_POST['totp_code'] ?? ''));
            $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$pending2fa['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || (int)($user['is_active'] ?? 1) !== 1) {
                unset($_SESSION['bm_pending_2fa']);
                $pending2fa = null;
                $error = 'This staff account is no longer active.';
            } elseif (!verifyTotpCode($user['totp_secret'] ?? '', $code, 1)) {
                $fails = (int)($_SESSION['bm_login_fails'] ?? 0) + 1;
                $_SESSION['bm_login_fails'] = $fails;
                if ($fails >= 5) {
                    $_SESSION['bm_login_lock_until'] = time() + 300;
                }
                recordLoginEvent($pdo, (int)$user['id'], $user['username'], '2FA_Failed', 'Invalid 6-digit authenticator code');
                usleep(350000);
                $error = 'Invalid 6-digit authenticator code. Please verify your device clock & code.';
            } else {
                completeStaffLogin($pdo, $user);
            }
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Please enter both username and password.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $authenticated = $user && password_verify($password, $user['password_hash']);

            if ($authenticated && (int)($user['is_active'] ?? 1) !== 1) {
                recordLoginEvent($pdo, (int)$user['id'], $username, 'Locked', 'Suspended staff account attempted sign-in');
                usleep(350000);
                $error = 'Your staff account has been suspended by the administrator.';
            } elseif ($authenticated) {
                if ((int)($user['totp_enabled'] ?? 0) === 1 && !empty($user['totp_secret'])) {
                    $_SESSION['bm_pending_2fa'] = [
                        'user_id'  => (int)$user['id'],
                        'username' => $user['username'],
                        'full_name'=> $user['full_name'],
                        'expires'  => time() + 300,
                    ];
                    $pending2fa = $_SESSION['bm_pending_2fa'];
                    $info = 'Password verified. Enter the 6-digit code from your Authenticator app.';
                } else {
                    completeStaffLogin($pdo, $user);
                }
            } else {
                $fails = (int)($_SESSION['bm_login_fails'] ?? 0) + 1;
                $_SESSION['bm_login_fails'] = $fails;
                if ($fails >= 5) {
                    $_SESSION['bm_login_lock_until'] = time() + 300;
                }
                recordLoginEvent($pdo, $user ? (int)$user['id'] : null, $username, $fails >= 5 ? 'Locked' : 'Failed', 'Invalid credentials');
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
    }
    .login-card {
      background: #FFFFFF;
      border: 1px solid var(--brand-border);
      border-top: 4px solid var(--brand-navy);
      border-radius: 0;
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28), 0 4px 16px rgba(15, 23, 42, 0.14);
      max-width: 420px;
      width: 100%;
      padding: 40px 36px;
    }
    .brand-logo {
      max-height: 52px;
      width: auto;
      display: block;
      margin: 0 auto 14px;
    }
    .brand-header {
      border-bottom: 1px solid #F1F5F9;
      padding-bottom: 18px;
      margin-bottom: 24px;
    }
    .brand-title {
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      font-size: 1.25rem;
      color: var(--brand-navy);
      text-align: center;
      margin: 0;
    }
    .brand-subtitle {
      font-size: 0.78rem;
      color: #64748B;
      text-align: center;
      margin-top: 4px;
      margin-bottom: 22px;
    }
    .form-label {
      font-family: 'Montserrat', sans-serif;
      font-size: 0.72rem;
      font-weight: 700;
      color: #334155;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .form-control {
      background-color: #FFFFFF;
      border: 1px solid #CBD5E1;
      border-radius: 0;
      color: var(--brand-navy);
      padding: 11px 14px;
      font-size: 0.9rem;
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
    .totp-input {
      font-family: 'Montserrat', monospace;
      font-size: 1.45rem;
      font-weight: 700;
      letter-spacing: 0.35em;
      text-align: center;
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
    <?php elseif (!empty($info)): ?>
      <div class="alert alert-info py-2 px-3 mb-3 small" role="alert">
        <?= htmlspecialchars($info) ?>
      </div>
    <?php endif; ?>

    <?php if ($pending2fa): ?>
      <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
        <input type="hidden" name="step" value="verify_2fa">

        <div class="mb-3 p-3 bg-light border text-center small">
          Signing in as <strong><?= htmlspecialchars($pending2fa['full_name'] ?: $pending2fa['username']) ?></strong><br>
          <span class="text-muted" style="font-size: 0.76rem;">Open Google / Microsoft Authenticator for your 6-digit code.</span>
        </div>

        <div class="mb-4">
          <label for="totp_code" class="form-label">Authenticator Code (2FA)</label>
          <input type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" id="totp_code" name="totp_code" class="form-control totp-input" placeholder="000000" autocomplete="one-time-code" required autofocus>
        </div>

        <button type="submit" class="btn btn-login">
          Verify &amp; Enter Portal
        </button>

        <a href="login.php?reset_2fa=1" class="back-link">← Cancel &amp; Switch Account</a>
      </form>
    <?php else: ?>
      <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
        <input type="hidden" name="step" value="credentials">

        <div class="mb-3">
          <label for="username" class="form-label">Username</label>
          <input type="text" id="username" name="username" class="form-control" placeholder="Username" autocomplete="username" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>

        <div class="mb-4">
          <label for="password" class="form-label">Password</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn-login">
          Sign In
        </button>
      </form>

      <a href="../" class="back-link">← Back to BalochistanMinerals.com</a>
    <?php endif; ?>
  </div>

</body>
</html>
