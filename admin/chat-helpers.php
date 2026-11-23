<?php
/**
 * Balochistan Minerals - Real-Time Multi-Agent Live Chat & Handover Engine
 * Core database persistence, buyer intelligence, geolocation, and routing utilities.
 */

if (!defined('CHAT_ONLINE_THRESHOLD_MINUTES')) {
    define('CHAT_ONLINE_THRESHOLD_MINUTES', 5);
}

/**
 * Automatically creates or upgrades chat tables in MySQL.
 */
function ensureLiveChatSchema(PDO $pdo): void {
    static $bootstrapped = false;
    if ($bootstrapped) return;

    $pdo->exec("CREATE TABLE IF NOT EXISTS `chat_sessions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `session_token` VARCHAR(64) NOT NULL UNIQUE,
        `visitor_name` VARCHAR(100) NOT NULL DEFAULT 'Guest Buyer',
        `visitor_email` VARCHAR(150) DEFAULT NULL,
        `visitor_phone` VARCHAR(50) DEFAULT NULL,
        `visitor_ip` VARCHAR(45) DEFAULT NULL,
        `country` VARCHAR(100) DEFAULT 'International',
        `country_code` VARCHAR(10) DEFAULT 'XX',
        `city` VARCHAR(100) DEFAULT NULL,
        `timezone` VARCHAR(64) DEFAULT NULL,
        `current_page` VARCHAR(255) DEFAULT NULL,
        `mineral_interest` VARCHAR(100) DEFAULT NULL,
        `internal_notes` TEXT DEFAULT NULL,
        `status` ENUM('bot', 'waiting_human', 'agent_active', 'closed') NOT NULL DEFAULT 'bot',
        `assigned_admin_id` INT DEFAULT NULL,
        `assigned_admin_name` VARCHAR(100) DEFAULT NULL,
        `last_message` TEXT DEFAULT NULL,
        `last_message_sender` VARCHAR(50) DEFAULT NULL,
        `last_message_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `unread_visitor_count` INT NOT NULL DEFAULT 0,
        `unread_admin_count` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_token` (`session_token`),
        INDEX `idx_status` (`status`),
        INDEX `idx_last_msg` (`last_message_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `chat_messages` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `session_id` INT NOT NULL,
        `sender_type` ENUM('bot', 'visitor', 'agent', 'system') NOT NULL,
        `sender_name` VARCHAR(100) NOT NULL,
        `message_text` TEXT NOT NULL,
        `is_read` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_session_id` (`session_id`),
        INDEX `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Idempotent column upgrades for live databases
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `chat_sessions` LIKE 'timezone'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `chat_sessions` ADD COLUMN `timezone` VARCHAR(64) DEFAULT NULL AFTER `city`");
        }
        $cols = $pdo->query("SHOW COLUMNS FROM `chat_sessions` LIKE 'country_code'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `chat_sessions` ADD COLUMN `country_code` VARCHAR(10) DEFAULT 'XX' AFTER `country`");
        }
        $cols = $pdo->query("SHOW COLUMNS FROM `chat_sessions` LIKE 'internal_notes'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `chat_sessions` ADD COLUMN `internal_notes` TEXT DEFAULT NULL AFTER `mineral_interest`");
        }

        // Migrate any legacy generic 'Guest Buyer' labels to distinguishable 'Buyer #XXXX'
        $pdo->exec("UPDATE `chat_sessions` SET `visitor_name` = CONCAT('Buyer #', 1000 + `id`) WHERE `visitor_name` = 'Guest Buyer' OR `visitor_name` = '' OR `visitor_name` IS NULL");
    } catch (Exception $e) {
        // Schema check suppressed
    }

    $bootstrapped = true;
}

/**
 * Returns number of trade desk officers currently active in the Admin Portal.
 */
function countOnlineTradeDeskOfficers(?PDO $pdo, int $withinMinutes = CHAT_ONLINE_THRESHOLD_MINUTES): int {
    if (!$pdo) return 0;
    try {
        ensureLiveChatSchema($pdo);
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM `admin_sessions` WHERE is_revoked = 0 AND last_seen_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $stmt->execute([$withinMinutes]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Returns active officer list with names and roles.
 */
function getActiveTradeDeskOfficers(?PDO $pdo, int $withinMinutes = CHAT_ONLINE_THRESHOLD_MINUTES): array {
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT u.id, u.username, u.full_name, u.role, s.last_seen_at, s.current_page
            FROM `admin_sessions` s
            JOIN `admin_users` u ON u.id = s.user_id
            WHERE s.is_revoked = 0 AND s.last_seen_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY s.last_seen_at DESC");
        $stmt->execute([$withinMinutes]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Returns country metadata (flag emoji, standard name, ISO code) by ISO-3166 code.
 */
function getCountryMetaByCode(?string $code): array {
    $code = strtoupper(trim((string)$code));
    if ($code === '' || $code === 'XX' || $code === 'T1') {
        return ['name' => 'International', 'flag' => '', 'code' => 'XX'];
    }

    $dict = [
        'CN' => ['name' => 'China', 'flag' => '🇨🇳', 'code' => 'CN'],
        'AE' => ['name' => 'United Arab Emirates', 'flag' => '🇦🇪', 'code' => 'AE'],
        'US' => ['name' => 'United States', 'flag' => '🇺🇸', 'code' => 'US'],
        'PK' => ['name' => 'Pakistan', 'flag' => '🇵🇰', 'code' => 'PK'],
        'SA' => ['name' => 'Saudi Arabia', 'flag' => '🇸🇦', 'code' => 'SA'],
        'GB' => ['name' => 'United Kingdom', 'flag' => '🇬🇧', 'code' => 'GB'],
        'IN' => ['name' => 'India', 'flag' => '🇮🇳', 'code' => 'IN'],
        'TR' => ['name' => 'Turkey', 'flag' => '🇹🇷', 'code' => 'TR'],
        'OM' => ['name' => 'Oman', 'flag' => '🇴🇲', 'code' => 'OM'],
        'QA' => ['name' => 'Qatar', 'flag' => '🇶🇦', 'code' => 'QA'],
        'KW' => ['name' => 'Kuwait', 'flag' => '🇰🇼', 'code' => 'KW'],
        'BH' => ['name' => 'Bahrain', 'flag' => '🇧🇭', 'code' => 'BH'],
        'SG' => ['name' => 'Singapore', 'flag' => '🇸🇬', 'code' => 'SG'],
        'MY' => ['name' => 'Malaysia', 'flag' => '🇲🇾', 'code' => 'MY'],
        'JP' => ['name' => 'Japan', 'flag' => '🇯🇵', 'code' => 'JP'],
        'KR' => ['name' => 'South Korea', 'flag' => '🇰🇷', 'code' => 'KR'],
        'TH' => ['name' => 'Thailand', 'flag' => '🇹🇭', 'code' => 'TH'],
        'VN' => ['name' => 'Vietnam', 'flag' => '🇻🇳', 'code' => 'VN'],
        'ID' => ['name' => 'Indonesia', 'flag' => '🇮🇩', 'code' => 'ID'],
        'DE' => ['name' => 'Germany', 'flag' => '🇩🇪', 'code' => 'DE'],
        'IT' => ['name' => 'Italy', 'flag' => '🇮🇹', 'code' => 'IT'],
        'ES' => ['name' => 'Spain', 'flag' => '🇪🇸', 'code' => 'ES'],
        'FR' => ['name' => 'France', 'flag' => '🇫🇷', 'code' => 'FR'],
        'NL' => ['name' => 'Netherlands', 'flag' => '🇳🇱', 'code' => 'NL'],
        'BE' => ['name' => 'Belgium', 'flag' => '🇧🇪', 'code' => 'BE'],
        'CH' => ['name' => 'Switzerland', 'flag' => '🇨🇭', 'code' => 'CH'],
        'RU' => ['name' => 'Russia', 'flag' => '🇷🇺', 'code' => 'RU'],
        'AU' => ['name' => 'Australia', 'flag' => '🇦🇺', 'code' => 'AU'],
        'CA' => ['name' => 'Canada', 'flag' => '🇨🇦', 'code' => 'CA'],
        'BR' => ['name' => 'Brazil', 'flag' => '🇧🇷', 'code' => 'BR'],
        'ZA' => ['name' => 'South Africa', 'flag' => '🇿🇦', 'code' => 'ZA'],
        'EG' => ['name' => 'Egypt', 'flag' => '🇪🇬', 'code' => 'EG'],
        'HK' => ['name' => 'Hong Kong', 'flag' => '🇭🇰', 'code' => 'HK'],
        'TW' => ['name' => 'Taiwan', 'flag' => '🇹🇼', 'code' => 'TW'],
        'IR' => ['name' => 'Iran', 'flag' => '🇮🇷', 'code' => 'IR'],
        'IQ' => ['name' => 'Iraq', 'flag' => '🇮🇶', 'code' => 'IQ'],
        'JO' => ['name' => 'Jordan', 'flag' => '🇯🇴', 'code' => 'JO'],
        'LB' => ['name' => 'Lebanon', 'flag' => '🇱🇧', 'code' => 'LB'],
        'GR' => ['name' => 'Greece', 'flag' => '🇬🇷', 'code' => 'GR'],
        'PL' => ['name' => 'Poland', 'flag' => '🇵🇱', 'code' => 'PL'],
        'SE' => ['name' => 'Sweden', 'flag' => '🇸🇪', 'code' => 'SE'],
        'NO' => ['name' => 'Norway', 'flag' => '🇳🇴', 'code' => 'NO'],
        'MX' => ['name' => 'Mexico', 'flag' => '🇲🇽', 'code' => 'MX'],
        'BD' => ['name' => 'Bangladesh', 'flag' => '🇧🇩', 'code' => 'BD'],
        'LK' => ['name' => 'Sri Lanka', 'flag' => '🇱🇰', 'code' => 'LK'],
    ];

    if (isset($dict[$code])) {
        return $dict[$code];
    }

    if (strlen($code) === 2 && ctype_alpha($code)) {
        $flag = mb_chr(127397 + ord($code[0]), 'UTF-8') . mb_chr(127397 + ord($code[1]), 'UTF-8');
        return ['name' => $code, 'flag' => $flag, 'code' => $code];
    }

    return ['name' => 'International', 'flag' => '', 'code' => 'XX'];
}

/**
 * Infers geographic country, flag, and primary commercial hub from standard IANA client timezone.
 */
function inferGeoFromTimezone(?string $tz): array {
    $tz = trim((string)$tz);
    if ($tz === '') {
        return ['country' => 'International', 'code' => 'XX', 'city' => '', 'flag' => '', 'tz' => 'UTC'];
    }

    $tzMap = [
        'Asia/Karachi'        => ['country' => 'Pakistan', 'code' => 'PK', 'city' => 'Karachi', 'flag' => '🇵🇰'],
        'Asia/Dubai'          => ['country' => 'United Arab Emirates', 'code' => 'AE', 'city' => 'Dubai', 'flag' => '🇦🇪'],
        'Asia/Muscat'         => ['country' => 'Oman', 'code' => 'OM', 'city' => 'Muscat', 'flag' => '🇴🇲'],
        'Asia/Riyadh'         => ['country' => 'Saudi Arabia', 'code' => 'SA', 'city' => 'Riyadh', 'flag' => '🇸🇦'],
        'Asia/Qatar'          => ['country' => 'Qatar', 'code' => 'QA', 'city' => 'Doha', 'flag' => '🇶🇦'],
        'Asia/Bahrain'        => ['country' => 'Bahrain', 'code' => 'BH', 'city' => 'Manama', 'flag' => '🇧🇭'],
        'Asia/Kuwait'         => ['country' => 'Kuwait', 'code' => 'KW', 'city' => 'Kuwait City', 'flag' => '🇰🇼'],
        'Asia/Shanghai'       => ['country' => 'China', 'code' => 'CN', 'city' => 'Shanghai', 'flag' => '🇨🇳'],
        'Asia/Chongqing'      => ['country' => 'China', 'code' => 'CN', 'city' => 'Chongqing', 'flag' => '🇨🇳'],
        'Asia/Urumqi'         => ['country' => 'China', 'code' => 'CN', 'city' => 'Urumqi', 'flag' => '🇨🇳'],
        'Asia/Hong_Kong'      => ['country' => 'Hong Kong', 'code' => 'HK', 'city' => 'Hong Kong', 'flag' => '🇭🇰'],
        'Asia/Taipei'         => ['country' => 'Taiwan', 'code' => 'TW', 'city' => 'Taipei', 'flag' => '🇹🇼'],
        'Asia/Tokyo'          => ['country' => 'Japan', 'code' => 'JP', 'city' => 'Tokyo', 'flag' => '🇯🇵'],
        'Asia/Seoul'          => ['country' => 'South Korea', 'code' => 'KR', 'city' => 'Seoul', 'flag' => '🇰🇷'],
        'Asia/Singapore'      => ['country' => 'Singapore', 'code' => 'SG', 'city' => 'Singapore', 'flag' => '🇸🇬'],
        'Asia/Kolkata'        => ['country' => 'India', 'code' => 'IN', 'city' => 'Mumbai / Delhi', 'flag' => '🇮🇳'],
        'Asia/Dhaka'          => ['country' => 'Bangladesh', 'code' => 'BD', 'city' => 'Dhaka', 'flag' => '🇧🇩'],
        'Asia/Colombo'        => ['country' => 'Sri Lanka', 'code' => 'LK', 'city' => 'Colombo', 'flag' => '🇱🇰'],
        'Asia/Bangkok'        => ['country' => 'Thailand', 'code' => 'TH', 'city' => 'Bangkok', 'flag' => '🇹🇭'],
        'Asia/Jakarta'        => ['country' => 'Indonesia', 'code' => 'ID', 'city' => 'Jakarta', 'flag' => '🇮🇩'],
        'Asia/Kuala_Lumpur'   => ['country' => 'Malaysia', 'code' => 'MY', 'city' => 'Kuala Lumpur', 'flag' => '🇲🇾'],
        'Europe/Istanbul'     => ['country' => 'Turkey', 'code' => 'TR', 'city' => 'Istanbul', 'flag' => '🇹🇷'],
        'Europe/London'       => ['country' => 'United Kingdom', 'code' => 'GB', 'city' => 'London', 'flag' => '🇬🇧'],
        'Europe/Berlin'       => ['country' => 'Germany', 'code' => 'DE', 'city' => 'Berlin', 'flag' => '🇩🇪'],
        'Europe/Paris'        => ['country' => 'France', 'code' => 'FR', 'city' => 'Paris', 'flag' => '🇫🇷'],
        'Europe/Amsterdam'    => ['country' => 'Netherlands', 'code' => 'NL', 'city' => 'Amsterdam', 'flag' => '🇳🇱'],
        'Europe/Rome'         => ['country' => 'Italy', 'code' => 'IT', 'city' => 'Rome', 'flag' => '🇮🇹'],
        'Europe/Madrid'       => ['country' => 'Spain', 'code' => 'ES', 'city' => 'Madrid', 'flag' => '🇪🇸'],
        'Europe/Moscow'       => ['country' => 'Russia', 'code' => 'RU', 'city' => 'Moscow', 'flag' => '🇷🇺'],
        'America/New_York'    => ['country' => 'United States', 'code' => 'US', 'city' => 'New York', 'flag' => '🇺🇸'],
        'America/Chicago'     => ['country' => 'United States', 'code' => 'US', 'city' => 'Chicago', 'flag' => '🇺🇸'],
        'America/Denver'      => ['country' => 'United States', 'code' => 'US', 'city' => 'Denver', 'flag' => '🇺🇸'],
        'America/Los_Angeles' => ['country' => 'United States', 'code' => 'US', 'city' => 'Los Angeles', 'flag' => '🇺🇸'],
        'America/Toronto'     => ['country' => 'Canada', 'code' => 'CA', 'city' => 'Toronto', 'flag' => '🇨🇦'],
        'America/Sao_Paulo'   => ['country' => 'Brazil', 'code' => 'BR', 'city' => 'São Paulo', 'flag' => '🇧🇷'],
        'Australia/Sydney'    => ['country' => 'Australia', 'code' => 'AU', 'city' => 'Sydney', 'flag' => '🇦🇺'],
        'Australia/Melbourne' => ['country' => 'Australia', 'code' => 'AU', 'city' => 'Melbourne', 'flag' => '🇦🇺'],
        'Australia/Perth'     => ['country' => 'Australia', 'code' => 'AU', 'city' => 'Perth', 'flag' => '🇦🇺'],
        'Africa/Johannesburg' => ['country' => 'South Africa', 'code' => 'ZA', 'city' => 'Johannesburg', 'flag' => '🇿🇦'],
        'Africa/Cairo'        => ['country' => 'Egypt', 'code' => 'EG', 'city' => 'Cairo', 'flag' => '🇪🇬'],
    ];

    if (isset($tzMap[$tz])) {
        $res = $tzMap[$tz];
        $res['tz'] = $tz;
        return $res;
    }

    if (str_starts_with($tz, 'Asia/')) {
        $city = str_replace('_', ' ', explode('/', $tz)[1] ?? '');
        return ['country' => 'Asia', 'code' => 'AS', 'city' => $city, 'flag' => '🌏', 'tz' => $tz];
    }
    if (str_starts_with($tz, 'Europe/')) {
        $city = str_replace('_', ' ', explode('/', $tz)[1] ?? '');
        return ['country' => 'Europe', 'code' => 'EU', 'city' => $city, 'flag' => '🌍', 'tz' => $tz];
    }
    if (str_starts_with($tz, 'America/')) {
        $city = str_replace('_', ' ', explode('/', $tz)[1] ?? '');
        return ['country' => 'United States', 'code' => 'US', 'city' => $city, 'flag' => '🇺🇸', 'tz' => $tz];
    }
    if (str_starts_with($tz, 'Africa/')) {
        $city = str_replace('_', ' ', explode('/', $tz)[1] ?? '');
        return ['country' => 'Africa', 'code' => 'AF', 'city' => $city, 'flag' => '🌍', 'tz' => $tz];
    }
    if (str_starts_with($tz, 'Australia/')) {
        $city = str_replace('_', ' ', explode('/', $tz)[1] ?? '');
        return ['country' => 'Australia', 'code' => 'AU', 'city' => $city, 'flag' => '🇦🇺', 'tz' => $tz];
    }

    return ['country' => 'International', 'code' => 'XX', 'city' => '', 'flag' => '', 'tz' => $tz];
}

/**
 * Multi-tier visitor geolocation resolution combining Cloudflare headers, client IANA timezone, UTC offset, and locale.
 */
function resolveVisitorGeo(string $ip, ?string $cfCountry = null, ?string $cfCity = null, ?string $clientTz = null, ?string $clientLocale = null, ?int $clientOffset = null): array {
    $countryCode = 'XX';
    $countryName = 'International';
    $flag = '';
    $city = '';
    $tz = 'UTC';

    // 1. Cloudflare network geolocation header check
    if (!empty($cfCountry) && $cfCountry !== 'XX' && $cfCountry !== 'T1') {
        $meta = getCountryMetaByCode($cfCountry);
        $countryCode = $meta['code'];
        $countryName = $meta['name'];
        $flag = $meta['flag'];
        if (!empty($cfCity)) {
            $city = trim($cfCity);
        }
    }

    // 2. Client browser timezone fallback (critical for local dev, LAN testing, non-proxied IP)
    if (($countryCode === 'XX' || $countryName === 'International') && !empty($clientTz)) {
        $tzGeo = inferGeoFromTimezone($clientTz);
        if ($tzGeo['code'] !== 'XX') {
            $countryCode = $tzGeo['code'];
            $countryName = $tzGeo['country'];
            $flag = $tzGeo['flag'];
            if (empty($city) && !empty($tzGeo['city'])) {
                $city = $tzGeo['city'];
            }
        }
        $tz = $clientTz;
    }

    // 3. Client UTC timezone offset fallback (minutes, e.g. -300 = PKT UTC+5, -240 = UAE UTC+4, -480 = CST UTC+8)
    if (($countryCode === 'XX' || $countryName === 'International') && $clientOffset !== null) {
        $offsetMap = [
            -300 => ['code' => 'PK', 'country' => 'Pakistan', 'flag' => '🇵🇰', 'city' => 'Karachi', 'tz' => 'Asia/Karachi'],
            -240 => ['code' => 'AE', 'country' => 'United Arab Emirates', 'flag' => '🇦🇪', 'city' => 'Dubai', 'tz' => 'Asia/Dubai'],
            -480 => ['code' => 'CN', 'country' => 'China', 'flag' => '🇨🇳', 'city' => 'Shanghai', 'tz' => 'Asia/Shanghai'],
            -330 => ['code' => 'IN', 'country' => 'India', 'flag' => '🇮🇳', 'city' => 'Mumbai / Delhi', 'tz' => 'Asia/Kolkata'],
            -180 => ['code' => 'SA', 'country' => 'Saudi Arabia', 'flag' => '🇸🇦', 'city' => 'Riyadh', 'tz' => 'Asia/Riyadh'],
            -210 => ['code' => 'IR', 'country' => 'Iran', 'flag' => '🇮🇷', 'city' => 'Tehran', 'tz' => 'Asia/Tehran'],
            -120 => ['code' => 'TR', 'country' => 'Turkey', 'flag' => '🇹🇷', 'city' => 'Istanbul', 'tz' => 'Europe/Istanbul'],
            0    => ['code' => 'GB', 'country' => 'United Kingdom', 'flag' => '🇬🇧', 'city' => 'London', 'tz' => 'Europe/London'],
            -60  => ['code' => 'DE', 'country' => 'Germany', 'flag' => '🇩🇪', 'city' => 'Berlin', 'tz' => 'Europe/Berlin'],
            300  => ['code' => 'US', 'country' => 'United States', 'flag' => '🇺🇸', 'city' => 'New York', 'tz' => 'America/New_York'],
            360  => ['code' => 'US', 'country' => 'United States', 'flag' => '🇺🇸', 'city' => 'Chicago', 'tz' => 'America/Chicago'],
            420  => ['code' => 'US', 'country' => 'United States', 'flag' => '🇺🇸', 'city' => 'Denver', 'tz' => 'America/Denver'],
            480  => ['code' => 'US', 'country' => 'United States', 'flag' => '🇺🇸', 'city' => 'Los Angeles', 'tz' => 'America/Los_Angeles'],
        ];
        if (isset($offsetMap[$clientOffset])) {
            $countryCode = $offsetMap[$clientOffset]['code'];
            $countryName = $offsetMap[$clientOffset]['country'];
            $flag        = $offsetMap[$clientOffset]['flag'];
            if (empty($city)) $city = $offsetMap[$clientOffset]['city'];
            if ($tz === 'UTC') $tz = $offsetMap[$clientOffset]['tz'];
        }
    }

    // 4. Client browser language / locale fallback (e.g. "zh-CN", "ur-PK", "ar-AE")
    if (($countryCode === 'XX' || $countryName === 'International') && !empty($clientLocale)) {
        if (preg_match('/[a-z]{2}[-_]([A-Z]{2})/i', $clientLocale, $m)) {
            $locCode = strtoupper($m[1]);
            $meta = getCountryMetaByCode($locCode);
            if ($meta['code'] !== 'XX') {
                $countryCode = $meta['code'];
                $countryName = $meta['name'];
                $flag = $meta['flag'];
            }
        }
    }

    // 5. Localhost / Private Network LAN safety net fallback (defaults to Pakistan headquarters)
    if (($countryCode === 'XX' || $countryName === 'International') && (in_array($ip, ['127.0.0.1', '::1', 'localhost']) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.'))) {
        $countryCode = 'PK';
        $countryName = 'Pakistan';
        $flag = '🇵🇰';
        $city = 'Karachi';
        if ($tz === 'UTC') $tz = 'Asia/Karachi';
    }

    if ($tz === 'UTC' && !empty($clientTz)) {
        $tz = $clientTz;
    }

    return [
        'country_code' => $countryCode,
        'country'      => $countryName,
        'flag'         => $flag,
        'city'         => $city,
        'timezone'     => $tz,
    ];
}

/**
 * Calculates live formatted buyer local time string (e.g. "05:42 PM (CST)").
 */
function getVisitorLocalTimeStr(?string $timezone): string {
    if (empty($timezone) || $timezone === 'UTC') {
        try {
            $tzObj = new DateTimeZone('Asia/Karachi');
            $dt = new DateTime('now', $tzObj);
            return $dt->format('h:i A') . ' PKT';
        } catch (Exception $e) {
            return date('h:i A');
        }
    }
    try {
        $tzObj = new DateTimeZone($timezone);
        $dt = new DateTime('now', $tzObj);
        return $dt->format('h:i A') . ' ' . $dt->format('T');
    } catch (Exception $e) {
        return date('h:i A');
    }
}

/**
 * Auto-extracts buyer intelligence (email, phone, mineral, volume, target port, buyer name) from messages.
 */
function extractLeadIntelligenceFromText(string $text): array {
    $res = [];

    // 1. Email address extraction
    if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/', $text, $m)) {
        $res['email'] = strtolower(trim($m[0]));
    }

    // 2. Phone / WhatsApp number extraction (international + or standard local)
    if (preg_match('/(?:\+|00)[1-9]\d{0,3}[-.\s]?\(?\d{1,4}\)?[-.\s]?\d{2,5}[-.\s]?\d{3,6}|\b03\d{2}[-.\s]?\d{7}\b|\b(?:\+?86[-.\s]?)?1[3-9]\d{9}\b/', $text, $m)) {
        $cleanPhone = preg_replace('/[^\d+]/', '', $m[0]);
        if (strlen($cleanPhone) >= 9) {
            $res['phone'] = trim($m[0]);
        }
    }

    // 3. Mineral interest keyword recognition
    $lower = strtolower($text);
    if (preg_match('/\b(barite|barytes?|api\s*13a|sg\s*4\.[12]\d?)\b/i', $text)) {
        $res['mineral'] = 'Barite 4.2+ SG';
    } elseif (preg_match('/\b(chromite|chrome\s*ore|cr2o3|lumpy\s*chromite)\b/i', $text)) {
        $res['mineral'] = 'Chromite 42-52%';
    } elseif (preg_match('/\b(copper|chagai|copper\s*ore|cu\s*concentrate|copper\s*dso)\b/i', $text)) {
        $res['mineral'] = 'Chagai Copper DSO';
    } elseif (preg_match('/\b(iron\s*ore|magnetite|hematite|fe\s*6\d%?)\b/i', $text)) {
        $res['mineral'] = 'Iron Ore (62%+ Fe)';
    } elseif (preg_match('/\b(fluorite|fluorspar|caf2)\b/i', $text)) {
        $res['mineral'] = 'Fluorspar (CaF2 85%+)';
    } elseif (preg_match('/\b(marble|onyx|ziarat\s*white|black\s*and\s*gold)\b/i', $text)) {
        $res['mineral'] = 'Balochistan Marble & Onyx';
    } elseif (preg_match('/\b(manganese|mn\s*ore)\b/i', $text)) {
        $res['mineral'] = 'Manganese Ore';
    } elseif (preg_match('/\b(lithium|spodumene)\b/i', $text)) {
        $res['mineral'] = 'Lithium Ore';
    } elseif (preg_match('/\b(antimony|stibnite)\b/i', $text)) {
        $res['mineral'] = 'Antimony Ore';
    }

    // 4. Volume / Tonnage extraction
    if (preg_match('/\b(\d{1,3}(?:,\d{3})*(?:\.\d+)?|\d+)\s*(?:mt|tons?|tonnes?|containers?|teu|fcl)\b/i', $text, $m)) {
        $res['tonnage'] = trim($m[0]);
    }

    // 5. Destination port recognition
    $ports = ['Tianjin', 'Qingdao', 'Shanghai', 'Ningbo', 'Fangcheng', 'Jebel Ali', 'Mundra', 'Kandla', 'Rotterdam', 'Antwerp', 'Chittagong', 'Karachi', 'Port Qasim', 'Dammam', 'Jeddah', 'Alexandria', 'Busan', 'Tokyo'];
    foreach ($ports as $port) {
        if (stripos($text, $port) !== false) {
            $res['port'] = $port;
            break;
        }
    }

    // 6. Name / Company introduction extraction
    if (preg_match('/(?:my name is|i am|this is|name:)\s+([A-Za-z]{2,18}(?:\s+[A-Za-z]{2,18})?)(?=\s+(?:from|at|with|and|,|\.|$))/i', $text, $m)) {
        $nameCandidate = trim($m[1]);
        if (!preg_match('/^(looking|interested|inquiring|asking|checking|here|buyer)/i', $nameCandidate)) {
            $res['name'] = ucwords(strtolower($nameCandidate));
        }
    }
    if (preg_match('/(?:from|company:?|representing)\s+([A-Za-z0-9\s&.-]{2,35}?)(?=\s+(?:we|i|and|need|looking|want|,|\.|\n|$))/i', $text, $m)) {
        $companyCandidate = trim($m[1]);
        if (!preg_match('/^(pakistan|china|dubai|india|usa|the|an?)\b/i', $companyCandidate)) {
            $res['company'] = ucwords(strtolower($companyCandidate));
        }
    }

    return $res;
}

/**
 * Fetches or creates a chat session given a client token, ingesting enriched geo & telemetry.
 */
function getOrCreateChatSession(PDO $pdo, string $token, array $meta = []): array {
    ensureLiveChatSchema($pdo);
    $token = trim($token);
    if ($token === '' || strlen($token) < 16) {
        $token = bin2hex(random_bytes(16));
    }

    $stmt = $pdo->prepare("SELECT * FROM `chat_sessions` WHERE session_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    $ip          = $meta['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $page        = substr((string)($meta['page'] ?? $meta['page_url'] ?? ''), 0, 250);
    $mineral     = substr((string)($meta['mineral_interest'] ?? $meta['mineral'] ?? ''), 0, 95);
    $country     = substr((string)($meta['country'] ?? 'International'), 0, 95);
    $countryCode = substr((string)($meta['country_code'] ?? 'XX'), 0, 10);
    $city        = substr((string)($meta['city'] ?? ''), 0, 95);
    $timezone    = substr((string)($meta['timezone'] ?? $meta['client_tz'] ?? ''), 0, 60);

    // Clean page if full URL was passed
    if ($page !== '' && (str_starts_with($page, 'http://') || str_starts_with($page, 'https://'))) {
        $parsedPath = parse_url($page, PHP_URL_PATH);
        if ($parsedPath) $page = $parsedPath;
    }

    if ($session) {
        // Upgrade session with new telemetry if available
        $updates = [];
        $params = [];

        if ($page !== '' && $page !== $session['current_page']) {
            $updates[] = "current_page = ?";
            $params[] = $page;
            $session['current_page'] = $page;
        }
        if ($mineral !== '' && empty($session['mineral_interest'])) {
            $updates[] = "mineral_interest = ?";
            $params[] = $mineral;
            $session['mineral_interest'] = $mineral;
        }
        if ($country !== 'International' && ($session['country'] === 'International' || empty($session['country']))) {
            $updates[] = "country = ?";
            $params[] = $country;
            $session['country'] = $country;
        }
        if ($countryCode !== 'XX' && (empty($session['country_code']) || $session['country_code'] === 'XX')) {
            $updates[] = "country_code = ?";
            $params[] = $countryCode;
            $session['country_code'] = $countryCode;
        }
        if ($city !== '' && empty($session['city'])) {
            $updates[] = "city = ?";
            $params[] = $city;
            $session['city'] = $city;
        }
        if ($timezone !== '' && empty($session['timezone'])) {
            $updates[] = "timezone = ?";
            $params[] = $timezone;
            $session['timezone'] = $timezone;
        }
        if ($ip !== '' && empty($session['visitor_ip'])) {
            $updates[] = "visitor_ip = ?";
            $params[] = $ip;
            $session['visitor_ip'] = $ip;
        }

        // Migrate 'Guest Buyer' to unique Buyer #ID if present
        if ($session['visitor_name'] === 'Guest Buyer' || empty($session['visitor_name'])) {
            $uniqueBuyerName = 'Buyer #' . (1000 + (int)$session['id']);
            $updates[] = "visitor_name = ?";
            $params[] = $uniqueBuyerName;
            $session['visitor_name'] = $uniqueBuyerName;
        }

        if (!empty($updates)) {
            $params[] = $session['id'];
            $pdo->prepare("UPDATE `chat_sessions` SET " . implode(", ", $updates) . ", updated_at = NOW() WHERE id = ?")->execute($params);
        }

        return $session;
    }

    // Insert new session with auto-assigned unique Buyer ID
    $ins = $pdo->prepare("INSERT INTO `chat_sessions` 
        (session_token, visitor_name, visitor_ip, country, country_code, city, timezone, current_page, mineral_interest, status, created_at, updated_at)
        VALUES (?, 'Guest Buyer', ?, ?, ?, ?, ?, ?, ?, 'bot', NOW(), NOW())");
    $ins->execute([$token, $ip, $country, $countryCode, $city, $timezone, $page, $mineral]);
    $newId = (int)$pdo->lastInsertId();

    // Assign unique Buyer #XXXX immediately
    $uniqueBuyerName = 'Buyer #' . (1000 + $newId);
    $pdo->prepare("UPDATE `chat_sessions` SET `visitor_name` = ? WHERE `id` = ?")->execute([$uniqueBuyerName, $newId]);

    $stmt = $pdo->prepare("SELECT * FROM `chat_sessions` WHERE id = ? LIMIT 1");
    $stmt->execute([$newId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Logs a message into chat_messages and updates chat_sessions meta.
 */
function logChatMessage(PDO $pdo, int $sessionId, string $senderType, string $senderName, string $messageText): int {
    ensureLiveChatSchema($pdo);
    $text = trim($messageText);
    if ($text === '') return 0;

    $stmt = $pdo->prepare("INSERT INTO `chat_messages` (session_id, sender_type, sender_name, message_text, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())");
    $stmt->execute([$sessionId, $senderType, $senderName, $text]);
    $msgId = (int)$pdo->lastInsertId();

    // Update parent session
    $preview = mb_substr($text, 0, 140);
    if ($senderType === 'visitor') {
        $pdo->prepare("UPDATE `chat_sessions` SET 
            last_message = ?, 
            last_message_sender = 'visitor', 
            last_message_at = NOW(), 
            unread_admin_count = unread_admin_count + 1 
            WHERE id = ?")
            ->execute([$preview, $sessionId]);
    } elseif ($senderType === 'agent') {
        $pdo->prepare("UPDATE `chat_sessions` SET 
            last_message = ?, 
            last_message_sender = 'agent', 
            last_message_at = NOW(), 
            unread_visitor_count = unread_visitor_count + 1 
            WHERE id = ?")
            ->execute([$preview, $sessionId]);
    } else {
        $pdo->prepare("UPDATE `chat_sessions` SET 
            last_message = ?, 
            last_message_sender = ?, 
            last_message_at = NOW() 
            WHERE id = ?")
            ->execute([$preview, $senderType, $sessionId]);
    }

    return $msgId;
}
