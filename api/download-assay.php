<?php
/**
 * Balochistan Minerals - Gated Lab Assay Certificate (COA) Lead Magnet
 * Captures buyer details before granting access to SGS / AHK assay certificates.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../admin/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (!empty($_POST['botcheck'])) {
    echo json_encode(['success' => true, 'message' => 'Verified.']);
    exit;
}

$name     = cleanInput($_POST['name'] ?? '');
$company  = cleanInput($_POST['company'] ?? '');
$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone    = cleanInput($_POST['whatsapp'] ?? $_POST['phone'] ?? '');
$mineral  = cleanInput($_POST['mineral'] ?? 'Chromite Lumps');
$country  = cleanInput($_POST['country'] ?? 'International');

if (empty($name) || empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please provide your name and WhatsApp number to unlock the official assay report.']);
    exit;
}

$pdo = getDB();
$leadId = 0;

if ($pdo !== null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $pdo->prepare('INSERT INTO inquiries 
            (buyer_name, company_name, country, email, whatsapp, inquiry_type, mineral_type, status, internal_notes, ip_address) 
            VALUES (?, ?, ?, ?, ?, "Assay Request", ?, "Assay Sent", "Downloaded official assay certificate directly from website", ?)');
        $stmt->execute([$name, $company, $country, $email, $phone, $mineral, $ip]);
        $leadId = (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Assay Download DB Error: " . $e->getMessage());
    }
}

// Generate WhatsApp connection to owner
$waOwnerMsg = "Hello Balochistan Minerals, I have just downloaded the official Certificate of Analysis (COA) for " . $mineral . " on your website. My name is " . $name . ($company ? " (" . $company . ")" : "") . ". I would like to discuss current FOB Karachi stockpiles and pricing.";
$waUrl = 'https://wa.me/923348888104?text=' . urlencode($waOwnerMsg);

echo json_encode([
    'success'      => true,
    'message'      => 'Assay certificate unlocked successfully.',
    'download_url' => 'documents/Balochistan_Minerals_Assay_Summary.html',
    'whatsapp_url' => $waUrl
]);
