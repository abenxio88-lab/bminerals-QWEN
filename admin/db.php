<?php
/**
 * Balochistan Minerals - Database Connection & Core Utilities
 * Smart Environment Auto-Detection (Local XAMPP vs Hostinger Production)
 */

// Enforce Pakistan Standard Time (PKT, UTC+5) across the entire application
date_default_timezone_set('Asia/Karachi');

$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['127.0.0.1:8000', 'localhost:8000', '127.0.0.1', 'localhost']) 
        || (php_sapi_name() === 'cli-server')
        || (php_sapi_name() === 'cli')
        || (strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false)
        || (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false);

if ($isLocal) {
    // ==========================================
    // 💻 LOCAL XAMPP CONFIGURATION
    // ==========================================
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'balochistan_minerals');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // ==========================================
    // 🌐 HOSTINGER PRODUCTION CONFIGURATION
    // (Replace with your Hostinger DB details when deploying live)
    // ==========================================
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'u123456_bm_db');
    define('DB_USER', 'u123456_bm_user');
    define('DB_PASS', 'YOUR_STRONG_HOSTINGER_PASSWORD');
}

// ==========================================
// 📬 CORPORATE INBOUND WEBMAIL CONFIGURATION
// (Syncs emails sent to sales@ and info@ into Admin Portal)
// ==========================================
define('MAIL_IMAP_HOST', 'mail.balochistanminerals.com');
define('MAIL_IMAP_PORT', 993);
define('MAIL_IMAP_USER', 'sales@balochistanminerals.com');
define('MAIL_IMAP_PASS', ''); // Set your Hostinger email account password here to enable live mail polling

define('DB_CHARSET', 'utf8mb4');

// Company Global Details
define('COMPANY_NAME', 'Balochistan Minerals (Pvt) Ltd');
define('COMPANY_PHONE', '+923348888104');
define('COMPANY_EMAIL', 'sales@balochistanminerals.com');
define('COMPANY_DOMAIN', 'https://balochistanminerals.com');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET time_zone = '+05:00'");
        } catch (PDOException $e) {
            // Return null if credentials fail
            error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

/**
 * Format international phone number for WhatsApp wa.me links
 * Strips whitespace, dashes, plus signs, brackets
 */
function cleanPhoneForWhatsApp($phone) {
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($cleaned, '0') === 0 && strlen($cleaned) === 11) {
        $cleaned = '92' . substr($cleaned, 1);
    }
    return $cleaned;
}

/**
 * Generate a pre-filled direct WhatsApp link
 */
function generateWhatsAppLink($buyerPhone, $message) {
    $clean = cleanPhoneForWhatsApp($buyerPhone);
    return 'https://wa.me/' . $clean . '?text=' . urlencode($message);
}

/**
 * Safe string sanitation
 */
function cleanInput($data) {
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}
