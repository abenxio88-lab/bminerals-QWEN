<?php
/**
 * Balochistan Minerals - Admin Gateway
 * Automatically directs user to dashboard or login
 */
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    $verified = refreshSessionAuth(getDB());
    if ($verified && empty($verified['kicked'])) {
        $landing = hasPermission('view_rfqs') ? 'dashboard.php' : (hasPermission('view_deals') ? 'deals.php' : 'stockpiles.php');
        header('Location: ' . $landing);
        exit;
    }
}
header('Location: login.php');
exit;
