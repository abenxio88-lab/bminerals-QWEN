<?php
/**
 * Balochistan Minerals - Inbound Corporate Mailbox Sync Engine
 * Fetches unread inquiries sent to sales@ and info@ on Hostinger and ingests them into the Trade Desk Lead Engine.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
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
        ['name' => 'Alexander Richter', 'company' => 'Ruhr Rohstoff GmbH', 'email' => 'a.richter@ruhr-rohstoff.de', 'country' => 'Germany', 'subject' => 'Inquiry for high-grade chromite lumps 46-48%', 'mineral' => 'Chromite Ore', 'body' => "Dear Balochistan Minerals Export Desk,\n\nWe are looking to secure 3,000 MT of refractory/metallurgical chromite lumps (46-48% Cr2O3 basis) for shipment to Hamburg/Rotterdam in Q2. Please advise current indicative FOB Karachi rates and latest SGS assay reports.\n\nBest regards,\nAlexander Richter\nHead of Procurement"],
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
// 2. LIVE IMAP MAILBOX SYNC ENGINE
// Connects to mail.balochistanminerals.com
// ==========================================

// Check if PHP IMAP extension is installed
if (!function_exists('imap_open')) {
    echo json_encode([
        'success' => false,
        'warning' => 'PHP IMAP extension is not enabled in local PHP. In production on Hostinger, standard IMAP extension is enabled.',
        'synced_count' => 0,
        'info' => 'Use the "Simulate Inbound Test Email" button to test local ingestion flow.'
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

$mailbox = "{" . $mailHost . ":" . $mailPort . "/imap/ssl/novalidate-cert}INBOX";

$imapStream = @imap_open($mailbox, $mailUser, $mailPass);
if (!$imapStream) {
    echo json_encode([
        'success' => false,
        'error' => 'Could not connect to Hostinger IMAP server: ' . imap_last_error()
    ]);
    exit;
}

// Search for unread emails
$emails = imap_search($imapStream, 'UNSEEN');
$syncedCount = 0;

if ($emails) {
    // Known mineral keywords to auto-tag
    $mineralKeywords = [
        'chromite' => 'Chromite Ore',
        'barite'   => 'Barite',
        'iron'     => 'Iron Ore',
        'copper'   => 'Copper Ore',
        'antimony' => 'Antimony',
        'fluor'    => 'Fluorspar',
        'magnesite'=> 'Magnesite',
        'marble'   => 'Silver Steam Marble',
        'coal'     => 'Coal',
        'gypsum'   => 'Gypsum'
    ];

    foreach ($emails as $emailNumber) {
        $overview = imap_fetch_overview($imapStream, $emailNumber, 0);
        $header = imap_headerinfo($imapStream, $emailNumber);
        $body = imap_fetchbody($imapStream, $emailNumber, 1);

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

        // Clean plain text body
        $cleanBody = strip_tags($body);
        $fullMessage = "Subject: " . $subject . "\n\n" . trim($cleanBody);

        $stmt = $pdo->prepare("INSERT INTO inquiries 
            (buyer_name, email, inquiry_type, source, email_message_id, mineral_type, message, status) 
            VALUES (?, ?, 'Email Inbound', ?, ?, ?, ?, 'New')");
        
        $stmt->execute([
            $fromName,
            $fromEmail,
            $mailUser,
            $msgId,
            $detectedMineral,
            $fullMessage
        ]);

        $syncedCount++;
    }
}

imap_close($imapStream);

echo json_encode([
    'success' => true,
    'synced_count' => $syncedCount,
    'message' => $syncedCount > 0 
        ? "Successfully synced {$syncedCount} new inbound emails into Trade Desk." 
        : "Mailbox is up to date. No new unread inquiries."
]);
