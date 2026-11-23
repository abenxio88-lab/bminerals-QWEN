<?php
/**
 * Balochistan Minerals - Live Chat Client API Engine
 * Manages visitor real-time chat sessions, agent availability, message polling, and human escalation.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../admin/db.php';
require_once __DIR__ . '/../admin/chat-helpers.php';

$pdo = getDB();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
    exit;
}

ensureLiveChatSchema($pdo);

// Parse input
$raw = file_get_contents('php://input');
$json = json_decode($raw, true) ?: [];
$action = cleanInput($json['action'] ?? $_POST['action'] ?? $_GET['action'] ?? 'get_status');
$token  = cleanInput($json['token'] ?? $_POST['token'] ?? $_GET['token'] ?? '');

$visitorIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $visitorIp = $_SERVER['HTTP_CF_CONNECTING_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $visitorIp = trim($parts[0]);
}

$page = cleanInput($json['page'] ?? $_POST['page'] ?? $_GET['page'] ?? '');
$mineral = cleanInput($json['mineral'] ?? $_POST['mineral'] ?? $_GET['mineral'] ?? '');
$country = cleanInput($_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'International');

// 1. GET STATUS: Online status of trade desk
if ($action === 'get_status') {
    $onlineCount = countOnlineTradeDeskOfficers($pdo, 5);
    echo json_encode([
        'success'        => true,
        'is_online'      => ($onlineCount > 0),
        'online_count'   => $onlineCount,
        'business_hours' => '09:00 - 18:00 PKT (Mon - Sat)',
        'contact_phone'  => '+92 334 8888104',
        'whatsapp_url'   => 'https://wa.me/923348888104?text=' . urlencode('Hello Balochistan Minerals, I am inquiring about export mineral supply.'),
    ]);
    exit;
}

// Ensure valid session token for other actions
if (empty($token) || strlen($token) < 16) {
    $token = bin2hex(random_bytes(16));
}

$session = getOrCreateChatSession($pdo, $token, [
    'ip'               => $visitorIp,
    'page'             => $page,
    'mineral_interest' => $mineral,
    'country'          => $country,
]);

// 2. REQUEST HUMAN AGENT
if ($action === 'request_human') {
    $onlineCount = countOnlineTradeDeskOfficers($pdo, 5);
    $isOnline = ($onlineCount > 0);

    if ($session['status'] === 'bot' || $session['status'] === 'closed') {
        $newStatus = $isOnline ? 'waiting_human' : 'waiting_human';
        $pdo->prepare("UPDATE `chat_sessions` SET status = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$newStatus, $session['id']]);

        // Insert system announcement message
        if ($isOnline) {
            $sysMsg = "Connecting you to an Export Desk Specialist. Our team is online and has been alerted.";
            logChatMessage($pdo, (int)$session['id'], 'system', 'Trade Desk System', $sysMsg);

            // Log notification for admin officers
            if (function_exists('logAdminNotification')) {
                logAdminNotification(
                    $pdo,
                    'urgent',
                    'lead',
                    '🔴 Live Chat Handover Requested',
                    "Visitor on {$session['current_page']} ({$session['country']}) requested live agent assistance.",
                    null,
                    'admin/live-chat.php?session=' . (int)$session['id'],
                    5
                );
            }
        } else {
            $sysMsg = "Our Trade Desk officers are currently offline (Operational hours: 09:00 - 18:00 PKT). You can leave your email/specs below, or message us directly on WhatsApp (+92 334 8888104).";
            logChatMessage($pdo, (int)$session['id'], 'system', 'Trade Desk System', $sysMsg);
        }
    }

    echo json_encode([
        'success'        => true,
        'status'         => $session['status'],
        'is_online'      => $isOnline,
        'online_count'   => $onlineCount,
        'assigned_agent' => $session['assigned_admin_name'],
    ]);
    exit;
}

// 3. SEND MESSAGE FROM VISITOR
if ($action === 'send_message') {
    $messageText = cleanInput($json['message'] ?? $_POST['message'] ?? '');
    if ($messageText === '') {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        exit;
    }

    // Save visitor message
    $msgId = logChatMessage($pdo, (int)$session['id'], 'visitor', $session['visitor_name'], $messageText);

    $replyData = null;

    // If currently in BOT mode, also query the AI knowledge engine to generate auto-response
    if ($session['status'] === 'bot') {
        // Query existing knowledge logic
        $botReplyText = '';
        $botChips = ['Talk to Live Specialist', 'Live Spot Prices', 'Stockpiles Ready', 'Request Official Quotation'];

        // Simple fast responder or dispatch to AI
        // Let's call chatbot internal query
        ob_start();
        $_POST['action'] = 'query';
        $_POST['query'] = $messageText;
        $_POST['page'] = $page;
        include __DIR__ . '/chatbot.php';
        $rawBot = ob_get_clean();
        $botJson = json_decode($rawBot, true);

        if (!empty($botJson['text'])) {
            $botReplyText = $botJson['text'];
            if (!empty($botJson['chips'])) {
                $botChips = $botJson['chips'];
            }
        } else {
            $botReplyText = "Thank you for your message. Would you like to connect directly with a live Trade Desk Specialist for current vessel allocations and official pricing?";
        }

        // Log bot answer into conversation
        $botMsgId = logChatMessage($pdo, (int)$session['id'], 'bot', 'Balochistan Minerals AI', $botReplyText);
        $replyData = [
            'id'          => $botMsgId,
            'sender_type' => 'bot',
            'sender_name' => 'Balochistan Minerals AI',
            'text'        => $botReplyText,
            'chips'       => $botChips,
            'time'        => date('h:i A'),
        ];
    } else {
        // If in waiting_human, send alert to admin if first message in wait state
        if (function_exists('logAdminNotification')) {
            logAdminNotification(
                $pdo,
                'urgent',
                'lead',
                '💬 New Message from Visitor in Queue',
                mb_substr($messageText, 0, 100),
                null,
                'admin/live-chat.php?session=' . (int)$session['id'],
                3
            );
        }
    }

    echo json_encode([
        'success'    => true,
        'msg_id'     => $msgId,
        'status'     => $session['status'],
        'assigned'   => $session['assigned_admin_name'],
        'bot_reply'  => $replyData,
    ]);
    exit;
}

// 4. SYNC / POLL (Fetch new messages)
if ($action === 'sync') {
    $lastId = (int)($json['last_id'] ?? $_POST['last_id'] ?? $_GET['last_id'] ?? 0);

    // Refresh session data
    $stmt = $pdo->prepare("SELECT * FROM `chat_sessions` WHERE id = ? LIMIT 1");
    $stmt->execute([$session['id']]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    $msgStmt = $pdo->prepare("SELECT id, sender_type, sender_name, message_text, created_at 
        FROM `chat_messages` 
        WHERE session_id = ? AND id > ? 
        ORDER BY id ASC");
    $msgStmt->execute([$session['id'], $lastId]);
    $newMessages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark agent/system messages as read by visitor
    if (!empty($newMessages)) {
        $pdo->prepare("UPDATE `chat_messages` SET is_read = 1 WHERE session_id = ? AND sender_type IN ('agent', 'system')")
            ->execute([$session['id']]);
        $pdo->prepare("UPDATE `chat_sessions` SET unread_visitor_count = 0 WHERE id = ?")
            ->execute([$session['id']]);
    }

    $onlineCount = countOnlineTradeDeskOfficers($pdo, 5);

    echo json_encode([
        'success'        => true,
        'status'         => $session['status'],
        'assigned_agent' => $session['assigned_admin_name'],
        'is_online'      => ($onlineCount > 0),
        'online_count'   => $onlineCount,
        'messages'       => array_map(function($m) {
            return [
                'id'          => (int)$m['id'],
                'sender_type' => $m['sender_type'],
                'sender_name' => $m['sender_name'],
                'text'        => $m['message_text'],
                'time'        => date('h:i A', strtotime($m['created_at'])),
            ];
        }, $newMessages),
    ]);
    exit;
}

// 5. LEAVE LEAD / CONTACT INFO
if ($action === 'leave_lead') {
    $name  = cleanInput($json['name'] ?? $_POST['name'] ?? '');
    $email = cleanInput($json['email'] ?? $_POST['email'] ?? '');
    $phone = cleanInput($json['phone'] ?? $_POST['phone'] ?? '');
    $notes = cleanInput($json['notes'] ?? $_POST['notes'] ?? '');

    $pdo->prepare("UPDATE `chat_sessions` SET 
        visitor_name = COALESCE(NULLIF(?, ''), visitor_name),
        visitor_email = COALESCE(NULLIF(?, ''), visitor_email),
        visitor_phone = COALESCE(NULLIF(?, ''), visitor_phone),
        updated_at = NOW()
        WHERE id = ?")
        ->execute([$name, $email, $phone, $session['id']]);

    // Insert into inquiries if email or phone provided
    if ($email !== '' || $phone !== '') {
        try {
            $inqStmt = $pdo->prepare("INSERT INTO inquiries 
                (full_name, email, phone, company_name, mineral_type, notes, source, status, created_at)
                VALUES (?, ?, ?, 'Live Chat Lead', ?, ?, 'Live Chat Desk', 'New', NOW())");
            $mineralInterest = $session['mineral_interest'] ?: 'General Inquiry';
            $fullNotes = "Lead captured via Live Chat Handover. Page: {$session['current_page']}. Notes: {$notes}";
            $inqStmt->execute([$name ?: 'Guest Buyer', $email, $phone, $mineralInterest, $fullNotes]);
        } catch (Exception $e) {}
    }

    logChatMessage($pdo, (int)$session['id'], 'system', 'Trade Desk System', "Contact details received ({$email} / {$phone}). An Export Desk Officer has been notified.");

    echo json_encode([
        'success' => true,
        'message' => 'Contact details saved. We will contact you promptly.',
    ]);
    exit;
}

// 6. END / RESET TO BOT
if ($action === 'reset_to_bot') {
    $pdo->prepare("UPDATE `chat_sessions` SET status = 'bot', updated_at = NOW() WHERE id = ?")
        ->execute([$session['id']]);
    logChatMessage($pdo, (int)$session['id'], 'system', 'Trade Desk System', "Session returned to AI Assistant mode.");
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
