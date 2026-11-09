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
// Connects to mail.balochistanminerals.com
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

// Hostinger Mail Settings
$mailHost = defined('MAIL_IMAP_HOST') ? MAIL_IMAP_HOST : 'mail.balochistanminerals.com';
$mailPort = defined('MAIL_IMAP_PORT') ? MAIL_IMAP_PORT : 993;
$mailUser = defined('MAIL_IMAP_USER') ? MAIL_IMAP_USER : 'sales@balochistanminerals.com';
$mailPass = defined('MAIL_IMAP_PASS') ? MAIL_IMAP_PASS : '';

if (empty($mailPass)) {
    echo json_encode([
        'success' => true,
        'synced_count' => 0,
        'message' => 'Hostinger email password not yet saved. You can configure credentials in admin/db.php under MAIL_IMAP_PASS.',
        'info' => 'Simulated mode available.'
    ]);
    exit;
}

$syncedInbound = 0;
$syncedSent = 0;

// ------------------------------------------
// PASS 1: SCAN INBOX (Incoming Inquiries)
// ------------------------------------------
$inboxPath = "{" . $mailHost . ":" . $mailPort . "/imap/ssl/novalidate-cert}INBOX";
$inboxStream = @imap_open($inboxPath, $mailUser, $mailPass);

if ($inboxStream) {
    $unreadEmails = imap_search($inboxStream, 'UNSEEN');
    if ($unreadEmails) {
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

        foreach ($unreadEmails as $emailNumber) {
            $overview = imap_fetch_overview($inboxStream, $emailNumber, 0);
            $header   = imap_headerinfo($inboxStream, $emailNumber);
            $body     = imap_fetchbody($inboxStream, $emailNumber, 1);

            $fromEmail = $header->from[0]->mailbox . '@' . $header->from[0]->host;
            $fromName  = isset($header->from[0]->personal) ? imap_utf8($header->from[0]->personal) : $header->from[0]->mailbox;
            $subject   = isset($overview[0]->subject) ? imap_utf8($overview[0]->subject) : 'Inbound Inquiry';
            $msgId     = isset($header->message_id) ? trim($header->message_id) : ('<msg-' . $emailNumber . '-' . time() . '>');

            // Check if already synced
            $chk = $pdo->prepare("SELECT id FROM inquiries WHERE email_message_id = ?");
            $chk->execute([$msgId]);
            if ($chk->fetch()) {
                continue;
            }

            // Detect mineral
            $detectedMineral = 'General Mineral';
            $fullTextLower = strtolower($subject . ' ' . $body);
            foreach ($mineralKeywords as $kw => $minName) {
                if (strpos($fullTextLower, $kw) !== false) {
                    $detectedMineral = $minName;
                    break;
                }
            }

            $cleanBody = strip_tags($body);
            $fullMessage = "Subject: " . $subject . "\n\n" . trim($cleanBody);
            $detectedCountry = detectCountryName('', $fromEmail, '', $fromName, $fullMessage);

            $stmt = $pdo->prepare("INSERT INTO inquiries 
                (buyer_name, email, country, inquiry_type, source, email_message_id, mineral_type, message, status) 
                VALUES (?, ?, ?, 'Email Inbound', ?, ?, ?, ?, 'New')");
            $stmt->execute([$fromName, $fromEmail, $detectedCountry, $mailUser, $msgId, $detectedMineral, $fullMessage]);
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
    // Check emails sent within the last 14 days
    $sinceDate = date("j-M-Y", strtotime("-14 days"));
    $recentSent = imap_search($sentStream, 'SINCE "' . $sinceDate . '"');

    if ($recentSent) {
        foreach ($recentSent as $sentMsgNo) {
            $header = imap_headerinfo($sentStream, $sentMsgNo);
            if (empty($header->to)) continue;

            $toEmail = strtolower($header->to[0]->mailbox . '@' . $header->to[0]->host);
            $msgId   = isset($header->message_id) ? trim($header->message_id) : '';
            $subject = isset($header->subject) ? imap_utf8($header->subject) : 'Export Reply';
            $sentDate = isset($header->date) ? date('M d, Y H:i', strtotime($header->date)) : date('M d, Y H:i');

            // Find matching open inquiry in database
            $matchStmt = $pdo->prepare("SELECT id, status, buyer_name FROM inquiries WHERE LOWER(email) = ? ORDER BY id DESC LIMIT 1");
            $matchStmt->execute([$toEmail]);
            $inquiry = $matchStmt->fetch(PDO::FETCH_ASSOC);

            if ($inquiry) {
                // If status was 'New', flip to 'Contacted'
                if ($inquiry['status'] === 'New') {
                    $note = "[" . $sentDate . "] Replied via Hostinger Webmail to " . $toEmail . "\nSubject: " . $subject . "\n-------------------\n";
                    $up = $pdo->prepare("UPDATE inquiries SET status = 'Contacted', internal_notes = CONCAT(?, COALESCE(internal_notes, '')) WHERE id = ?");
                    $up->execute([$note, $inquiry['id']]);
                    $syncedSent++;

                    // Also archive into email_messages if not already recorded
                    $chkMsg = $pdo->prepare("SELECT id FROM email_messages WHERE message_id = ?");
                    $chkMsg->execute([$msgId]);
                    if (!$chkMsg->fetch()) {
                        $log = $pdo->prepare("INSERT INTO email_messages 
                            (inquiry_id, direction, from_email, to_email, subject, body_text, status, message_id) 
                            VALUES (?, 'outbound', ?, ?, ?, ?, 'Sent', ?)");
                        $body = imap_fetchbody($sentStream, $sentMsgNo, 1);
                        $log->execute([$inquiry['id'], $mailUser, $toEmail, $subject, strip_tags($body), $msgId]);
                    }
                }
            }
        }
    }
    imap_close($sentStream);
}

echo json_encode([
    'success' => true,
    'synced_inbound' => $syncedInbound,
    'synced_sent' => $syncedSent,
    'message' => "Hostinger Two-Way Sync Complete: {$syncedInbound} new inbound leads ingested; {$syncedSent} external replies synchronized to [Contacted]."
]);
