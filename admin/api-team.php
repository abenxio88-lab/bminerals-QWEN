<?php
/**
 * Balochistan Minerals - Owner Master Control, Session Oversight, 2FA & Maker-Checker API
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';
requireAuth(true);

if (!hasPermission('manage_team') && !hasPermission('approve_emails')) {
    requirePermission('manage_team', true);
}

$pdo = getDB();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}
ensureTeamSecuritySchema($pdo);

$currentUser = getLoggedInUser();
$currentUid  = (int)$currentUser['id'];
$canManageTeam = hasPermission('manage_team');
$canApproveEmails = hasPermission('approve_emails');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $currentSid = session_id();
    releaseSessionLock();
    $pendingDrafts = [];
    if ($canApproveEmails) {
        $stDrafts = $pdo->query("SELECT e.id, e.inquiry_id, e.from_email, e.to_email, e.cc_email, e.subject, e.body_text, e.body_html, e.attachments, e.sent_at AS created_at, e.created_by_user_id,
                u.full_name AS author_name, u.username AS author_username, u.role AS author_role,
                i.company_name AS buyer_company, i.buyer_name
            FROM `email_messages` e
            LEFT JOIN `admin_users` u ON u.id = e.created_by_user_id
            LEFT JOIN `inquiries` i ON i.id = e.inquiry_id
            WHERE e.status = 'Pending Approval'
            ORDER BY e.sent_at ASC, e.id ASC");
        require_once __DIR__ . '/mail-helpers.php';
        foreach ($stDrafts->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $atts = decodeAttachmentsMeta($row['attachments'] ?? '');
            $row['rendered_html'] = buildRenderableEmailHtml($row['body_html'], $row['body_text'], (int)$row['id'], $atts);
            $row['attachments_list'] = formatPublicAttachmentsForMessage((int)$row['id'], $row['attachments'] ?? '');
            $pendingDrafts[] = $row;
        }
    }

    // Staff SLA & Workload Summary (visible to Owner & Manager)
    $slaStats = $pdo->query("SELECT u.id, u.username, u.full_name, u.role, COALESCE(u.is_active,1) AS is_active,
            COALESCE(u.can_send_direct_email,0) AS can_send_direct_email, COALESCE(u.totp_enabled,0) AS totp_enabled, u.last_login,
            (SELECT COUNT(*) FROM `inquiries` iq WHERE iq.assigned_user_id = u.id) AS assigned_leads,
            (SELECT COUNT(*) FROM `deals` dl WHERE dl.assigned_user_id = u.id AND dl.deal_state IN ('Active', 'Disputed')) AS active_deals,
            (SELECT COUNT(*) FROM `email_messages` em WHERE em.created_by_user_id = u.id AND em.status = 'Sent') AS emails_sent,
            (SELECT COUNT(*) FROM `email_messages` em2 WHERE em2.created_by_user_id = u.id AND em2.status = 'Pending Approval') AS emails_pending,
            (SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, iq2.created_at, iq2.first_responded_at)))
             FROM `inquiries` iq2 WHERE iq2.assigned_user_id = u.id AND iq2.first_responded_at IS NOT NULL) AS avg_response_mins
        FROM `admin_users` u
        ORDER BY FIELD(u.role,'Owner','Manager','Junior','Yard'), u.id ASC")->fetchAll(PDO::FETCH_ASSOC);

    $staffList = [];
    $sessions  = [];
    $loginEvents = [];
    $auditLogs   = [];

    if ($canManageTeam) {
        $staffList = $slaStats;
        $sessions = $pdo->query("SELECT s.session_id, s.user_id, s.ip_address, s.device_label, s.current_page, s.login_at AS created_at, s.last_seen_at, s.is_revoked,
                TIMESTAMPDIFF(SECOND, s.last_seen_at, NOW()) AS idle_seconds,
                u.username, u.full_name, u.role
            FROM `admin_sessions` s
            INNER JOIN `admin_users` u ON u.id = s.user_id
            WHERE s.is_revoked = 0 AND s.last_seen_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY s.last_seen_at DESC")->fetchAll(PDO::FETCH_ASSOC);

        $currentSid = session_id();
        foreach ($sessions as &$sess) {
            $sess['is_current_device'] = ($sess['session_id'] === $currentSid);
            $sess['session_preview'] = substr($sess['session_id'], 0, 8) . '…';
        }
        unset($sess);

        $loginEvents = $pdo->query("SELECT id, user_id, username_tried, event_type, ip_address, device_label, details, created_at
            FROM `admin_login_events`
            ORDER BY id DESC
            LIMIT 120")->fetchAll(PDO::FETCH_ASSOC);

        $auditModule = trim((string)($_GET['module'] ?? ''));
        $auditSearch = trim((string)($_GET['q'] ?? ''));
        $sqlAudit = "SELECT id, actor_user_id, actor_name, actor_role, module, target_id, summary, ip_address, created_at
            FROM `system_audit_log` WHERE 1=1";
        $params = [];
        if ($auditModule !== '') {
            $sqlAudit .= " AND module = ?";
            $params[] = $auditModule;
        }
        if ($auditSearch !== '') {
            $like = '%' . $auditSearch . '%';
            $sqlAudit .= " AND (actor_name LIKE ? OR summary LIKE ? OR target_id LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sqlAudit .= " ORDER BY id DESC LIMIT 180";
        $stAud = $pdo->prepare($sqlAudit);
        $stAud->execute($params);
        $auditLogs = $stAud->fetchAll(PDO::FETCH_ASSOC);
    }

    // Current user 2FA state
    $meRow = $pdo->prepare("SELECT id, username, email, role, COALESCE(totp_enabled,0) AS totp_enabled FROM `admin_users` WHERE id = ? LIMIT 1");
    $meRow->execute([$currentUid]);
    $me = $meRow->fetch(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success'            => true,
        'can_manage_team'    => $canManageTeam,
        'can_approve_emails' => $canApproveEmails,
        'me'                 => $me,
        'pending_drafts'     => $pendingDrafts,
        'sla_stats'          => $slaStats,
        'staff'              => $staffList,
        'sessions'           => $sessions,
        'login_events'       => $loginEvents,
        'audit_logs'         => $auditLogs,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$csrf = $input['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF security token.']);
    exit;
}

$action = trim((string)($input['action'] ?? ''));

// ---------------------------------------------------------
// Maker-Checker Email Queue Actions (Owner & Manager)
// ---------------------------------------------------------
if ($action === 'approve_email') {
    requirePermission('approve_emails', true);
    $emailId = (int)($input['email_id'] ?? 0);
    if ($emailId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid email draft ID.']);
        exit;
    }

    // Optional edit before approval by Manager/Owner
    $editedSubject = isset($input['subject']) ? trim((string)$input['subject']) : null;
    $editedBody    = isset($input['body_text']) ? trim((string)$input['body_text']) : null;
    if ($editedSubject !== null || $editedBody !== null) {
        $chk = $pdo->prepare("SELECT subject, body_text FROM `email_messages` WHERE id = ? AND status = 'Pending Approval' LIMIT 1");
        $chk->execute([$emailId]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $newSubj = ($editedSubject !== null && $editedSubject !== '') ? $editedSubject : $existing['subject'];
            $newBody = ($editedBody !== null && $editedBody !== '') ? $editedBody : $existing['body_text'];
            $clearHtml = ($editedBody !== null && trim($editedBody) !== trim((string)$existing['body_text']));
            if ($clearHtml) {
                $pdo->prepare("UPDATE `email_messages` SET subject = ?, body_text = ?, body_html = NULL WHERE id = ? AND status = 'Pending Approval'")
                    ->execute([$newSubj, $newBody, $emailId]);
            } else {
                $pdo->prepare("UPDATE `email_messages` SET subject = ? WHERE id = ? AND status = 'Pending Approval'")
                    ->execute([$newSubj, $emailId]);
            }
        }
    }

    $res = approveAndSendQueuedEmail($pdo, $emailId);
    echo json_encode($res);
    exit;
}

if ($action === 'reject_email') {
    requirePermission('approve_emails', true);
    $emailId = (int)($input['email_id'] ?? 0);
    $reason  = trim((string)($input['reason'] ?? 'Needs revision before customer dispatch.'));
    if ($emailId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid email draft ID.']);
        exit;
    }

    $st = $pdo->prepare("SELECT id, inquiry_id, to_email, subject, created_by_user_id FROM `email_messages` WHERE id = ? AND status = 'Pending Approval' LIMIT 1");
    $st->execute([$emailId]);
    $draft = $st->fetch(PDO::FETCH_ASSOC);
    if (!$draft) {
        echo json_encode(['success' => false, 'error' => 'Draft not found or already processed.']);
        exit;
    }

    $pdo->prepare("UPDATE `email_messages` SET status = 'Rejected', approved_by_user_id = ?, approved_at = NOW() WHERE id = ?")
        ->execute([$currentUid, $emailId]);

    if (!empty($draft['inquiry_id'])) {
        $note = "[" . date('M d, Y H:i') . "] Draft email rejected by {$currentUser['full_name']}: {$draft['subject']} — Reason: {$reason}\n-------------------\n";
        $pdo->prepare("UPDATE `inquiries` SET internal_notes = CONCAT(?, COALESCE(internal_notes, '')) WHERE id = ?")
            ->execute([$note, (int)$draft['inquiry_id']]);
    }

    logSystemAudit($pdo, 'email', "Rejected queued draft #{$emailId} to {$draft['to_email']} ({$reason})", $draft['inquiry_id'] ?: $emailId);
    echo json_encode(['success' => true, 'message' => 'Draft marked Rejected and feedback saved to CRM notes.']);
    exit;
}

// ---------------------------------------------------------
// Personal Authenticator 2FA Setup (Owner & Manager)
// ---------------------------------------------------------
if ($action === 'begin_2fa_setup') {
    $secret = generateTotpSecret(16);
    $_SESSION['bm_setup_totp_secret'] = $secret;
    $uri = getTotpOtpAuthUri($currentUser['username'], $secret);
    echo json_encode([
        'success' => true,
        'secret'  => $secret,
        'otpauth' => $uri,
        'issuer'  => 'Balochistan Minerals',
        'account' => $currentUser['username'],
    ]);
    exit;
}

if ($action === 'confirm_2fa_setup') {
    $secret = $_SESSION['bm_setup_totp_secret'] ?? '';
    $code   = trim((string)($input['totp_code'] ?? ''));
    if ($secret === '') {
        echo json_encode(['success' => false, 'error' => 'No active 2FA setup key. Click Generate Key first.']);
        exit;
    }
    if (!verifyTotpCode($secret, $code, 1)) {
        echo json_encode(['success' => false, 'error' => 'Code did not match. Ensure your phone time is set to Automatic and enter the fresh 6 digits.']);
        exit;
    }

    $pdo->prepare("UPDATE `admin_users` SET totp_secret = ?, totp_enabled = 1 WHERE id = ?")
        ->execute([$secret, $currentUid]);
    unset($_SESSION['bm_setup_totp_secret']);
    logSystemAudit($pdo, 'security', "Enabled Authenticator 2FA on own account ({$currentUser['username']})", $currentUid);
    echo json_encode(['success' => true, 'message' => 'Two-Factor Authentication (2FA) is now active on your account.']);
    exit;
}

if ($action === 'disable_own_2fa') {
    $password = trim((string)($input['confirm_password'] ?? ''));
    $st = $pdo->prepare("SELECT password_hash FROM `admin_users` WHERE id = ? LIMIT 1");
    $st->execute([$currentUid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || !password_verify($password, $row['password_hash'])) {
        echo json_encode(['success' => false, 'error' => 'Current password confirmation failed.']);
        exit;
    }

    $pdo->prepare("UPDATE `admin_users` SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?")->execute([$currentUid]);
    logSystemAudit($pdo, 'security', "Disabled Authenticator 2FA on own account ({$currentUser['username']})", $currentUid);
    echo json_encode(['success' => true, 'message' => '2FA removed from your account.']);
    exit;
}

// ---------------------------------------------------------
// Owner-Only Master Team & Session Control Actions
// ---------------------------------------------------------
requirePermission('manage_team', true);

if ($action === 'create_staff') {
    $username = strtolower(preg_replace('/[^a-zA-Z0-9_.-]/', '', trim((string)($input['username'] ?? ''))));
    $fullName = trim((string)($input['full_name'] ?? ''));
    $email    = filter_var(trim((string)($input['email'] ?? '')), FILTER_SANITIZE_EMAIL);
    $role     = normalizeRoleTier($input['role'] ?? 'Junior');
    $password = (string)($input['password'] ?? '');
    $canDirect = !empty($input['can_send_direct_email']) ? 1 : 0;

    if (strlen($username) < 3 || $fullName === '' || strlen($password) < 8) {
        echo json_encode(['success' => false, 'error' => 'Username (min 3 chars), full name, and strong password (min 8 chars) are required.']);
        exit;
    }

    $dup = $pdo->prepare("SELECT id FROM `admin_users` WHERE username = ? LIMIT 1");
    $dup->execute([$username]);
    if ($dup->fetchColumn()) {
        echo json_encode(['success' => false, 'error' => "Username '{$username}' already exists."]);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO `admin_users` (`username`, `password_hash`, `full_name`, `email`, `role`, `is_active`, `can_send_direct_email`, `created_by`)
        VALUES (?, ?, ?, ?, ?, 1, ?, ?)")
        ->execute([$username, $hash, $fullName, $email ?: null, $role, $canDirect, $currentUid]);
    $newId = (int)$pdo->lastInsertId();

    logSystemAudit($pdo, 'team', "Created staff account '{$username}' ({$fullName}) with role '{$role}'", $newId);
    echo json_encode(['success' => true, 'message' => "Created {$role} account '{$username}' successfully.", 'user_id' => $newId]);
    exit;
}

if ($action === 'update_staff') {
    $targetId  = (int)($input['user_id'] ?? 0);
    $fullName  = trim((string)($input['full_name'] ?? ''));
    $email     = filter_var(trim((string)($input['email'] ?? '')), FILTER_SANITIZE_EMAIL);
    $role      = normalizeRoleTier($input['role'] ?? 'Junior');
    $canDirect = !empty($input['can_send_direct_email']) ? 1 : 0;

    if ($targetId <= 0 || $fullName === '') {
        echo json_encode(['success' => false, 'error' => 'Invalid staff ID or name.']);
        exit;
    }

    // Prevent Owner from accidentally stripping their own Owner role
    if ($targetId === $currentUid && $role !== 'Owner') {
        echo json_encode(['success' => false, 'error' => 'You cannot downgrade your own active Owner account.']);
        exit;
    }

    $pdo->prepare("UPDATE `admin_users` SET full_name = ?, email = ?, role = ?, can_send_direct_email = ? WHERE id = ?")
        ->execute([$fullName, $email ?: null, $role, $canDirect, $targetId]);

    logSystemAudit($pdo, 'team', "Updated staff #{$targetId} ({$fullName}): role={$role}, direct_email={$canDirect}", $targetId);
    echo json_encode(['success' => true, 'message' => "Updated staff permissions for {$fullName}."]);
    exit;
}

if ($action === 'toggle_freeze') {
    $targetId = (int)($input['user_id'] ?? 0);
    if ($targetId <= 0 || $targetId === $currentUid) {
        echo json_encode(['success' => false, 'error' => 'You cannot freeze your own active Owner session.']);
        exit;
    }

    $st = $pdo->prepare("SELECT username, COALESCE(is_active,1) AS is_active FROM `admin_users` WHERE id = ? LIMIT 1");
    $st->execute([$targetId]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        echo json_encode(['success' => false, 'error' => 'Staff account not found.']);
        exit;
    }

    $newActive = ((int)$u['is_active'] === 1) ? 0 : 1;
    $pdo->prepare("UPDATE `admin_users` SET is_active = ? WHERE id = ?")->execute([$newActive, $targetId]);
    if ($newActive === 0) {
        $pdo->prepare("UPDATE `admin_sessions` SET is_revoked = 1 WHERE user_id = ?")->execute([$targetId]);
        recordLoginEvent($pdo, $targetId, $u['username'], 'Revoked', 'Account frozen by Owner');
    }

    logSystemAudit($pdo, 'security', ($newActive === 0 ? 'Suspended/Froze' : 'Reactivated') . " staff account '{$u['username']}'", $targetId);
    echo json_encode([
        'success' => true,
        'message' => $newActive === 0
            ? "Account '{$u['username']}' frozen and all active sessions kicked immediately."
            : "Account '{$u['username']}' reactivated.",
    ]);
    exit;
}

if ($action === 'reset_password') {
    $targetId = (int)($input['user_id'] ?? 0);
    $newPass  = (string)($input['new_password'] ?? '');
    if ($targetId <= 0 || strlen($newPass) < 8) {
        echo json_encode(['success' => false, 'error' => 'Valid staff ID and new password (min 8 chars) required.']);
        exit;
    }

    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE `admin_users` SET password_hash = ? WHERE id = ?")->execute([$hash, $targetId]);
    if ($targetId !== $currentUid) {
        $pdo->prepare("UPDATE `admin_sessions` SET is_revoked = 1 WHERE user_id = ?")->execute([$targetId]);
    }

    logSystemAudit($pdo, 'security', "Reset password for staff #{$targetId}" . ($targetId !== $currentUid ? ' (revoked prior sessions)' : ''), $targetId);
    echo json_encode(['success' => true, 'message' => 'Password updated cleanly.']);
    exit;
}

if ($action === 'reset_user_2fa') {
    $targetId = (int)($input['user_id'] ?? 0);
    if ($targetId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid staff ID.']);
        exit;
    }
    $pdo->prepare("UPDATE `admin_users` SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?")->execute([$targetId]);
    logSystemAudit($pdo, 'security', "Cleared 2FA enrollment for staff #{$targetId}", $targetId);
    echo json_encode(['success' => true, 'message' => 'Staff 2FA reset. They can sign in cleanly and re-enroll.']);
    exit;
}

if ($action === 'revoke_session') {
    $sid = trim((string)($input['session_id'] ?? ''));
    if ($sid === '') {
        echo json_encode(['success' => false, 'error' => 'Missing session ID.']);
        exit;
    }
    if ($sid === session_id()) {
        echo json_encode(['success' => false, 'error' => 'Use Power button to sign out of your current device.']);
        exit;
    }

    $st = $pdo->prepare("SELECT user_id, ip_address, device_label FROM `admin_sessions` WHERE session_id = ? LIMIT 1");
    $st->execute([$sid]);
    $s = $st->fetch(PDO::FETCH_ASSOC);

    $pdo->prepare("UPDATE `admin_sessions` SET is_revoked = 1 WHERE session_id = ?")->execute([$sid]);
    if ($s) {
        recordLoginEvent($pdo, (int)$s['user_id'], 'session', 'Revoked', "Force signed out from {$s['ip_address']} ({$s['device_label']})");
    }
    logSystemAudit($pdo, 'security', "Force-revoked remote session (" . substr($sid, 0, 8) . "…)", $s['user_id'] ?? null);
    echo json_encode(['success' => true, 'message' => 'Remote device signed out immediately.']);
    exit;
}

if ($action === 'revoke_other_sessions') {
    $cur = session_id();
    $pdo->prepare("UPDATE `admin_sessions` SET is_revoked = 1 WHERE session_id != ? AND is_revoked = 0")->execute([$cur]);
    logSystemAudit($pdo, 'security', 'Executed Panic Button: Signed out ALL other active staff sessions');
    echo json_encode(['success' => true, 'message' => 'All other active devices across all accounts have been signed out immediately.']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unsupported action.']);
