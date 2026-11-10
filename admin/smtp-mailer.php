<?php
/**
 * Balochistan Minerals - Pure PHP Hostinger Corporate SMTP Mailer
 * Connects directly to smtp.hostinger.com via SSL (port 465) or TLS (port 587)
 * with multipart MIME, HTML styling, attachments, and database message archival.
 */

require_once __DIR__ . '/db.php';

// Load saved mail configuration if available
$mailConfigFile = __DIR__ . '/mail-config.php';
if (file_exists($mailConfigFile)) {
    require_once $mailConfigFile;
}

if (!defined('BM_SMTP_HOST')) define('BM_SMTP_HOST', 'smtp.hostinger.com');
if (!defined('BM_SMTP_PORT')) define('BM_SMTP_PORT', 465); // 465 SSL, 587 TLS
if (!defined('BM_SMTP_USER')) define('BM_SMTP_USER', 'sales@balochistanminerals.com');
if (!defined('BM_SMTP_PASS')) {
    $sessionPass = $_SESSION['bm_mail_pass'] ?? '';
    $pass = $sessionPass !== '' ? $sessionPass : (defined('MAIL_IMAP_PASS') ? MAIL_IMAP_PASS : '');
    define('BM_SMTP_PASS', $pass);
}

/**
 * Sends an email via direct authenticated Hostinger SMTP socket
 * 
 * @param string $toEmail Recipient email address
 * @param string $subject Email subject line
 * @param string $bodyText Plain text message body
 * @param string|null $fromEmail Sender address (defaults to sales@balochistanminerals.com)
 * @param string|null $ccEmail Optional CC recipient
 * @param array $attachments Array of file paths or document names to attach
 * @param int|null $inquiryId Associated inquiry ID for logging
 * @return array ['success' => bool, 'message' => string, 'simulated' => bool]
 */
function sendHostingerMail($toEmail, $subject, $bodyText, $fromEmail = null, $ccEmail = null, $attachments = [], $inquiryId = null) {
    $host = defined('BM_SMTP_HOST') ? BM_SMTP_HOST : 'smtp.hostinger.com';
    $port = defined('BM_SMTP_PORT') ? (int)BM_SMTP_PORT : 465;
    $defaultUser = defined('BM_SMTP_USER') ? BM_SMTP_USER : 'sales@balochistanminerals.com';
    $defaultPass = defined('BM_SMTP_PASS') ? BM_SMTP_PASS : ($_SESSION['bm_mail_pass'] ?? '');

    if (empty($fromEmail)) {
        $fromEmail = $defaultUser;
    }

    // Match SMTP login credentials to the selected sender mailbox (sales@ or info@)
    $mailboxes = function_exists('getConfiguredMailboxes') ? getConfiguredMailboxes() : [];
    $fromKey   = strtolower(trim($fromEmail));
    if (isset($mailboxes[$fromKey]) && $mailboxes[$fromKey]['pass'] !== '') {
        $user = $mailboxes[$fromKey]['user'];
        $pass = $mailboxes[$fromKey]['pass'];
    } elseif ($fromKey === strtolower($defaultUser) && $defaultPass !== '') {
        $user = $defaultUser;
        $pass = $defaultPass;
    } else {
        // Selected sender mailbox password not configured: use default SMTP account if available only when sender matches
        $user = $fromEmail;
        $pass = '';
    }
    $fromName = 'Balochistan Minerals (Pvt) Ltd — Commercial Export Desk';

    // Generate HTML version with corporate styling
    $bodyHtml = buildCorporateEmailHtml($subject, $bodyText, $fromEmail);
    $msgId = '<bm-' . uniqid() . '.' . time() . '@balochistanminerals.com>';
    $attachmentListStr = !empty($attachments) ? implode(', ', $attachments) : null;

    // Archive in email_messages table
    $pdo = getDB();
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare("INSERT INTO email_messages 
                (inquiry_id, direction, from_email, to_email, cc_email, subject, body_text, body_html, attachments, status, message_id)
                VALUES (?, 'outbound', ?, ?, ?, ?, ?, ?, ?, 'Sent', ?)");
            $stmt->execute([
                $inquiryId, $fromEmail, $toEmail, $ccEmail, $subject, $bodyText, $bodyHtml, $attachmentListStr, $msgId
            ]);
        } catch (Exception $e) {
            error_log("Email archival error: " . $e->getMessage());
        }
    }

    // If no password set, log as simulated/desk delivery
    if (empty($pass)) {
        return [
            'success' => true,
            'simulated' => true,
            'message' => 'Dispatched & logged into Buyer Dossier and Outbox (Hostinger SMTP password not set in settings).',
            'message_id' => $msgId
        ];
    }

    // Connect via socket
    $transport = ($port == 465) ? 'ssl://' . $host : $host;
    $timeout = 10;

    $socket = @fsockopen($transport, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        return [
            'success' => false,
            'error' => "Cannot connect to SMTP server $host:$port ($errstr [$errno])"
        ];
    }

    stream_set_timeout($socket, $timeout);

    // Read helper
    $getResponse = function() use ($socket) {
        $res = '';
        while ($str = fgets($socket, 515)) {
            $res .= $str;
            if (substr($str, 3, 1) == ' ') break;
        }
        return $res;
    };

    $sendCommand = function($cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };

    $greeting = $getResponse();
    if (substr($greeting, 0, 3) != '220') {
        fclose($socket);
        return ['success' => false, 'error' => "SMTP greeting rejected: $greeting"];
    }

    $sendCommand("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'balochistanminerals.com'));
    $ehlo = $getResponse();

    // STARTTLS if port 587
    if ($port == 587) {
        $sendCommand("STARTTLS");
        $tlsRes = $getResponse();
        if (substr($tlsRes, 0, 3) == '220') {
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $sendCommand("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'balochistanminerals.com'));
            $ehlo = $getResponse();
        }
    }

    // AUTH LOGIN
    $sendCommand("AUTH LOGIN");
    $authRes = $getResponse();
    if (substr($authRes, 0, 3) != '334') {
        fclose($socket);
        return ['success' => false, 'error' => "AUTH LOGIN rejected: $authRes"];
    }

    $sendCommand(base64_encode($user));
    $userRes = $getResponse();
    if (substr($userRes, 0, 3) != '334') {
        fclose($socket);
        return ['success' => false, 'error' => "Username rejected: $userRes"];
    }

    $sendCommand(base64_encode($pass));
    $passRes = $getResponse();
    if (substr($passRes, 0, 3) != '235') {
        fclose($socket);
        return ['success' => false, 'error' => "Authentication failed. Check your Hostinger mailbox password."];
    }

    // MAIL FROM
    $sendCommand("MAIL FROM: <$fromEmail>");
    $fromRes = $getResponse();
    if (substr($fromRes, 0, 3) != '250') {
        fclose($socket);
        return ['success' => false, 'error' => "MAIL FROM rejected: $fromRes"];
    }

    // RCPT TO
    $sendCommand("RCPT TO: <$toEmail>");
    $rcptRes = $getResponse();
    if (substr($rcptRes, 0, 3) != '250') {
        fclose($socket);
        return ['success' => false, 'error' => "RCPT TO rejected: $rcptRes"];
    }

    // Optional CC recipient
    if (!empty($ccEmail)) {
        $sendCommand("RCPT TO: <$ccEmail>");
        $getResponse();
    }

    // DATA
    $sendCommand("DATA");
    $dataRes = $getResponse();
    if (substr($dataRes, 0, 3) != '354') {
        fclose($socket);
        return ['success' => false, 'error' => "DATA rejected: $dataRes"];
    }

    // Build MIME message with boundary
    $boundary = "----=_Part_" . md5(uniqid()) . "_" . time();
    $headers = [
        "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>",
        "To: <$toEmail>",
        "Reply-To: <$fromEmail>",
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
        "Date: " . date('r'),
        "Message-ID: $msgId",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"$boundary\"",
        "X-Mailer: Balochistan Minerals Trade Desk SMTP Engine"
    ];

    if (!empty($ccEmail)) {
        $headers[] = "Cc: <$ccEmail>";
    }

    $mimeMsg  = implode("\r\n", $headers) . "\r\n\r\n";
    $mimeMsg .= "--" . $boundary . "\r\n";
    $mimeMsg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $mimeMsg .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $mimeMsg .= $bodyText . "\r\n\r\n";

    $mimeMsg .= "--" . $boundary . "\r\n";
    $mimeMsg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $mimeMsg .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $mimeMsg .= $bodyHtml . "\r\n\r\n";

    $mimeMsg .= "--" . $boundary . "--\r\n.";

    $sendCommand($mimeMsg);
    $sendRes = $getResponse();

    $sendCommand("QUIT");
    fclose($socket);

    if (substr($sendRes, 0, 3) == '250') {
        return [
            'success' => true,
            'simulated' => false,
            'message' => 'Official B2B trade offer delivered successfully via Hostinger SMTP relay.',
            'message_id' => $msgId
        ];
    }

    return ['success' => false, 'error' => "Message delivery failed: $sendRes"];
}

/**
 * Builds high-converting HTML corporate email with official signature
 */
function buildCorporateEmailHtml($subject, $bodyText, $fromEmail) {
    $paragraphs = explode("\n\n", htmlspecialchars($bodyText));
    $htmlBody = '';
    foreach ($paragraphs as $p) {
        $trimmed = trim($p);
        if (empty($trimmed)) continue;
        $htmlBody .= '<p style="margin: 0 0 14px; font-size: 14px; line-height: 1.6; color: #1E293B;">' . nl2br($trimmed) . '</p>';
    }

    return '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>' . htmlspecialchars($subject) . '</title>
</head>
<body style="margin: 0; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #F8FAFC;">
  <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 650px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
    
    <!-- Corporate Header Banner -->
    <tr>
      <td style="padding: 24px 30px; background: #0F172A; border-bottom: 3px solid #E65C1A;">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td>
              <div style="font-size: 18px; font-weight: 800; color: #FFFFFF; text-transform: uppercase; letter-spacing: 0.5px;">
                BALOCHISTAN MINERALS (PVT) LTD
              </div>
              <div style="font-size: 11px; color: #94A3B8; text-transform: uppercase; letter-spacing: 1px; margin-top: 3px;">
                Commercial Export Desk • Gwadar Port, KPT & Port Qasim Corridors
              </div>
            </td>
            <td align="right">
              <span style="display: inline-block; padding: 4px 10px; background: rgba(230,92,26,0.18); border: 1px solid #E65C1A; color: #E65C1A; font-size: 11px; font-weight: 700; border-radius: 4px;">
                OFFICIAL B2B OFFER
              </span>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Body Content -->
    <tr>
      <td style="padding: 30px; background: #FFFFFF;">
        ' . $htmlBody . '
      </td>
    </tr>

    <!-- Official Corporate Signature & Banking Compliance Block -->
    <tr>
      <td style="padding: 24px 30px; background: #F8FAFC; border-top: 1px solid #E2E8F0;">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="vertical-align: top; width: 65%;">
              <div style="font-weight: 800; font-size: 13px; color: #0F172A; text-transform: uppercase;">
                Commercial Export Operations Desk
              </div>
              <div style="font-size: 12px; font-weight: 700; color: #E65C1A; margin-top: 2px;">
                Balochistan Minerals (Pvt) Ltd
              </div>
              <div style="font-size: 11px; color: #64748B; margin-top: 6px; line-height: 1.5;">
                • Registered with Securities & Exchange Commission of Pakistan (SECP)<br>
                • Concessions: Muslim Bagh, Khuzdar & Chagai, Balochistan<br>
                • Port Corridors: Karachi Port Trust (KPT) & Port Muhammad Bin Qasim (QICT)<br>
                • Direct Desk: +92 334 8888104 • Email: ' . htmlspecialchars($fromEmail) . '
              </div>
            </td>
            <td style="vertical-align: top; text-align: right; width: 35%;">
              <div style="display: inline-block; padding: 8px 12px; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 6px; text-align: left; font-size: 10px; color: #475569; line-height: 1.4;">
                <strong style="color: #0F172A;">QUALITY ASSURANCE:</strong><br>
                100% Verified by SGS / PCSIR / Alfred H Knight at load port.
              </div>
            </td>
          </tr>
        </table>

        <div style="margin-top: 16px; padding-top: 14px; border-top: 1px dashed #CBD5E1; font-size: 9.5px; color: #94A3B8; line-height: 1.4;">
          CONFIDENTIALITY NOTE: This transmission contains proprietary commercial export terms compliant with State Bank of Pakistan (SBP) Foreign Exchange Regulations. If you are not the intended recipient, please notify the sender and delete immediately.
        </div>
      </td>
    </tr>

  </table>
</body>
</html>';
}

/**
 * Tests Hostinger SMTP connection and credentials
 */
function testHostingerSmtp($host, $port, $user, $pass) {
    $transport = ($port == 465) ? 'ssl://' . $host : $host;
    $socket = @fsockopen($transport, $port, $errno, $errstr, 8);
    if (!$socket) {
        return ['success' => false, 'error' => "Could not open socket connection: $errstr ($errno)"];
    }

    stream_set_timeout($socket, 8);
    $greeting = fgets($socket, 515);
    if (substr($greeting, 0, 3) != '220') {
        fclose($socket);
        return ['success' => false, 'error' => "Server did not send 220 greeting: $greeting"];
    }

    fputs($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'balochistanminerals.com') . "\r\n");
    $ehlo = '';
    while ($str = fgets($socket, 515)) {
        $ehlo .= $str;
        if (substr($str, 3, 1) == ' ') break;
    }

    fputs($socket, "AUTH LOGIN\r\n");
    $auth = fgets($socket, 515);
    if (substr($auth, 0, 3) != '334') {
        fclose($socket);
        return ['success' => false, 'error' => "AUTH LOGIN rejected: $auth"];
    }

    fputs($socket, base64_encode($user) . "\r\n");
    $uRes = fgets($socket, 515);

    fputs($socket, base64_encode($pass) . "\r\n");
    $pRes = fgets($socket, 515);

    fputs($socket, "QUIT\r\n");
    fclose($socket);

    if (substr($pRes, 0, 3) == '235') {
        return ['success' => true, 'message' => "Handshake & authentication successful on $host:$port ($user)"];
    }

    return ['success' => false, 'error' => "Authentication failed: $pRes. Please verify mailbox password."];
}
