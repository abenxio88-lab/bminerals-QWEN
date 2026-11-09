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

/**
 * Master catalog of all minerals and commodities produced and exported by Balochistan Minerals.
 * Structured by commercial category for UI grouping, filtering, and reporting.
 */
function getCommodityCatalog() {
    return [
        'Metallic Ores & Concentrates' => [
            'Chromite' => [
                'name'    => 'Chromite Ore',
                'badge'   => '40%-54% Cr2O3',
                'aliases' => ['Chromite', 'Chrome'],
                'category'=> 'metallic',
            ],
            'Antimony' => [
                'name'    => 'Antimony Ore (Stibnite)',
                'badge'   => '40%-65% Sb',
                'aliases' => ['Antimony', 'Stibnite'],
                'category'=> 'metallic',
            ],
            'Copper' => [
                'name'    => 'Copper Ore',
                'badge'   => '18%-32% Cu',
                'aliases' => ['Copper', 'Cu '],
                'category'=> 'metallic',
            ],
            'Iron' => [
                'name'    => 'Iron Ore (Magnetite / Hematite)',
                'badge'   => '60%-64% Fe Basis',
                'aliases' => ['Iron', 'Magnetite', 'Hematite'],
                'category'=> 'metallic',
            ],
            'Manganese' => [
                'name'    => 'Manganese Ore',
                'badge'   => '38%-48% Mn',
                'aliases' => ['Manganese'],
                'category'=> 'metallic',
            ],
            'Bauxite' => [
                'name'    => 'Bauxite Ore',
                'badge'   => '48%-56% Al2O3',
                'aliases' => ['Bauxite'],
                'category'=> 'metallic',
            ],
            'Lead-Zinc' => [
                'name'    => 'Lead & Zinc Ore',
                'badge'   => 'Galena & Sphalerite',
                'aliases' => ['Lead', 'Zinc'],
                'category'=> 'metallic',
            ],
        ],
        'Industrial & Drilling Minerals' => [
            'Barite' => [
                'name'    => 'Barite (Barytes / Drilling API 13A)',
                'badge'   => '4.10-4.25+ SG',
                'aliases' => ['Barite', 'Baryte'],
                'category'=> 'industrial',
            ],
            'Fluorite' => [
                'name'    => 'Fluorite / Fluorspar',
                'badge'   => '75%-97% CaF2',
                'aliases' => ['Fluorite', 'Fluorspar', 'Fluor'],
                'category'=> 'industrial',
            ],
            'Gypsum' => [
                'name'    => 'Gypsum Ore',
                'badge'   => '90%-96% CaSO4·2H2O',
                'aliases' => ['Gypsum'],
                'category'=> 'industrial',
            ],
            'Magnesite' => [
                'name'    => 'Magnesite',
                'badge'   => '45%-47% MgO',
                'aliases' => ['Magnesite'],
                'category'=> 'industrial',
            ],
        ],
        'Energy Minerals & Solid Fuels' => [
            'Coal' => [
                'name'    => 'Balochistan Coal',
                'badge'   => '5,500-6,800 kcal/kg Steam Coal',
                'aliases' => ['Coal'],
                'category'=> 'energy',
            ],
        ],
        'Natural Stones & Dimensional Blocks' => [
            'Marble' => [
                'name'    => 'Marble (Balochistan & Iranian)',
                'badge'   => 'Blocks & Slabs',
                'aliases' => ['Marble', 'Silver Steam'],
                'category'=> 'stone',
            ],
            'Onyx' => [
                'name'    => 'Onyx Stone',
                'badge'   => 'Dark & Multi-Green Onyx',
                'aliases' => ['Onyx'],
                'category'=> 'stone',
            ],
            'Granite' => [
                'name'    => 'Granite Stone',
                'badge'   => 'Dimensional Slabs',
                'aliases' => ['Granite'],
                'category'=> 'stone',
            ],
        ],
    ];
}

/**
 * Returns complete catalog merged with any custom mineral values found dynamically in DB.
 */
function getAllFilterableCommodities($pdo = null) {
    $catalog = getCommodityCatalog();
    if ($pdo === null) {
        $pdo = getDB();
    }
    if (!$pdo) {
        return $catalog;
    }

    try {
        $knownAliases = [];
        foreach ($catalog as $group => $items) {
            foreach ($items as $key => $meta) {
                foreach ($meta['aliases'] as $alias) {
                    $knownAliases[] = strtolower($alias);
                }
            }
        }

        $extra = [];
        $queries = [
            "SELECT DISTINCT mineral_name FROM mineral_prices WHERE mineral_name IS NOT NULL AND mineral_name != ''",
            "SELECT DISTINCT target_mineral FROM buyer_prospects WHERE target_mineral IS NOT NULL AND target_mineral != ''",
            "SELECT DISTINCT mineral_name FROM stockpiles WHERE mineral_name IS NOT NULL AND mineral_name != ''",
            "SELECT DISTINCT mineral_type FROM inquiries WHERE mineral_type IS NOT NULL AND mineral_type != '' AND mineral_type != 'General Mineral'"
        ];

        foreach ($queries as $q) {
            $stmt = $pdo->query($q);
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $val = trim($row[0]);
                if (!empty($val)) {
                    $extra[] = $val;
                }
            }
        }

        $custom = [];
        foreach (array_unique($extra) as $item) {
            $matched = false;
            $low = strtolower($item);
            foreach ($knownAliases as $alias) {
                if (strpos($low, $alias) !== false) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $custom[$item] = [
                    'name'    => $item,
                    'badge'   => 'Custom Asset',
                    'aliases' => [$item],
                    'category'=> 'custom',
                ];
            }
        }

        if (!empty($custom)) {
            $catalog['Additional Commercial Minerals'] = $custom;
        }
    } catch (Exception $e) {
        // fail-soft
    }

    return $catalog;
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
