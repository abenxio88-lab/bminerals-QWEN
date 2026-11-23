<?php
/**
 * Balochistan Minerals - Live Chat Admin API
 * Real-time polling, message sending, session claiming, and RFQ conversion.
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
        // active = waiting_human or agent_active or recent bot
        $where = "status IN ('waiting_human', 'agent_active') OR (status = 'bot' AND last_message_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR))";
    }

    $stmt = $pdo->query("SELECT * FROM `chat_sessions` WHERE {$where} ORDER BY (status = 'waiting_human') DESC, last_message_at DESC LIMIT 50");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $waitingCount = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status = 'waiting_human'")->fetchColumn();
    $activeCount  = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status = 'agent_active'")->fetchColumn();
    $myCount      = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status = 'agent_active' AND assigned_admin_id = {$uid}")->fetchColumn();

    echo json_encode([
        'success'       => true,
        'waiting_count' => $waitingCount,
        'active_count'  => $activeCount,
        'my_count'      => $myCount,
        'sessions'      => array_map(function($s) {
            return [
                'id'            => (int)$s['id'],
                'token'         => $s['session_token'],
                'name'          => $s['visitor_name'],
                'email'         => $s['visitor_email'],
                'phone'         => $s['visitor_phone'],
                'ip'            => $s['visitor_ip'],
                'country'       => $s['country'] ?: 'International',
                'page'          => $s['current_page'],
                'mineral'       => $s['mineral_interest'],
                'status'        => $s['status'],
                'assigned_id'   => (int)$s['assigned_admin_id'],
                'assigned_name' => $s['assigned_admin_name'],
                'last_message'  => $s['last_message'],
                'last_sender'   => $s['last_message_sender'],
                'last_time'     => date('h:i A', strtotime($s['last_message_at'])),
                'unread_admin'  => (int)$s['unread_admin_count'],
            ];
        }, $sessions),
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
    $session = $sStmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
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

    echo json_encode([
        'success'  => true,
        'session'  => $session,
        'messages' => array_map(function($m) {
            return [
                'id'          => (int)$m['id'],
                'sender_type' => $m['sender_type'],
                'sender_name' => $m['sender_name'],
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
            
            logChatMessage($pdo, $sessionId, 'system', 'Trade Desk System', "Officer {$agentName} has joined the conversation.");
        }
    }

    $msgId = logChatMessage($pdo, $sessionId, 'agent', $agentName . ' (' . $agentRole . ')', $messageText);

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

    logChatMessage($pdo, $sessionId, 'system', 'Trade Desk System', "Officer {$agentName} has joined the conversation.");

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

    logChatMessage($pdo, $sessionId, 'system', 'Trade Desk System', "This live session has been closed by Officer {$agentName}.");

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
    
    $buyerName = $session['visitor_name'] ?: 'Live Chat Visitor';
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

echo json_encode(['success' => false, 'error' => 'Unknown action']);
