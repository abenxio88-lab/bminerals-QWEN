<?php
/**
 * Balochistan Minerals - Meta Universal Webhook Receiver
 * Handles verification handshakes and incoming messages for Facebook Messenger, Instagram Direct, and Meta WhatsApp.
 */

header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../admin/db.php';
require_once __DIR__ . '/../admin/chat-helpers.php';

$pdo = getDB();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
    exit;
}

ensureLiveChatSchema($pdo);
$metaSettings = getAllMetaSettings($pdo);

// =========================================================================
// 1. GET REQUEST: META HUB VERIFICATION HANDSHAKE & HEALTH CHECK
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if Meta Hub challenge parameters exist (query keys can use . or _)
    $hubMode        = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
    $hubVerifyToken = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $hubChallenge   = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

    $configuredToken = $metaSettings['meta_verify_token'] ?? 'bm_meta_token';

    if ($hubMode === 'subscribe' && !empty($hubChallenge)) {
        if ($hubVerifyToken === $configuredToken) {
            // Meta expects raw plain text challenge echo with HTTP 200
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(200);
            echo $hubChallenge;
            exit;
        } else {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Verification token mismatch';
            exit;
        }
    }

    // Default status response for browser / diagnostic access
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'         => 'active',
        'service'        => 'Balochistan Minerals Meta Webhook Receiver',
        'version'        => '2.0.0',
        'channels'       => ['facebook_messenger', 'instagram_direct', 'whatsapp'],
        'verification'   => [
            'mode'       => 'subscribe',
            'token_set'  => !empty($configuredToken),
            'token_hint' => substr($configuredToken, 0, 3) . '***' . substr($configuredToken, -3),
        ],
        'webhook_url'    => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/api/meta-webhook.php',
    ]);
    exit;
}

// =========================================================================
// 2. POST REQUEST: INCOMING MESSAGE PAYLOAD & SIMULATION HANDLER
// =========================================================================
header('Content-Type: application/json; charset=utf-8');

$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload, true) ?: [];

// Action simulation handler (for testing from Admin Panel Meta Hub)
$action = $_GET['action'] ?? $payload['action'] ?? '';
if ($action === 'simulate') {
    $channel    = in_array($payload['channel'] ?? '', ['facebook', 'instagram', 'whatsapp'], true) ? $payload['channel'] : 'facebook';
    $senderId   = trim((string)($payload['sender_id'] ?? 'sim_' . time()));
    $senderName = trim((string)($payload['sender_name'] ?? ($channel === 'instagram' ? 'insta_buyer_pk' : 'Global Minerals Trading')));
    $message    = trim((string)($payload['message'] ?? 'Inquiring about 5,000 MT Barite 4.2+ SG export to Qingdao port. Please provide quotation.'));
    $country    = trim((string)($payload['country'] ?? 'International'));
    $phone      = trim((string)($payload['phone'] ?? ''));

    // Check or create session
    $sStmt = $pdo->prepare("SELECT * FROM `chat_sessions` WHERE channel = ? AND channel_id = ? LIMIT 1");
    $sStmt->execute([$channel, $senderId]);
    $session = $sStmt->fetch(PDO::FETCH_ASSOC);

    $metaInfo = [
        'channel'          => $channel,
        'channel_id'       => $senderId,
        'visitor_name'     => $senderName,
        'country'          => $country,
        'status'           => 'waiting_human',
        'page'             => 'Meta ' . ucfirst($channel) . ' Direct',
        'channel_metadata' => ['simulated' => true, 'sender_id' => $senderId],
    ];
    if (!empty($phone)) {
        $metaInfo['visitor_phone'] = $phone;
        $geo = inferGeoFromPhoneNumber($phone);
        if ($geo['country'] !== 'International') {
            $metaInfo['country'] = $geo['country'];
            $metaInfo['country_code'] = $geo['code'];
            $metaInfo['timezone'] = $geo['tz'];
        }
    }

    if (!$session) {
        $token = 'meta_' . $channel . '_' . bin2hex(random_bytes(10));
        $session = getOrCreateChatSession($pdo, $token, $metaInfo);
    } else {
        // Reopen if closed
        if ($session['status'] === 'closed') {
            $pdo->prepare("UPDATE `chat_sessions` SET `status` = 'waiting_human', `updated_at` = NOW() WHERE `id` = ?")->execute([$session['id']]);
        }
    }

    // Auto-extract lead intelligence (mineral, email, tonnage, port)
    $intel = extractLeadIntelligenceFromText($message);
    if (!empty($intel)) {
        $upd = [];
        $params = [];
        if (!empty($intel['mineral']) && empty($session['mineral_interest'])) {
            $upd[] = "`mineral_interest` = ?";
            $params[] = $intel['mineral'];
        }
        if (!empty($intel['email']) && empty($session['visitor_email'])) {
            $upd[] = "`visitor_email` = ?";
            $params[] = $intel['email'];
        }
        if (!empty($intel['phone']) && empty($session['visitor_phone'])) {
            $upd[] = "`visitor_phone` = ?";
            $params[] = $intel['phone'];
        }
        if (!empty($upd)) {
            $params[] = $session['id'];
            $pdo->prepare("UPDATE `chat_sessions` SET " . implode(", ", $upd) . " WHERE `id` = ?")->execute($params);
        }
    }

    $msgId = logChatMessage($pdo, (int)$session['id'], 'visitor', $senderName, $message, $channel);

    echo json_encode([
        'success'    => true,
        'simulated'  => true,
        'channel'    => $channel,
        'session_id' => (int)$session['id'],
        'msg_id'     => $msgId,
        'buyer_name' => $senderName,
    ]);
    exit;
}

// Check for valid Meta webhook payload
if (empty($payload['entry']) || !is_array($payload['entry'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or empty Meta webhook payload']);
    exit;
}

$processedEvents = 0;
$objectType = $payload['object'] ?? 'page'; // 'page', 'instagram', or 'whatsapp_business_account'

foreach ($payload['entry'] as $entry) {
    // -------------------------------------------------------------
    // A. Facebook Messenger & Instagram Direct Messages
    // -------------------------------------------------------------
    if (!empty($entry['messaging']) && is_array($entry['messaging'])) {
        foreach ($entry['messaging'] as $event) {
            $senderId    = $event['sender']['id'] ?? '';
            $recipientId = $event['recipient']['id'] ?? '';

            // Skip echoes or outbound messages sent by page
            if (!empty($event['message']['is_echo'])) {
                continue;
            }
            if ($senderId === ($metaSettings['fb_page_id'] ?? '') || $senderId === ($metaSettings['ig_account_id'] ?? '')) {
                continue;
            }

            $messageText = $event['message']['text'] ?? '';
            $mid         = $event['message']['mid'] ?? null;

            // If message text is empty (e.g. buyer sent an image or sticker)
            if (empty($messageText) && !empty($event['message']['attachments'])) {
                $firstAtt = $event['message']['attachments'][0]['type'] ?? 'attachment';
                $messageText = "[Buyer sent a {$firstAtt}]";
            }

            if (empty($senderId) || empty($messageText)) {
                continue;
            }

            $channel = ($objectType === 'instagram') ? 'instagram' : 'facebook';

            // Check if session exists
            $sStmt = $pdo->prepare("SELECT * FROM `chat_sessions` WHERE channel = ? AND channel_id = ? LIMIT 1");
            $sStmt->execute([$channel, $senderId]);
            $session = $sStmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                $buyerPrefix = ($channel === 'instagram') ? 'Instagram User' : 'Facebook User';
                $token = 'meta_' . $channel . '_' . bin2hex(random_bytes(10));
                $session = getOrCreateChatSession($pdo, $token, [
                    'channel'          => $channel,
                    'channel_id'       => $senderId,
                    'visitor_name'     => "{$buyerPrefix} #" . substr($senderId, -4),
                    'country'          => 'International',
                    'status'           => 'waiting_human',
                    'page'             => ucfirst($channel) . ' Messenger',
                    'channel_metadata' => ['sender_id' => $senderId, 'recipient_id' => $recipientId],
                ]);
            } else {
                if ($session['status'] === 'closed') {
                    $pdo->prepare("UPDATE `chat_sessions` SET `status` = 'waiting_human', `updated_at` = NOW() WHERE `id` = ?")
                        ->execute([$session['id']]);
                }
            }

            // Intelligence extraction
            $intel = extractLeadIntelligenceFromText($messageText);
            if (!empty($intel)) {
                $upd = [];
                $params = [];
                if (!empty($intel['mineral']) && empty($session['mineral_interest'])) {
                    $upd[] = "`mineral_interest` = ?";
                    $params[] = $intel['mineral'];
                }
                if (!empty($intel['email']) && empty($session['visitor_email'])) {
                    $upd[] = "`visitor_email` = ?";
                    $params[] = $intel['email'];
                }
                if (!empty($intel['phone']) && empty($session['visitor_phone'])) {
                    $upd[] = "`visitor_phone` = ?";
                    $params[] = $intel['phone'];
                }
                if (!empty($upd)) {
                    $params[] = $session['id'];
                    $pdo->prepare("UPDATE `chat_sessions` SET " . implode(", ", $upd) . " WHERE `id` = ?")->execute($params);
                }
            }

            logChatMessage($pdo, (int)$session['id'], 'visitor', $session['visitor_name'], $messageText, $channel, $mid);
            $processedEvents++;
        }
    }

    // -------------------------------------------------------------
    // B. WhatsApp Cloud API / Webhook format (if routed through Meta)
    // -------------------------------------------------------------
    if (!empty($entry['changes']) && is_array($entry['changes'])) {
        foreach ($entry['changes'] as $change) {
            $value = $change['value'] ?? [];
            if (!empty($value['messages']) && is_array($value['messages'])) {
                foreach ($value['messages'] as $waMsg) {
                    $fromNumber = $waMsg['from'] ?? '';
                    $waMid      = $waMsg['id'] ?? null;
                    $text       = $waMsg['text']['body'] ?? '';

                    if (empty($fromNumber) || empty($text)) continue;

                    $formattedPhone = '+' . ltrim($fromNumber, '+');
                    $geo = inferGeoFromPhoneNumber($formattedPhone);

                    $sStmt = $pdo->prepare("SELECT * FROM `chat_sessions` WHERE channel = 'whatsapp' AND channel_id = ? LIMIT 1");
                    $sStmt->execute([$formattedPhone]);
                    $session = $sStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$session) {
                        $contactName = $value['contacts'][0]['profile']['name'] ?? ('WhatsApp Buyer ' . substr($formattedPhone, -4));
                        $token = 'meta_wa_' . bin2hex(random_bytes(10));
                        $session = getOrCreateChatSession($pdo, $token, [
                            'channel'          => 'whatsapp',
                            'channel_id'       => $formattedPhone,
                            'visitor_name'     => $contactName,
                            'visitor_phone'    => $formattedPhone,
                            'country'          => $geo['country'],
                            'country_code'     => $geo['code'],
                            'city'             => $geo['city'] ?? '',
                            'timezone'         => $geo['tz'],
                            'status'           => 'waiting_human',
                            'page'             => 'WhatsApp Direct',
                        ]);
                    }

                    logChatMessage($pdo, (int)$session['id'], 'visitor', $session['visitor_name'], $text, 'whatsapp', $waMid);
                    $processedEvents++;
                }
            }
        }
    }
}

// Meta expects HTTP 200 with 'EVENT_RECEIVED' or JSON
http_response_code(200);
echo json_encode([
    'success'          => true,
    'message'          => 'EVENT_RECEIVED',
    'processed_events' => $processedEvents,
]);
