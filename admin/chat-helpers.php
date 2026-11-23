<?php
/**
 * Balochistan Minerals - Real-Time Multi-Agent Live Chat & Handover Engine
 * Core database persistence, session tracking, and routing utilities.
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
        `city` VARCHAR(100) DEFAULT NULL,
        `current_page` VARCHAR(255) DEFAULT NULL,
        `mineral_interest` VARCHAR(100) DEFAULT NULL,
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
 * Fetches or creates a chat session given a client token.
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

    $ip = $meta['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $page = substr((string)($meta['page'] ?? ''), 0, 250);
    $mineral = substr((string)($meta['mineral_interest'] ?? ''), 0, 95);
    $country = substr((string)($meta['country'] ?? 'International'), 0, 95);

    if ($session) {
        // Update page & mineral if provided
        if ($page !== '' || $mineral !== '') {
            $upSql = "UPDATE `chat_sessions` SET updated_at = NOW()";
            $params = [];
            if ($page !== '') {
                $upSql .= ", current_page = ?";
                $params[] = $page;
            }
            if ($mineral !== '') {
                $upSql .= ", mineral_interest = ?";
                $params[] = $mineral;
            }
            $upSql .= " WHERE id = ?";
            $params[] = $session['id'];
            $pdo->prepare($upSql)->execute($params);
            if ($page !== '') $session['current_page'] = $page;
            if ($mineral !== '') $session['mineral_interest'] = $mineral;
        }
        return $session;
    }

    // Insert new session
    $ins = $pdo->prepare("INSERT INTO `chat_sessions` 
        (session_token, visitor_name, visitor_ip, country, current_page, mineral_interest, status, created_at, updated_at)
        VALUES (?, 'Guest Buyer', ?, ?, ?, ?, 'bot', NOW(), NOW())");
    $ins->execute([$token, $ip, $country, $page, $mineral]);
    $newId = (int)$pdo->lastInsertId();

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
        VALUES (?, ?, ?, ?, ?, NOW())");
    $isRead = ($senderType === 'agent') ? 0 : 0;
    $stmt->execute([$sessionId, $senderType, $senderName, $text, $isRead]);
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
