<?php
/**
 * Balochistan Minerals - Asynchronous Visitor Traffic & Mineral Demand Tracker
 * Lightweight beacon for logging international buyer pageviews and commodity interest.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../admin/db.php';

// Allow GET or POST
$pdo = getDB();
if (!$pdo) {
    echo json_encode(['success' => false]);
    exit;
}

$raw = file_get_contents('php://input');
$data = [];
if (!empty($raw)) {
    $data = json_decode($raw, true) ?: [];
}
if (empty($data)) {
    $data = $_POST ?: $_GET;
}

$page = cleanInput($data['page'] ?? $_SERVER['REQUEST_URI'] ?? '/');
$mineral = cleanInput($data['mineral'] ?? '');
$referrer = cleanInput($data['referrer'] ?? $_SERVER['HTTP_REFERER'] ?? 'Direct');
$clientCountry = cleanInput($data['country'] ?? '');
$clientCity = cleanInput($data['city'] ?? '');
$userAgent = cleanInput(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250));

// Extract IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip = trim($parts[0]);
}

// Country detection
$country = 'International';
$city = 'Unknown';

if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
    $country = $_SERVER['HTTP_CF_IPCOUNTRY'];
} elseif (!empty($clientCountry)) {
    $country = $clientCountry;
} elseif ($ip === '127.0.0.1' || $ip === '::1') {
    $country = 'Pakistan (Local Test)';
    $city = 'Karachi Desk';
}

if (!empty($clientCity)) {
    $city = $clientCity;
}

// Auto-detect mineral from page or URL if not explicitly given
if (empty($mineral)) {
    $lower = strtolower($page . ' ' . $referrer);
    if (strpos($lower, 'chrom') !== false) {
        $mineral = 'Chromite Ore';
    } elseif (strpos($lower, 'barit') !== false) {
        $mineral = 'Barite (Barytes)';
    } elseif (strpos($lower, 'antimon') !== false) {
        $mineral = 'Antimony Ore';
    } elseif (strpos($lower, 'copp') !== false) {
        $mineral = 'Copper Ore';
    } elseif (strpos($lower, 'fluor') !== false) {
        $mineral = 'Fluorite (Fluorspar)';
    } elseif (strpos($lower, 'iron') !== false) {
        $mineral = 'Iron Ore';
    } else {
        $mineral = 'General Exploration';
    }
}

try {
    $stmt = $pdo->prepare("INSERT INTO visitor_radar (ip_address, country, city, page_url, mineral_interest, referrer, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$ip, $country, $city, substr($page, 0, 250), $mineral, substr($referrer, 0, 250), $userAgent]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
