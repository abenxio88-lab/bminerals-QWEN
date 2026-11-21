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
    // Loaded from server-only db-config.php (outside public_html & Git)
    // ==========================================
    $serverConfigPaths = [
        dirname(__DIR__, 2) . '/db-config.php', // Recommended: /domains/<domain>/db-config.php
        __DIR__ . '/db-config.php',             // Fallback: inside admin/ (excluded from Git & FTP)
    ];
    foreach ($serverConfigPaths as $configPath) {
        if (is_file($configPath)) {
            require_once $configPath;
            break;
        }
    }
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_NAME')) define('DB_NAME', '');
    if (!defined('DB_USER')) define('DB_USER', '');
    if (!defined('DB_PASS')) define('DB_PASS', '');
}

// ==========================================
// 📬 CORPORATE INBOUND WEBMAIL CONFIGURATION
// (Syncs emails sent to sales@, info@ & support@ into Admin Portal)
// ==========================================
if (!defined('MAIL_IMAP_HOST')) define('MAIL_IMAP_HOST', 'imap.hostinger.com');
if (!defined('MAIL_IMAP_PORT')) define('MAIL_IMAP_PORT', 993);
if (!defined('MAIL_IMAP_USER')) define('MAIL_IMAP_USER', 'sales@balochistanminerals.com');
if (!defined('MAIL_IMAP_PASS')) define('MAIL_IMAP_PASS', ''); // Defined in server-only db-config.php
if (!defined('INFO_MAIL_USER')) define('INFO_MAIL_USER', 'info@balochistanminerals.com');
if (!defined('INFO_MAIL_PASS')) define('INFO_MAIL_PASS', ''); // Defined in server-only db-config.php
if (!defined('SUPPORT_MAIL_USER')) define('SUPPORT_MAIL_USER', 'support@balochistanminerals.com');
if (!defined('SUPPORT_MAIL_PASS')) define('SUPPORT_MAIL_PASS', ''); // Defined in server-only db-config.php

if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

require_once __DIR__ . '/spam-shield.php';

/**
 * Unified catalog of corporate Hostinger mailboxes supported in the Admin Trade Desk.
 */
function getCorporateMailboxOptions() {
    $salesUser   = defined('MAIL_IMAP_USER') ? MAIL_IMAP_USER : 'sales@balochistanminerals.com';
    $infoUser    = defined('INFO_MAIL_USER') ? INFO_MAIL_USER : 'info@balochistanminerals.com';
    $supportUser = defined('SUPPORT_MAIL_USER') ? SUPPORT_MAIL_USER : 'support@balochistanminerals.com';

    return [
        strtolower($salesUser) => [
            'user'  => $salesUser,
            'short' => 'sales@',
            'label' => $salesUser . ' (Export Desk)',
            'pass'  => defined('MAIL_IMAP_PASS') ? MAIL_IMAP_PASS : '',
        ],
        strtolower($infoUser) => [
            'user'  => $infoUser,
            'short' => 'info@',
            'label' => $infoUser . ' (General Operations)',
            'pass'  => defined('INFO_MAIL_PASS') ? INFO_MAIL_PASS : '',
        ],
        strtolower($supportUser) => [
            'user'  => $supportUser,
            'short' => 'support@',
            'label' => $supportUser . ' (Client Support)',
            'pass'  => defined('SUPPORT_MAIL_PASS') ? SUPPORT_MAIL_PASS : '',
        ],
    ];
}

/**
 * Matches a corporate mailbox address inside a source/header string, falling back cleanly.
 */
function matchCorporateMailboxFromString($haystack, $default = 'sales@balochistanminerals.com') {
    $text = strtolower((string)$haystack);
    foreach (getCorporateMailboxOptions() as $emailKey => $meta) {
        if (strpos($text, $emailKey) !== false || strpos($text, $meta['short']) !== false) {
            return $meta['user'];
        }
    }
    return $default;
}

/**
 * Returns configured corporate mailboxes (user => password) from secrets or mail-config.php.
 */
function getConfiguredMailboxes() {
    $mailConfigFile = __DIR__ . '/mail-config.php';
    if (is_file($mailConfigFile)) {
        include_once $mailConfigFile;
    }

    $boxes = [];
    foreach (getCorporateMailboxOptions() as $emailKey => $meta) {
        $boxes[$emailKey] = [
            'user' => $meta['user'],
            'pass' => $meta['pass'],
        ];
    }

    if (defined('BM_SMTP_USER') && BM_SMTP_USER !== '') {
        $smtpKey  = strtolower(BM_SMTP_USER);
        $smtpPass = defined('BM_SMTP_PASS') && BM_SMTP_PASS !== ''
            ? BM_SMTP_PASS
            : ($_SESSION['bm_mail_pass'] ?? '');
        if ($smtpPass !== '') {
            $boxes[$smtpKey] = [
                'user' => BM_SMTP_USER,
                'pass' => $smtpPass,
            ];
        }
    }

    return $boxes;
}

/**
 * Matches an inquiry mineral name against active mineral_prices benchmarks cleanly.
 */
function getMineralBenchmarkForType($mineralType) {
    static $cachedRows = null;
    if ($cachedRows === null) {
        $cachedRows = [];
        $pdo = getDB();
        if ($pdo) {
            try {
                $cachedRows = $pdo->query("SELECT mineral_name, grade_name, purity_spec, price_low_usd, price_high_usd, unit, delivery_terms FROM mineral_prices WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $cachedRows = [];
            }
        }
    }

    $needle = strtolower(trim((string)$mineralType));
    if ($needle !== '' && !empty($cachedRows)) {
        // 1. Exact or strong substring match
        foreach ($cachedRows as $r) {
            $m = strtolower(trim((string)($r['mineral_name'] ?? '')));
            if ($m !== '' && ($m === $needle || strpos($needle, $m) !== false || strpos($m, $needle) !== false)) {
                return $r;
            }
        }
        // 2. First keyword prefix match (e.g. "Marble (Balochistan & Iranian)" -> "Marble")
        $firstWord = preg_split('/[\s\(,&\/]+/', $needle)[0] ?? '';
        if (strlen($firstWord) >= 4) {
            foreach ($cachedRows as $r) {
                $m = strtolower(trim((string)($r['mineral_name'] ?? '')));
                if ($m !== '' && strpos($m, $firstWord) !== false) {
                    return $r;
                }
            }
        }
    }

    return null;
}

/**
 * Cross-request schema version marker in sys_get_temp_dir() to eliminate redundant DDL/SHOW COLUMNS per HTTP hit.
 */
function getSchemaBootFlagPath($key) {
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $name = defined('DB_NAME') ? DB_NAME : '';
    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'bm_schema_' . md5($host . '|' . $name . '|' . $key) . '.ok';
}

function isSchemaBootstrapped($key) {
    $name = defined('DB_NAME') ? DB_NAME : '';
    if ($name === '') return false;
    return is_file(getSchemaBootFlagPath($key));
}

function markSchemaBootstrapped($key) {
    $name = defined('DB_NAME') ? DB_NAME : '';
    if ($name === '') return;
    @file_put_contents(getSchemaBootFlagPath($key), '1');
}

/**
 * Ensures the persistent admin_notifications table exists.
 */
function ensureNotificationsTable($pdo) {
    static $ensured = false;
    if ($ensured || !$pdo) return;
    if (isSchemaBootstrapped('notif_v1')) {
        $ensured = true;
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `type` ENUM('info', 'success', 'warning', 'error') NOT NULL DEFAULT 'info',
            `category` VARCHAR(40) NOT NULL DEFAULT 'system',
            `title` VARCHAR(180) NOT NULL,
            `message` TEXT NOT NULL,
            `inquiry_id` INT NULL,
            `link_url` VARCHAR(255) NULL,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_is_read` (`is_read`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        markSchemaBootstrapped('notif_v1');
        $ensured = true;
    } catch (Exception $e) {
        error_log("Notification table ensure notice: " . $e->getMessage());
    }
}

/**
 * Persists an admin notification in MySQL so alerts/errors remain viewable in the Notification Center.
 */
function logAdminNotification($pdo, $type, $category, $title, $message, $inquiryId = null, $linkUrl = null, $dedupMinutes = 0, $createdAt = null) {
    if (!$pdo) return false;
    $allowed = ['info', 'success', 'warning', 'error'];
    $safeType = in_array($type, $allowed, true) ? $type : 'info';
    $safeCat  = substr((string)$category, 0, 40) ?: 'system';
    $safeTitle = substr((string)$title, 0, 180) ?: 'Notification';
    $safeMsg  = trim((string)$message);
    if ($safeMsg === '') return false;

    try {
        ensureNotificationsTable($pdo);

        if ((int)$dedupMinutes > 0) {
            $chk = $pdo->prepare("SELECT id FROM admin_notifications
                WHERE is_read = 0 AND category = ? AND title = ? AND message = ?
                  AND created_at >= (NOW() - INTERVAL ? MINUTE)
                ORDER BY id DESC LIMIT 1");
            $chk->execute([$safeCat, $safeTitle, $safeMsg, (int)$dedupMinutes]);
            $existingId = $chk->fetchColumn();
            if ($existingId) {
                $upd = $pdo->prepare("UPDATE admin_notifications SET created_at = NOW() WHERE id = ?");
                $upd->execute([$existingId]);
                return true;
            }
        }

        if (!empty($createdAt)) {
            $ins = $pdo->prepare("INSERT INTO admin_notifications (type, category, title, message, inquiry_id, link_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$safeType, $safeCat, $safeTitle, $safeMsg, $inquiryId ?: null, $linkUrl ?: null, $createdAt]);
        } else {
            $ins = $pdo->prepare("INSERT INTO admin_notifications (type, category, title, message, inquiry_id, link_url) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->execute([$safeType, $safeCat, $safeTitle, $safeMsg, $inquiryId ?: null, $linkUrl ?: null]);
        }

        // Keep notification history lean (latest 150 records by timestamp)
        $pdo->exec("DELETE FROM admin_notifications WHERE id NOT IN (SELECT id FROM (SELECT id FROM admin_notifications ORDER BY created_at DESC, id DESC LIMIT 150) AS keep_rows)");
        return true;
    } catch (Exception $e) {
        error_log("logAdminNotification error: " . $e->getMessage());
        return false;
    }
}

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

/**
 * Master catalog of all minerals and commodities produced and exported by Balochistan Minerals.
 * Exactly mirrors the 4 product showcase categories and cards from products.html in exact order.
 */
function getCommodityCatalog() {
    return [
        'Metallic Minerals' => [
            'Copper' => [
                'name'    => 'Copper Lumps',
                'badge'   => '2% - 10%+ Cu Flagship Export',
                'aliases' => ['Copper', 'Copper Lumps', 'Cu'],
                'category'=> 'metallic',
            ],
            'Chromite' => [
                'name'    => 'Chrome Lumps',
                'badge'   => '12% - 52% Cr2O3 Metallurgical',
                'aliases' => ['Chromite', 'Chrome', 'Chrome Lumps', 'Chromite Ore', 'Cr2O3'],
                'category'=> 'metallic',
            ],
            'Iron-Ore' => [
                'name'    => 'Iron Ore Lumps',
                'badge'   => '60% - 64% Fe Basis',
                'aliases' => ['Iron', 'Iron Ore', 'Iron Ore Lumps', 'Magnetite', 'Hematite', 'Fe'],
                'category'=> 'metallic',
            ],
            'Antimony' => [
                'name'    => 'Antimony Lumps',
                'badge'   => '40% - 65% Sb Grade',
                'aliases' => ['Antimony', 'Antimony Lumps', 'Stibnite', 'Sb'],
                'category'=> 'metallic',
            ],
            'Manganese' => [
                'name'    => 'Manganese Ore',
                'badge'   => '38% - 48% Mn Basis',
                'aliases' => ['Manganese', 'Manganese Ore', 'Mn'],
                'category'=> 'metallic',
            ],
            'Bauxite' => [
                'name'    => 'Bauxite Ore',
                'badge'   => '48% - 56% Al2O3 Refractory',
                'aliases' => ['Bauxite', 'Bauxite Ore', 'Al2O3'],
                'category'=> 'metallic',
            ],
            'Lead-Zinc' => [
                'name'    => 'Lead & Zinc Ore',
                'badge'   => 'Galena & Sphalerite Mixed Sulfide',
                'aliases' => ['Lead', 'Zinc', 'Lead & Zinc', 'Lead and Zinc', 'Lead & Zinc Ore'],
                'category'=> 'metallic',
            ],
        ],
        'Industrial Minerals' => [
            'Barite' => [
                'name'    => 'Barite',
                'badge'   => 'API 13A SG 4.20+ / Chemical',
                'aliases' => ['Barite', 'Baryte', 'Drilling & Industrial Barite', 'API Grade Barite', 'API 13A'],
                'category'=> 'industrial',
            ],
            'Fluorite' => [
                'name'    => 'Fluorite',
                'badge'   => 'CaF2 Metallurgical & Ceramic',
                'aliases' => ['Fluorite', 'Fluorspar', 'CaF2', 'Fluor'],
                'category'=> 'industrial',
            ],
            'Gypsum' => [
                'name'    => 'Gypsum',
                'badge'   => 'Construction & Cement Grade (90%+)',
                'aliases' => ['Gypsum', 'Gypsum Ore', 'Spintangi Gypsum', 'CaSO4'],
                'category'=> 'industrial',
            ],
            'Magnesite' => [
                'name'    => 'Magnesite',
                'badge'   => 'Raw & Calcined Magnesite',
                'aliases' => ['Magnesite', 'Raw & Calcined Magnesite', 'MgO'],
                'category'=> 'industrial',
            ],
            'Phosphate' => [
                'name'    => 'Phosphate Rock',
                'badge'   => 'Fertilizer & Chemical Feed',
                'aliases' => ['Phosphate', 'Phosphate Rock', 'P2O5'],
                'category'=> 'industrial',
            ],
        ],
        'Natural Stones & Marble' => [
            'Marble' => [
                'name'    => 'Marble',
                'badge'   => 'Commercial and premium marble',
                'aliases' => ['Marble', 'Commercial and premium marble', 'Balochistan Marble', 'Marble (Balochistan & Iranian)'],
                'category'=> 'stone',
            ],
            'White-Marble' => [
                'name'    => 'White Marble',
                'badge'   => 'Bright white marble selection',
                'aliases' => ['White Marble', 'Bright white marble selection', 'Bright white marble'],
                'category'=> 'stone',
            ],
            'Persian-Silk-Tundra-Grey' => [
                'name'    => 'Persian Silk Tundra Grey',
                'badge'   => 'Iranian Marble Slabs & Tiles',
                'aliases' => ['Persian Silk Tundra Grey', 'Tundra Grey', 'Persian Silk', 'Iranian Marble'],
                'category'=> 'stone',
            ],
            'Persian-Silk-Block' => [
                'name'    => 'Persian Silk Block',
                'badge'   => 'Raw processing blocks',
                'aliases' => ['Persian Silk Block', 'Persian Silk Blocks', 'Raw processing blocks'],
                'category'=> 'stone',
            ],
            'Pietra-Grey-Block' => [
                'name'    => 'Pietra Grey Block',
                'badge'   => 'Premium Dark Marble Blocks',
                'aliases' => ['Pietra Grey Block', 'Pietra Grey Blocks', 'Pietra Grey', 'Dark Marble'],
                'category'=> 'stone',
            ],
            'Pietra-Grey-Slab' => [
                'name'    => 'Pietra Grey Slab',
                'badge'   => 'Finished Slab Selection',
                'aliases' => ['Pietra Grey Slab', 'Pietra Grey Slabs', 'Finished Slab'],
                'category'=> 'stone',
            ],
            'Irani-Marble' => [
                'name'    => 'Iranian Marble',
                'badge'   => 'Silver Steam Premium White Slabs & Blocks',
                'aliases' => ['Iranian Marble', 'Silver Steam', 'Silver Steam Marble', 'Silver Steam - a luminous white'],
                'category'=> 'stone',
            ],
        ],
        'Energy Minerals' => [
            'Coal-Sorange-Degari' => [
                'name'    => 'Sorange-Degari',
                'badge'   => 'Quetta coalfield',
                'aliases' => ['Sorange-Degari', 'Sorange', 'Degari', 'Quetta coalfield', 'Coal', 'Balochistan Coal', 'Steam Coal'],
                'category'=> 'energy',
            ],
            'Coal-Mach-Anjira' => [
                'name'    => 'Mach-Anjira',
                'badge'   => 'Mach corridor coal sourcing',
                'aliases' => ['Mach-Anjira', 'Mach', 'Anjira', 'Mach corridor'],
                'category'=> 'energy',
            ],
        ],
    ];
}

/**
 * Returns the exact commodities catalog matching products.html.
 */
function getAllFilterableCommodities($pdo = null) {
    return getCommodityCatalog();
}

/**
 * Helper to build SQL WHERE clause and params for mineral filtering.
 * Searches across all aliases (e.g. 'Fluorite' -> checks 'Fluorite', 'Fluorspar', 'Fluor').
 */
function buildMineralFilterClause($filterMineral, $column = 'mineral_type') {
    if (empty($filterMineral)) {
        return ['', []];
    }
    $catalog = getAllFilterableCommodities();
    $aliases = [];
    foreach ($catalog as $group => $items) {
        if (isset($items[$filterMineral])) {
            $aliases = $items[$filterMineral]['aliases'];
            break;
        }
    }
    if (empty($aliases)) {
        $aliases = [$filterMineral];
    }

    $clauses = [];
    $params = [];
    foreach ($aliases as $alias) {
        $clauses[] = "$column LIKE ?";
        $params[] = "%" . $alias . "%";
    }
    return [" AND (" . implode(" OR ", $clauses) . ")", $params];
}

/**
 * Render standard HTML <option> items grouped by category with badges.
 */
function renderCommodityOptions($selectedKey = '', $includeEmpty = true, $emptyLabel = 'All Commodities') {
    $catalog = getAllFilterableCommodities();
    $html = '';
    if ($includeEmpty) {
        $html .= '<option value="">' . htmlspecialchars($emptyLabel) . '</option>' . "\n";
    }
    foreach ($catalog as $group => $items) {
        $html .= '  <optgroup label="' . htmlspecialchars($group) . '">' . "\n";
        foreach ($items as $key => $meta) {
            $sel = ($selectedKey === $key || $selectedKey === $meta['name']) ? ' selected' : '';
            $badge = !empty($meta['badge']) ? ' (' . $meta['badge'] . ')' : '';
            $html .= '    <option value="' . htmlspecialchars($key) . '"' . $sel . '>' . htmlspecialchars($meta['name'] . $badge) . '</option>' . "\n";
        }
        $html .= '  </optgroup>' . "\n";
    }
    return $html;
}

/**
 * Render HTML <datalist> for instant autocomplete on text input fields.
 */
function renderCommodityDatalist($id = 'mineralList') {
    $catalog = getAllFilterableCommodities();
    $html = '<datalist id="' . htmlspecialchars($id) . '">' . "\n";
    foreach ($catalog as $group => $items) {
        foreach ($items as $key => $meta) {
            $html .= '  <option value="' . htmlspecialchars($meta['name']) . '">' . "\n";
        }
    }
    $html .= '</datalist>' . "\n";
    return $html;
}
