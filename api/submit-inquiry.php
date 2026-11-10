<?php
/**
 * Balochistan Minerals - Inbound Lead & RFQ Processor
 * Replaces Web3Forms with local MySQL logging, email dispatch, and instant WhatsApp linking.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../admin/db.php';
require_once __DIR__ . '/../admin/flag-helper.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// 1. Anti-Bot Honeypot Check
if (!empty($_POST['botcheck'])) {
    // Bot filled hidden field, silently succeed without storing spam
    echo json_encode(['success' => true, 'message' => 'Inquiry received.']);
    exit;
}

// 2. Extract and Sanitize Inputs
$name     = cleanInput($_POST['name'] ?? '');
$company  = cleanInput($_POST['company'] ?? '');
$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone    = cleanInput($_POST['phone'] ?? $_POST['whatsapp'] ?? '');
$inqType  = cleanInput($_POST['inquiry_type'] ?? 'Product Quote');
$recipient = cleanInput($_POST['recipient'] ?? '');
$source   = cleanInput($_POST['source'] ?? 'Website Form');
if (!empty($recipient) && strpos($recipient, '@') !== false) {
    $source = $recipient;
} elseif ($source === 'Website Form') {
    $source = 'Website Form ➔ sales@balochistanminerals.com';
}
$mineral  = cleanInput($_POST['mineral'] ?? $_POST['mineral_type'] ?? 'General Mineral');
$quantity = cleanInput($_POST['quantity'] ?? '');
$port     = cleanInput($_POST['destination_port'] ?? 'FOB Karachi');
$message  = cleanInput($_POST['message'] ?? '');
$rawCountry = cleanInput($_POST['country'] ?? '');
$country  = detectCountryName($rawCountry, $email, $phone, $company, $message);

if (empty($name) || empty($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please provide your name and email address.']);
    exit;
}

// 3. Save to MySQL Database
$leadId = 0;
$pdo = getDB();

if ($pdo !== null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $pdo->prepare('INSERT INTO inquiries 
            (buyer_name, company_name, country, email, whatsapp, inquiry_type, source, mineral_type, quantity, destination_port, message, status, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "New", ?)');
        $stmt->execute([$name, $company, $country, $email, $phone, $inqType, $source, $mineral, $quantity, $port, $message, $ip]);
        $leadId = (int)$pdo->lastInsertId();

        // Mirror website direct-mailbox messages into Email Desk archive too
        $targetBox = (strpos($recipient, '@balochistanminerals.com') !== false)
            ? strtolower($recipient)
            : 'sales@balochistanminerals.com';
        if (strpos($source, '@balochistanminerals.com') !== false || stripos($inqType, 'Direct') !== false) {
            $emStmt = $pdo->prepare('INSERT INTO email_messages 
                (inquiry_id, direction, from_email, to_email, subject, body_text, status, message_id) 
                VALUES (?, "inbound", ?, ?, ?, ?, "Received", ?)');
            $emMsgId = '<web-' . ($leadId ?: uniqid()) . '@balochistanminerals.com>';
            $emSubject = 'Website Inquiry: ' . $mineral . ($company ? ' (' . $company . ')' : '');
            $emStmt->execute([$leadId, $email, $targetBox, $emSubject, $message ?: ('RFQ submitted for ' . $mineral), $emMsgId]);
        }
    } catch (Exception $e) {
        // Fallback silently if DB error to prevent breaking user submission
        error_log("DB Inquiry Error: " . $e->getMessage());
    }
}

// 4. Generate Pre-Filled Direct WhatsApp Link to Balochistan Minerals Export Desk (+923348888104)
$waDeskPhone = '923348888104';
$prefilledBuyerText = "Hello Balochistan Minerals, I have just submitted an RFQ on your website.\n\n"
                    . "• Name: " . $name . ($company ? " (" . $company . ")" : "") . "\n"
                    . "• Mineral: " . $mineral . ($quantity ? " - " . $quantity : "") . "\n"
                    . "• Email: " . $email . "\n"
                    . "• Inquiry: " . substr($message, 0, 150);

$ownerWhatsAppUrl = 'https://wa.me/' . $waDeskPhone . '?text=' . urlencode($prefilledBuyerText);

// 5. Send Alert Email to Selected Corporate Mailbox (info@ or sales@)
$to = (strpos($recipient, '@balochistanminerals.com') !== false) ? strtolower($recipient) : COMPANY_EMAIL;
$subject = "🔥 New Website RFQ #" . ($leadId ?: 'Web') . ": " . $mineral . " from " . $name;
$emailBody = "New Mineral Inquiry Received from BalochistanMinerals.com\n\n"
           . "Buyer Name: $name\n"
           . "Company: " . ($company ?: 'N/A') . "\n"
           . "Email: $email\n"
           . "Phone / WhatsApp: $phone\n"
           . "Mineral: $mineral\n"
           . "Quantity: $quantity\n"
           . "Destination Port: $port\n\n"
           . "Message:\n$message\n\n"
           . "---\n"
           . "View & Manage in Trade Desk Portal: https://balochistanminerals.com/admin/";

$headers = "From: Balochistan Minerals Web <" . COMPANY_EMAIL . ">\r\n"
         . "Reply-To: $email\r\n"
         . "X-Mailer: PHP/" . phpversion();

@mail($to, $subject, $emailBody, $headers);

// 6. Return JSON Response
echo json_encode([
    'success'      => true,
    'message'      => 'Your inquiry has been successfully transmitted to our Marketing & Export Department.',
    'lead_id'      => $leadId,
    'whatsapp_url' => $ownerWhatsAppUrl
]);
