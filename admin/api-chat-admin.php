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

    $channel = in_array($s['channel'] ?? 'web', ['web', 'whatsapp', 'instagram', 'facebook'], true) ? $s['channel'] : 'web';
    $channelId = (string)($s['channel_id'] ?? '');

    $svgWa = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" style="vertical-align:-1.5px;flex-shrink:0;"><path fill="#25D366" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2z"/><path fill="#FFFFFF" d="M17.5 14.33c-.28-.14-1.65-.81-1.9-.91-.26-.09-.45-.14-.64.14-.19.28-.73.91-.9 1.1-.16.19-.33.21-.61.07-.28-.14-1.18-.44-2.25-1.39-.83-.74-1.4-1.66-1.56-1.94-.16-.28-.02-.43.12-.57.13-.13.28-.33.42-.5.14-.16.19-.28.28-.47.09-.19.05-.35-.02-.5-.07-.14-.64-1.54-.87-2.11-.23-.56-.47-.48-.64-.49-.17-.01-.36-.01-.56-.01-.19 0-.5.07-.76.35-.26.28-1 1-1 2.43s1.03 2.82 1.17 3.01c.14.19 2.02 3.1 4.9 4.35 2.87 1.25 2.87.83 3.39.79.52-.05 1.65-.67 1.88-1.33.23-.65.23-1.22.16-1.33-.07-.12-.26-.19-.54-.33z"/></svg>';

    $svgIg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" style="vertical-align:-1.5px;flex-shrink:0;"><defs><linearGradient id="igBadgeGrad" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" stop-color="#FFD600"/><stop offset="25%" stop-color="#FF0069"/><stop offset="50%" stop-color="#D300C5"/><stop offset="75%" stop-color="#7638FA"/><stop offset="100%" stop-color="#00B2FE"/></linearGradient></defs><rect width="24" height="24" rx="6" fill="url(#igBadgeGrad)"/><rect x="5.5" y="5.5" width="13" height="13" rx="3.5" stroke="#FFFFFF" stroke-width="1.8"/><circle cx="12" cy="12" r="3.2" stroke="#FFFFFF" stroke-width="1.8"/><circle cx="15.8" cy="8.2" r="0.9" fill="#FFFFFF"/></svg>';

    $svgFb = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" style="vertical-align:-1.5px;flex-shrink:0;"><path fill="#0084FF" d="M12 2C6.48 2 2 6.15 2 11.26c0 2.91 1.45 5.52 3.73 7.18v3.56l3.42-1.88c.9.25 1.86.39 2.85.39 5.52 0 10-4.15 10-9.25C22 6.15 17.52 2 12 2z"/><path fill="#FFFFFF" d="m6.8 13.7 3.3-3.5 2.5 2.5 4.6-4.5-3.3 3.5-2.5-2.5z"/></svg>';

    $svgWeb = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1.5px;flex-shrink:0;"><path d="M6 3h12l4 6-10 12L2 9z"></path><path d="M11 3v6l-4 3"></path><path d="M13 3v6l4 3"></path></svg>';

    $channelBadges = [
        'whatsapp' => [
            'name'  => 'WhatsApp',
            'icon'  => 'bi-whatsapp',
            'color' => '#25D366',
            'html'  => '<span class="badge-channel-item badge-channel-whatsapp" title="WhatsApp Official Direct">' . $svgWa . ' <span>WhatsApp</span></span>',
        ],
        'instagram' => [
            'name'  => 'Instagram',
            'icon'  => 'bi-instagram',
            'color' => '#E1306C',
            'html'  => '<span class="badge-channel-item badge-channel-instagram" title="Instagram Direct">' . $svgIg . ' <span>Instagram</span></span>',
        ],
        'facebook' => [
            'name'  => 'Facebook',
            'icon'  => 'bi-messenger',
            'color' => '#1877F2',
            'html'  => '<span class="badge-channel-item badge-channel-facebook" title="Facebook Messenger">' . $svgFb . ' <span>Facebook</span></span>',
        ],
        'web' => [
            'name'  => 'Chatbot Buddy',
            'icon'  => 'bi-gem',
            'color' => '#D97706',
            'html'  => '<span class="badge-channel-item badge-channel-web" title="Website AI Chatbot">' . $svgWeb . ' <span>Web Desk</span></span>',
        ],
    ];

    $channelInfo = $channelBadges[$channel] ?? $channelBadges['web'];

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
        'channel'           => $channel,
        'channel_id'        => $channelId,
        'channel_name'      => $channelInfo['name'],
        'channel_icon'      => $channelInfo['icon'],
        'channel_color'     => $channelInfo['color'],
        'channel_badge_html'=> $channelInfo['html'],
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
    $channelFilter = cleanInput($_GET['channel'] ?? 'all');

    $whereClauses = [];
    $params = [];

    if ($filter === 'waiting') {
        $whereClauses[] = "status = 'waiting_human'";
    } elseif ($filter === 'my_chats') {
        $whereClauses[] = "assigned_admin_id = ? AND status = 'agent_active'";
        $params[] = $uid;
    } elseif ($filter === 'closed') {
        $whereClauses[] = "status = 'closed'";
    } else {
        // Active desk queue shows all active visitors (conversing with AI bot, waiting for human, or claimed by agent)
        $whereClauses[] = "status IN ('bot', 'waiting_human', 'agent_active')";
    }

    if ($channelFilter !== 'all' && in_array($channelFilter, ['web', 'whatsapp', 'instagram', 'facebook'], true)) {
        if ($channelFilter === 'web') {
            $whereClauses[] = "(channel = 'web' OR channel IS NULL OR channel = '')";
        } else {
            $whereClauses[] = "channel = ?";
            $params[] = $channelFilter;
        }
    }

    $where = implode(' AND ', $whereClauses);
    $stmt = $pdo->prepare("SELECT * FROM `chat_sessions` WHERE {$where} ORDER BY (status = 'waiting_human') DESC, last_message_at DESC LIMIT 50");
    $stmt->execute($params);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $waitingCount = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status = 'waiting_human'")->fetchColumn();
    $activeCount  = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status IN ('bot', 'waiting_human', 'agent_active')")->fetchColumn();
    $myCount      = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status = 'agent_active' AND assigned_admin_id = {$uid}")->fetchColumn();

    // Channel-specific active metrics
    $webCount = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status IN ('bot', 'waiting_human', 'agent_active') AND (channel = 'web' OR channel IS NULL OR channel = '')")->fetchColumn();
    $waCount  = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status IN ('bot', 'waiting_human', 'agent_active') AND channel = 'whatsapp'")->fetchColumn();
    $igCount  = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status IN ('bot', 'waiting_human', 'agent_active') AND channel = 'instagram'")->fetchColumn();
    $fbCount  = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status IN ('bot', 'waiting_human', 'agent_active') AND channel = 'facebook'")->fetchColumn();

    echo json_encode([
        'success'         => true,
        'waiting_count'   => $waitingCount,
        'active_count'    => $activeCount,
        'my_count'        => $myCount,
        'web_count'       => $webCount,
        'whatsapp_count'  => $waCount,
        'instagram_count' => $igCount,
        'facebook_count'  => $fbCount,
        'sessions'        => array_map('enrichSessionForAdmin', $sessions),
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
    $channel = $session['channel'] ?? 'web';
    $msgId = logChatMessage($pdo, $sessionId, 'agent', $publicOfficerTitle, $messageText, $channel);

    // Route outbound reply to external Meta channels (WhatsApp, Facebook Messenger, Instagram Direct)
    $dispatchResult = ['success' => true];
    if ($channel !== 'web') {
        $dispatchResult = dispatchOutboundChannelMessage($pdo, $session, $messageText);
    }

    echo json_encode([
        'success'         => true,
        'msg_id'          => $msgId,
        'channel'         => $channel,
        'dispatch_result' => $dispatchResult,
        'time'            => date('h:i A'),
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

// 8. GET META SETTINGS & INTEGRATION STATUS
if ($action === 'get_meta_settings') {
    $meta = getAllMetaSettings($pdo);
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $proto = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $webhookUrl = "{$proto}://{$host}/api/meta-webhook.php";

    echo json_encode([
        'success'      => true,
        'settings'     => [
            'meta_verify_token'         => $meta['meta_verify_token'] ?? '',
            'meta_app_id'               => $meta['meta_app_id'] ?? '',
            'meta_app_secret'           => !empty($meta['meta_app_secret']) ? '••••••••' : '',
            'fb_page_id'                => $meta['fb_page_id'] ?? '',
            'fb_page_access_token'      => !empty($meta['fb_page_access_token']) ? substr($meta['fb_page_access_token'], 0, 12) . '...' . substr($meta['fb_page_access_token'], -6) : '',
            'ig_account_id'             => $meta['ig_account_id'] ?? '',
            'whatsapp_companion_status' => $meta['whatsapp_companion_status'] ?? 'standby',
            'whatsapp_companion_phone'  => $meta['whatsapp_companion_phone'] ?? '+92 334 8888104',
            'whatsapp_last_sync'        => $meta['whatsapp_last_sync'] ?? '',
        ],
        'webhook_url'  => $webhookUrl,
    ]);
    exit;
}

// 9. SAVE META SETTINGS
if ($action === 'save_meta_settings') {
    $allowed = ['meta_verify_token', 'meta_app_id', 'meta_app_secret', 'fb_page_id', 'fb_page_access_token', 'ig_account_id', 'whatsapp_companion_phone'];
    foreach ($allowed as $k) {
        if (isset($json[$k])) {
            $v = trim((string)$json[$k]);
            // Do not overwrite with masked values
            if (!str_contains($v, '••••')) {
                setMetaSetting($pdo, $k, $v);
            }
        }
    }
    echo json_encode(['success' => true, 'message' => 'Meta settings updated successfully']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
