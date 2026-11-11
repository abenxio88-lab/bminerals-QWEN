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
require_once __DIR__ . '/mail-helpers.php';

// Must have Email Desk permission to trigger sync, then release session lock immediately so parallel page clicks NEVER block
requirePermission('draft_reply', true);
$allMailboxes = function_exists('getConfiguredMailboxes') ? getConfiguredMailboxes() : [
    'sales' => [
        'user' => defined('MAIL_IMAP_USER') ? MAIL_IMAP_USER : 'sales@balochistanminerals.com',
        'pass' => defined('MAIL_IMAP_PASS') ? MAIL_IMAP_PASS : '',
    ],
];
releaseSessionLock();

if (function_exists('imap_timeout')) {
    @imap_timeout(IMAP_OPENTIMEOUT, 5);
    @imap_timeout(IMAP_READTIMEOUT, 8);
    @imap_timeout(IMAP_WRITETIMEOUT, 8);
    @imap_timeout(IMAP_CLOSETIMEOUT, 3);
}

$pdo = getDB();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit;
}

// Check if PHP IMAP extension is installed
if (!function_exists('imap_open')) {
    echo json_encode([
        'success' => false,
        'warning' => 'PHP IMAP extension is not enabled in local PHP. On Hostinger production, IMAP extension is enabled.',
        'synced_count' => 0,
        'info' => 'Configure PHP imap extension locally or sync directly from production Hostinger.'
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

// Hostinger Mail Settings (supports both sales@ and info@ mailboxes)
$mailHost = defined('MAIL_IMAP_HOST') ? MAIL_IMAP_HOST : 'imap.hostinger.com';
$mailPort = defined('MAIL_IMAP_PORT') ? MAIL_IMAP_PORT : 993;

$activeBoxes = array_values(array_filter($allMailboxes, function ($box) {
    return !empty($box['user']) && !empty($box['pass']);
}));

if (empty($activeBoxes)) {
    echo json_encode([
        'success' => true,
        'synced_count' => 0,
        'synced_inbound' => 0,
        'synced_sent' => 0,
        'message' => 'Hostinger email password not yet saved. Configure MAIL_IMAP_PASS / INFO_MAIL_PASS secrets or Mail Settings.'
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

$isManual  = isset($_GET['manual']) && $_GET['manual'] === '1';
$sinceDate = date("j-M-Y", strtotime($isManual ? "-14 days" : "-3 days"));
$autoCooldownFile = sys_get_temp_dir() . '/bm_imap_last_auto_sync.json';
if (!$isManual && is_file($autoCooldownFile)) {
    $cdData = @json_decode((string)@file_get_contents($autoCooldownFile), true);
    if (is_array($cdData) && !empty($cdData['ts']) && (time() - (int)$cdData['ts']) < 120) {
        echo json_encode([
            'success'          => true,
            'skipped_cooldown' => true,
            'synced_count'     => 0,
            'synced_inbound'   => 0,
            'synced_sent'      => 0,
            'warnings'         => [],
            'message'          => 'Mailboxes recently synced.',
        ]);
        exit;
    }
}
@file_put_contents($autoCooldownFile, json_encode(['ts' => time()]));

/**
 * Prevents repeated background IMAP login storms when Hostinger blocks or rejects a mailbox.
 */
function getImapBackoffFile($mailUser) {
    return sys_get_temp_dir() . '/bm_imap_backoff_' . md5(strtolower(trim((string)$mailUser))) . '.json';
}

function getImapBackoffState($mailUser) {
    $file = getImapBackoffFile($mailUser);
    if (!is_file($file)) return null;
    $data = @json_decode((string)@file_get_contents($file), true);
    if (!is_array($data) || empty($data['until'])) return null;
    if ((int)$data['until'] <= time()) {
        @unlink($file);
        return null;
    }
    return $data;
}

function setImapBackoffState($mailUser, $seconds, $reason) {
    $file = getImapBackoffFile($mailUser);
    @file_put_contents($file, json_encode([
        'until'  => time() + (int)$seconds,
        'reason' => (string)$reason,
    ]));
}

function clearImapBackoffState($mailUser) {
    $file = getImapBackoffFile($mailUser);
    if (is_file($file)) @unlink($file);
}

foreach ($activeBoxes as $box) {
    $mailUser = $box['user'];
    $mailPass = $box['pass'];

    $backoff = getImapBackoffState($mailUser);
    if ($backoff !== null && !$isManual) {
        // Skip background auto-sync while mailbox is in cooldown so Hostinger does not extend IP/tuple lockout
        continue;
    }

    $syncedBoxLabels[] = $mailUser;

    // ------------------------------------------
    // PASS 1: SCAN INBOX (Recent 14 days, deduped by Message-ID)
    // Ensures emails already opened on phone/Webmail still land cleanly in Admin
    // ------------------------------------------
    $inboxPath = "{" . $mailHost . ":" . $mailPort . "/imap/ssl/novalidate-cert}INBOX";
    $inboxStream = @imap_open($inboxPath, $mailUser, $mailPass);

    if (!$inboxStream) {
        $lastErr = function_exists('imap_last_error') ? imap_last_error() : 'Connection failed';
        if (function_exists('imap_errors')) @imap_errors();
        if (function_exists('imap_alerts')) @imap_alerts();
        $errText = $lastErr ?: 'Unable to connect to IMAP INBOX';

        // Back off for 15 minutes on auth failure / temporary blacklist and DO NOT hammer PASS 2 Sent folders
        if (preg_match('/blacklisted|authenticate|login\s*failed|auth/i', $errText)) {
            setImapBackoffState($mailUser, 900, $errText);
        }
        $warnings[] = $mailUser . ': ' . $errText;
        logAdminNotification($pdo, 'warning', 'mail_sync', "Mailbox Sync Issue ({$mailUser})", $errText, null, 'email-desk.php', 15);
        continue;
    }

    clearImapBackoffState($mailUser);

    $recentEmails = @imap_search($inboxStream, 'SINCE "' . $sinceDate . '"');
    if ($recentEmails) {
        // Process oldest -> newest so DB auto-increment IDs stay aligned with chronological arrival
        sort($recentEmails, SORT_NUMERIC);
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

            // Strict deduplication across both inquiries and email_messages (with HTML/attachment backfill for legacy rows)
            $chkInq = $pdo->prepare("SELECT id, message FROM inquiries WHERE email_message_id = ?");
            $chkInq->execute([$msgId]);
            $existingInqRow = $chkInq->fetch(PDO::FETCH_ASSOC);
            $chkMsg = $pdo->prepare("SELECT id, body_html, attachments FROM email_messages WHERE message_id = ?");
            $chkMsg->execute([$msgId]);
            $existingMsgRow = $chkMsg->fetch(PDO::FETCH_ASSOC);

            if ($existingInqRow || $existingMsgRow) {
                $needsHtmlBackfill = $existingMsgRow && (trim((string)($existingMsgRow['body_html'] ?? '')) === '' || empty($existingMsgRow['attachments']));
                if ($needsHtmlBackfill) {
                    $parts = extractImapMessageParts($inboxStream, $emailNumber, true);
                    $cleanBody = $parts['body_text'] !== '' ? $parts['body_text'] : '(No plain-text message body)';
                    $attJson   = !empty($existingMsgRow['attachments'])
                        ? $existingMsgRow['attachments']
                        : encodeAttachmentsMeta($parts['attachments']);
                    $newHtml   = trim((string)($existingMsgRow['body_html'] ?? '')) !== ''
                        ? $existingMsgRow['body_html']
                        : $parts['body_html'];

                    $updMsg = $pdo->prepare("UPDATE email_messages SET body_text = ?, body_html = ?, attachments = ? WHERE id = ?");
                    $updMsg->execute([$cleanBody, $newHtml, $attJson, (int)$existingMsgRow['id']]);
                }
                if ($existingInqRow && strpos((string)($existingInqRow['message'] ?? ''), '[cid:') !== false) {
                    $cleanedLeadMsg = cleanEmailPlainText($existingInqRow['message']);
                    $pdo->prepare("UPDATE inquiries SET message = ? WHERE id = ?")->execute([$cleanedLeadMsg, (int)$existingInqRow['id']]);
                }
                continue;
            }

            $parts     = extractImapMessageParts($inboxStream, $emailNumber, true);
            $cleanBody = $parts['body_text'] !== '' ? $parts['body_text'] : '(No plain-text message body)';
            $bodyHtml  = $parts['body_html'];
            $attJson   = encodeAttachmentsMeta($parts['attachments']);

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

            // Always archive into email_messages so Email Desk & 360° Dossier show rich HTML, CID images & attachments
            $emStmt = $pdo->prepare("INSERT INTO email_messages
                (inquiry_id, direction, from_email, to_email, subject, body_text, body_html, attachments, status, message_id, sent_at)
                VALUES (?, 'inbound', ?, ?, ?, ?, ?, ?, 'Received', ?, ?)");
            $emStmt->execute([$inquiryId, $fromEmail, $mailUser, substr($subject, 0, 250), $cleanBody, $bodyHtml, $attJson, $msgId, $receivedAt]);

            logAdminNotification(
                $pdo,
                'info',
                'inbound_email',
                "New Email in {$mailUser}",
                "{$fromName} ({$fromEmail}) — {$subject}",
                $inquiryId,
                'dashboard.php',
                0,
                $receivedAt
            );

            $syncedInbound++;
        }
    }

    // ------------------------------------------
    // PASS 2: SCAN INBOX.Sent (Sent Outbound Mailbox via imap_reopen on existing stream)
    // Avoids extra TLS login attempts per cycle while detecting Webmail replies
    // ------------------------------------------
    $sentFolderNames = ['INBOX.Sent', 'Sent', 'Sent Items', 'INBOX/Sent'];
    $sentStream = false;

    foreach ($sentFolderNames as $folder) {
        $sentPath = "{" . $mailHost . ":" . $mailPort . "/imap/ssl/novalidate-cert}" . $folder;
        if (@imap_reopen($inboxStream, $sentPath)) {
            $sentStream = $inboxStream;
            break;
        }
    }

    if ($sentStream) {
        $recentSent = @imap_search($sentStream, 'SINCE "' . $sinceDate . '"');

        if ($recentSent) {
            sort($recentSent, SORT_NUMERIC);
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
                    $chkMsg = $pdo->prepare("SELECT id, body_html, attachments FROM email_messages WHERE message_id = ?");
                    $chkMsg->execute([$msgId]);
                    $existingSentRow = $chkMsg->fetch(PDO::FETCH_ASSOC);

                    if ($inquiry['status'] === 'New') {
                        $note = "[" . $sentDate . "] Replied via Hostinger Webmail (" . $mailUser . ") to " . $toEmail . "\nSubject: " . $subject . "\n-------------------\n";
                        $up = $pdo->prepare("UPDATE inquiries SET status = 'Contacted', internal_notes = CONCAT(?, COALESCE(internal_notes, '')) WHERE id = ?");
                        $up->execute([$note, $inquiry['id']]);
                        $syncedSent++;
                    }

                    if (!$existingSentRow) {
                        $sentParts = extractImapMessageParts($sentStream, $sentMsgNo, true);
                        $log = $pdo->prepare("INSERT INTO email_messages 
                            (inquiry_id, direction, from_email, to_email, subject, body_text, body_html, attachments, status, message_id, sent_at) 
                            VALUES (?, 'outbound', ?, ?, ?, ?, ?, ?, 'Sent', ?, ?)");
                        $log->execute([
                            $inquiry['id'],
                            $mailUser,
                            $toEmail,
                            substr($subject, 0, 250),
                            $sentParts['body_text'],
                            $sentParts['body_html'],
                            encodeAttachmentsMeta($sentParts['attachments']),
                            $msgId,
                            $sentAtSql
                        ]);
                    } elseif (trim((string)($existingSentRow['body_html'] ?? '')) === '') {
                        $sentParts = extractImapMessageParts($sentStream, $sentMsgNo, true);
                        $attJson   = !empty($existingSentRow['attachments'])
                            ? $existingSentRow['attachments']
                            : encodeAttachmentsMeta($sentParts['attachments']);
                        $pdo->prepare("UPDATE email_messages SET body_text = ?, body_html = ?, attachments = ? WHERE id = ?")
                            ->execute([$sentParts['body_text'], $sentParts['body_html'], $attJson, (int)$existingSentRow['id']]);
                    }
                }
            }
        }
    }

    @imap_close($inboxStream);
    if (function_exists('imap_errors')) @imap_errors();
    if (function_exists('imap_alerts')) @imap_alerts();
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
