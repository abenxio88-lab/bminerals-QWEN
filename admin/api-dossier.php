<?php
/**
 * Balochistan Minerals - 360° Buyer Dossier & Conversation Threading API
 * Aggregates all inbound/outbound emails, multi-touch activity, notes, and Ball-in-Court status.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flag-helper.php';
require_once __DIR__ . '/smtp-mailer.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required. Please refresh or sign in.']);
    exit;
}

$pdo = getDB();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit;
}

// ------------------------------------------
// 1. GET: Fetch Buyer Dossier & Message Thread
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $inquiryId = (int)($_GET['inquiry_id'] ?? 0);
    if ($inquiryId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid Inquiry ID']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ?");
    $stmt->execute([$inquiryId]);
    $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inquiry) {
        echo json_encode(['success' => false, 'error' => 'Inquiry not found']);
        exit;
    }

    $buyerEmail = trim(strtolower($inquiry['email'] ?? ''));

    // Fetch all related messages (inbound & outbound)
    if (!empty($buyerEmail)) {
        $msgStmt = $pdo->prepare("SELECT * FROM email_messages 
            WHERE to_email = ? OR from_email = ? OR inquiry_id = ?
            ORDER BY sent_at ASC, id ASC");
        $msgStmt->execute([$buyerEmail, $buyerEmail, $inquiryId]);
    } else {
        $msgStmt = $pdo->prepare("SELECT * FROM email_messages WHERE inquiry_id = ? ORDER BY sent_at ASC, id ASC");
        $msgStmt->execute([$inquiryId]);
    }
    $rawMessages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format messages for thread timeline
    $messages = [];

    // If initial inquiry contains user message text, include it as turn #1
    if (!empty($inquiry['message'])) {
        $messages[] = [
            'id' => 0,
            'direction' => 'inbound',
            'from_email' => $inquiry['email'] ?: 'Inbound Web Portal',
            'to_email' => 'sales@balochistanminerals.com',
            'subject' => 'Web RFQ: ' . $inquiry['mineral_type'] . ' (' . ($inquiry['quantity'] ?: 'Export') . ')',
            'body_text' => $inquiry['message'],
            'sent_at' => date('M d, Y • h:i A', strtotime($inquiry['created_at'])),
            'timestamp' => strtotime($inquiry['created_at'])
        ];
    }

    foreach ($rawMessages as $m) {
        $messages[] = [
            'id' => (int)$m['id'],
            'direction' => $m['direction'] ?: 'outbound',
            'from_email' => $m['from_email'],
            'to_email' => $m['to_email'],
            'subject' => $m['subject'],
            'body_text' => $m['body_text'],
            'sent_at' => date('M d, Y • h:i A', strtotime($m['sent_at'])),
            'timestamp' => strtotime($m['sent_at'])
        ];
    }

    // Determine Ball-in-Court Status
    $lastDir = !empty($messages) ? $messages[count($messages) - 1]['direction'] : 'inbound';
    $lastTime = !empty($messages) ? $messages[count($messages) - 1]['timestamp'] : strtotime($inquiry['created_at']);
    $daysDiff = (time() - $lastTime) / 86400;

    $ballInCourt = [
        'code' => 'desk_turn',
        'label' => 'Action: Desk Turn',
        'sub' => 'Buyer waiting for quotation / response',
        'badgeClass' => 'bg-danger-subtle text-danger border border-danger-subtle',
        'icon' => 'bi-exclamation-circle-fill'
    ];

    if ($inquiry['status'] === 'Contract Signed') {
        $ballInCourt = [
            'code' => 'closed',
            'label' => 'Contract Executed',
            'sub' => 'Export deal locked & finalized',
            'badgeClass' => 'bg-success-subtle text-success border border-success-subtle',
            'icon' => 'bi-check-circle-fill'
        ];
    } elseif ($lastDir === 'outbound') {
        if ($daysDiff >= 5) {
            $ballInCourt = [
                'code' => 'stalled',
                'label' => 'Stalled (Follow-up)',
                'sub' => 'No activity for ' . floor($daysDiff) . ' days. Re-engage client.',
                'badgeClass' => 'bg-warning-subtle text-warning border border-warning-subtle',
                'icon' => 'bi-hourglass-split'
            ];
        } else {
            $ballInCourt = [
                'code' => 'buyer_turn',
                'label' => 'Awaiting Buyer',
                'sub' => 'Quote dispatched. Ball is in client court.',
                'badgeClass' => 'bg-primary-subtle text-primary border border-primary-subtle',
                'icon' => 'bi-clock-history'
            ];
        }
    }

    // Estimated Deal USD Size
    $qtyStr = preg_replace('/[^0-9]/', '', $inquiry['quantity'] ?? '0');
    $qty = (int)$qtyStr ?: 1500;
    $spotPrice = (float)($inquiry['spot_price_quoted'] ?? 0);
    if ($spotPrice <= 0) {
        // Fallback to mineral benchmark price
        $mLower = strtolower($inquiry['mineral_type'] ?? '');
        $priceRow = $pdo->query("SELECT price_high_usd FROM mineral_prices WHERE LOWER(mineral_name) LIKE '%" . addslashes(substr($mLower, 0, 5)) . "%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $spotPrice = (float)($priceRow['price_high_usd'] ?? 220);
    }
    $estimatedDealUsd = $qty * $spotPrice;

    // Buyer Initials
    $words = preg_split('/\s+/', trim($inquiry['buyer_name'] ?? 'Buyer'));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) $initials .= mb_substr($w, 0, 1);
        if (strlen($initials) >= 2) break;
    }
    $initials = strtoupper($initials ?: 'BM');

    // WhatsApp Pre-filled link
    $waText = "Hello " . $inquiry['buyer_name'] . ", this is Balochistan Minerals regarding your export inquiry for " . $inquiry['mineral_type'] . " (" . $inquiry['quantity'] . "). We have verified port stockpiles ready.";
    $waUrl = generateWhatsAppLink($inquiry['whatsapp'] ?? '', $waText);

    echo json_encode([
        'success' => true,
        'inquiry' => [
            'id' => (int)$inquiry['id'],
            'buyer_name' => $inquiry['buyer_name'],
            'company_name' => $inquiry['company_name'] ?: 'Private Commodity Trader',
            'country' => $inquiry['country'] ?: 'International',
            'email' => $inquiry['email'],
            'whatsapp' => $inquiry['whatsapp'],
            'mineral_type' => $inquiry['mineral_type'],
            'quantity' => $inquiry['quantity'] ?: 'Trial / FCL Order',
            'destination_port' => $inquiry['destination_port'] ?: 'FOB Karachi / CIF',
            'status' => $inquiry['status'],
            'spot_price_quoted' => $spotPrice,
            'estimated_deal_usd' => $estimatedDealUsd,
            'internal_notes' => $inquiry['internal_notes'] ?? '',
            'message' => $inquiry['message'] ?? '',
            'created_at' => date('M d, Y • h:i A', strtotime($inquiry['created_at'])),
            'initials' => $initials,
            'wa_url' => $waUrl
        ],
        'flag_svg' => getCountryFlagSvg($inquiry['country'] ?? ''),
        'ball_in_court' => $ballInCourt,
        'message_count' => count($messages),
        'messages' => $messages
    ]);
    exit;
}

// ------------------------------------------
// 2. POST: In-Dossier Actions (Notes, Quick Reply, Status)
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = cleanInput($_POST['action'] ?? '');
    $inquiryId = (int)($_POST['inquiry_id'] ?? 0);

    if ($inquiryId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid Inquiry ID']);
        exit;
    }

    // A. Save Private Scratchpad Notes
    if ($action === 'save_notes') {
        $notes = cleanInput($_POST['notes'] ?? '');
        $stmt = $pdo->prepare("UPDATE inquiries SET internal_notes = ? WHERE id = ?");
        $stmt->execute([$notes, $inquiryId]);
        echo json_encode(['success' => true, 'message' => 'Internal deal notes updated.']);
        exit;
    }

    // B. Update Deal Stage Status
    if ($action === 'update_status') {
        $newStatus = cleanInput($_POST['status'] ?? 'Contacted');
        $stmt = $pdo->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $inquiryId]);
        echo json_encode(['success' => true, 'message' => "Stage updated to [$newStatus]."]);
        exit;
    }

    // C. Send Direct Reply from Inside the Dossier
    if ($action === 'send_reply') {
        $toEmail = filter_var(trim($_POST['to_email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $subject = cleanInput($_POST['subject'] ?? 'Export Inquiry Follow-up');
        $body    = cleanInput($_POST['body'] ?? '');
        $fromEmail = cleanInput($_POST['from_email'] ?? 'sales@balochistanminerals.com');

        if (empty($toEmail) || empty($body)) {
            echo json_encode(['success' => false, 'error' => 'Please provide recipient and reply text.']);
            exit;
        }

        // Dispatch via Hostinger SMTP
        $res = sendHostingerMail($toEmail, $subject, $body, $fromEmail, null, [], $inquiryId);

        // Update inquiry status
        $logNote = "[" . date('M d, Y H:i') . "] Dossier reply sent to " . $toEmail . ": " . $subject . "\n-------------------\n";
        $up = $pdo->prepare("UPDATE inquiries SET status = CASE WHEN status = 'New' THEN 'Contacted' ELSE status END, internal_notes = CONCAT(?, COALESCE(internal_notes, '')) WHERE id = ?");
        $up->execute([$logNote, $inquiryId]);

        echo json_encode([
            'success' => true,
            'message' => 'Official reply dispatched via Hostinger SMTP relay.',
            'sent_at' => date('M d, Y • h:i A')
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}
