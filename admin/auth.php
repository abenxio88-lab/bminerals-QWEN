<?php
/**
 * Balochistan Minerals - Authentication, Live Session Enforcement & 4-Tier RBAC Guard
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security-helpers.php';

function isLoggedIn() {
    return isset($_SESSION['bm_admin_id']) && !empty($_SESSION['bm_admin_id']);
}

function forceDestroyAdminSession() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    @session_destroy();
    $GLOBALS['bm_auth_verified_user'] = null;
}

/**
 * Releases PHP's exclusive file session lock before slow DB/IMAP/streaming work
 * so parallel page navigations & AJAX requests never queue behind each other.
 */
function releaseSessionLock() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }
}

/**
 * Verifies active status + non-revoked session row in MySQL (cached per request).
 * Returns enriched user array or null / ['kicked' => reason].
 */
function refreshSessionAuth($pdo = null) {
    if (array_key_exists('bm_auth_verified_user', $GLOBALS) && is_array($GLOBALS['bm_auth_verified_user'])) {
        return $GLOBALS['bm_auth_verified_user'];
    }
    if (!isLoggedIn()) {
        return null;
    }

    $pdo = $pdo ?: getDB();
    $uid = (int)$_SESSION['bm_admin_id'];
    if (!$pdo) {
        // DB temporarily unavailable: fall back safely to session claims
        $role = normalizeRoleTier($_SESSION['bm_admin_role'] ?? 'Owner');
        return [
            'id'          => $uid,
            'username'    => $_SESSION['bm_admin_user'] ?? 'Admin',
            'full_name'   => $_SESSION['bm_admin_name'] ?? 'Admin',
            'email'       => $_SESSION['bm_admin_email'] ?? '',
            'role'        => $role,
            'tier'        => $role,
            'is_active'   => 1,
            'permissions' => getPermissionsForUser(['role' => $role]),
        ];
    }

    try {
        ensureTeamSecuritySchema($pdo);
        $st = $pdo->prepare("SELECT id, username, full_name, email, role, COALESCE(is_active,1) AS is_active, COALESCE(can_send_direct_email,0) AS can_send_direct_email, COALESCE(allowed_scope,'all') AS allowed_scope, COALESCE(totp_enabled,0) AS totp_enabled FROM `admin_users` WHERE id = ? LIMIT 1");
        $st->execute([$uid]);
        $user = $st->fetch(PDO::FETCH_ASSOC);

        if (!$user || (int)$user['is_active'] !== 1) {
            forceDestroyAdminSession();
            return ['kicked' => 'frozen'];
        }

        $sid = session_id();
        if ($sid !== '') {
            $sChk = $pdo->prepare("SELECT is_revoked FROM `admin_sessions` WHERE session_id = ? AND user_id = ? LIMIT 1");
            $sChk->execute([$sid, $uid]);
            $sRow = $sChk->fetch(PDO::FETCH_ASSOC);
            if ($sRow && (int)$sRow['is_revoked'] === 1) {
                forceDestroyAdminSession();
                return ['kicked' => 'revoked'];
            }
            $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
            $isApiEndpoint = (strpos($script, 'api-') === 0 || $script === 'mail-sync.php');
            if (!$sRow) {
                upsertAdminSession($pdo, $uid, $isApiEndpoint ? 'dashboard.php' : $script);
            } else {
                touchAdminSession($pdo, $uid, $isApiEndpoint ? '' : $script);
            }
        }

        $tier = normalizeRoleTier($user['role'] ?? 'Owner');
        $user['role'] = $tier;
        $user['tier'] = $tier;
        $user['permissions'] = getPermissionsForUser($user);

        // Propagate live DB role/identity updates immediately into session
        $_SESSION['bm_admin_user']  = $user['username'];
        $_SESSION['bm_admin_name']  = $user['full_name'];
        $_SESSION['bm_admin_email'] = $user['email'];
        $_SESSION['bm_admin_role']  = $tier;

        $GLOBALS['bm_auth_verified_user'] = $user;
        return $user;
    } catch (Exception $e) {
        error_log('refreshSessionAuth notice: ' . $e->getMessage());
        $role = normalizeRoleTier($_SESSION['bm_admin_role'] ?? 'Owner');
        return [
            'id'          => $uid,
            'username'    => $_SESSION['bm_admin_user'] ?? 'Admin',
            'full_name'   => $_SESSION['bm_admin_name'] ?? 'Admin',
            'email'       => $_SESSION['bm_admin_email'] ?? '',
            'role'        => $role,
            'tier'        => $role,
            'is_active'   => 1,
            'permissions' => getPermissionsForUser(['role' => $role]),
        ];
    }
}

function isApiRequestContext($forceApi = false) {
    if ($forceApi) return true;
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($script, 'api-') === 0) return true;
    $xrw = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    if ($xrw === 'xmlhttprequest') return true;
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    return strpos($accept, 'application/json') !== false && strpos($accept, 'text/html') === false;
}

function requireAuth($isApi = false) {
    $res = refreshSessionAuth(getDB());
    $kicked = is_array($res) && !empty($res['kicked']) ? $res['kicked'] : '';
    if (!$res || $kicked !== '') {
        $redirect = $kicked !== '' ? ('login.php?revoked=' . urlencode($kicked)) : 'login.php';
        if (isApiRequestContext($isApi)) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'      => false,
                'auth_revoked' => true,
                'error'        => $kicked === 'frozen'
                    ? 'Your staff account has been suspended by the administrator.'
                    : 'Your session was signed out or expired.',
                'redirect'     => $redirect,
            ]);
            exit;
        }
        header('Location: ' . $redirect);
        exit;
    }
}

function getLoggedInUser() {
    $u = refreshSessionAuth(getDB());
    if (!$u || !empty($u['kicked'])) return null;
    return [
        'id'          => $u['id'],
        'username'    => $u['username'],
        'full_name'   => $u['full_name'],
        'email'       => $u['email'],
        'role'        => $u['role'],
        'tier'        => $u['tier'],
        'permissions' => $u['permissions'] ?? [],
    ];
}

function getCsrfToken() {
    if (empty($_SESSION['bm_csrf_token'])) {
        $_SESSION['bm_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['bm_csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['bm_csrf_token']) && hash_equals($_SESSION['bm_csrf_token'], (string)$token);
}

function getAdminRole() {
    $u = getLoggedInUser();
    return $u ? $u['tier'] : normalizeRoleTier($_SESSION['bm_admin_role'] ?? 'Owner');
}

function hasPermission($perm) {
    $u = refreshSessionAuth(getDB());
    if (!$u || !empty($u['kicked'])) return false;
    return !empty($u['permissions'][$perm]);
}

function requirePermission($perm, $isApi = false) {
    requireAuth($isApi);
    if (hasPermission($perm)) {
        return;
    }

    $pdo = getDB();
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'admin'));
    if ($pdo) {
        logSystemAudit($pdo, 'security', "Blocked unauthorized access to '{$perm}' on {$script}");
    }

    http_response_code(403);
    if (isApiRequestContext($isApi)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'   => false,
            'forbidden' => true,
            'error'     => 'Permission denied for your account tier (' . htmlspecialchars(getAdminRole()) . '). Contact the Owner for authorization.',
        ]);
        exit;
    }

    $fallback = hasPermission('view_rfqs') ? 'dashboard.php' : (hasPermission('view_deals') ? 'deals.php' : 'stockpiles.php');
    $safeRole = htmlspecialchars(getAdminRole());
    $safePerm = htmlspecialchars($perm);
    echo "<!doctype html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Access Restricted | Balochistan Minerals</title>"
        . "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'></head>"
        . "<body style='background:#F8FAFC;font-family:system-ui,-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;'>"
        . "<div style='max-width:460px;background:#fff;border:1px solid #E2E8F0;padding:34px;text-align:center;box-shadow:0 10px 30px rgba(15,23,42,.06);'>"
        . "<div style='display:inline-block;padding:4px 10px;background:#FEF2F2;color:#B91C1C;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin-bottom:14px;'>403 • Access Restricted</div>"
        . "<h2 style='font-size:1.35rem;font-weight:700;color:#0A192F;margin:0 0 8px;'>Permission Required</h2>"
        . "<p style='color:#475569;font-size:.9rem;line-height:1.55;margin:0 0 22px;'>Your signed-in role (<strong>{$safeRole}</strong>) does not have authorization for this module (<code>{$safePerm}</code>). All attempts are logged for Owner oversight.</p>"
        . "<a href='{$fallback}' class='btn btn-dark rounded-0 px-4 fw-semibold' style='background:#0A192F;border-color:#0A192F;'>Return to Authorized Desk</a>"
        . "</div></body></html>";
    exit;
}

function getAllowedSidebarPages() {
    return [
        'dashboard.php'        => hasPermission('view_rfqs'),
        'email-desk.php'       => hasPermission('draft_reply'),
        'prospects.php'        => hasPermission('view_prospects'),
        'radar.php'            => hasPermission('view_radar'),
        'analytics.php'        => hasPermission('view_analytics'),
        'deals.php'            => hasPermission('view_deals'),
        'stockpiles.php'       => hasPermission('view_stockpiles'),
        'prices.php'           => hasPermission('manage_prices'),
        'reviews.php'          => hasPermission('manage_community'),
        'faqs.php'             => hasPermission('manage_community'),
        'blog-discussions.php' => hasPermission('manage_community'),
        'generate-quote.php'   => hasPermission('generate_quote'),
        'team.php'             => hasPermission('manage_team') || hasPermission('approve_emails'),
        'tracking'             => hasPermission('view_deals'),
    ];
}

function isRestrictedFieldRole() {
    return getAdminRole() === 'Yard';
}

function canViewDealFinancials() {
    return isLoggedIn() && hasPermission('view_deal_financials');
}

function canSealOrBackupDeals() {
    return isLoggedIn() && hasPermission('download_backup');
}

function canCloseStandardDeal() {
    return isLoggedIn() && hasPermission('standard_close_deal');
}

function canVerifyLockDocs() {
    return isLoggedIn() && hasPermission('verify_lock_docs');
}
