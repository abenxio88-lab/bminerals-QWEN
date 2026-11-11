<?php
/**
 * Balochistan Minerals - Logout & Session Revocation
 */
require_once __DIR__ . '/auth.php';

$pdo = getDB();
$uid = (int)($_SESSION['bm_admin_id'] ?? 0);
$uname = (string)($_SESSION['bm_admin_user'] ?? '');
$sid = session_id();

if ($pdo && $uid > 0) {
    try {
        ensureTeamSecuritySchema($pdo);
        if ($sid !== '') {
            $pdo->prepare("UPDATE `admin_sessions` SET is_revoked = 1 WHERE session_id = ? AND user_id = ?")
                ->execute([$sid, $uid]);
        }
        recordLoginEvent($pdo, $uid, $uname, 'Logout', 'Manual sign out');
    } catch (Exception $e) {
        error_log('logout session cleanup notice: ' . $e->getMessage());
    }
}

forceDestroyAdminSession();
header('Location: login.php');
exit;
