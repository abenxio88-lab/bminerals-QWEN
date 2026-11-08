<?php
/**
 * Balochistan Minerals - Authentication & Session Security Handler
 */

if (session_status() === PHP_SESSION_NONE) {
    // Enable secure session parameters
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

require_once __DIR__ . '/db.php';

function isLoggedIn() {
    return isset($_SESSION['bm_admin_id']) && !empty($_SESSION['bm_admin_id']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getLoggedInUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'        => $_SESSION['bm_admin_id'],
        'username'  => $_SESSION['bm_admin_user'] ?? 'Admin',
        'full_name' => $_SESSION['bm_admin_name'] ?? 'Admin',
        'email'     => $_SESSION['bm_admin_email'] ?? '',
        'role'      => $_SESSION['bm_admin_role'] ?? 'Owner'
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
