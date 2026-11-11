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
require_once __DIR__ . '/mail-helpers.php';

requirePermission('view_rfqs', true);

$pdo = getDB();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit;
}
ensureTeamSecuritySchema($pdo);
$currentUser = getLoggedInUser();
$currentUid  = (int)($currentUser['id'] ?? 0);
$currentTier = $currentUser['tier'] ?? 'Owner';

// ------------------------------------------
// 1. GET: Fetch Buyer Dossier & Message Thread
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $canAssignDossier = hasPermission('assign_records');
    $canDirectDossier = hasPermission('send_direct_email');
    releaseSessionLock();
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
    if ($currentTier === 'Junior' && !empty($inquiry['assigned_user_id']) && (int)$inquiry['assigned_user_id'] !== $currentUid) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'This buyer RFQ is assigned to another colleague.']);
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

    // If initial inquiry contains user message text and isn't already mirrored in email_messages, include it as turn #1
    $hasMirroredInitial = false;
    $initialMsgId = trim($inquiry['email_message_id'] ?? '');
    $initialTrimmed = trim($inquiry['message'] ?? '');
    foreach ($rawMessages as $m) {
        if ($initialMsgId !== '' && trim($m['message_id'] ?? '') === $initialMsgId) {
            $hasMirroredInitial = true;
            break;
        }
        if ($initialTrimmed !== '' && ($m['direction'] ?? '') === 'inbound' && strpos(trim($m['body_text'] ?? ''), $initialTrimmed) !== false) {
            $hasMirroredInitial = true;
            break;
        }
    }

    if ($initialTrimmed !== '' && !$hasMirroredInitial) {
        $sourceMailbox = (!empty($inquiry['source']) && strpos($inquiry['source'], '@') !== false)
            ? $inquiry['source']
            : 'sales@balochistanminerals.com';
        $cleanInit = cleanEmailPlainText($inquiry['message']);
        $messages[] = [
            'id' => 0,
            'direction' => 'inbound',
            'from_email' => $inquiry['email'] ?: 'Inbound Web Portal',
            'to_email' => $sourceMailbox,
            'subject' => 'Web RFQ: ' . $inquiry['mineral_type'] . ' (' . ($inquiry['quantity'] ?: 'Export') . ')',
            'body_text' => $cleanInit,
            'body_html' => buildRenderableEmailHtml(null, $inquiry['message'], 0, []),
            'attachments' => [],
            'sent_at' => date('M d, Y • h:i A', strtotime($inquiry['created_at'])),
            'timestamp' => strtotime($inquiry['created_at'])
        ];
    }

    foreach ($rawMessages as $m) {
        $msgIdInt = (int)$m['id'];
        $attMeta  = decodeAttachmentsMeta($m['attachments'] ?? '');
        $messages[] = [
            'id' => $msgIdInt,
            'direction' => $m['direction'] ?: 'outbound',
            'from_email' => $m['from_email'],
            'to_email' => $m['to_email'],
            'subject' => $m['subject'],
            'body_text' => cleanEmailPlainText($m['body_text'] ?? ''),
            'body_html' => buildRenderableEmailHtml($m['body_html'] ?? null, $m['body_text'] ?? '', $msgIdInt, $attMeta),
            'attachments' => formatPublicAttachmentsForMessage($msgIdInt, $m['attachments'] ?? ''),
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
        'label' => 'Desk Turn',
        'sub' => 'Buyer waiting for quotation / response',
        'badgeClass' => 'badge-vel-desk',
        'icon' => 'bi-arrow-return-right'
    ];

    if ($inquiry['status'] === 'Contract Signed') {
        $ballInCourt = [
            'code' => 'closed',
            'label' => 'Contract Executed',
            'sub' => 'Export deal locked & finalized',
            'badgeClass' => 'badge-vel-closed',
            'icon' => 'bi-check-circle-fill'
        ];
    } elseif ($lastDir === 'outbound') {
        if ($daysDiff >= 5) {
            $ballInCourt = [
                'code' => 'stalled',
                'label' => 'Stalled (' . floor($daysDiff) . 'd)',
                'sub' => 'No activity for ' . floor($daysDiff) . ' days. Re-engage client.',
                'badgeClass' => 'badge-vel-stalled',
                'icon' => 'bi-hourglass-split'
            ];
        } else {
            $ballInCourt = [
                'code' => 'buyer_turn',
                'label' => 'Awaiting Buyer',
                'sub' => 'Quote dispatched. Ball is in client court.',
                'badgeClass' => 'badge-vel-buyer',
                'icon' => 'bi-clock-history'
            ];
        }
    }

    // Estimated Deal USD Size (only compute when buyer specifies explicit tonnage/quantity)
    $rawQtyText = trim((string)($inquiry['quantity'] ?? ''));
    $qty = 0;
    if ($rawQtyText !== '' && preg_match('/([0-9][0-9,]*(?:\.[0-9]+)?)/', $rawQtyText, $qm)) {
        $qty = (float)str_replace(',', '', $qm[1]);
    }
    $spotPrice = (float)($inquiry['spot_price_quoted'] ?? 0);
    $benchmarkRow = function_exists('getMineralBenchmarkForType') ? getMineralBenchmarkForType($inquiry['mineral_type'] ?? '') : null;
    if ($spotPrice <= 0 && $benchmarkRow) {
        $spotPrice = (float)($benchmarkRow['price_high_usd'] ?? 0);
    }
    $estimatedDealUsd = ($qty > 0 && $spotPrice > 0) ? round($qty * $spotPrice) : null;

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

    $resolvedCountry = detectCountryName($inquiry['country'] ?? '', $inquiry['email'] ?? '', $inquiry['whatsapp'] ?? '', $inquiry['company_name'] ?? '', $inquiry['message'] ?? '');

    echo json_encode([
        'success' => true,
        'inquiry' => [
            'id' => (int)$inquiry['id'],
            'buyer_name' => $inquiry['buyer_name'],
            'company_name' => $inquiry['company_name'] ?: 'Private Commodity Trader',
            'country' => $resolvedCountry,
            'email' => $inquiry['email'],
            'whatsapp' => $inquiry['whatsapp'],
            'mineral_type' => $inquiry['mineral_type'],
            'quantity' => $inquiry['quantity'] ?: 'Open / TBD',
            'destination_port' => $inquiry['destination_port'] ?: 'FOB Karachi / CIF',
            'status' => $inquiry['status'],
            'spot_price_quoted' => $spotPrice,
            'benchmark' => $benchmarkRow ? [
                'grade' => $benchmarkRow['grade_name'] ?? '',
                'purity' => $benchmarkRow['purity_spec'] ?? '',
                'price_low' => (float)($benchmarkRow['price_low_usd'] ?? 0),
                'price_high' => (float)($benchmarkRow['price_high_usd'] ?? 0),
                'delivery' => $benchmarkRow['delivery_terms'] ?? 'FOB Karachi',
            ] : null,
            'estimated_deal_usd' => $estimatedDealUsd,
            'internal_notes' => $inquiry['internal_notes'] ?? '',
            'message' => cleanEmailPlainText($inquiry['message'] ?? ''),
            'message_html' => buildRenderableEmailHtml(null, $inquiry['message'] ?? '', 0, []),
            'source' => $inquiry['source'] ?? '',
            'created_at' => date('M d, Y • h:i A', strtotime($inquiry['created_at'])),
            'initials' => $initials,
            'wa_url' => $waUrl,
            'assigned_user_id' => !empty($inquiry['assigned_user_id']) ? (int)$inquiry['assigned_user_id'] : null,
            'can_assign' => hasPermission('assign_records'),
            'can_send_direct_email' => hasPermission('send_direct_email'),
            'staff_list' => hasPermission('assign_records') ? getActiveStaffList($pdo) : []
        ],
        'flag_svg' => getCountryFlagSvg($resolvedCountry, $inquiry['email'] ?? '', $inquiry['whatsapp'] ?? '', $inquiry['company_name'] ?? ''),
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

    if ($currentTier === 'Junior') {
        $chkAs = $pdo->prepare("SELECT assigned_user_id FROM inquiries WHERE id = ? LIMIT 1");
        $chkAs->execute([$inquiryId]);
        $asUid = $chkAs->fetchColumn();
        if (!empty($asUid) && (int)$asUid !== $currentUid) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'This inquiry is assigned to another colleague.']);
            exit;
        }
    }

    // Assigning Inquiry to Staff (Owner / Manager Only)
    if ($action === 'assign_inquiry') {
        requirePermission('assign_records', true);
        $assigneeId = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
        $pdo->prepare("UPDATE inquiries SET assigned_user_id = ? WHERE id = ?")->execute([$assigneeId, $inquiryId]);
        $aName = 'Unassigned';
        if ($assigneeId) {
            $uSt = $pdo->prepare("SELECT full_name FROM admin_users WHERE id = ? LIMIT 1");
            $uSt->execute([$assigneeId]);
            $aName = $uSt->fetchColumn() ?: ('Staff #' . $assigneeId);
        }
        logSystemAudit($pdo, 'rfq', "Assigned RFQ #{$inquiryId} to {$aName}", $inquiryId);
        echo json_encode(['success' => true, 'assigned_name' => $aName, 'message' => "Lead assigned to {$aName}."]);
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

    // C. Send Direct Reply from Inside the Dossier (with Optional Multipart File Attachments)
    if ($action === 'send_reply') {
        $toEmail = filter_var(trim($_POST['to_email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $subject = cleanInput($_POST['subject'] ?? 'Export Inquiry Follow-up');
        $body    = cleanInput($_POST['body'] ?? '');
        $fromEmail = cleanInput($_POST['from_email'] ?? 'sales@balochistanminerals.com');

        if (empty($toEmail) || empty($body)) {
            echo json_encode(['success' => false, 'error' => 'Please provide recipient and reply text.']);
            exit;
        }

        $uploadRes = handleUploadedAttachments('attachments');
        if (!empty($uploadRes['errors'])) {
            echo json_encode(['success' => false, 'error' => implode(' ', $uploadRes['errors'])]);
            exit;
        }
        $attachedFiles = $uploadRes['files'];

        // Dispatch via Hostinger SMTP OR Maker-Checker Approval Queue
        $res = dispatchOrQueueStaffEmail($pdo, [
            'to_email'    => $toEmail,
            'from_email'  => $fromEmail,
            'subject'     => $subject,
            'body_text'   => $body,
            'attachments' => $attachedFiles,
            'inquiry_id'  => $inquiryId,
        ]);

        if (!empty($res['queued_for_approval'])) {
            $logNote = "[" . date('M d, Y H:i') . "] Draft reply queued for approval (" . $currentUser['full_name'] . ") to " . $toEmail . ": " . $subject . "\n-------------------\n";
            $pdo->prepare("UPDATE inquiries SET internal_notes = CONCAT(?, COALESCE(internal_notes, '')) WHERE id = ?")->execute([$logNote, $inquiryId]);
        } else {
            $attNote = !empty($attachedFiles) ? (" [Attachments: " . implode(', ', array_column($attachedFiles, 'name')) . "]") : '';
            $logNote = "[" . date('M d, Y H:i') . "] Dossier reply sent from " . $fromEmail . " to " . $toEmail . ": " . $subject . $attNote . "\n-------------------\n";
            $up = $pdo->prepare("UPDATE inquiries SET status = CASE WHEN status = 'New' THEN 'Contacted' ELSE status END, internal_notes = CONCAT(?, COALESCE(internal_notes, '')) WHERE id = ?");
            $up->execute([$logNote, $inquiryId]);
        }

        if (empty($res['success'])) {
            echo json_encode([
                'success' => false,
                'error' => $res['error'] ?? 'SMTP delivery failed.',
                'sent_at' => date('M d, Y • h:i A')
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'queued_for_approval' => !empty($res['queued_for_approval']),
            'attachment_count' => count($attachedFiles),
            'attachments' => $res['attachments'] ?? [],
            'message' => $res['message'] ?? 'Official reply dispatched via Hostinger SMTP relay.',
            'sent_at' => date('M d, Y • h:i A')
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}
