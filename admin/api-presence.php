<?php
/**
 * Balochistan Minerals - Lightweight Live Presence & RBAC UI Sync Endpoint
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';
requireAuth(true);

$pdo = getDB();
$user = getLoggedInUser();
$allowedPages = getAllowedSidebarPages();
$pendingApprovals = ($pdo && hasPermission('approve_emails')) ? getPendingApprovalCount($pdo) : 0;
releaseSessionLock();

$currentPage = trim((string)($_GET['page'] ?? ''));

if ($pdo && $user) {
    $pg = basename($currentPage);
    if ($pg !== '' && strpos($pg, 'api-') !== 0) {
        touchAdminSession($pdo, (int)$user['id'], $pg);
    } else {
        touchAdminSession($pdo, (int)$user['id'], '');
    }
}

echo json_encode([
    'success'           => true,
    'user'              => [
        'id'        => (int)($user['id'] ?? 0),
        'username'  => $user['username'] ?? '',
        'full_name' => $user['full_name'] ?? '',
        'role'      => $user['tier'] ?? 'Owner',
    ],
    'permissions'       => $user['permissions'] ?? [],
    'allowed_pages'     => $allowedPages,
    'pending_approvals' => $pendingApprovals,
]);
