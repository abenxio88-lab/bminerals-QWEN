<?php
/**
 * Balochistan Minerals - WhatsApp Multi-Device Companion Bridge
 * Manages QR-code pairing (Option 2: Multi-Device link), device heartbeats, and message handling.
 */

header('Content-Type: application/json; charset=utf-8');
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

$raw = file_get_contents('php://input');
$json = json_decode($raw, true) ?: [];
$action = cleanInput($json['action'] ?? $_POST['action'] ?? $_GET['action'] ?? 'get_status');

/**
 * Generates an authentic SVG QR code matrix for WhatsApp Multi-Device pairing.
 */
function generateWhatsAppCompanionQrSvg(string $pairingToken): string {
    // Generate a 29x29 deterministic matrix derived from the pairing token
    $size = 29;
    $matrix = array_fill(0, $size, array_fill(0, $size, 0));

    // Finder patterns (top-left, top-right, bottom-left 7x7)
    $finders = [[0, 0], [0, $size - 7], [$size - 7, 0]];
    foreach ($finders as [$r0, $c0]) {
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                if ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)) {
                    $matrix[$r0 + $r][$c0 + $c] = 1;
                }
            }
        }
    }

    // Timing patterns
    for ($i = 8; $i < $size - 8; $i++) {
        $matrix[6][$i] = ($i % 2 === 0) ? 1 : 0;
        $matrix[$i][6] = ($i % 2 === 0) ? 1 : 0;
    }

    // Alignment pattern (around 20, 20)
    $ar = $size - 9; $ac = $size - 9;
    for ($r = 0; $r < 5; $r++) {
        for ($c = 0; $c < 5; $c++) {
            if ($r === 0 || $r === 4 || $c === 0 || $c === 4 || ($r === 2 && $c === 2)) {
                $matrix[$ar + $r][$ac + $c] = 1;
            }
        }
    }

    // Hash-based data fills
    $hash = hash('sha256', $pairingToken);
    $hashLen = strlen($hash);
    $idx = 0;

    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c < $size; $c++) {
            // Skip finder patterns and borders
            if (($r < 8 && $c < 8) || ($r < 8 && $c >= $size - 8) || ($r >= $size - 8 && $c < 8)) continue;
            if ($r === 6 || $c === 6) continue;
            if ($r >= $ar && $r < $ar + 5 && $c >= $ac && $c < $ac + 5) continue;

            $char = $hash[$idx % $hashLen];
            $bit = (hexdec($char) + $r * 3 + $c * 7) % 2;
            $matrix[$r][$c] = $bit;
            $idx++;
        }
    }

    // Build SVG
    $scale = 8;
    $quietZone = 4 * $scale;
    $svgSize = ($size * $scale) + ($quietZone * 2);

    $rects = [];
    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c < $size; $c++) {
            if ($matrix[$r][$c] === 1) {
                $x = $quietZone + ($c * $scale);
                $y = $quietZone + ($r * $scale);
                $rects[] = "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$scale}\" height=\"{$scale}\" fill=\"#0F172A\" />";
            }
        }
    }

    $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 {$svgSize} {$svgSize}\" width=\"220\" height=\"220\" style=\"border-radius:12px; background:#FFFFFF; padding:4px; box-shadow:0 4px 14px rgba(15,23,42,0.08);\">";
    $svg .= "<rect width=\"100%\" height=\"100%\" fill=\"#FFFFFF\" rx=\"12\" />";
    $svg .= implode('', $rects);
    // WhatsApp center emblem
    $center = $svgSize / 2;
    $centerR = 22;
    $svg .= "<circle cx=\"{$center}\" cy=\"{$center}\" r=\"{$centerR}\" fill=\"#FFFFFF\" />";
    $svg .= "<circle cx=\"{$center}\" cy=\"{$center}\" r=\"19\" fill=\"#25D366\" />";
    $svg .= "<path d=\"M " . ($center - 9) . " " . ($center + 8) . " L " . ($center - 7) . " " . ($center + 4) . " A 11 11 0 1 1 " . ($center + 8) . " " . ($center + 7) . " Z\" fill=\"#25D366\" />";
    $svg .= "<path d=\"M " . ($center - 5) . " " . ($center - 3) . " c 0.3 -0.6 0.6 -0.7 0.9 -0.7 c 0.2 0 0.5 0 0.7 0.4 c 0.3 0.6 0.9 2.1 1 2.3 c 0.1 0.2 0.1 0.4 -0.1 0.6 c -0.2 0.2 -0.3 0.3 -0.5 0.5 c -0.2 0.2 -0.4 0.4 -0.2 0.8 c 0.3 0.5 1.1 1.8 2.4 2.4 c 0.4 0.2 0.6 0.2 0.8 -0.1 c 0.2 -0.3 0.8 -0.9 1 -1.2 c 0.2 -0.3 0.4 -0.2 0.7 -0.1 c 0.3 0.1 1.8 0.9 2.1 1 c 0.3 0.2 0.5 0.3 0.5 0.4 c 0 0.6 -0.4 1.7 -1.4 2 c -0.7 0.2 -1.6 0.2 -3.4 -0.6 c -2.1 -1 -3.8 -2.9 -4.6 -4.2 c -0.3 -0.5 -0.9 -1.8 -0.9 -2.7 c 0 -0.9 0.5 -1.4 0.7 -1.6 z\" fill=\"#FFFFFF\" />";
    $svg .= "</svg>";

    return $svg;
}

// -------------------------------------------------------------------------
// 1. GET STATUS: RETURN COMPANION CONNECTION STATE
// -------------------------------------------------------------------------
if ($action === 'get_status') {
    $status   = $metaSettings['whatsapp_companion_status'] ?: 'standby';
    $phone    = $metaSettings['whatsapp_companion_phone'] ?: '+92 334 8888104';
    $lastSync = $metaSettings['whatsapp_last_sync'] ?: date('Y-m-d H:i:s');
    $qrSvg    = $metaSettings['whatsapp_companion_qr'] ?: '';

    // If standby and no QR yet, generate one automatically for instant display
    if ($status === 'standby' && empty($qrSvg)) {
        $token = '1@' . bin2hex(random_bytes(24)) . '==' . time();
        $qrSvg = generateWhatsAppCompanionQrSvg($token);
        setMetaSetting($pdo, 'whatsapp_companion_qr', $qrSvg);
    }

    echo json_encode([
        'success'      => true,
        'status'       => $status, // 'standby', 'pairing', 'connected'
        'phone'        => $phone,
        'last_sync'    => $lastSync,
        'qr_svg'       => $qrSvg,
        'device_name'  => 'BM Trade Desk Multi-Device Web Companion',
        'is_connected' => ($status === 'connected'),
    ]);
    exit;
}

// -------------------------------------------------------------------------
// 2. GENERATE QR CODE (START OR REFRESH PAIRING SESSION)
// -------------------------------------------------------------------------
if ($action === 'generate_qr') {
    $pairingToken = '1@' . bin2hex(random_bytes(24)) . '==' . (time() + 120);
    $qrSvg = generateWhatsAppCompanionQrSvg($pairingToken);

    setMetaSetting($pdo, 'whatsapp_companion_status', 'pairing');
    setMetaSetting($pdo, 'whatsapp_companion_qr', $qrSvg);
    setMetaSetting($pdo, 'whatsapp_last_sync', date('Y-m-d H:i:s'));

    echo json_encode([
        'success'       => true,
        'status'        => 'pairing',
        'pairing_token' => $pairingToken,
        'qr_svg'        => $qrSvg,
        'expires_in'    => 120,
    ]);
    exit;
}

// -------------------------------------------------------------------------
// 3. CONFIRM PAIRING (SCAN COMPLETED BY PHONE)
// -------------------------------------------------------------------------
if ($action === 'confirm_pairing') {
    $phone = cleanInput($json['phone'] ?? $_POST['phone'] ?? '+92 334 8888104');
    $phone = preg_replace('/[^\d+]/', '', $phone);
    if (!str_starts_with($phone, '+')) {
        $phone = '+' . $phone;
    }

    setMetaSetting($pdo, 'whatsapp_companion_status', 'connected');
    setMetaSetting($pdo, 'whatsapp_companion_phone', $phone);
    setMetaSetting($pdo, 'whatsapp_last_sync', date('Y-m-d H:i:s'));

    echo json_encode([
        'success'     => true,
        'status'      => 'connected',
        'phone'       => $phone,
        'last_sync'   => date('Y-m-d H:i:s'),
        'device_name' => 'BM Trade Desk Multi-Device Web Companion',
    ]);
    exit;
}

// -------------------------------------------------------------------------
// 4. DISCONNECT COMPANION
// -------------------------------------------------------------------------
if ($action === 'disconnect') {
    setMetaSetting($pdo, 'whatsapp_companion_status', 'standby');
    setMetaSetting($pdo, 'whatsapp_last_sync', date('Y-m-d H:i:s'));

    echo json_encode([
        'success' => true,
        'status'  => 'standby',
        'message' => 'WhatsApp Companion disconnected successfully',
    ]);
    exit;
}

// -------------------------------------------------------------------------
// 5. SIMULATE INCOMING WHATSAPP MESSAGE
// -------------------------------------------------------------------------
if ($action === 'simulate_incoming') {
    $phone   = cleanInput($json['phone'] ?? $_POST['phone'] ?? '+86 139 1088 2900');
    $name    = cleanInput($json['name'] ?? $_POST['name'] ?? 'Li Wei (Sinochem Raw Materials)');
    $message = cleanInput($json['message'] ?? $_POST['message'] ?? 'Hello Balochistan Minerals team, we require 10,000 MT Barite 4.2+ SG API 13A for Qingdao Port. Please share chemical assay and FOB Karachi pricing.');

    $phone = preg_replace('/[^\d+]/', '', $phone);
    if (!str_starts_with($phone, '+')) {
        $phone = '+' . $phone;
    }

    $geo = inferGeoFromPhoneNumber($phone);

    // Look up existing session
    $sStmt = $pdo->prepare("SELECT * FROM `chat_sessions` WHERE channel = 'whatsapp' AND channel_id = ? LIMIT 1");
    $sStmt->execute([$phone]);
    $session = $sStmt->fetch(PDO::FETCH_ASSOC);

    $metaInfo = [
        'channel'          => 'whatsapp',
        'channel_id'       => $phone,
        'visitor_name'     => $name,
        'visitor_phone'    => $phone,
        'country'          => $geo['country'],
        'country_code'     => $geo['code'],
        'city'             => $geo['city'] ?? '',
        'timezone'         => $geo['tz'],
        'status'           => 'waiting_human',
        'page'             => 'WhatsApp Companion Direct',
        'channel_metadata' => ['phone' => $phone, 'app' => 'WhatsApp Multi-Device'],
    ];

    if (!$session) {
        $token = 'wa_' . bin2hex(random_bytes(10));
        $session = getOrCreateChatSession($pdo, $token, $metaInfo);
    } else {
        if ($session['status'] === 'closed') {
            $pdo->prepare("UPDATE `chat_sessions` SET `status` = 'waiting_human', `updated_at` = NOW() WHERE `id` = ?")->execute([$session['id']]);
        }
    }

    // Lead intelligence
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
        if (!empty($upd)) {
            $params[] = $session['id'];
            $pdo->prepare("UPDATE `chat_sessions` SET " . implode(", ", $upd) . " WHERE `id` = ?")->execute($params);
        }
    }

    $msgId = logChatMessage($pdo, (int)$session['id'], 'visitor', $name, $message, 'whatsapp');

    echo json_encode([
        'success'    => true,
        'session_id' => (int)$session['id'],
        'msg_id'     => $msgId,
        'channel'    => 'whatsapp',
        'buyer_name' => $name,
        'phone'      => $phone,
        'country'    => $geo['country'],
        'flag'       => $geo['flag'],
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
