<?php
/**
 * Balochistan Minerals - Real-Time Trade Desk Live Heartbeat & Auto-Sync Engine
 * Checks for new inbound RFQs, email sync updates, and KPI counter metrics without requiring page refresh.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/flag-helper.php';
require_once __DIR__ . '/inquiry-row-helper.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$pdo = getDB();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
    exit;
}

$lastSeenId    = (int)($_GET['last_seen_id'] ?? 0);
$filterStatus  = cleanInput($_GET['status'] ?? '');
$filterMineral = cleanInput($_GET['mineral'] ?? '');
$searchQuery   = cleanInput($_GET['q'] ?? '');

try {
    // 1. Fetch current global metrics
    $totalLeads     = (int)$pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
    $newLeads       = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'New'")->fetchColumn();
    $contactedLeads = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'Contacted'")->fetchColumn();
    $quotedLeads    = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status IN ('Quoted', 'Assay Sent', 'Sample Sent')")->fetchColumn();
    $closedLeads    = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'Contract Signed'")->fetchColumn();
    $pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'Pending'")->fetchColumn();
    $maxId          = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM inquiries")->fetchColumn();

    $hasNew = false;
    $newRowsHtml = '';
    $previews = [];

    // 2. If client has an older ID and new records exist
    if ($lastSeenId > 0 && $maxId > $lastSeenId) {
        $sql = "SELECT i.*, 
                (SELECT COUNT(*) FROM email_messages em WHERE em.inquiry_id = i.id OR (em.to_email = i.email AND i.email != '') OR (em.from_email = i.email AND i.email != '')) AS email_count,
                (SELECT em.direction FROM email_messages em WHERE em.inquiry_id = i.id OR (em.to_email = i.email AND i.email != '') OR (em.from_email = i.email AND i.email != '') ORDER BY em.sent_at DESC, em.id DESC LIMIT 1) AS last_email_dir,
                (SELECT em.sent_at FROM email_messages em WHERE em.inquiry_id = i.id OR (em.to_email = i.email AND i.email != '') OR (em.from_email = i.email AND i.email != '') ORDER BY em.sent_at DESC, em.id DESC LIMIT 1) AS last_email_time
                FROM inquiries i 
                WHERE i.id > ?";
        $params = [$lastSeenId];

        if (!empty($filterStatus)) {
            $sql .= " AND i.status = ?";
            $params[] = $filterStatus;
        }
        if (!empty($filterMineral)) {
            list($mClause, $mParams) = buildMineralFilterClause($filterMineral, 'i.mineral_type');
            $sql .= $mClause;
            $params = array_merge($params, $mParams);
        }
        if (!empty($searchQuery)) {
            $sql .= " AND (i.buyer_name LIKE ? OR i.company_name LIKE ? OR i.email LIKE ? OR i.whatsapp LIKE ? OR i.country LIKE ?)";
            $searchTerm = "%" . $searchQuery . "%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        $sql .= " ORDER BY i.id DESC LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $newRecords = $stmt->fetchAll();

        if (!empty($newRecords)) {
            $hasNew = true;
            $csrf = getCsrfToken();
            foreach ($newRecords as $rec) {
                $newRowsHtml .= renderInquiryRow($rec, $csrf, true);
                $country = detectCountryName($rec['country'] ?? '', $rec['email'] ?? '', $rec['whatsapp'] ?? '', $rec['company_name'] ?? '', $rec['message'] ?? '');
                $previews[] = [
                    'id' => (int)$rec['id'],
                    'buyer_name' => $rec['buyer_name'],
                    'company_name' => $rec['company_name'] ?: 'Private Trader',
                    'mineral_type' => $rec['mineral_type'],
                    'country' => $country,
                    'quantity' => $rec['quantity'] ?: 'Unspecified',
                    'created_at' => $rec['created_at']
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'has_new' => $hasNew,
        'latest_id' => $maxId,
        'new_count' => count($previews),
        'rows_html' => $newRowsHtml,
        'previews' => $previews,
        'metrics' => [
            'totalLeads'     => $totalLeads,
            'newLeads'       => $newLeads,
            'contactedLeads' => $contactedLeads,
            'quotedLeads'    => $quotedLeads,
            'closedLeads'    => $closedLeads,
            'pendingReviews' => $pendingReviews
        ],
        'timestamp' => time()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
