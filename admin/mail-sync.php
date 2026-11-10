<?php
/**
 * Balochistan Minerals - Two-Way Hostinger Mailbox Sync Engine
 * 1. Ingests unread incoming inquiries from Hostinger INBOX into Trade Desk.
 * 2. Scans Hostinger INBOX.Sent folder to automatically detect replies sent from mail.hostinger.com
 *    and transitions the corresponding inquiry status from "New" to "Contacted".
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/flag-helper.php';
require_once __DIR__ . '/auth.php';

// Must be logged in to trigger sync
requireAuth();

$pdo = getDB();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit;
}

$action = cleanInput($_GET['action'] ?? 'sync');

// ==========================================
// 1. SIMULATION / TEST LEAD GENERATOR
// Allows instant local testing without live IMAP connection
// ==========================================
if ($action === 'simulate') {
    $testSenders = [
        ['name' => 'Demo Trader', 'company' => 'Sample Trade Corp', 'email' => 'trader@example.com', 'country' => 'Germany', 'subject' => 'Inquiry for high-grade chromite lumps 46-48%', 'mineral' => 'Chromite Ore', 'body' => "Dear Balochistan Minerals Export Desk,\n\nWe are looking to secure 3,000 MT of refractory/metallurgical chromite lumps (46-48% Cr2O3 basis) for shipment to Hamburg/Rotterdam in Q2. Please advise current indicative FOB Karachi rates and latest SGS assay reports.\n\nBest regards,\nDemo Trader\nHead of Procurement"],
        ['name' => 'Fahad Al-Mansouri', 'company' => 'Emirates Industrial Trading', 'email' => 'f.mansouri@emiratesind.ae', 'country' => 'UAE', 'subject' => 'Urgent: API 13A Barite supply for Jebel Ali', 'mineral' => 'Barite', 'body' => "Salam,\n\nWe require prompt delivery of 1,500 MT API grade barite powder (4.20 SG minimum) packaged in 1.5 MT big bags. Can you confirm port stock availability at KPT?\n\nThanks,\nFahad Al-Mansouri"],
        ['name' => 'Kenji Tanaka', 'company' => 'Nippon Rare Earths & Minerals', 'email' => 'k.tanaka@nippon-rem.jp', 'country' => 'Japan', 'subject' => 'Antimony Stibnite 60%+ procurement from Balochistan', 'mineral' => 'Antimony', 'body' => "Dear Commercial Team,\n\nOur smelting operations in Chiba require steady lots of high-purity Stibnite lumps (min 60% Sb). Please send export specifications, MOQ, and payment terms against SBP regulations.\n\nSincerely,\nKenji Tanaka"]
    ];

    $picked = $testSenders[array_rand($testSenders)];
    $targetMailboxes = ['sales@balochistanminerals.com', 'info@balochistanminerals.com'];
    $assignedBox = $targetMailboxes[array_rand($targetMailboxes)];
    $simMsgId = '<sim-' . uniqid() . '@' . parse_url($picked['email'], PHP_URL_HOST) . '>';

    try {
        $stmt = $pdo->prepare("INSERT INTO inquiries 
            (buyer_name, company_name, country, email, whatsapp, inquiry_type, source, email_message_id, mineral_type, message, status) 
            VALUES (?, ?, ?, ?, ?, 'Email Inbound', ?, ?, ?, ?, 'New')");
        
        $fullBody = "Subject: " . $picked['subject'] . "\n\n" . $picked['body'];
        $stmt->execute([
            $picked['name'],
            $picked['company'],
            $picked['country'],
            $picked['email'],
            '+' . rand(10000000000, 99999999999),
            $assignedBox,
            $simMsgId,
            $picked['mineral'],
            $fullBody
        ]);
        $newLeadId = (int)$pdo->lastInsertId();

        $emStmt = $pdo->prepare("INSERT INTO email_messages
            (inquiry_id, direction, from_email, to_email, subject, body_text, status, message_id)
            VALUES (?, 'inbound', ?, ?, ?, ?, 'Received', ?)");
        $emStmt->execute([$newLeadId, $picked['email'], $assignedBox, $picked['subject'], $picked['body'], $simMsgId]);

        echo json_encode([
            'success' => true,
            'synced_count' => 1,
            'message' => 'Inbound email from ' . $picked['name'] . ' landed into [' . $assignedBox . '] and ingested into Trade Desk.'
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ==========================================
// 1.1 SIMULATE HOSTINGER WEBMAIL SENT SYNC
// Tests flipping "New" inquiries to "Contacted" when team replies via webmail
// ==========================================
if ($action === 'simulate_sent_sync') {
    try {
        // Find most recent "New" inquiry
        $stmt = $pdo->query("SELECT id, buyer_name, email, mineral_type FROM inquiries WHERE status = 'New' ORDER BY id DESC LIMIT 1");
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            echo json_encode([
                'success' => true,
                'synced_count' => 0,
                'message' => 'All inquiries have already been contacted. No pending "New" inquiries found to sync.'
            ]);
            exit;
        }

        // Simulate reply from team member on Hostinger Webmail
        $simSubject = "Re: Commercial Export Quotation for " . $target['mineral_type'];
        $simBody = "Dear " . $target['buyer_name'] . ",\n\nThank you for reaching out to Balochistan Minerals. We have verified stock available at our Karachi Port Trust yard.\n\nPlease find our preliminary technical assay sheet attached.\n\nBest regards,\nCommercial Trading Desk\nBalochistan Minerals (Pvt) Ltd";
        $simMsgId = '<webmail-' . uniqid() . '@balochistanminerals.com>';

        // 1. Insert into email_messages
        $logStmt = $pdo->prepare("INSERT INTO email_messages 
            (inquiry_id, direction, from_email, to_email, subject, body_text, status, message_id) 
            VALUES (?, 'outbound', 'sales@balochistanminerals.com', ?, ?, ?, 'Sent', ?)");
        $logStmt->execute([$target['id'], $target['email'], $simSubject, $simBody, $simMsgId]);

        // 2. Transition inquiry status from "New" to "Contacted"
        $note = "[" . date('M d, Y H:i') . "] Replied via Hostinger Webmail (mail.hostinger.com) to " . $target['email'] . "\nSubject: " . $simSubject . "\n-------------------\n";
        $upStmt = $pdo->prepare("UPDATE inquiries SET status = 'Contacted', internal_notes = CONCAT(?, COALESCE(internal_notes, '')) WHERE id = ?");
        $upStmt->execute([$note, $target['id']]);

        echo json_encode([
            'success' => true,
            'synced_count' => 1,
            'inquiry_id' => $target['id'],
            'buyer' => $target['buyer_name'],
            'message' => "Hostinger Sent Mail Sync: Detected outgoing reply to " . $target['buyer_name'] . " (" . $target['email'] . "). Inquiry #" . $target['id'] . " automatically transitioned to [Contacted]!"
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ==========================================
// 2. LIVE TWO-WAY HOSTINGER IMAP SYNC ENGINE
// Connects to imap.hostinger.com
// ==========================================

// Check if PHP IMAP extension is installed
if (!function_exists('imap_open')) {
    echo json_encode([
        'success' => false,
        'warning' => 'PHP IMAP extension is not enabled in local PHP. In production on Hostinger, standard IMAP extension is enabled.',
        'synced_count' => 0,
        'info' => 'Use "Simulate Inbound Test Email" or "Simulate Hostinger Sent Sync" to verify workflow locally.'
    ]);
    exit;
}

/**
 * Decodes MIME-encoded email headers safely to UTF-8.
 */
function decodeMimeHeaderText($value) {
    $raw = (string)$value;
    if ($raw === '') return '';
    if (function_exists('iconv_mime_decode')) {
        $decoded = @iconv_mime_decode($raw, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        if ($decoded !== false) return trim($decoded);
    }
    if (function_exists('mb_decode_mimeheader')) {
        return trim((string)@mb_decode_mimeheader($raw));
    }
    return trim((string)imap_utf8($raw));
}

/**
 * Decodes an IMAP part payload according to transfer encoding and converts charset to UTF-8.
 */
function decodeImapPartPayload($raw, $encoding, $params = []) {
    $text = (string)$raw;
    if ((int)$encoding === 3) {
        $decoded = base64_decode($text, true);
        if ($decoded !== false) $text = $decoded;
    } elseif ((int)$encoding === 4) {
        $text = quoted_printable_decode($text);
    }

    $charset = 'UTF-8';
    foreach ($params as $p) {
        if (isset($p->attribute) && strtolower($p->attribute) === 'charset' && !empty($p->value)) {
            $charset = strtoupper(trim($p->value));
            break;
        }
    }
    if ($charset !== '' && $charset !== 'UTF-8' && $charset !== 'UTF8' && function_exists('mb_convert_encoding')) {
        $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
        if ($converted !== false) $text = $converted;
    }
    return $text;
}

/**
 * Recursively extracts plain text (preferred) or stripped HTML body from an IMAP message.
 */
function extractPlainTextEmailBody($stream, $msgNo) {
    $structure = @imap_fetchstructure($stream, $msgNo);
    if (!$structure) {
        return trim(strip_tags((string)@imap_body($stream, $msgNo, FT_PEEK)));
    }

    $plainParts = [];
    $htmlParts  = [];

    $walk = function ($part, $partNum) use (&$walk, $stream, $msgNo, &$plainParts, &$htmlParts) {
        $subParts = isset($part->parts) && is_array($part->parts) ? $part->parts : [];
        if (!empty($subParts)) {
            foreach ($subParts as $idx => $sub) {
                $prefix = $partNum === '' ? (string)($idx + 1) : ($partNum . '.' . ($idx + 1));
                $walk($sub, $prefix);
            }
            return;
        }

        // Skip explicit attachments
        $disposition = isset($part->disposition) ? strtolower((string)$part->disposition) : '';
        if ($disposition === 'attachment') {
            return;
        }

        $section = $partNum === '' ? '1' : $partNum;
        $raw = @imap_fetchbody($stream, $msgNo, $section, FT_PEEK);
        if ($raw === false || $raw === '') return;

        $params = [];
        if (!empty($part->parameters)) $params = array_merge($params, $part->parameters);
        if (!empty($part->dparameters)) $params = array_merge($params, $part->dparameters);

        $decoded = decodeImapPartPayload($raw, $part->encoding ?? 0, $params);
        $subtype = strtoupper((string)($part->subtype ?? 'PLAIN'));

        if ((int)($part->type ?? 0) === 0) {
            if ($subtype === 'PLAIN') {
                $plainParts[] = $decoded;
            } elseif ($subtype === 'HTML') {
                $htmlParts[] = $decoded;
            }
        }
    };

    $walk($structure, '');

    if (!empty($plainParts)) {
        return trim(implode("\n\n", $plainParts));
    }
    if (!empty($htmlParts)) {
        $html = implode("\n\n", $htmlParts);
        $html = preg_replace('/<(br|p|div|li|tr)\b[^>]*>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    return trim(strip_tags((string)@imap_body($stream, $msgNo, FT_PEEK)));
}

// Hostinger Mail Settings (supports both sales@ and info@ mailboxes)
$mailHost = defined('MAIL_IMAP_HOST') ? MAIL_IMAP_HOST : 'imap.hostinger.com';
$mailPort = defined('MAIL_IMAP_PORT') ? MAIL_IMAP_PORT : 993;

$allMailboxes = function_exists('getConfiguredMailboxes') ? getConfiguredMailboxes() : [
    'sales' => [
        'user' => defined('MAIL_IMAP_USER') ? MAIL_IMAP_USER : 'sales@balochistanminerals.com',
        'pass' => defined('MAIL_IMAP_PASS') ? MAIL_IMAP_PASS : '',
    ],
];
$activeBoxes = array_values(array_filter($allMailboxes, function ($box) {
    return !empty($box['user']) && !empty($box['pass']);
}));

if (empty($activeBoxes)) {
    echo json_encode([
        'success' => true,
        'synced_count' => 0,
        'synced_inbound' => 0,
        'synced_sent' => 0,
        'message' => 'Hostinger email password not yet saved. Configure MAIL_IMAP_PASS / INFO_MAIL_PASS secrets or Mail Settings.',
        'info' => 'Simulated mode available.'
    ]);
    exit;
}

$syncedInbound = 0;
$syncedSent = 0;
$syncedBoxLabels = [];
$warnings = [];
$ownAddresses = array_map(function ($b) {
    return strtolower(trim($b['user']));
}, $allMailboxes);

$mineralKeywords = [
    'chromite'  => 'Chromite Ore',
    'barite'    => 'Barite',
    'iron'      => 'Iron Ore',
    'copper'    => 'Copper Ore',
    'antimony'  => 'Antimony',
    'stibnite'  => 'Antimony',
    'fluor'     => 'Fluorspar',
    'magnesite' => 'Magnesite',
    'bauxite'   => 'Bauxite Ore',
    'manganese' => 'Manganese Ore',
    'lead'      => 'Lead & Zinc Ore',
    'zinc'      => 'Lead & Zinc Ore',
    'onyx'      => 'Onyx Stone',
    'granite'   => 'Granite Stone',
    'marble'    => 'Marble (Balochistan & Iranian)',
    'coal'      => 'Balochistan Coal',
    'gypsum'    => 'Gypsum Ore'
];

$sinceDate = date("j-M-Y", strtotime("-14 days"));

foreach ($activeBoxes as $box) {
    $mailUser = $box['user'];
    $mailPass = $box['pass'];
    $syncedBoxLabels[] = $mailUser;

    // ------------------------------------------
    // PASS 1: SCAN INBOX (Recent 14 days, deduped by Message-ID)
    // Ensures emails already opened on phone/Webmail still land cleanly in Admin
    // ------------------------------------------
    $inboxPath = "{" . $mailHost . ":" . $mailPort . "/imap/ssl/novalidate-cert}INBOX";
    $inboxStream = @imap_open($inboxPath, $mailUser, $mailPass);

    if (!$inboxStream) {
        $lastErr = function_exists('imap_last_error') ? imap_last_error() : 'Connection failed';
        $warnings[] = $mailUser . ': ' . ($lastErr ?: 'Unable to connect to IMAP INBOX');
    } else {
        $recentEmails = @imap_search($inboxStream, 'SINCE "' . $sinceDate . '"');
        if ($recentEmails) {
            rsort($recentEmails);
            foreach ($recentEmails as $emailNumber) {
                $overview = @imap_fetch_overview($inboxStream, $emailNumber, 0);
                $header   = @imap_headerinfo($inboxStream, $emailNumber);
                if (empty($header->from[0]->mailbox) || empty($header->from[0]->host)) {
                    continue;
                }

                $fromEmail = strtolower(trim($header->from[0]->mailbox . '@' . $header->from[0]->host));
                if (in_array($fromEmail, $ownAddresses, true) || preg_match('/^(mailer-daemon|postmaster)@/i', $fromEmail)) {
                    continue;
                }

                $fromName  = !empty($header->from[0]->personal) ? decodeMimeHeaderText($header->from[0]->personal) : $header->from[0]->mailbox;
                $subject   = !empty($overview[0]->subject) ? decodeMimeHeaderText($overview[0]->subject) : 'Inbound Inquiry';
                $msgId     = !empty($header->message_id) ? trim($header->message_id) : ('<msg-' . md5($mailUser . '|' . $fromEmail . '|' . $subject . '|' . ($overview[0]->date ?? $emailNumber)) . '>');

                // Strict deduplication across both inquiries and email_messages
                $chkInq = $pdo->prepare("SELECT id FROM inquiries WHERE email_message_id = ?");
                $chkInq->execute([$msgId]);
                $chkMsg = $pdo->prepare("SELECT id FROM email_messages WHERE message_id = ?");
                $chkMsg->execute([$msgId]);
                if ($chkInq->fetch() || $chkMsg->fetch()) {
                    continue;
                }

                $cleanBody = extractPlainTextEmailBody($inboxStream, $emailNumber);
                if ($cleanBody === '') {
                    $cleanBody = '(No plain-text message body)';
                }

                // Detect mineral
                $detectedMineral = 'General Mineral';
                $fullTextLower = strtolower($subject . ' ' . $cleanBody);
                foreach ($mineralKeywords as $kw => $minName) {
                    if (strpos($fullTextLower, $kw) !== false) {
                        $detectedMineral = $minName;
                        break;
                    }
                }

                $fullMessage = "Subject: " . $subject . "\n\n" . $cleanBody;
                $detectedCountry = detectCountryName('', $fromEmail, '', $fromName, $fullMessage);
                $receivedAt = !empty($header->date) ? date('Y-m-d H:i:s', strtotime($header->date)) : date('Y-m-d H:i:s');

                // Thread into existing buyer inquiry if email already exists, else create new inquiry
                $existStmt = $pdo->prepare("SELECT id FROM inquiries WHERE LOWER(email) = ? ORDER BY id DESC LIMIT 1");
                $existStmt->execute([$fromEmail]);
                $existingLead = $existStmt->fetch(PDO::FETCH_ASSOC);

                if ($existingLead) {
                    $inquiryId = (int)$existingLead['id'];
                    $note = "[" . date('M d, Y H:i', strtotime($receivedAt)) . "] Inbound reply received in " . $mailUser . "\nSubject: " . $subject . "\n-------------------\n";
                    $upLead = $pdo->prepare("UPDATE inquiries SET internal_notes = CONCAT(?, COALESCE(internal_notes, '')) WHERE id = ?");
                    $upLead->execute([$note, $inquiryId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO inquiries 
                        (buyer_name, email, country, inquiry_type, source, email_message_id, mineral_type, message, status, created_at) 
                        VALUES (?, ?, ?, 'Email Inbound', ?, ?, ?, ?, 'New', ?)");
                    $stmt->execute([$fromName, $fromEmail, $detectedCountry, $mailUser, $msgId, $detectedMineral, $fullMessage, $receivedAt]);
                    $inquiryId = (int)$pdo->lastInsertId();
                }

                // Always archive into email_messages so Email Desk & 360° Dossier show both sales@ and info@ threads
                $emStmt = $pdo->prepare("INSERT INTO email_messages
                    (inquiry_id, direction, from_email, to_email, subject, body_text, status, message_id, sent_at)
                    VALUES (?, 'inbound', ?, ?, ?, ?, 'Received', ?, ?)");
                $emStmt->execute([$inquiryId, $fromEmail, $mailUser, substr($subject, 0, 250), $cleanBody, $msgId, $receivedAt]);

                $syncedInbound++;
            }
        }
        imap_close($inboxStream);
    }

    // ------------------------------------------
    // PASS 2: SCAN INBOX.Sent (Sent Outbound Mailbox)
    // Automatically detects replies sent from Hostinger Webmail
    // ------------------------------------------
    $sentFolderNames = ['INBOX.Sent', 'Sent', 'Sent Items', 'INBOX/Sent'];
    $sentStream = false;

    foreach ($sentFolderNames as $folder) {
        $sentPath = "{" . $mailHost . ":" . $mailPort . "/imap/ssl/novalidate-cert}" . $folder;
        $sentStream = @imap_open($sentPath, $mailUser, $mailPass);
        if ($sentStream) {
            break;
        }
    }

    if ($sentStream) {
        $recentSent = @imap_search($sentStream, 'SINCE "' . $sinceDate . '"');

        if ($recentSent) {
            foreach ($recentSent as $sentMsgNo) {
                $header = @imap_headerinfo($sentStream, $sentMsgNo);
                if (empty($header->to[0]->mailbox) || empty($header->to[0]->host)) continue;

                $toEmail = strtolower(trim($header->to[0]->mailbox . '@' . $header->to[0]->host));
                $msgId   = !empty($header->message_id) ? trim($header->message_id) : ('<sent-' . md5($mailUser . '|' . $toEmail . '|' . ($header->date ?? $sentMsgNo)) . '>');
                $subject = !empty($header->subject) ? decodeMimeHeaderText($header->subject) : 'Export Reply';
                $sentTs  = !empty($header->date) ? strtotime($header->date) : time();
                $sentDate = date('M d, Y H:i', $sentTs);
                $sentAtSql = date('Y-m-d H:i:s', $sentTs);

                // Find matching open inquiry in database
                $matchStmt = $pdo->prepare("SELECT id, status, buyer_name FROM inquiries WHERE LOWER(email) = ? ORDER BY id DESC LIMIT 1");
                $matchStmt->execute([$toEmail]);
                $inquiry = $matchStmt->fetch(PDO::FETCH_ASSOC);

                if ($inquiry) {
                    $chkMsg = $pdo->prepare("SELECT id FROM email_messages WHERE message_id = ?");
                    $chkMsg->execute([$msgId]);
                    $alreadyArchived = (bool)$chkMsg->fetch();

                    if ($inquiry['status'] === 'New') {
                        $note = "[" . $sentDate . "] Replied via Hostinger Webmail (" . $mailUser . ") to " . $toEmail . "\nSubject: " . $subject . "\n-------------------\n";
                        $up = $pdo->prepare("UPDATE inquiries SET status = 'Contacted', internal_notes = CONCAT(?, COALESCE(internal_notes, '')) WHERE id = ?");
                        $up->execute([$note, $inquiry['id']]);
                        $syncedSent++;
                    }

                    if (!$alreadyArchived) {
                        $cleanSentBody = extractPlainTextEmailBody($sentStream, $sentMsgNo);
                        $log = $pdo->prepare("INSERT INTO email_messages 
                            (inquiry_id, direction, from_email, to_email, subject, body_text, status, message_id, sent_at) 
                            VALUES (?, 'outbound', ?, ?, ?, ?, 'Sent', ?, ?)");
                        $log->execute([$inquiry['id'], $mailUser, $toEmail, substr($subject, 0, 250), $cleanSentBody, $msgId, $sentAtSql]);
                    }
                }
            }
        }
        imap_close($sentStream);
    }
}

$totalSynced = $syncedInbound + $syncedSent;
echo json_encode([
    'success' => true,
    'synced_count' => $totalSynced,
    'synced_inbound' => $syncedInbound,
    'synced_sent' => $syncedSent,
    'mailboxes' => $syncedBoxLabels,
    'warnings' => $warnings,
    'message' => "Hostinger Two-Way Sync Complete (" . implode(', ', $syncedBoxLabels) . "): {$syncedInbound} new inbound email(s) synced; {$syncedSent} webmail reply update(s)."
]);
