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

// Parse input (supports JSON, FormData, and GET)
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

$page = cleanInput($json['page'] ?? $json['page_url'] ?? $_POST['page'] ?? $_POST['page_url'] ?? $_GET['page'] ?? $_GET['page_url'] ?? '');
if ($page !== '' && (str_starts_with($page, 'http://') || str_starts_with($page, 'https://'))) {
    $parsedPath = parse_url($page, PHP_URL_PATH);
    if ($parsedPath) $page = $parsedPath;
}

$mineral      = cleanInput($json['mineral'] ?? $json['mineral_interest'] ?? $_POST['mineral'] ?? $_POST['mineral_interest'] ?? $_GET['mineral'] ?? '');
$clientTz     = cleanInput($json['client_tz'] ?? $_POST['client_tz'] ?? $_GET['client_tz'] ?? '');
$clientLocale = cleanInput($json['client_locale'] ?? $_POST['client_locale'] ?? $_GET['client_locale'] ?? '');
$clientOffset = isset($json['client_offset']) ? (int)$json['client_offset'] : (isset($_POST['client_offset']) ? (int)$_POST['client_offset'] : (isset($_GET['client_offset']) ? (int)$_GET['client_offset'] : null));

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

// Multi-tier geolocation resolution
$cfCountry = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null;
$cfCity    = $_SERVER['HTTP_CF_IPCITY'] ?? null;
$geo = resolveVisitorGeo($visitorIp, $cfCountry, $cfCity, $clientTz, $clientLocale, $clientOffset);

$session = getOrCreateChatSession($pdo, $token, [
    'ip'               => $visitorIp,
    'page'             => $page,
    'mineral_interest' => $mineral,
    'country'          => $geo['country'],
    'country_code'     => $geo['country_code'],
    'city'             => $geo['city'],
    'timezone'         => $geo['timezone'],
]);

// 2. REQUEST HUMAN AGENT
if ($action === 'request_human') {
    $onlineCount = countOnlineTradeDeskOfficers($pdo, 5);
    $isOnline = ($onlineCount > 0);

    if ($session['status'] === 'bot' || $session['status'] === 'closed') {
        $newStatus = 'waiting_human';
        $pdo->prepare("UPDATE `chat_sessions` SET status = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$newStatus, $session['id']]);

        $context = cleanInput($json['context'] ?? $_POST['context'] ?? '');
        if ($context !== '') {
            logChatMessage($pdo, (int)$session['id'], 'visitor', $session['visitor_name'] ?: ('Buyer #' . (1000 + (int)$session['id'])), $context);

            // Auto-extract lead intelligence from initial inquiry context
            $intel = extractLeadIntelligenceFromText($context);
            if (!empty($intel)) {
                $sessionUp = [];
                $params = [];
                if (!empty($intel['email']) && empty($session['visitor_email'])) {
                    $sessionUp[] = "visitor_email = ?";
                    $params[] = $intel['email'];
                }
                if (!empty($intel['phone']) && empty($session['visitor_phone'])) {
                    $sessionUp[] = "visitor_phone = ?";
                    $params[] = $intel['phone'];
                }
                if (!empty($intel['mineral']) && (empty($session['mineral_interest']) || $session['mineral_interest'] === 'Export Minerals')) {
                    $sessionUp[] = "mineral_interest = ?";
                    $params[] = $intel['mineral'];
                }
                if (!empty($sessionUp)) {
                    $params[] = $session['id'];
                    $pdo->prepare("UPDATE `chat_sessions` SET " . implode(", ", $sessionUp) . ", updated_at = NOW() WHERE id = ?")->execute($params);
                }
            }
        }

        // Log notification for admin officers
        if ($isOnline) {
            if (function_exists('logAdminNotification')) {
                $notifSummary = $context ? "Inquiry: \"{$context}\"" : "Buyer on {$session['current_page']} ({$session['country']}) requested live assistance.";
                logAdminNotification(
                    $pdo,
                    'urgent',
                    'lead',
                    '🔴 Live Chat Handover Requested',
                    $notifSummary,
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

    $buyerDisplayName = $session['visitor_name'] ?: ('Buyer #' . (1000 + (int)$session['id']));

    // Save visitor message
    $msgId = logChatMessage($pdo, (int)$session['id'], 'visitor', $buyerDisplayName, $messageText);

    // Auto-extract lead intelligence (email, phone, mineral, buyer name)
    $intel = extractLeadIntelligenceFromText($messageText);
    if (!empty($intel)) {
        $sessionUp = [];
        $params = [];
        if (!empty($intel['email']) && empty($session['visitor_email'])) {
            $sessionUp[] = "visitor_email = ?";
            $params[] = $intel['email'];
            $session['visitor_email'] = $intel['email'];
        }
        if (!empty($intel['phone']) && empty($session['visitor_phone'])) {
            $sessionUp[] = "visitor_phone = ?";
            $params[] = $intel['phone'];
            $session['visitor_phone'] = $intel['phone'];
        }
        if (!empty($intel['mineral']) && (empty($session['mineral_interest']) || $session['mineral_interest'] === 'Export Minerals')) {
            $sessionUp[] = "mineral_interest = ?";
            $params[] = $intel['mineral'];
            $session['mineral_interest'] = $intel['mineral'];
        }

        // Smart name promotion if generic Buyer #ID
        $currentName = $session['visitor_name'];
        $isGeneric = (empty($currentName) || $currentName === 'Guest Buyer' || str_starts_with($currentName, 'Buyer #'));
        if ($isGeneric) {
            $promotedName = '';
            if (!empty($intel['name']) && !empty($intel['company'])) {
                $promotedName = "{$intel['name']} ({$intel['company']})";
            } elseif (!empty($intel['name'])) {
                $promotedName = "{$intel['name']} (Buyer #" . (1000 + (int)$session['id']) . ")";
            } elseif (!empty($intel['company'])) {
                $promotedName = "{$intel['company']} (Buyer #" . (1000 + (int)$session['id']) . ")";
            } elseif (!empty($intel['email'])) {
                $emailUser = explode('@', $intel['email'])[0];
                $promotedName = ucwords(str_replace(['.', '_', '-'], ' ', $emailUser)) . " (Buyer #" . (1000 + (int)$session['id']) . ")";
            }

            if ($promotedName !== '') {
                $sessionUp[] = "visitor_name = ?";
                $params[] = $promotedName;
                $session['visitor_name'] = $promotedName;
            }
        }

        if (!empty($sessionUp)) {
            $params[] = $session['id'];
            $pdo->prepare("UPDATE `chat_sessions` SET " . implode(", ", $sessionUp) . ", updated_at = NOW() WHERE id = ?")->execute($params);
        }
    }

    // If in waiting_human or agent_active, notify trade desk officers
    if ($session['status'] === 'waiting_human' || $session['status'] === 'agent_active') {
        if (function_exists('logAdminNotification')) {
            logAdminNotification(
                $pdo,
                'urgent',
                'lead',
                '💬 Live Chat Message from Buyer',
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
    ]);
    exit;
}

// 4. SYNC / POLL (Fetch new messages)
if ($action === 'sync') {
    $lastId = (int)($json['last_id'] ?? $_POST['last_id'] ?? $_GET['last_id'] ?? 0);

    // Refresh session data and update current_page if live navigation passed
    if ($page !== '' && $page !== $session['current_page']) {
        $pdo->prepare("UPDATE `chat_sessions` SET current_page = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$page, $session['id']]);
        $session['current_page'] = $page;
    }

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

    // Clean assigned agent title for public visitor display
    $publicAgentName = 'BM Helper Desk';

    echo json_encode([
        'success'        => true,
        'status'         => $session['status'],
        'assigned_agent' => $publicAgentName,
        'is_online'      => ($onlineCount > 0),
        'online_count'   => $onlineCount,
        'messages'       => array_map(function($m) {
            $sender = $m['sender_name'];
            $text   = $m['message_text'];
            if ($m['sender_type'] === 'agent') {
                $sender = 'BM Helper Desk';
            } elseif ($m['sender_type'] === 'system') {
                if (stripos($text, 'has joined') !== false || stripos($text, 'joined the conversation') !== false) {
                    $text = 'BM Helper Desk has joined the conversation.';
                } elseif (stripos($text, 'closed') !== false) {
                    $text = 'Live session closed and archived by BM Helper Desk.';
                } elseif (stripos($text, 'connecting') !== false) {
                    $text = 'Connecting to BM Helper Desk...';
                } elseif (stripos($text, 'returned to ai') !== false) {
                    $text = 'Switched to AI Assistant.';
                }
            }
            return [
                'id'          => (int)$m['id'],
                'sender_type' => $m['sender_type'],
                'sender_name' => $sender,
                'text'        => $text,
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
            $inqStmt->execute([$name ?: ('Buyer #' . (1000 + (int)$session['id'])), $email, $phone, $mineralInterest, $fullNotes]);
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
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
