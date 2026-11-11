<?php
/**
 * Balochistan Minerals - Gated Lab Assay Certificate (COA) Lead Magnet
 * Captures buyer details before granting access to SGS / AHK assay certificates.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../admin/db.php';
require_once __DIR__ . '/../admin/flag-helper.php';
require_once __DIR__ . '/../admin/deal-helpers.php';

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
$rawCountry = cleanInput($_POST['country'] ?? '');
$country  = detectCountryName($rawCountry, $email, $phone, $company);

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

// Resolve mineral-specific verified public COA if uploaded into Vault, else static summary
$downloadUrl = 'documents/Balochistan_Minerals_Assay_Summary.html';
if ($pdo !== null) {
    try {
        ensureDealVaultSchema($pdo);
        $term = '%' . preg_replace('/[^a-zA-Z0-9 ]+/', '', trim($mineral)) . '%';
        $pubStmt = $pdo->prepare("SELECT dd.id
            FROM `deal_documents` dd
            JOIN `deals` d ON d.id = dd.deal_id
            WHERE dd.visibility = 'public_sample'
              AND dd.doc_status != 'Superseded'
              AND dd.doc_category IN ('coa_pdf', 'weight_moisture_pdf', 'general_evidence')
              AND (d.commodity LIKE ? OR dd.title LIKE ?)
            ORDER BY (dd.doc_status = 'Verified Final') DESC, dd.id DESC
            LIMIT 1");
        $pubStmt->execute([$term, $term]);
        $pubDocId = (int)$pubStmt->fetchColumn();
        if ($pubDocId > 0) {
            $downloadUrl = 'admin/api-vault-file.php?public_doc_id=' . $pubDocId;
        }
    } catch (Exception $e) {
        error_log("Assay sample routing note: " . $e->getMessage());
    }
}

// Generate WhatsApp connection to owner
$waOwnerMsg = "Hello Balochistan Minerals, I have just downloaded the official Certificate of Analysis (COA) for " . $mineral . " on your website. My name is " . $name . ($company ? " (" . $company . ")" : "") . ". I would like to discuss current FOB Karachi stockpiles and pricing.";
$waUrl = 'https://wa.me/923348888104?text=' . urlencode($waOwnerMsg);

echo json_encode([
    'success'      => true,
    'message'      => 'Assay certificate unlocked successfully.',
    'download_url' => $downloadUrl,
    'whatsapp_url' => $waUrl
]);
