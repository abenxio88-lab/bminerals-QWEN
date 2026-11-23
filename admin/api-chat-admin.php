<?php
/**
 * Balochistan Minerals - Live Chat Admin API
 * Real-time polling, message sending, session claiming, buyer intelligence, and RFQ conversion.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/chat-helpers.php';
requirePermission('draft_reply', true);

$pdo = getDB();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
    exit;
}

ensureLiveChatSchema($pdo);

$user = getLoggedInUser();
$uid = (int)($user['id'] ?? 0);
$agentName = $user['full_name'] ?: ($user['username'] ?? 'Export Desk Specialist');
$agentRole = $user['tier'] ?? 'Sales Specialist';

/**
 * Returns a clean, highly professional B2B trade desk officer title for visitors.
 */
function getCleanOfficerPublicTitle($name, $role = '') {
    return 'BM Helper Desk';
}

/**
 * Enriches raw chat session with enterprise buyer intelligence (flag, country, city, local time, distinct ID).
 */
function enrichSessionForAdmin(array $s): array {
    $id = (int)$s['id'];
    $name = trim((string)($s['visitor_name'] ?? ''));
    if ($name === '' || $name === 'Guest Buyer') {
        $name = 'Buyer #' . (1000 + $id);
    }

    $cCode = strtoupper(trim((string)($s['country_code'] ?? '')));
    if ($cCode === '' || $cCode === 'XX') {
        $cName = trim((string)($s['country'] ?? ''));
        if ($cName === 'China') $cCode = 'CN';
        elseif ($cName === 'Pakistan') $cCode = 'PK';
        elseif ($cName === 'United Arab Emirates' || $cName === 'UAE') $cCode = 'AE';
        elseif ($cName === 'United States' || $cName === 'USA') $cCode = 'US';
        elseif ($cName === 'United Kingdom' || $cName === 'UK') $cCode = 'GB';
        elseif ($cName === 'Saudi Arabia') $cCode = 'SA';
        elseif ($cName === 'India') $cCode = 'IN';
        elseif ($cName === 'Turkey') $cCode = 'TR';
        elseif (!empty($s['timezone'])) {
            $tzGeo = inferGeoFromTimezone($s['timezone']);
            $cCode = $tzGeo['code'];
        }
    }

    $meta = getCountryMetaByCode($cCode);
    $countryDisplay = ($meta['name'] !== 'International') ? $meta['name'] : ($s['country'] ?: 'International');
    $flag = $meta['flag'];
    $city = trim((string)($s['city'] ?? ''));

    if ($city !== '' && $countryDisplay !== 'International') {
        $countryWithCity = "{$flag} {$countryDisplay} ({$city})";
    } elseif ($countryDisplay !== 'International') {
        $countryWithCity = "{$flag} {$countryDisplay}";
    } else {
        $countryWithCity = "International";
    }

    $localTime = getVisitorLocalTimeStr($s['timezone'] ?? null);

    $page = trim((string)($s['current_page'] ?? ''));
    if ($page === '') $page = '/';

    return [
        'id'                => $id,
        'token'             => $s['session_token'],
        'name'              => $name,
        'display_name'      => $name,
        'email'             => $s['visitor_email'] ?: '',
        'phone'             => $s['visitor_phone'] ?: '',
        'ip'                => $s['visitor_ip'] ?: '',
        'country'           => $countryDisplay,
        'country_code'      => $cCode,
        'city'              => $city,
        'flag'              => $flag,
        'country_with_city' => $countryWithCity,
        'timezone'          => $s['timezone'] ?: 'UTC',
        'local_time_str'    => $localTime,
        'page'              => $page,
        'current_page'      => $page,
        'mineral'           => $s['mineral_interest'] ?: 'Export Minerals',
        'mineral_interest'  => $s['mineral_interest'] ?: 'Export Minerals',
        'internal_notes'    => $s['internal_notes'] ?? '',
        'status'            => $s['status'],
        'assigned_id'       => (int)($s['assigned_admin_id'] ?? 0),
        'assigned_name'     => $s['assigned_admin_name'] ?: 'Unassigned',
        'last_message'      => $s['last_message'] ?: '',
        'last_sender'       => $s['last_message_sender'] ?: '',
        'last_time'         => !empty($s['last_message_at']) ? date('h:i A', strtotime($s['last_message_at'])) : '',
        'unread_admin'      => (int)($s['unread_admin_count'] ?? 0),
    ];
}

$raw = file_get_contents('php://input');
$json = json_decode($raw, true) ?: [];
$action = cleanInput($json['action'] ?? $_POST['action'] ?? $_GET['action'] ?? 'poll');

// 1. POLL / FETCH ACTIVE SESSIONS
if ($action === 'poll_sessions') {
    $filter = cleanInput($_GET['filter'] ?? 'active');

    $where = "1=1";
    if ($filter === 'waiting') {
        $where = "status = 'waiting_human'";
    } elseif ($filter === 'my_chats') {
        $where = "assigned_admin_id = " . (int)$uid . " AND status = 'agent_active'";
    } elseif ($filter === 'closed') {
        $where = "status = 'closed'";
    } else {
        // Active desk queue shows all active visitors (conversing with AI bot, waiting for human, or claimed by agent)
        $where = "status IN ('bot', 'waiting_human', 'agent_active')";
    }

    $stmt = $pdo->query("SELECT * FROM `chat_sessions` WHERE {$where} ORDER BY (status = 'waiting_human') DESC, last_message_at DESC LIMIT 50");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $waitingCount = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status = 'waiting_human'")->fetchColumn();
    $activeCount  = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status IN ('bot', 'waiting_human', 'agent_active')")->fetchColumn();
    $myCount      = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status = 'agent_active' AND assigned_admin_id = {$uid}")->fetchColumn();

    echo json_encode([
        'success'       => true,
        'waiting_count' => $waitingCount,
        'active_count'  => $activeCount,
        'my_count'      => $myCount,
        'sessions'      => array_map('enrichSessionForAdmin', $sessions),
    ]);
    exit;
}

// 2. FETCH MESSAGES FOR A SESSION
if ($action === 'fetch_messages') {
    $sessionId = (int)($_GET['session_id'] ?? $json['session_id'] ?? 0);
    $lastId    = (int)($_GET['last_id'] ?? $json['last_id'] ?? 0);

    if ($sessionId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid session ID']);
        exit;
    }

    $sStmt = $pdo->prepare("SELECT * FROM `chat_sessions` WHERE id = ? LIMIT 1");
    $sStmt->execute([$sessionId]);
    $rawSession = $sStmt->fetch(PDO::FETCH_ASSOC);

    if (!$rawSession) {
        echo json_encode(['success' => false, 'error' => 'Session not found']);
        exit;
    }

    $mStmt = $pdo->prepare("SELECT * FROM `chat_messages` WHERE session_id = ? AND id > ? ORDER BY id ASC");
    $mStmt->execute([$sessionId, $lastId]);
    $messages = $mStmt->fetchAll(PDO::FETCH_ASSOC);

    // Reset unread for admin
    if (!empty($messages)) {
        $pdo->prepare("UPDATE `chat_messages` SET is_read = 1 WHERE session_id = ? AND sender_type = 'visitor'")
            ->execute([$sessionId]);
        $pdo->prepare("UPDATE `chat_sessions` SET unread_admin_count = 0 WHERE id = ?")
            ->execute([$sessionId]);
    }

    // Clean assigned officer title
    if (!empty($rawSession['assigned_admin_name'])) {
        $rawSession['assigned_admin_name'] = getCleanOfficerPublicTitle($rawSession['assigned_admin_name']);
    }

    $enriched = enrichSessionForAdmin($rawSession);

    echo json_encode([
        'success'  => true,
        'session'  => $enriched,
        'messages' => array_map(function($m) {
            $sender = $m['sender_name'];
            if ($m['sender_type'] === 'agent') {
                $sender = getCleanOfficerPublicTitle($sender);
            }
            return [
                'id'          => (int)$m['id'],
                'sender_type' => $m['sender_type'],
                'sender_name' => $sender,
                'text'        => $m['message_text'],
                'time'        => date('h:i A', strtotime($m['created_at'])),
                'is_read'     => (int)$m['is_read'],
            ];
        }, $messages),
    ]);
    exit;
}

// 3. SEND AGENT MESSAGE
if ($action === 'send_agent_message') {
    $sessionId   = (int)($json['session_id'] ?? $_POST['session_id'] ?? 0);
    $messageText = cleanInput($json['message'] ?? $_POST['message'] ?? '');

    if ($sessionId <= 0 || $messageText === '') {
        echo json_encode(['success' => false, 'error' => 'Session and message are required']);
        exit;
    }

    // Auto-claim if unassigned or in waiting_human
    $sStmt = $pdo->prepare("SELECT * FROM `chat_sessions` WHERE id = ? LIMIT 1");
    $sStmt->execute([$sessionId]);
    $session = $sStmt->fetch(PDO::FETCH_ASSOC);

    if ($session) {
        if ($session['status'] === 'waiting_human' || empty($session['assigned_admin_id'])) {
            $pdo->prepare("UPDATE `chat_sessions` SET 
                status = 'agent_active', 
                assigned_admin_id = ?, 
                assigned_admin_name = ?, 
                updated_at = NOW() 
                WHERE id = ?")
                ->execute([$uid, $agentName, $sessionId]);
            
            logChatMessage($pdo, $sessionId, 'system', 'Trade Desk System', "BM Helper Desk has joined the conversation to assist your inquiry.");
        }
    }

    $publicOfficerTitle = getCleanOfficerPublicTitle($agentName, $agentRole);
    $msgId = logChatMessage($pdo, $sessionId, 'agent', $publicOfficerTitle, $messageText);

    echo json_encode([
        'success' => true,
        'msg_id'  => $msgId,
        'time'    => date('h:i A'),
    ]);
    exit;
}

// 4. CLAIM CHAT SESSION
if ($action === 'claim_session') {
    $sessionId = (int)($json['session_id'] ?? $_POST['session_id'] ?? 0);
    if ($sessionId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid session ID']);
        exit;
    }

    $pdo->prepare("UPDATE `chat_sessions` SET 
        status = 'agent_active', 
        assigned_admin_id = ?, 
        assigned_admin_name = ?, 
        updated_at = NOW() 
        WHERE id = ?")
        ->execute([$uid, $agentName, $sessionId]);

    logChatMessage($pdo, $sessionId, 'system', 'Trade Desk System', "BM Helper Desk has joined the conversation to assist your inquiry.");

    echo json_encode(['success' => true]);
    exit;
}

// 5. CLOSE CHAT SESSION
if ($action === 'close_session') {
    $sessionId = (int)($json['session_id'] ?? $_POST['session_id'] ?? 0);
    if ($sessionId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid session ID']);
        exit;
    }

    $pdo->prepare("UPDATE `chat_sessions` SET status = 'closed', updated_at = NOW() WHERE id = ?")
        ->execute([$sessionId]);

    logChatMessage($pdo, $sessionId, 'system', 'Trade Desk System', "Live session closed and archived by BM Helper Desk.");

    echo json_encode(['success' => true]);
    exit;
}

// 6. CONVERT TO RFQ / INQUIRY
if ($action === 'convert_to_rfq') {
    $sessionId = (int)($json['session_id'] ?? $_POST['session_id'] ?? 0);
    if ($sessionId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid session ID']);
        exit;
    }

    $sStmt = $pdo->prepare("SELECT * FROM `chat_sessions` WHERE id = ? LIMIT 1");
    $sStmt->execute([$sessionId]);
    $session = $sStmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        echo json_encode(['success' => false, 'error' => 'Session not found']);
        exit;
    }

    // Get messages for summary
    $mStmt = $pdo->prepare("SELECT sender_type, sender_name, message_text FROM `chat_messages` WHERE session_id = ? ORDER BY id ASC");
    $mStmt->execute([$sessionId]);
    $msgs = $mStmt->fetchAll(PDO::FETCH_ASSOC);

    $transcript = "=== LIVE CHAT TRANSCRIPT ===\n";
    foreach ($msgs as $m) {
        $transcript .= "[{$m['sender_type']}] {$m['sender_name']}: {$m['message_text']}\n";
    }

    $inqStmt = $pdo->prepare("INSERT INTO inquiries 
        (full_name, email, phone, company_name, mineral_type, notes, source, status, internal_notes, created_at)
        VALUES (?, ?, ?, 'Live Chat Sourcing', ?, ?, 'Live Chat Desk', 'New', ?, NOW())");
    
    $buyerName = $session['visitor_name'] ?: ('Buyer #' . (1000 + (int)$session['id']));
    $email = $session['visitor_email'] ?: ('chat_' . $session['id'] . '@guest.balochistanminerals.com');
    $phone = $session['visitor_phone'] ?: '';
    $mineral = $session['mineral_interest'] ?: 'Export Minerals';
    $notes = "Origin: {$session['country']} (IP: {$session['visitor_ip']}). Page: {$session['current_page']}";
    $inqStmt->execute([$buyerName, $email, $phone, $mineral, $notes, $transcript]);
    $inquiryId = (int)$pdo->lastInsertId();

    logChatMessage($pdo, $sessionId, 'system', 'Trade Desk System', "Conversation converted to formal Inquiry #{$inquiryId} by Officer {$agentName}.");

    echo json_encode([
        'success'    => true,
        'inquiry_id' => $inquiryId,
    ]);
    exit;
}

// 7. UPDATE BUYER DOSSIER
if ($action === 'update_buyer_dossier') {
    $sessionId = (int)($json['session_id'] ?? $_POST['session_id'] ?? 0);
    if ($sessionId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid session ID']);
        exit;
    }

    $name    = cleanInput($json['visitor_name'] ?? $_POST['visitor_name'] ?? '');
    $email   = cleanInput($json['visitor_email'] ?? $_POST['visitor_email'] ?? '');
    $phone   = cleanInput($json['visitor_phone'] ?? $_POST['visitor_phone'] ?? '');
    $mineral = cleanInput($json['mineral_interest'] ?? $_POST['mineral_interest'] ?? '');
    $notes   = cleanInput($json['internal_notes'] ?? $_POST['internal_notes'] ?? '');

    $pdo->prepare("UPDATE `chat_sessions` SET 
        visitor_name = COALESCE(NULLIF(?, ''), visitor_name),
        visitor_email = ?,
        visitor_phone = ?,
        mineral_interest = COALESCE(NULLIF(?, ''), mineral_interest),
        internal_notes = ?,
        updated_at = NOW()
        WHERE id = ?")
        ->execute([$name, $email, $phone, $mineral, $notes, $sessionId]);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
