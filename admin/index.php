<?php
/**
 * Balochistan Minerals - Admin Gateway
 * Automatically directs user to dashboard or login
 */
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
