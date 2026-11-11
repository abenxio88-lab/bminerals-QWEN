<?php
/**
 * Balochistan Minerals - Multi-Role Access Control (RBAC), Live Session Registry,
 * Maker-Checker Approval Queue, Pure-PHP TOTP 2FA, and Company-Wide Audit Trail.
 */

require_once __DIR__ . '/db.php';

/**
 * Idempotent installer/upgrader for team, session, audit, and approval columns.
 */
function ensureTeamSecuritySchema($pdo) {
    static $ensured = false;
    if ($ensured || !$pdo) {
        return;
    }
    if (function_exists('isSchemaBootstrapped') && isSchemaBootstrapped('team_rbac_v2')) {
        $ensured = true;
        return;
    }

    try {
        // 1. Upgrade admin_users columns safely
        $uCols = $pdo->query("SHOW COLUMNS FROM `admin_users`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('is_active', $uCols, true)) {
            $pdo->exec("ALTER TABLE `admin_users` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`");
        }
        if (!in_array('can_send_direct_email', $uCols, true)) {
            $pdo->exec("ALTER TABLE `admin_users` ADD COLUMN `can_send_direct_email` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_active`");
        }
        if (!in_array('allowed_scope', $uCols, true)) {
            $pdo->exec("ALTER TABLE `admin_users` ADD COLUMN `allowed_scope` VARCHAR(30) NOT NULL DEFAULT 'all' AFTER `can_send_direct_email`");
        }
        if (!in_array('totp_secret', $uCols, true)) {
            $pdo->exec("ALTER TABLE `admin_users` ADD COLUMN `totp_secret` VARCHAR(64) NULL AFTER `allowed_scope`");
        }
        if (!in_array('totp_enabled', $uCols, true)) {
            $pdo->exec("ALTER TABLE `admin_users` ADD COLUMN `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `totp_secret`");
        }
        if (!in_array('created_by', $uCols, true)) {
            $pdo->exec("ALTER TABLE `admin_users` ADD COLUMN `created_by` VARCHAR(60) NULL AFTER `totp_enabled`");
        }

        // 2. Upgrade inquiries with assignment & SLA response tracking
        $iCols = $pdo->query("SHOW COLUMNS FROM `inquiries`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('assigned_user_id', $iCols, true)) {
            $pdo->exec("ALTER TABLE `inquiries` ADD COLUMN `assigned_user_id` INT NULL AFTER `status`, ADD INDEX `idx_inq_assigned` (`assigned_user_id`)");
        }
        if (!in_array('first_responded_at', $iCols, true)) {
            $pdo->exec("ALTER TABLE `inquiries` ADD COLUMN `first_responded_at` DATETIME NULL AFTER `assigned_user_id`");
        }

        // 3. Upgrade deals with assigned_user_id if table exists
        $hasDeals = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'deals'")->fetchColumn();
        if ($hasDeals > 0) {
            $dCols = $pdo->query("SHOW COLUMNS FROM `deals`")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('assigned_user_id', $dCols, true)) {
                $pdo->exec("ALTER TABLE `deals` ADD COLUMN `assigned_user_id` INT NULL AFTER `inquiry_id`, ADD INDEX `idx_deal_assigned` (`assigned_user_id`)");
            }
        }

        // 4. Upgrade email_messages for Maker-Checker ownership & approval audit
        $eCols = $pdo->query("SHOW COLUMNS FROM `email_messages`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('created_by_user_id', $eCols, true)) {
            $pdo->exec("ALTER TABLE `email_messages` ADD COLUMN `created_by_user_id` INT NULL AFTER `status`");
        }
        if (!in_array('approved_by_user_id', $eCols, true)) {
            $pdo->exec("ALTER TABLE `email_messages` ADD COLUMN `approved_by_user_id` INT NULL AFTER `created_by_user_id`");
        }
        if (!in_array('approved_at', $eCols, true)) {
            $pdo->exec("ALTER TABLE `email_messages` ADD COLUMN `approved_at` DATETIME NULL AFTER `approved_by_user_id`");
        }

        // 5. Active admin_sessions table (live online tracking & force-logout)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_sessions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `session_id` VARCHAR(128) NOT NULL UNIQUE,
            `user_id` INT NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
            `device_label` VARCHAR(100) NOT NULL DEFAULT 'Browser',
            `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
            `current_page` VARCHAR(120) NOT NULL DEFAULT 'dashboard.php',
            `login_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `last_seen_at` DATETIME NOT NULL,
            `is_revoked` TINYINT(1) NOT NULL DEFAULT 0,
            INDEX `idx_sess_user` (`user_id`, `is_revoked`),
            INDEX `idx_sess_seen` (`last_seen_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 6. Permanent login security history
        $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_login_events` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NULL,
            `username_tried` VARCHAR(80) NOT NULL,
            `event_type` ENUM('Success','Failed','Locked','2FA_Failed','Revoked','Logout') NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
            `device_label` VARCHAR(100) NOT NULL DEFAULT '',
            `details` VARCHAR(255) NOT NULL DEFAULT '',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_le_user` (`user_id`, `created_at`),
            INDEX `idx_le_type` (`event_type`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 7. Company-wide immutable action audit trail
        $pdo->exec("CREATE TABLE IF NOT EXISTS `system_audit_log` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `actor_user_id` INT NULL,
            `actor_name` VARCHAR(100) NOT NULL,
            `actor_role` VARCHAR(30) NOT NULL DEFAULT 'Owner',
            `module` VARCHAR(32) NOT NULL,
            `target_id` VARCHAR(64) NULL,
            `summary` VARCHAR(255) NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_sys_mod` (`module`, `created_at`),
            INDEX `idx_sys_actor` (`actor_user_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 8. Performance indexes for fast CRM / email thread lookups
        try {
            $inqIdx = $pdo->query("SHOW INDEX FROM `inquiries`")->fetchAll(PDO::FETCH_COLUMN, 2);
            if (!in_array('idx_inq_email', $inqIdx, true)) {
                $pdo->exec("ALTER TABLE `inquiries` ADD INDEX `idx_inq_email` (`email`)");
            }
            $emIdx = $pdo->query("SHOW INDEX FROM `email_messages`")->fetchAll(PDO::FETCH_COLUMN, 2);
            if (!in_array('idx_from_email', $emIdx, true)) {
                $pdo->exec("ALTER TABLE `email_messages` ADD INDEX `idx_from_email` (`from_email`)");
            }
            if (!in_array('idx_sent_at', $emIdx, true)) {
                $pdo->exec("ALTER TABLE `email_messages` ADD INDEX `idx_sent_at` (`sent_at`)");
            }
        } catch (Exception $e) {}

        if (function_exists('markSchemaBootstrapped')) {
            markSchemaBootstrapped('team_rbac_v2');
        }
        $ensured = true;
    } catch (Exception $e) {
        error_log('ensureTeamSecuritySchema notice: ' . $e->getMessage());
    }
}

/**
 * Normalizes any role name into one of 4 canonical tiers: Owner, Manager, Junior, Yard.
 */
function normalizeRoleTier($role) {
    $low = strtolower(trim((string)$role));
    if (in_array($low, ['owner', 'admin', 'ceo', 'director', 'executive'], true) || $low === '') {
        return 'Owner';
    }
    if (in_array($low, ['manager', 'ops_manager', 'export_manager'], true)) {
        return 'Manager';
    }
    if (in_array($low, ['junior', 'coordinator', 'desk', 'trainee', 'assistant'], true)) {
        return 'Junior';
    }
    if (in_array($low, ['yard', 'field', 'clearing', 'supervisor', 'inspector', 'agent', 'logistics_staff'], true)) {
        return 'Yard';
    }
    return 'Junior';
}

/**
 * Returns authoritative permission list for a canonical role + user row flags.
 */
function getPermissionsForUser($userRow) {
    $tier = normalizeRoleTier($userRow['role'] ?? 'Owner');
    $canDirectMail = !isset($userRow['can_send_direct_email']) || (int)$userRow['can_send_direct_email'] === 1;

    $matrix = [
        'Owner' => [
            'manage_team'        => true,
            'view_analytics'     => true,
            'manage_prices'      => true,
            'view_prospects'     => true,
            'export_prospects'   => true,
            'manage_mail_config' => true,
            'download_backup'    => true,
            'hard_delete'        => true,
            'reopen_deal'        => true,
            'force_close_deal'   => true,
            'standard_close_deal'=> true,
            'verify_lock_docs'   => true,
            'approve_emails'     => true,
            'assign_records'     => true,
            'create_deal'        => true,
            'view_deals'         => true,
            'edit_deal_stage'    => true,
            'view_deal_financials'=> true,
            'manage_stockpiles'  => true,
            'view_stockpiles'    => true,
            'view_rfqs'          => true,
            'edit_rfqs'          => true,
            'draft_reply'        => true,
            'send_direct_email'  => true,
            'generate_quote'     => true,
            'view_radar'         => true,
            'manage_community'   => true,
        ],
        'Manager' => [
            'manage_team'        => false,
            'view_analytics'     => false,
            'manage_prices'      => false,
            'view_prospects'     => true,
            'export_prospects'   => false,
            'manage_mail_config' => false,
            'download_backup'    => false,
            'hard_delete'        => false,
            'reopen_deal'        => false,
            'force_close_deal'   => false,
            'standard_close_deal'=> true,
            'verify_lock_docs'   => true,
            'approve_emails'     => true,
            'assign_records'     => true,
            'create_deal'        => true,
            'view_deals'         => true,
            'edit_deal_stage'    => true,
            'view_deal_financials'=> true,
            'manage_stockpiles'  => true,
            'view_stockpiles'    => true,
            'view_rfqs'          => true,
            'edit_rfqs'          => true,
            'draft_reply'        => true,
            'send_direct_email'  => $canDirectMail,
            'generate_quote'     => true,
            'view_radar'         => true,
            'manage_community'   => true,
        ],
        'Junior' => [
            'manage_team'        => false,
            'view_analytics'     => false,
            'manage_prices'      => false,
            'view_prospects'     => false,
            'export_prospects'   => false,
            'manage_mail_config' => false,
            'download_backup'    => false,
            'hard_delete'        => false,
            'reopen_deal'        => false,
            'force_close_deal'   => false,
            'standard_close_deal'=> false,
            'verify_lock_docs'   => false,
            'approve_emails'     => false,
            'assign_records'     => false,
            'create_deal'        => false,
            'view_deals'         => true,
            'edit_deal_stage'    => true,
            'view_deal_financials'=> false,
            'manage_stockpiles'  => false,
            'view_stockpiles'    => true,
            'view_rfqs'          => true,
            'edit_rfqs'          => true,
            'draft_reply'        => true,
            'send_direct_email'  => $canDirectMail,
            'generate_quote'     => true,
            'view_radar'         => true,
            'manage_community'   => false,
        ],
        'Yard' => [
            'manage_team'        => false,
            'view_analytics'     => false,
            'manage_prices'      => false,
            'view_prospects'     => false,
            'export_prospects'   => false,
            'manage_mail_config' => false,
            'download_backup'    => false,
            'hard_delete'        => false,
            'reopen_deal'        => false,
            'force_close_deal'   => false,
            'standard_close_deal'=> false,
            'verify_lock_docs'   => false,
            'approve_emails'     => false,
            'assign_records'     => false,
            'create_deal'        => false,
            'view_deals'         => true,
            'edit_deal_stage'    => true,
            'view_deal_financials'=> false,
            'manage_stockpiles'  => true,
            'view_stockpiles'    => true,
            'view_rfqs'          => false,
            'edit_rfqs'          => false,
            'draft_reply'        => false,
            'send_direct_email'  => false,
            'generate_quote'     => false,
            'view_radar'         => false,
            'manage_community'   => false,
        ],
    ];

    $perms = $matrix[$tier] ?? $matrix['Junior'];
    // Allow explicit override if Owner specifically granted direct send to a vetted Junior
    if ($tier === 'Junior' && isset($userRow['can_send_direct_email'])) {
        $perms['send_direct_email'] = ((int)$userRow['can_send_direct_email'] === 1);
    }
    return $perms;
}

/**
 * Parses clean device summary from HTTP_USER_AGENT.
 */
function parseDeviceLabel($ua = null) {
    $raw = (string)($ua ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $browser = 'Browser';
    if (stripos($raw, 'Edg/') !== false) $browser = 'Edge';
    elseif (stripos($raw, 'Chrome/') !== false) $browser = 'Chrome';
    elseif (stripos($raw, 'Firefox/') !== false) $browser = 'Firefox';
    elseif (stripos($raw, 'Safari/') !== false) $browser = 'Safari';

    $os = 'Device';
    if (stripos($raw, 'Windows') !== false) $os = 'Windows';
    elseif (stripos($raw, 'Android') !== false) $os = 'Android';
    elseif (stripos($raw, 'iPhone') !== false || stripos($raw, 'iPad') !== false) $os = 'iOS';
    elseif (stripos($raw, 'Mac OS') !== false) $os = 'macOS';
    elseif (stripos($raw, 'Linux') !== false) $os = 'Linux';

    return $browser . ' on ' . $os;
}

function getClientIpSafe() {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/**
 * Registers or refreshes active staff session in MySQL.
 */
function upsertAdminSession($pdo, $userId, $page = 'dashboard.php') {
    if (!$pdo || $userId <= 0) return;
    $sid = session_id();
    if ($sid === '') return;

    try {
        ensureTeamSecuritySchema($pdo);
        $ip  = getClientIpSafe();
        $ua  = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
        $dev = parseDeviceLabel($ua);
        $pg  = substr(basename((string)$page), 0, 120);

        $stmt = $pdo->prepare("INSERT INTO `admin_sessions` (`session_id`, `user_id`, `ip_address`, `device_label`, `user_agent`, `current_page`, `last_seen_at`, `is_revoked`)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 0)
            ON DUPLICATE KEY UPDATE
              user_id = VALUES(user_id),
              ip_address = VALUES(ip_address),
              device_label = VALUES(device_label),
              user_agent = VALUES(user_agent),
              current_page = VALUES(current_page),
              last_seen_at = NOW(),
              is_revoked = 0");
        $stmt->execute([$sid, $userId, $ip, $dev, $ua, $pg]);
    } catch (Exception $e) {
        error_log('upsertAdminSession notice: ' . $e->getMessage());
    }
}

/**
 * Updates last_seen_at & current_page for an active non-revoked session.
 */
function touchAdminSession($pdo, $userId, $page = '') {
    if (!$pdo || $userId <= 0) return;
    $sid = session_id();
    if ($sid === '') return;
    try {
        $pg = trim((string)$page);
        if ($pg !== '') {
            $pdo->prepare("UPDATE `admin_sessions` SET last_seen_at = NOW(), current_page = ? WHERE session_id = ? AND user_id = ? AND is_revoked = 0")
                ->execute([substr($pg, 0, 120), $sid, $userId]);
        } else {
            $pdo->prepare("UPDATE `admin_sessions` SET last_seen_at = NOW() WHERE session_id = ? AND user_id = ? AND is_revoked = 0")
                ->execute([$sid, $userId]);
        }
    } catch (Exception $e) {}
}

/**
 * Writes permanent login security log and alerts Owner if new IP or failed brute attempts occur.
 */
function recordLoginEvent($pdo, $userId, $usernameTried, $eventType, $details = '') {
    if (!$pdo) return;
    try {
        ensureTeamSecuritySchema($pdo);
        $ip  = getClientIpSafe();
        $dev = parseDeviceLabel();

        $pdo->prepare("INSERT INTO `admin_login_events` (`user_id`, `username_tried`, `event_type`, `ip_address`, `device_label`, `details`)
            VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$userId ?: null, substr((string)$usernameTried, 0, 80), $eventType, $ip, $dev, substr((string)$details, 0, 255)]);

        if ($eventType === 'Success' && $userId > 0) {
            // Warn Owner if user logged in from an IP not seen in their prior successful logins
            $seenStmt = $pdo->prepare("SELECT COUNT(*) FROM `admin_login_events` WHERE user_id = ? AND event_type = 'Success' AND ip_address = ? AND created_at < DATE_SUB(NOW(), INTERVAL 2 SECOND)");
            $seenStmt->execute([$userId, $ip]);
            if ((int)$seenStmt->fetchColumn() === 0) {
                logAdminNotification($pdo, 'info', 'security', 'New IP Staff Sign-In', "Account '{$usernameTried}' signed in from IP {$ip} ({$dev})", null, 'team.php', 10);
            }
        } elseif ($eventType === 'Failed' || $eventType === 'Locked' || $eventType === '2FA_Failed') {
            $fChk = $pdo->prepare("SELECT COUNT(*) FROM `admin_login_events` WHERE ip_address = ? AND event_type IN ('Failed','2FA_Failed') AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $fChk->execute([$ip]);
            if ((int)$fChk->fetchColumn() >= 3) {
                logAdminNotification($pdo, 'warning', 'security', 'Repeated Failed Login Attempts', "Username '{$usernameTried}' failed login ({$eventType}) from {$ip} ({$dev})", null, 'team.php', 10);
            }
        }
    } catch (Exception $e) {
        error_log('recordLoginEvent notice: ' . $e->getMessage());
    }
}

/**
 * Records an immutable action event into company-wide system_audit_log.
 */
function logSystemAudit($pdo, $module, $summary, $targetId = null) {
    if (!$pdo) return;
    try {
        ensureTeamSecuritySchema($pdo);
        $uid  = $_SESSION['bm_admin_id'] ?? null;
        $name = $_SESSION['bm_admin_name'] ?? 'System';
        $role = normalizeRoleTier($_SESSION['bm_admin_role'] ?? 'Owner');
        $ip   = getClientIpSafe();

        $pdo->prepare("INSERT INTO `system_audit_log` (`actor_user_id`, `actor_name`, `actor_role`, `module`, `target_id`, `summary`, `ip_address`)
            VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$uid ?: null, substr((string)$name, 0, 100), $role, substr((string)$module, 0, 32), $targetId !== null ? substr((string)$targetId, 0, 64) : null, substr((string)$summary, 0, 255), $ip]);
    } catch (Exception $e) {
        error_log('logSystemAudit notice: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------
// Pure-PHP RFC 6238 TOTP (Google / Microsoft Authenticator)
// ---------------------------------------------------------

function generateTotpSecret($length = 16) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, 31)];
    }
    return $secret;
}

function base32DecodeStrict($input) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $clean = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', (string)$input));
    $binary = '';
    for ($i = 0, $len = strlen($clean); $i < $len; $i++) {
        $pos = strpos($alphabet, $clean[$i]);
        if ($pos === false) continue;
        $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    for ($i = 0; $i + 8 <= strlen($binary); $i += 8) {
        $bytes .= chr(bindec(substr($binary, $i, 8)));
    }
    return $bytes;
}

function computeTotpCodeAt($secret, $timeSlice) {
    $key = base32DecodeStrict($secret);
    if ($key === '') return '';
    $timeBytes = pack('N*', 0) . pack('N*', (int)$timeSlice);
    $hmac = hash_hmac('sha1', $timeBytes, $key, true);
    $offset = ord(substr($hmac, -1)) & 0x0F;
    $part = substr($hmac, $offset, 4);
    $value = unpack('N', $part)[1] & 0x7FFFFFFF;
    return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
}

function verifyTotpCode($secret, $code, $window = 1, $timestamp = null) {
    $cleanCode = preg_replace('/\D/', '', (string)$code);
    if (strlen($cleanCode) !== 6 || $secret === '') {
        return false;
    }
    $ts = $timestamp !== null ? (int)$timestamp : time();
    $slice = (int)floor($ts / 30);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(computeTotpCodeAt($secret, $slice + $i), $cleanCode)) {
            return true;
        }
    }
    return false;
}

function getTotpOtpAuthUri($username, $secret, $issuer = 'Balochistan Minerals') {
    $label = rawurlencode($issuer . ':' . $username);
    return "otpauth://totp/{$label}?secret=" . urlencode($secret) . "&issuer=" . rawurlencode($issuer);
}

// ---------------------------------------------------------
// Maker-Checker Email Dispatch & Approval Queue
// ---------------------------------------------------------

/**
 * Dispatches immediately when user has direct-send permission, OR queues for Manager/Owner approval.
 */
function dispatchOrQueueStaffEmail($pdo, array $opts) {
    require_once __DIR__ . '/smtp-mailer.php';
    require_once __DIR__ . '/mail-helpers.php';

    $toEmail       = filter_var(trim((string)($opts['to_email'] ?? '')), FILTER_SANITIZE_EMAIL);
    $fromEmail     = trim((string)($opts['from_email'] ?? 'sales@balochistanminerals.com'));
    $ccEmail       = trim((string)($opts['cc_email'] ?? ''));
    $bccEmail      = trim((string)($opts['bcc_email'] ?? ''));
    $subject       = trim((string)($opts['subject'] ?? 'Export Inquiry Follow-up'));
    $bodyText      = (string)($opts['body_text'] ?? '');
    $bodyHtml      = $opts['body_html'] ?? null;
    $attachedFiles = is_array($opts['attachments'] ?? null) ? $opts['attachments'] : [];
    $inquiryId     = !empty($opts['inquiry_id']) ? (int)$opts['inquiry_id'] : null;

    if (empty($toEmail) || (trim($bodyText) === '' && trim((string)$bodyHtml) === '')) {
        return ['success' => false, 'error' => 'Recipient email and message body are required.'];
    }

    $uid  = (int)($_SESSION['bm_admin_id'] ?? 0);
    $name = $_SESSION['bm_admin_name'] ?? 'Staff';

    // Check permission via auth helper if loaded, else compute cleanly
    $canDirect = function_exists('hasPermission') ? hasPermission('send_direct_email') : true;

    if ($canDirect) {
        $res = sendHostingerMail(
            $toEmail,
            $subject,
            $bodyText,
            $fromEmail,
            $ccEmail !== '' ? $ccEmail : null,
            $attachedFiles,
            $inquiryId,
            ($bodyHtml !== null && trim((string)$bodyHtml) !== '') ? $bodyHtml : null,
            $bccEmail !== '' ? $bccEmail : null
        );

        if (!empty($res['email_message_id'])) {
            $pdo->prepare("UPDATE `email_messages` SET created_by_user_id = ? WHERE id = ?")->execute([$uid ?: null, (int)$res['email_message_id']]);
        }
        if ($inquiryId > 0 && !empty($res['success'])) {
            $pdo->prepare("UPDATE `inquiries` SET first_responded_at = COALESCE(first_responded_at, NOW()) WHERE id = ?")->execute([$inquiryId]);
        }

        logSystemAudit($pdo, 'email', "Sent outbound email to {$toEmail}: {$subject}", $inquiryId ?: ($res['email_message_id'] ?? null));
        $res['queued_for_approval'] = false;
        return $res;
    }

    // Maker-Checker Queue: Store clean draft without sending via SMTP yet
    ensureTeamSecuritySchema($pdo);
    $draftHtml    = (is_string($bodyHtml) && trim($bodyHtml) !== '') ? $bodyHtml : null;
    $attMetaJson  = encodeAttachmentsMeta($attachedFiles);
    $msgId        = '<bm-draft-' . uniqid() . '.' . time() . '@balochistanminerals.com>';

    $ins = $pdo->prepare("INSERT INTO `email_messages`
        (`inquiry_id`, `direction`, `from_email`, `to_email`, `cc_email`, `subject`, `body_text`, `body_html`, `attachments`, `status`, `created_by_user_id`, `message_id`)
        VALUES (?, 'outbound', ?, ?, ?, ?, ?, ?, ?, 'Pending Approval', ?, ?)");
    $ins->execute([
        $inquiryId,
        $fromEmail,
        $toEmail,
        $ccEmail !== '' ? $ccEmail : null,
        $subject,
        $bodyText,
        $draftHtml,
        $attMetaJson,
        $uid ?: null,
        $msgId,
    ]);
    $draftId = (int)$pdo->lastInsertId();

    logSystemAudit($pdo, 'email', "Drafted reply #{$draftId} to {$toEmail} (Pending Manager/Owner approval)", $inquiryId ?: $draftId);
    logAdminNotification($pdo, 'info', 'approval', 'Junior Reply Awaiting Approval', "{$name} drafted an email to {$toEmail} ({$subject})", $inquiryId, 'team.php#approvals', 0);

    return [
        'success'             => true,
        'queued_for_approval' => true,
        'email_message_id'    => $draftId,
        'attachment_count'    => count($attachedFiles),
        'attachments'         => formatPublicAttachmentsForMessage($draftId, $attMetaJson),
        'message'             => 'Draft saved cleanly and queued for Manager / Owner approval before sending.',
    ];
}

/**
 * Approves and dispatches a pending Junior reply draft via live Hostinger SMTP.
 */
function approveAndSendQueuedEmail($pdo, $emailId) {
    require_once __DIR__ . '/smtp-mailer.php';
    require_once __DIR__ . '/mail-helpers.php';

    $st = $pdo->prepare("SELECT * FROM `email_messages` WHERE id = ? AND status = 'Pending Approval' LIMIT 1");
    $st->execute([$emailId]);
    $draft = $st->fetch(PDO::FETCH_ASSOC);
    if (!$draft) {
        return ['success' => false, 'error' => 'Draft not found or already processed.'];
    }

    $atts = decodeAttachmentsMeta($draft['attachments'] ?? '');
    $res = sendHostingerMail(
        $draft['to_email'],
        $draft['subject'],
        $draft['body_text'] ?: '',
        $draft['from_email'],
        $draft['cc_email'] ?: null,
        $atts,
        $draft['inquiry_id'] ? (int)$draft['inquiry_id'] : null,
        $draft['body_html'] ?: null
    );

    $approverId = (int)($_SESSION['bm_admin_id'] ?? 0);
    if (!empty($res['success'])) {
        // Transfer creator credit and mark old draft superseded/deleted cleanly (sendHostingerMail created clean Sent record)
        if (!empty($res['email_message_id'])) {
            $pdo->prepare("UPDATE `email_messages` SET created_by_user_id = ?, approved_by_user_id = ?, approved_at = NOW() WHERE id = ?")
                ->execute([$draft['created_by_user_id'], $approverId ?: null, (int)$res['email_message_id']]);
        }
        $pdo->prepare("DELETE FROM `email_messages` WHERE id = ?")->execute([$emailId]);

        if (!empty($draft['inquiry_id'])) {
            $inqId = (int)$draft['inquiry_id'];
            $attNote = !empty($atts) ? (" [Attachments: " . implode(', ', array_column($atts, 'name')) . "]") : '';
            $logNote = "[" . date('M d, Y H:i') . "] Approved & sent reply to {$draft['to_email']}: {$draft['subject']}{$attNote}\n-------------------\n";
            $pdo->prepare("UPDATE `inquiries` SET status = CASE WHEN status = 'New' THEN 'Contacted' ELSE status END, first_responded_at = COALESCE(first_responded_at, NOW()), internal_notes = CONCAT(?, COALESCE(internal_notes, '')) WHERE id = ?")
                ->execute([$logNote, $inqId]);
        }

        logSystemAudit($pdo, 'email', "Approved & dispatched queued draft #{$emailId} to {$draft['to_email']}", $draft['inquiry_id'] ?: $emailId);
        return ['success' => true, 'message' => "Approved and sent email to {$draft['to_email']}."];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'SMTP dispatch failed on approval.'];
}

/**
 * Counts email replies awaiting Manager/Owner Maker-Checker approval.
 */
function getPendingApprovalCount($pdo) {
    if (!$pdo) return 0;
    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM `email_messages` WHERE status = 'Pending Approval'")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Returns active staff accounts (id, full_name, username, role) for assignment dropdowns.
 */
function getActiveStaffList($pdo) {
    if (!$pdo) return [];
    try {
        ensureTeamSecuritySchema($pdo);
        return $pdo->query("SELECT id, username, full_name, email, role FROM `admin_users` WHERE COALESCE(is_active, 1) = 1 ORDER BY FIELD(role,'Owner','Manager','Junior','Yard'), full_name ASC")
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}
