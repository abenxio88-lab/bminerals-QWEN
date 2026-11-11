<?php
/**
 * Balochistan Minerals - Executive Trade Desk Portal
 * Ultra-Refined Corporate Aesthetic (Deep Navy, Slate & Mineral Bronze)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/smtp-mailer.php';
require_once __DIR__ . '/flag-helper.php';
require_once __DIR__ . '/inquiry-row-helper.php';
require_once __DIR__ . '/mail-helpers.php';
requirePermission('view_rfqs');

$user = getLoggedInUser();
$pdo  = getDB();
if ($pdo !== null) {
    ensureTeamSecuritySchema($pdo);
}
$currentUid  = (int)($user['id'] ?? 0);
$currentTier = $user['tier'] ?? 'Owner';

$flashMessage = $_SESSION['bm_flash_msg'] ?? '';
$flashType    = $_SESSION['bm_flash_type'] ?? 'success';
unset($_SESSION['bm_flash_msg'], $_SESSION['bm_flash_type']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    getCsrfToken();
    releaseSessionLock();
}

// Handle quick updates (Status change, spot quote, internal notes, or delete)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $pdo !== null) {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (verifyCsrfToken($csrf)) {
        if ($action === 'update_inquiry') {
            $id         = (int)($_POST['inquiry_id'] ?? 0);
            $status     = cleanInput($_POST['status'] ?? 'New');
            $spotPrice  = !empty($_POST['spot_price']) ? (float)$_POST['spot_price'] : null;
            $notes      = cleanInput($_POST['internal_notes'] ?? '');

            if (hasPermission('assign_records') && array_key_exists('assigned_user_id', $_POST)) {
                $assignId = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
                $stmt = $pdo->prepare('UPDATE inquiries SET status = ?, spot_price_quoted = ?, internal_notes = ?, assigned_user_id = ? WHERE id = ?');
                $stmt->execute([$status, $spotPrice, $notes, $assignId, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE inquiries SET status = ?, spot_price_quoted = ?, internal_notes = ? WHERE id = ?');
                $stmt->execute([$status, $spotPrice, $notes, $id]);
            }
            logSystemAudit($pdo, 'rfq', "Updated RFQ #{$id} (status: {$status})", $id);
            $_SESSION['bm_flash_msg'] = 'Inquiry #' . $id . ' updated successfully.';
            $_SESSION['bm_flash_type'] = 'success';
        } elseif ($action === 'send_reply') {
            $id            = (int)($_POST['inquiry_id'] ?? 0);
            $toEmail       = filter_var(trim($_POST['to_email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $fromEmail     = cleanInput($_POST['from_email'] ?? 'sales@balochistanminerals.com');
            $ccEmail       = trim((string)($_POST['cc_email'] ?? ''));
            $bccEmail      = trim((string)($_POST['bcc_email'] ?? ''));
            $subject       = cleanInput($_POST['subject'] ?? 'Quotation & Export Inquiry Follow-up');
            $replyBody     = trim((string)($_POST['reply_body'] ?? ''));
            $replyHtmlBody = trim((string)($_POST['reply_html_body'] ?? ''));
            $autoLog       = !isset($_POST['auto_log_submitted']) || !empty($_POST['auto_log']);

            if ($replyBody === '' && $replyHtmlBody !== '') {
                $replyBody = trim(html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], "\n", $replyHtmlBody)), ENT_QUOTES, 'UTF-8'));
            }

            $uploadRes = handleUploadedAttachments('attachments');
            if (!empty($uploadRes['errors'])) {
                $_SESSION['bm_flash_msg'] = 'Reply not sent: ' . implode(' ', $uploadRes['errors']);
                $_SESSION['bm_flash_type'] = 'danger';
            } else {
                $attachedFiles = $uploadRes['files'];
                $attNote = !empty($attachedFiles) ? (" [Attachments: " . implode(', ', array_column($attachedFiles, 'name')) . "]") : '';

                // Dispatch directly via Hostinger SMTP OR Queue for Manager/Owner approval
                $sendRes = dispatchOrQueueStaffEmail($pdo, [
                    'to_email'    => $toEmail,
                    'from_email'  => $fromEmail,
                    'cc_email'    => $ccEmail,
                    'bcc_email'   => $bccEmail,
                    'subject'     => $subject,
                    'body_text'   => $replyBody,
                    'body_html'   => $replyHtmlBody !== '' ? $replyHtmlBody : null,
                    'attachments' => $attachedFiles,
                    'inquiry_id'  => $id,
                ]);

                if (!empty($sendRes['queued_for_approval'])) {
                    if ($autoLog && $id > 0) {
                        $logEntry = "[" . date('M d, Y H:i') . "] Draft reply queued for approval ({$user['full_name']}) to {$toEmail}: {$subject}{$attNote}\n-------------------\n";
                        $pdo->prepare('UPDATE inquiries SET internal_notes = CONCAT(?, COALESCE(internal_notes, "")) WHERE id = ?')->execute([$logEntry, $id]);
                    }
                    $_SESSION['bm_flash_msg'] = 'Reply draft saved and queued for Manager / Owner approval before sending to ' . $toEmail . '.';
                    $_SESSION['bm_flash_type'] = 'info';
                } elseif ($sendRes['success']) {
                    if ($autoLog && $id > 0) {
                        $ccNote = $ccEmail !== '' ? " (CC: $ccEmail)" : '';
                        $logEntry = "[" . date('M d, Y H:i') . "] Email sent from " . $fromEmail . " to " . $toEmail . $ccNote . ":\nSubject: " . $subject . $attNote . "\n" . $replyBody . "\n-------------------\n";
                        $stmt = $pdo->prepare('UPDATE inquiries SET internal_notes = CONCAT(?, COALESCE(internal_notes, "")), status = CASE WHEN status = "New" THEN "Contacted" ELSE status END WHERE id = ?');
                        $stmt->execute([$logEntry, $id]);
                    }
                    $_SESSION['bm_flash_msg'] = 'Official email reply dispatched to ' . $toEmail . ($autoLog ? ' and logged to Buyer Dossier #' . $id . '.' : '.');
                    $_SESSION['bm_flash_type'] = 'success';
                } else {
                    $_SESSION['bm_flash_msg'] = 'SMTP reported: ' . ($sendRes['error'] ?? 'Delivery failed.');
                    $_SESSION['bm_flash_type'] = 'warning';
                }
            }
        } elseif ($action === 'save_mail_settings') {
            if (!hasPermission('manage_mail_config')) {
                logSystemAudit($pdo, 'security', 'Blocked non-Owner save_mail_settings attempt');
                $_SESSION['bm_flash_msg'] = 'Only Owner can modify corporate SMTP mail settings.';
                $_SESSION['bm_flash_type'] = 'danger';
            } else {
                $smtpHost = cleanInput($_POST['smtp_host'] ?? 'smtp.hostinger.com');
                $smtpPort = (int)($_POST['smtp_port'] ?? 465);
                $smtpUser = cleanInput($_POST['smtp_user'] ?? 'sales@balochistanminerals.com');
                $smtpPass = trim($_POST['smtp_pass'] ?? '');

                $_SESSION['bm_mail_pass'] = $smtpPass;
                $confContent = "<?php\ndefine('BM_SMTP_HOST', " . var_export($smtpHost, true) . ");\ndefine('BM_SMTP_PORT', " . var_export($smtpPort, true) . ");\ndefine('BM_SMTP_USER', " . var_export($smtpUser, true) . ");\ndefine('BM_SMTP_PASS', " . var_export($smtpPass, true) . ");\n";
                @file_put_contents(__DIR__ . '/mail-config.php', $confContent);
                logSystemAudit($pdo, 'security', "Updated Hostinger SMTP settings ({$smtpUser}:{$smtpPort})");

                $_SESSION['bm_flash_msg'] = 'Hostinger mail server configuration saved successfully.';
                $_SESSION['bm_flash_type'] = 'success';
            }
        } elseif ($action === 'test_smtp') {
            if (!hasPermission('manage_mail_config')) {
                $_SESSION['bm_flash_msg'] = 'Only Owner can test corporate SMTP configuration.';
                $_SESSION['bm_flash_type'] = 'danger';
            } else {
                $smtpHost = cleanInput($_POST['smtp_host'] ?? 'smtp.hostinger.com');
                $smtpPort = (int)($_POST['smtp_port'] ?? 465);
                $smtpUser = cleanInput($_POST['smtp_user'] ?? 'sales@balochistanminerals.com');
                $smtpPass = trim($_POST['smtp_pass'] ?? '');

                $testRes = testHostingerSmtp($smtpHost, $smtpPort, $smtpUser, $smtpPass);
                if ($testRes['success']) {
                    $_SESSION['bm_flash_msg'] = '✓ ' . $testRes['message'];
                    $_SESSION['bm_flash_type'] = 'success';
                } else {
                    $_SESSION['bm_flash_msg'] = 'SMTP Test Error: ' . $testRes['error'];
                    $_SESSION['bm_flash_type'] = 'danger';
                }
            }
        } elseif ($action === 'delete_inquiry') {
            if (!hasPermission('hard_delete')) {
                logSystemAudit($pdo, 'security', 'Blocked non-Owner delete_inquiry attempt');
                $_SESSION['bm_flash_msg'] = 'Only Owner can permanently delete buyer inquiries.';
                $_SESSION['bm_flash_type'] = 'danger';
            } else {
                $id = (int)($_POST['inquiry_id'] ?? 0);
                $stmt = $pdo->prepare('DELETE FROM inquiries WHERE id = ?');
                $stmt->execute([$id]);
                logSystemAudit($pdo, 'rfq', "Permanently deleted inquiry #{$id}", $id);
                $_SESSION['bm_flash_msg'] = 'Inquiry #' . $id . ' permanently deleted.';
                $_SESSION['bm_flash_type'] = 'warning';
            }
        }
    } else {
        $_SESSION['bm_flash_msg'] = 'Session expired. Please try again.';
        $_SESSION['bm_flash_type'] = 'danger';
    }

    // Post/Redirect/Get: Prevent browser form resubmission dialog on page refresh
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Fetch Metrics & Inquiries
$inquiries = [];
$totalLeads = 0;
$newLeads = 0;
$contactedLeads = 0;
$quotedLeads = 0;
$closedLeads = 0;
$pendingReviews = 0;

$filterStatus  = cleanInput($_GET['status'] ?? '');
$filterMineral = cleanInput($_GET['mineral'] ?? '');
$searchQuery   = cleanInput($_GET['q'] ?? '');

if ($pdo !== null) {
    $totalLeads     = (int)$pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
    $newLeads       = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'New'")->fetchColumn();
    $contactedLeads = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'Contacted'")->fetchColumn();
    $quotedLeads    = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status IN ('Quoted', 'Assay Sent', 'Sample Sent')")->fetchColumn();
    $closedLeads    = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'Contract Signed'")->fetchColumn();
    $pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'Pending'")->fetchColumn();
    $pendingDiscussions = (int)$pdo->query("SELECT COUNT(*) FROM blog_discussions WHERE status = 'Pending'")->fetchColumn();
    $totalProspects = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects")->fetchColumn();

    $sql = "SELECT i.*, 
            (SELECT COUNT(*) FROM email_messages em WHERE em.inquiry_id = i.id OR (em.to_email = i.email AND i.email != '') OR (em.from_email = i.email AND i.email != '')) AS email_count,
            (SELECT em.direction FROM email_messages em WHERE em.inquiry_id = i.id OR (em.to_email = i.email AND i.email != '') OR (em.from_email = i.email AND i.email != '') ORDER BY em.sent_at DESC, em.id DESC LIMIT 1) AS last_email_dir,
            (SELECT em.sent_at FROM email_messages em WHERE em.inquiry_id = i.id OR (em.to_email = i.email AND i.email != '') OR (em.from_email = i.email AND i.email != '') ORDER BY em.sent_at DESC, em.id DESC LIMIT 1) AS last_email_time
            FROM inquiries i WHERE 1=1";
    $params = [];

    if ($currentTier === 'Junior' && $currentUid > 0) {
        $sql .= " AND (i.assigned_user_id IS NULL OR i.assigned_user_id = ?)";
        $params[] = $currentUid;
    }
    if (!empty($filterStatus)) {
        $sql .= " AND i.status = ?";
        $params[] = $filterStatus;
    }
    if (!empty($filterMineral)) {
        list($mClause, $mParams) = buildMineralFilterClause($filterMineral, 'i.mineral_type');
        $sql .= $mClause;
        $params = array_merge($params, $mParams);
    }
    if (!empty($searchQuery)) {
        $sql .= " AND (i.buyer_name LIKE ? OR i.company_name LIKE ? OR i.email LIKE ? OR i.whatsapp LIKE ? OR i.country LIKE ?)";
        $searchTerm = "%" . $searchQuery . "%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    $sql .= " ORDER BY i.created_at DESC, i.id DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inquiries = $stmt->fetchAll();
}

/**
 * Compute Ball-in-Court Velocity Status for deal negotiations
 */
function getBallInCourtStatus($row) {
    if (($row['status'] ?? '') === 'Contract Signed') {
        return [
            'code' => 'closed',
            'label' => 'Contract Executed',
            'badgeClass' => 'badge-vel-closed',
            'icon' => 'bi-check-circle-fill'
        ];
    }
    $lastDir = $row['last_email_dir'] ?? null;
    $lastTime = !empty($row['last_email_time']) ? strtotime($row['last_email_time']) : (!empty($row['created_at']) ? strtotime($row['created_at']) : time());
    $daysDiff = (time() - $lastTime) / 86400;

    if (empty($lastDir) || $lastDir === 'inbound') {
        return [
            'code' => 'desk_turn',
            'label' => 'Desk Turn',
            'badgeClass' => 'badge-vel-desk',
            'icon' => 'bi-arrow-return-right'
        ];
    } else {
        if ($daysDiff >= 5) {
            return [
                'code' => 'stalled',
                'label' => 'Stalled (' . floor($daysDiff) . 'd)',
                'badgeClass' => 'badge-vel-stalled',
                'icon' => 'bi-hourglass-split'
            ];
        } else {
            return [
                'code' => 'buyer_turn',
                'label' => 'Awaiting Buyer',
                'badgeClass' => 'badge-vel-buyer',
                'icon' => 'bi-clock-history'
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Executive Trade Desk | Balochistan Minerals</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="admin-layout.css?v=<?= filemtime(__DIR__ . '/admin-layout.css') ?>">
  <style>
    /* Sovereign Executive Deal Velocity Badges */
    .badge-vel-desk,
    .badge-vel-buyer,
    .badge-vel-stalled,
    .badge-vel-closed {
      font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, sans-serif !important;
      font-size: 0.63rem !important;
      font-weight: 600 !important;
      letter-spacing: 0.04em !important;
      text-transform: uppercase !important;
      padding: 2.5px 8.5px !important;
      border-radius: 9999px !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 5.5px !important;
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03) !important;
      white-space: nowrap !important;
      line-height: 1.25 !important;
      cursor: pointer !important;
      user-select: none !important;
      -webkit-user-select: none !important;
      transition: all 0.15s ease !important;
    }

    /* Desk Turn - Action required by export desk (Cobalt / Royal Blue - zero brown, zero red) */
    .badge-vel-desk {
      background: #EFF6FF !important;
      color: #1D4ED8 !important;
      border: 1px solid rgba(29, 78, 216, 0.22) !important;
    }
    .badge-vel-desk:hover {
      background: #DBEAFE !important;
      border-color: rgba(29, 78, 216, 0.38) !important;
    }
    .badge-vel-desk::before {
      content: '';
      display: inline-block;
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: #2563EB !important;
      box-shadow: 0 0 0 1.5px rgba(37, 99, 235, 0.2) !important;
      flex-shrink: 0;
    }

    /* Awaiting Buyer - Ball in client court (Refined Linear/Stripe Violet) */
    .badge-vel-buyer {
      background: #F5F3FF !important;
      color: #6D28D9 !important;
      border: 1px solid rgba(109, 40, 217, 0.2) !important;
    }
    .badge-vel-buyer:hover {
      background: #EDE9FE !important;
      border-color: rgba(109, 40, 217, 0.36) !important;
    }
    .badge-vel-buyer::before {
      content: '';
      display: inline-block;
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: #8B5CF6 !important;
      box-shadow: 0 0 0 1.5px rgba(139, 92, 246, 0.2) !important;
      flex-shrink: 0;
    }

    /* Stalled - Inactivity warning (Clean Cool Slate) */
    .badge-vel-stalled {
      background: #F8FAFC !important;
      color: #475569 !important;
      border: 1px solid rgba(148, 163, 184, 0.25) !important;
    }
    .badge-vel-stalled:hover {
      background: #F1F5F9 !important;
      border-color: rgba(148, 163, 184, 0.4) !important;
    }
    .badge-vel-stalled::before {
      content: '';
      display: inline-block;
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: #94A3B8 !important;
      flex-shrink: 0;
    }

    /* Closed - Executed Contract (Executive Emerald) */
    .badge-vel-closed {
      background: #F0FDF4 !important;
      color: #15803D !important;
      border: 1px solid rgba(22, 163, 74, 0.24) !important;
    }
    .badge-vel-closed:hover {
      background: #DCFCE7 !important;
      border-color: rgba(22, 163, 74, 0.4) !important;
    }
    .badge-vel-closed::before {
      content: '';
      display: inline-block;
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: #16A34A !important;
      box-shadow: 0 0 0 1.5px rgba(22, 163, 74, 0.2) !important;
      flex-shrink: 0;
    }
    #dossierFlagChip, .dossier-flag-chip {
      width: 18px !important;
      height: 12px !important;
      max-width: 18px !important;
      max-height: 12px !important;
      min-width: 18px !important;
      min-height: 12px !important;
      border-radius: 2px;
      overflow: hidden;
      display: inline-flex !important;
      align-items: center;
      justify-content: center;
      border: none !important;
      flex-shrink: 0;
      vertical-align: middle;
      margin: 0 4px;
    }
    #dossierFlagChip svg, .dossier-flag-chip svg, .country-flag-svg {
      width: 18px !important;
      height: 12px !important;
      max-width: 18px !important;
      max-height: 12px !important;
      display: block !important;
      object-fit: cover !important;
    }

    :root {
      --brand-navy: #0F172A;
      --brand-slate: #1E293B;
      --brand-gold: #B48C36;
      --brand-gold-subtle: #F8F5EE;
      --brand-border: #E2E8F0;
      --brand-canvas: #F8FAFC;
      --brand-surface: #FFFFFF;
      --text-dark: #0F172A;
      --text-muted: #64748B;
      --whatsapp-green: #15803D;
      --sidebar-width: 280px;
    }

    * { box-sizing: border-box; }

    body {
      font-family: 'Poppins', sans-serif;
      font-size: 0.88rem;
      color: var(--text-dark);
      background-color: var(--brand-canvas);
      margin: 0;
      padding: 0;
      -webkit-font-smoothing: antialiased;
    }

    h1, h2, h3, h4, h5, h6, .brand-font {
      font-family: 'Montserrat', sans-serif;
      letter-spacing: -0.015em;
    }

    .app-wrapper {
      display: flex;
      min-height: 100vh;
      width: 100%;
    }

    /* Left Sidebar - Crisp Clean Corporate */
    .app-sidebar {
      width: var(--sidebar-width);
      background: var(--brand-surface);
      border-right: 1px solid var(--brand-border);
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0;
      bottom: 0;
      left: 0;
      z-index: 1040;
    }



    .sidebar-nav {
      padding: 16px 12px;
      flex: 1;
      overflow-y: auto;
    }

    .sidebar-section-title {
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #94A3B8;
      padding: 12px 10px 6px;
    }

    .sidebar-link {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 9px 12px;
      border-radius: 6px;
      color: #475569;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.82rem;
      font-weight: 600;
      text-decoration: none;
      margin-bottom: 2px;
      transition: all 0.15s ease;
    }

    .sidebar-link i {
      font-size: 1.05rem;
      margin-right: 10px;
      color: #64748B;
    }

    .sidebar-link:hover {
      background: #F1F5F9;
      color: var(--brand-navy);
    }

    .sidebar-link.active {
      background: #F8FAFC;
      color: var(--brand-navy);
      border: 1px solid var(--brand-border);
      position: relative;
    }

    .sidebar-link.active::before {
      content: '';
      position: absolute;
      left: -12px;
      top: 6px;
      bottom: 6px;
      width: 3.5px;
      background: var(--brand-gold);
      border-radius: 0 3px 3px 0;
    }

    .sidebar-link.active i {
      color: var(--brand-gold);
    }

    .sidebar-footer {
      padding: 14px 18px;
      border-top: 1px solid var(--brand-border);
      background: #FAFCFE;
    }

    /* Main Content Area */
    .app-main {
      margin-left: var(--sidebar-width);
      flex: 1;
      display: flex;
      flex-direction: column;
      min-width: 0;
    }

    .top-bar {
      background: var(--brand-surface);
      border-bottom: 1px solid var(--brand-border);
      padding: 10px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 1030;
    }

    .content-canvas {
      padding: 16px 24px;
      flex: 1;
    }

    /* Compact Executive Stat Strip */
    .stat-card {
      background: #FFFFFF;
      border: 1px solid var(--brand-border);
      border-radius: 8px;
      padding: 10px 16px;
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
      transition: all 0.15s ease;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 100%;
    }

    .stat-card:hover {
      border-color: #CBD5E1;
      box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
    }

    .stat-card__info {
      min-width: 0;
    }

    .stat-card__label {
      font-size: 0.67rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #64748B;
      margin-bottom: 2px;
      white-space: nowrap;
    }

    .stat-card__subtext {
      font-size: 0.68rem;
      color: #94A3B8;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .stat-card__number {
      font-family: 'Montserrat', sans-serif;
      font-size: 1.55rem;
      font-weight: 800;
      color: var(--brand-navy);
      line-height: 1;
      margin: 0;
      padding-left: 10px;
      font-variant-numeric: tabular-nums;
    }

    /* Status Badges - Elegant Corporate System */
    .status-badge {
      font-family: 'Montserrat', sans-serif;
      font-size: 0.72rem;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 4px;
      letter-spacing: 0.02em;
      display: inline-block;
      white-space: nowrap;
    }

    .status-New {
      background: #0F172A;
      color: #FFFFFF;
      border: 1px solid #0F172A;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.2);
    }

    .status-Contacted {
      background: #F1F5F9;
      color: #334155;
      border: 1px solid #CBD5E1;
    }

    .status-Assay_Sent {
      background: #F8FAFC;
      color: #0F172A;
      border: 1px solid #94A3B8;
    }

    .status-Quoted {
      background: #FEF3C7;
      color: #92400E;
      border: 1px solid #FDE68A;
    }

    .status-Contract_Signed {
      background: #ECFDF5;
      color: #065F46;
      border: 1px solid #A7F3D0;
    }

    .status-Closed, .status-Archived {
      background: #F8FAFC;
      color: #64748B;
      border: 1px solid #E2E8F0;
    }

    /* Main Leads Table */
    .table-panel {
      background: var(--brand-surface);
      border: 1px solid var(--brand-border);
      border-radius: 10px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.02);
      overflow: hidden;
      position: relative;
    }

    .table-panel-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--brand-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #FFFFFF;
      position: sticky;
      top: 0;
      z-index: 20;
    }

    /* Fixed Scrollable Viewport with Persistent Headers */
    .table-scroll-container {
      max-height: 520px;
      overflow-y: auto;
      overflow-x: auto;
      position: relative;
    }

    /* Custom High-Contrast Slate Scrollbar */
    .table-scroll-container::-webkit-scrollbar {
      width: 8px;
      height: 9px;
    }
    .table-scroll-container::-webkit-scrollbar-track {
      background: #E2E8F0;
    }
    .table-scroll-container::-webkit-scrollbar-thumb {
      background: #64748B;
      border-radius: 4px;
    }
    .table-scroll-container::-webkit-scrollbar-thumb:hover {
      background: #475569;
    }

    .table-clean {
      width: 100%;
      margin: 0;
      border-collapse: separate;
      border-spacing: 0;
    }

    .table-clean thead th {
      background: #F8FAFC !important;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #475569;
      padding: 12px 16px;
      border-bottom: 1px solid var(--brand-border);
      position: sticky;
      top: 0;
      z-index: 10;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    }

    .table-clean td {
      padding: 14px 16px;
      border-bottom: 1px solid #F1F5F9;
      vertical-align: middle;
      font-size: 0.85rem;
    }

    .table-clean tr:hover td {
      background-color: #FAFCFE;
    }

    .mineral-spec-tag {
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      font-size: 0.78rem;
      color: var(--brand-navy);
      background: #F1F5F9;
      border: 1px solid #E2E8F0;
      padding: 3px 8px;
      border-radius: 4px;
      display: inline-block;
    }

    .country-pill {
      font-size: 0.72rem;
      font-weight: 600;
      color: #475569;
      background: #F1F5F9;
      padding: 2px 6px;
      border-radius: 4px;
    }

    /* Action Buttons (Flat, Zero Shade, Neutral Elegant Line Effect) */
    .btn-action-email {
      background: #FFFFFF !important;
      color: #0F172A !important;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.74rem;
      font-weight: 600;
      height: 32px;
      padding: 0 12px;
      border-radius: 6px;
      border: 1px solid #CBD5E1 !important;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      box-shadow: none !important;
      transition: all 0.15s ease;
      cursor: pointer;
      white-space: nowrap;
    }

    .btn-action-email i {
      color: #475569 !important;
      font-size: 0.85rem;
      line-height: 1;
      transition: color 0.15s ease;
    }

    .btn-action-email:hover {
      background: #F8FAFC !important;
      color: #0F172A !important;
      border-color: #0F172A !important;
      box-shadow: none !important;
    }

    .btn-action-email:hover i {
      color: #0F172A !important;
    }

    .btn-action-wa {
      background: #15803D;
      color: #FFFFFF !important;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.74rem;
      font-weight: 600;
      height: 32px;
      padding: 0 12px;
      border-radius: 6px;
      border: 1px solid #15803D;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      box-shadow: none !important;
      transition: all 0.15s ease;
      white-space: nowrap;
    }

    .btn-action-wa:hover {
      background: #166534;
      border-color: #166534;
      box-shadow: none !important;
    }

    .btn-action-quote {
      background: #FFFFFF !important;
      color: #0F172A !important;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.74rem;
      font-weight: 600;
      height: 32px;
      padding: 0 11px;
      border-radius: 6px;
      border: 1px solid #CBD5E1 !important;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      box-shadow: none !important;
      transition: all 0.15s ease;
      white-space: nowrap;
      cursor: pointer;
    }

    .btn-action-quote i {
      color: #475569 !important;
      font-size: 0.88rem;
      line-height: 1;
      transition: color 0.15s ease;
    }

    .btn-action-quote:hover {
      background: #F8FAFC !important;
      color: #0F172A !important;
      border-color: #0F172A !important;
      box-shadow: none !important;
    }

    .btn-action-quote:hover i {
      color: #0F172A !important;
    }

    .btn-action-quote:active {
      box-shadow: none !important;
    }

    /* Buyer Direct Email & Message Snippet Styles */
    .buyer-email-link {
      color: #0284C7;
      text-decoration: none;
      font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
      font-size: 0.77rem;
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: calc(100% - 26px);
      display: inline-block;
      vertical-align: middle;
      transition: color 0.15s ease;
    }
    .buyer-email-link:hover {
      color: #0369A1;
      text-decoration: underline;
    }
    .btn-copy-clip {
      background: transparent;
      border: none;
      color: #94A3B8;
      padding: 0 2px;
      font-size: 0.72rem;
      cursor: pointer;
      line-height: 1;
      transition: color 0.15s ease;
    }
    .btn-copy-clip:hover {
      color: var(--brand-navy);
    }
    .inquiry-message-snippet a {
      transition: opacity 0.15s ease;
    }
    .inquiry-message-snippet a:hover {
      opacity: 0.85;
    }
  </style>
</head>
<body>

  <div class="app-wrapper">

    <!-- 1. LEFT EXECUTIVE SIDEBAR (Slideable & Collapsible) -->
    <aside class="app-sidebar" id="appSidebar">
      <div class="sidebar-header">
        <a href="../" target="_blank" class="sidebar-logo-wrap" title="Balochistan Minerals - View Live Site">
          <picture style="display: contents;"><source media="(min-width: 769px)" srcset="../images/new-logo-dekstop.png" width="723" height="278"><img src="../images/new-logo.png" alt="Balochistan Minerals" class="sidebar-logo" onerror="this.style.display='none'"></picture>
          <span class="sidebar-badge">TRADE DESK PORTAL</span>
        </a>
        <button type="button" id="sidebarCloseBtn" class="btn-sidebar-panel-toggle" aria-label="Toggle Sidebar" title="Collapse Sidebar (Ctrl + B)">
          <i class="bi bi-layout-sidebar-inset"></i>
        </button>
      </div>

      <nav class="sidebar-nav">
        <div class="sidebar-section-title">Lead & Deal Engine</div>
        <a href="dashboard.php" class="sidebar-link active">
          <div><i class="bi bi-inbox-fill"></i> Inbound RFQs</div>
          <?php if ($newLeads > 0): ?>
            <span class="badge bg-dark font-monospace" style="font-size: 0.7rem;"><?= $newLeads ?></span>
          <?php endif; ?>
        </a>
        <a href="email-desk.php" class="sidebar-link">
          <div><i class="bi bi-envelope"></i> Email Reply Desk</div>
          <span class="badge-sidebar-hostinger">HOSTINGER</span>
        </a>
        <a href="prospects.php" class="sidebar-link">
          <div><i class="bi bi-bullseye"></i> Buyer Directory</div>
          <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.7rem;"><?= $totalProspects ?></span>
        </a>
        <a href="radar.php" class="sidebar-link">
          <div><i class="bi bi-broadcast-pin"></i> Live Visitor Radar</div>
          <span class="badge bg-success font-monospace" style="font-size: 0.65rem;">LIVE</span>
        </a>

        <div class="sidebar-section-title">Trade Analytics & Intelligence</div>
        <a href="analytics.php" class="sidebar-link">
          <div><i class="bi bi-graph-up"></i> Intelligence Hub</div>
          <span class="badge-sidebar-intel">PROJ</span>
        </a>

        <div class="sidebar-section-title">Logistics & Trade Operations</div>
        <a href="deals.php" class="sidebar-link">
          <div><i class="bi bi-shield-check"></i> Deals &amp; Evidence Vault</div>
          <span class="badge-sidebar-intel">VAULT</span>
        </a>
        <a href="stockpiles.php" class="sidebar-link">
          <div><i class="bi bi-boxes"></i> Stockpiles & Shipments</div>
        </a>
        <a href="prices.php" class="sidebar-link">
          <div><i class="bi bi-cash-stack"></i> Mineral Spot Prices</div>
        </a>

        <div class="sidebar-section-title">Buyer Engagement & Content</div>
        <a href="reviews.php" class="sidebar-link">
          <div><i class="bi bi-star-fill"></i> Customer Reviews</div>
          <?php if ($pendingReviews > 0): ?>
            <span class="badge bg-secondary font-monospace" style="font-size: 0.7rem;"><?= $pendingReviews ?></span>
          <?php endif; ?>
        </a>
        <a href="blog-discussions.php" class="sidebar-link">
          <div><i class="bi bi-chat-square-text-fill"></i> Blog Discussions</div>
          <?php if (!empty($pendingDiscussions) && $pendingDiscussions > 0): ?>
            <span class="badge bg-warning text-dark font-monospace" style="font-size: 0.7rem;"><?= $pendingDiscussions ?></span>
          <?php endif; ?>
        </a>
        <a href="faqs.php" class="sidebar-link">
          <div><i class="bi bi-question-circle-fill"></i> Dynamic FAQs</div>
        </a>

        <div class="sidebar-section-title">System & Links</div>
        <a href="../" target="_blank" class="sidebar-link">
          <div><i class="bi bi-globe2"></i> Live Website</div>
          <i class="bi bi-box-arrow-up-right small text-muted"></i>
        </a>
      </nav>

      <div class="sidebar-footer">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="fw-bold small" style="color: var(--brand-navy);"><?= htmlspecialchars($user['full_name']) ?></div>
            <div class="text-muted" style="font-size: 0.7rem;">Commercial Export Desk</div>
          </div>
          <a href="logout.php" class="btn btn-sm btn-outline-secondary p-1 px-2" title="Sign Out">
            <i class="bi bi-power"></i>
          </a>
        </div>
      </div>
    </aside>

    <!-- Mobile / Tablet Backdrop Overlay -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- 2. MAIN WORKSPACE CANVAS -->
    <div class="app-main">

      <header class="top-bar">
        <div class="topbar-left">
          <button type="button" id="sidebarToggle" class="btn-sidebar-toggle" title="Toggle Sidebar (Ctrl + B)">
            <i class="bi bi-layout-sidebar-inset"></i>
          </button>
          <div class="topbar-title-wrap">
            <h1 class="topbar-title">Commercial Operations & Leads</h1>
            <div class="topbar-subtitle">Balochistan Minerals (Pvt) Ltd • Export Desk Management</div>
          </div>
        </div>

        <div class="topbar-right">
          <?php if (hasPermission('manage_mail_config')): ?>
          <button type="button" class="btn-topbar-action" data-bs-toggle="modal" data-bs-target="#mailConfigModal">
            <i class="bi bi-gear"></i> <span>Mail Settings</span>
          </button>
          <?php endif; ?>
          <div class="pill-status d-none d-xl-inline-flex" title="Export Port Corridors Active: Gwadar Port, KPT & Port Qasim">
            <span class="status-dot dot-green"></span>
            <span>Port Corridors: Gwadar, KPT & Port Qasim</span>
          </div>
          <div class="pill-status pill-clock" title="Official Pakistan Standard Time (PKT, UTC+5)">
            <span class="status-dot dot-green"></span>
            <span class="clock-date"><?= date('M d') ?> •</span>
            <span id="livePktClock"><?= date('h:i:s A') ?></span>
            <span class="clock-tz">PKT</span>
          </div>
        </div>
      </header>

      <main class="content-canvas">
        <?php if ($pdo !== null && hasPermission('approve_emails') && ($dashPend = getPendingApprovalCount($pdo)) > 0): ?>
        <div class="alert alert-warning d-flex align-items-center justify-content-between py-2 px-3 mb-3 small shadow-sm">
          <div><i class="bi bi-envelope-exclamation-fill me-2"></i><strong><?= (int)$dashPend ?></strong> Junior reply draft(s) awaiting Manager / Owner Maker-Checker review before dispatch.</div>
          <a href="team.php#approvals" class="btn btn-sm btn-dark fw-semibold py-0.5 px-2.5">Review &amp; Approve</a>
        </div>
        <?php endif; ?>

        <!-- Executive Metrics Strip (Executive Neutral Styling, Zero Shade) -->
        <div class="row g-2.5 g-md-3 mb-3">
          <div class="col-6 col-lg-3">
            <div class="metric-card metric-leads">
              <div class="metric-card-top">
                <span class="metric-card-label">Total Inbound Leads</span>
                <div class="metric-card-icon"><i class="bi bi-building-check"></i></div>
              </div>
              <div class="metric-card-bottom">
                <h3 class="metric-card-value" id="metricTotalLeads"><?= $totalLeads ?></h3>
                <span class="metric-card-subtext d-none d-sm-inline">Registered RFQs</span>
              </div>
            </div>
          </div>

          <div class="col-6 col-lg-3">
            <div class="metric-card metric-action">
              <div class="metric-card-top">
                <span class="metric-card-label">New Inquiries (Action)</span>
                <div class="metric-card-icon"><i class="bi bi-send-check"></i></div>
              </div>
              <div class="metric-card-bottom">
                <h3 class="metric-card-value" id="metricNewLeads"><?= $newLeads ?></h3>
                <span class="metric-card-subtext d-none d-sm-inline">Needs Outreach</span>
              </div>
            </div>
          </div>

          <div class="col-6 col-lg-3">
            <div class="metric-card metric-quotes">
              <div class="metric-card-top">
                <span class="metric-card-label">Active Negotiations</span>
                <div class="metric-card-icon"><i class="bi bi-chat-quote"></i></div>
              </div>
              <div class="metric-card-bottom">
                <h3 class="metric-card-value" id="metricQuotedLeads"><?= $quotedLeads ?></h3>
                <span class="metric-card-subtext d-none d-sm-inline">Assays & Quotes</span>
              </div>
            </div>
          </div>

          <div class="col-6 col-lg-3">
            <div class="metric-card metric-contracts">
              <div class="metric-card-top">
                <span class="metric-card-label">Contracts Executed</span>
                <div class="metric-card-icon"><i class="bi bi-award"></i></div>
              </div>
              <div class="metric-card-bottom">
                <h3 class="metric-card-value" id="metricClosedLeads"><?= $closedLeads ?></h3>
                <span class="metric-card-subtext d-none d-sm-inline">L/C Ready</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="filter-toolbar">
          <form method="GET" action="dashboard.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
              <div class="input-group input-group-sm search-input-group">
                <span class="input-group-text py-1.5"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control py-1.5" placeholder="Search buyer, company, country, WhatsApp..." value="<?= htmlspecialchars($searchQuery) ?>">
                <?php if (!empty($searchQuery)): ?>
                  <a href="dashboard.php" class="input-group-text text-decoration-none py-1.5" title="Clear search"><i class="bi bi-x-circle text-muted"></i></a>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-6 col-md-2">
              <select name="status" class="form-select form-select-sm py-1.5" onchange="this.form.submit()">
                <option value="">All Deal Stages</option>
                <option value="New" <?= $filterStatus === 'New' ? 'selected' : '' ?>>New</option>
                <option value="Contacted" <?= $filterStatus === 'Contacted' ? 'selected' : '' ?>>Contacted</option>
                <option value="Assay Sent" <?= $filterStatus === 'Assay Sent' ? 'selected' : '' ?>>Assay Sent</option>
                <option value="Quoted" <?= $filterStatus === 'Quoted' ? 'selected' : '' ?>>Quoted</option>
                <option value="Sample Sent" <?= $filterStatus === 'Sample Sent' ? 'selected' : '' ?>>Sample Sent</option>
                <option value="Contract Signed" <?= $filterStatus === 'Contract Signed' ? 'selected' : '' ?>>Contract Signed</option>
                <option value="Closed" <?= $filterStatus === 'Closed' ? 'selected' : '' ?>>Closed</option>
              </select>
            </div>
            <div class="col-6 col-md-4">
              <select name="mineral" class="form-select form-select-sm py-1.5" onchange="this.form.submit()" title="Filter by Mineral or Commodity">
                <?= renderCommodityOptions($filterMineral, true, 'All Commodities') ?>
              </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-1.5">
              <button type="submit" class="btn-filter-action">
                <i class="bi bi-funnel"></i> <span>Filter</span>
              </button>
              <?php if (!empty($searchQuery) || !empty($filterStatus) || !empty($filterMineral)): ?>
                <a href="dashboard.php" class="btn btn-sm btn-outline-secondary py-1.5 px-2.5" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
              <?php endif; ?>
            </div>
          </form>

          <!-- Quick Stage Filters (Compact, Proper Spacing, Zero Shade) -->
          <div class="quick-view-wrapper">
            <span class="quick-view-label"><i class="bi bi-tag-fill"></i> Quick View:</span>
            <a href="dashboard.php" class="quick-filter-pill <?= empty($filterStatus) ? 'active' : '' ?>">All (<span id="quickFilterAllCount"><?= $totalLeads ?></span>)</a>
            <a href="dashboard.php?status=New" class="quick-filter-pill <?= $filterStatus === 'New' ? 'active' : '' ?>">New (<span id="quickFilterNewCount"><?= $newLeads ?></span>)</a>
            <a href="dashboard.php?status=Contacted" class="quick-filter-pill <?= $filterStatus === 'Contacted' ? 'active' : '' ?>">Contacted (<span id="quickFilterContactedCount"><?= $contactedLeads ?></span>)</a>
            <a href="dashboard.php?status=Quoted" class="quick-filter-pill <?= $filterStatus === 'Quoted' ? 'active' : '' ?>">Quoted (<span id="quickFilterQuotedCount"><?= $quotedLeads ?></span>)</a>
            <a href="dashboard.php?status=Contract+Signed" class="quick-filter-pill <?= $filterStatus === 'Contract Signed' ? 'active' : '' ?>">Executed (<span id="quickFilterExecutedCount"><?= $closedLeads ?></span>)</a>
          </div>
        </div>

        <!-- Inbound RFQ Pipeline Table -->
        <div class="table-panel">
          <div class="table-panel-header">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <h2 class="table-panel-title">
                <i class="bi bi-table text-secondary"></i> Commercial Inquiries & RFQs
              </h2>
              <span class="badge rounded-pill bg-dark font-monospace px-2.5 py-1" id="inquiriesTableCount"><?= count($inquiries) ?></span>
              <span class="table-scroll-hint" title="Use mouse wheel or drag horizontally to view all columns">
                <i class="bi bi-arrows-expand-vertical"></i> Scroll ↔
              </span>
              <!-- Real-Time Live Sync Indicator -->
              <div class="live-sync-pill" id="liveSyncStatusBadge" onclick="checkLiveUpdates(true)" title="Real-Time Auto Sync Active • Click to refresh">
                <span class="live-sync-dot"></span>
                <span id="liveSyncStatusText">Live Sync Active</span>
              </div>
            </div>
            <div class="table-panel-actions">
              <button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1.5 fw-semibold px-3 py-1.5" style="border-radius: 6px;" id="btnSyncMail" onclick="syncCorporateMail()" title="Check Hostinger IMAP Inboxes (sales@ and info@)">
                <i class="bi bi-arrow-repeat"></i> <span>Sync Emails</span>
              </button>
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary py-1.5 d-none" id="btnResetRfqCols" onclick="resetRfqColumnWidths()" title="Reset table columns to balanced default (auto-expanding Commodity &amp; Specs)">
                  <i class="bi bi-layout-three-columns"></i> <span class="d-none d-sm-inline ms-1">Reset Widths</span>
                </button>
                <button type="button" class="btn btn-outline-secondary py-1.5" id="btnToggleTableHeight" onclick="toggleTableHeight()" title="Toggle Scroll View vs Expanded View">
                  <i class="bi bi-arrows-expand"></i> <span class="d-none d-sm-inline ms-1">Expand</span>
                </button>
                <button type="button" class="btn btn-outline-secondary py-1.5" onclick="window.print()" title="Print RFQ Register">
                  <i class="bi bi-printer"></i> <span class="d-none d-sm-inline ms-1">Print</span>
                </button>
              </div>
            </div>
          </div>

          <div class="table-responsive table-scroll-container" id="rfqTableContainer">
            <table class="table-clean" id="rfqDataGrid">
              <colgroup id="rfqColGroup">
                <col data-col="buyer" style="width: 258px;">
                <col data-col="commodity" style="width: auto;">
                <col data-col="port" style="width: 112px;">
                <col data-col="received" style="width: 112px;">
                <col data-col="stage" style="width: 118px;">
                <col data-col="quote" style="width: 108px;">
                <col data-col="actions" style="width: 296px;">
              </colgroup>
              <thead>
                <tr>
                  <th data-col="buyer">Buyer &amp; Company<span class="col-resize-handle" data-resize-col="buyer" title="Drag to resize column • Double-click to reset"></span></th>
                  <th data-col="commodity">Commodity &amp; Specs<span class="col-resize-handle" data-resize-col="commodity" title="Drag to resize column • Double-click for auto-expand"></span></th>
                  <th data-col="port">Port<span class="col-resize-handle" data-resize-col="port" title="Drag to resize column • Double-click to reset"></span></th>
                  <th data-col="received">Received<span class="col-resize-handle" data-resize-col="received" title="Drag to resize column • Double-click to reset"></span></th>
                  <th data-col="stage">Deal Stage<span class="col-resize-handle" data-resize-col="stage" title="Drag to resize column • Double-click to reset"></span></th>
                  <th data-col="quote">Spot Quote<span class="col-resize-handle" data-resize-col="quote" title="Drag to resize column • Double-click to reset"></span></th>
                  <th data-col="actions" class="text-end">Actions<span class="col-resize-handle" data-resize-col="actions" title="Drag to resize column • Double-click to reset"></span></th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($inquiries)): ?>
                  <tr id="noInquiriesRow">
                    <td colspan="7" class="text-center py-5 text-muted">
                      No inquiries found matching your filter criteria.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php 
                    $csrf = getCsrfToken();
                    foreach ($inquiries as $row): 
                      echo renderInquiryRow($row, $csrf);
                    endforeach; 
                  ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Modal: Hostinger Mail Server Settings -->
        <?php if (hasPermission('manage_mail_config')): ?>
        <div class="modal fade" id="mailConfigModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
            <div class="modal-content">
              <form method="POST" action="dashboard.php">
                <input type="hidden" name="action" value="save_mail_settings">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <div class="modal-header py-2.5 px-3">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge-rfq-mild">
                      <i class="bi bi-gear-fill"></i> SERVER CONFIG
                    </span>
                    <h6 class="modal-title fw-bold brand-font mb-0" style="color: var(--brand-navy); font-size: 0.95rem;">
                      Hostinger Corporate Mailbox Settings
                    </h6>
                  </div>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-3.5">
                  <div class="p-2.5 rounded bg-light border mb-3" style="font-size: 0.78rem; color: #475569; line-height: 1.45;">
                    <strong class="text-dark"><i class="bi bi-info-circle-fill text-primary me-1"></i> Two-Way Commercial Email Integration:</strong><br>
                    Configure your Hostinger business email credentials to dispatch official export letters and sync incoming RFQs directly inside the Trade Desk.
                  </div>

                  <div class="row g-2 mb-2">
                    <div class="col-8">
                      <label class="form-label">SMTP Host</label>
                      <input type="text" name="smtp_host" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars(defined('BM_SMTP_HOST') ? BM_SMTP_HOST : 'smtp.hostinger.com') ?>" required>
                    </div>
                    <div class="col-4">
                      <label class="form-label">Port</label>
                      <select name="smtp_port" class="form-select form-select-sm font-monospace">
                        <option value="465" selected>465 (SSL)</option>
                        <option value="587">587 (TLS)</option>
                      </select>
                    </div>
                  </div>

                  <div class="mb-2">
                    <label class="form-label">Corporate Email Address (Username)</label>
                    <input type="email" name="smtp_user" class="form-control form-control-sm font-monospace fw-bold" value="<?= htmlspecialchars(defined('BM_SMTP_USER') ? BM_SMTP_USER : 'sales@balochistanminerals.com') ?>" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Mailbox Password</label>
                    <input type="password" name="smtp_pass" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($_SESSION['bm_mail_pass'] ?? (defined('BM_SMTP_PASS') ? BM_SMTP_PASS : '')) ?>" placeholder="Enter Hostinger email password">
                    <div class="text-muted mt-1" style="font-size: 0.68rem;">Saved securely in your encrypted session / local config.</div>
                  </div>

                  <div class="p-2 rounded bg-white border d-flex align-items-center justify-content-between">
                    <span class="small text-muted font-monospace" style="font-size: 0.72rem;">Verify handshake with smtp.hostinger.com</span>
                    <button type="submit" name="action" value="test_smtp" class="btn btn-sm btn-outline-dark fw-bold py-1 px-2.5" style="font-size: 0.74rem;">
                      <i class="bi bi-arrow-repeat me-1"></i> Test Connection
                    </button>
                  </div>
                </div>

                <div class="modal-footer d-flex justify-content-end gap-2 py-2.5 px-3 bg-light border-top">
                  <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-1.5" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-sm btn-dark px-3.5 py-1.5 fw-bold text-white shadow-sm" style="background: #0F172A; border-color: #0F172A; border-radius: 6px;">
                    <i class="bi bi-check2 me-1"></i> Save Mail Settings
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </main>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    /* ============================================================
       REAL-TIME ACTIVE TRADE DESK AUTO-SYNC & HEARTBEAT CONTROLLER
       ============================================================ */
    let lastSeenInquiryId = <?= (int)($inquiries[0]['id'] ?? 0) ?>;
    const activeFilterStatus = <?= json_encode($filterStatus) ?>;
    const activeFilterMineral = <?= json_encode($filterMineral) ?>;
    const activeSearchQuery = <?= json_encode($searchQuery) ?>;

    let pollIntervalSeconds = 15;
    let countdownRemaining = pollIntervalSeconds;
    let countdownTimer = null;
    let isAudioChimeEnabled = localStorage.getItem('bm_chime_enabled') !== '0';

    // Web Audio API Chime Synthesizer (Zero External Dependencies)
    function playExecutiveChime() {
      if (!isAudioChimeEnabled) return;
      try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const now = ctx.currentTime;
        
        // Tone 1: 587.33 Hz (D5)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, now);
        gain1.gain.setValueAtTime(0.08, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.18);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.18);

        // Tone 2: 880.00 Hz (A5)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(880.00, now + 0.09);
        gain2.gain.setValueAtTime(0.12, now + 0.09);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.38);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.09);
        osc2.stop(now + 0.38);
      } catch (e) {}
    }

    function toggleChimeAudio() {
      isAudioChimeEnabled = !isAudioChimeEnabled;
      try {
        localStorage.setItem('bm_chime_enabled', isAudioChimeEnabled ? '1' : '0');
      } catch(e) {}
      updateChimeUi();
    }

    function updateChimeUi() {
      const icon = document.getElementById('chimeIcon');
      const btn = document.getElementById('btnToggleChime');
      if (!icon || !btn) return;
      if (isAudioChimeEnabled) {
        icon.className = 'bi bi-volume-up';
        btn.classList.remove('muted');
        btn.title = 'Audio Chime ON (Click to mute)';
      } else {
        icon.className = 'bi bi-volume-mute';
        btn.classList.add('muted');
        btn.title = 'Audio Chime MUTED (Click to unmute)';
      }
    }

    // Unified Industry-Standard Toast Alert (delegates to admin-layout.js BMNotify)
    function showLiveToast(title, message, inquiryId, type = 'info', openBell = false) {
      if (window.BMNotify && typeof window.BMNotify.toast === 'function') {
        window.BMNotify.toast({
          type,
          title,
          message,
          inquiryId: inquiryId || null,
          openBell
        });
      }
    }

    function animateNumber(elementId, targetValue) {
      const el = document.getElementById(elementId);
      if (!el) return;
      const current = parseInt(el.textContent.replace(/[^\d]/g, ''), 10) || 0;
      if (current === targetValue) return;

      const duration = 600;
      const start = performance.now();

      function step(now) {
        const progress = Math.min((now - start) / duration, 1);
        const value = Math.round(current + (targetValue - current) * progress);
        el.textContent = value;
        if (progress < 1) {
          requestAnimationFrame(step);
        } else {
          el.textContent = targetValue;
        }
      }
      requestAnimationFrame(step);
    }

    let isPollingInProgress = false;

    async function checkLiveUpdates(isManual = false) {
      if (isPollingInProgress) return;
      isPollingInProgress = true;

      const timerEl = document.getElementById('liveSyncTimer');
      if (timerEl && isManual) timerEl.textContent = 'Syncing...';

      try {
        const url = `api-live-poll.php?last_seen_id=${lastSeenInquiryId}&status=${encodeURIComponent(activeFilterStatus)}&mineral=${encodeURIComponent(activeFilterMineral)}&q=${encodeURIComponent(activeSearchQuery)}`;
        const res = await fetch(url);
        const data = await res.json();

        if (data && data.success) {
          // 1. Update Metrics smoothly
          if (data.metrics) {
            animateNumber('metricTotalLeads', data.metrics.totalLeads);
            animateNumber('metricNewLeads', data.metrics.newLeads);
            animateNumber('metricQuotedLeads', data.metrics.quotedLeads);
            animateNumber('metricClosedLeads', data.metrics.closedLeads);

            animateNumber('quickFilterAllCount', data.metrics.totalLeads);
            animateNumber('quickFilterNewCount', data.metrics.newLeads);
            animateNumber('quickFilterContactedCount', data.metrics.contactedLeads);
            animateNumber('quickFilterQuotedCount', data.metrics.quotedLeads);
            animateNumber('quickFilterExecutedCount', data.metrics.closedLeads);
          }

          // 2. Prepend new inquiries if available
          if (data.has_new && data.rows_html) {
            const tbody = document.querySelector('#rfqTableContainer table.table-clean tbody');
            if (tbody) {
              const emptyRow = document.getElementById('noInquiriesRow');
              if (emptyRow) emptyRow.remove();

              const temp = document.createElement('tbody');
              temp.innerHTML = data.rows_html;

              const newRows = Array.from(temp.querySelectorAll('tr[data-inquiry-id]'));
              newRows.reverse().forEach(row => {
                const rowId = row.getAttribute('data-inquiry-id');
                if (!document.getElementById('inquiryRow_' + rowId)) {
                  tbody.insertBefore(row, tbody.firstChild);
                }
              });

              if (window.bootstrap && bootstrap.Tooltip) {
                tbody.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                  try { new bootstrap.Tooltip(el); } catch(e) {}
                });
              }

              const tableCount = document.getElementById('inquiriesTableCount');
              if (tableCount) {
                const currentCount = tbody.querySelectorAll('tr[data-inquiry-id]').length;
                tableCount.textContent = currentCount;
              }

              playExecutiveChime();

              if (data.previews && data.previews.length > 0) {
                if (data.previews.length > 2) {
                  showLiveToast(
                    `${data.previews.length} New Inquiries Received`,
                    `Latest: ${data.previews[0].buyer_name} (${data.previews[0].mineral_type}) + ${data.previews.length - 1} more added to pipeline.`,
                    null,
                    'info',
                    true
                  );
                } else {
                  data.previews.forEach(p => {
                    showLiveToast(
                      'New RFQ Received #' + p.id,
                      `${p.buyer_name} (${p.company_name}) • ${p.mineral_type} • ${p.country}`,
                      p.id,
                      'info'
                    );
                  });
                }
                if (window.BMNotify) window.BMNotify.refresh();
              }
            }
          }

          // 3. Update last seen ID
          if (data.latest_id && data.latest_id > lastSeenInquiryId) {
            lastSeenInquiryId = data.latest_id;
          }
        }
      } catch (err) {
        console.warn('Live sync poll notice:', err);
      } finally {
        isPollingInProgress = false;
        countdownRemaining = pollIntervalSeconds;
        if (timerEl) timerEl.textContent = countdownRemaining + 's';
      }
    }

    function startHeartbeatLoop() {
      clearInterval(countdownTimer);
      countdownTimer = setInterval(() => {
        countdownRemaining--;
        const timerEl = document.getElementById('liveSyncTimer');
        if (timerEl) {
          timerEl.textContent = (countdownRemaining > 0 ? countdownRemaining : 0) + 's';
        }
        if (countdownRemaining <= 0) {
          countdownRemaining = pollIntervalSeconds;
          checkLiveUpdates(false);
        }
      }, 1000);
    }

    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'hidden') {
        pollIntervalSeconds = 45;
      } else {
        pollIntervalSeconds = 15;
        countdownRemaining = 2;
      }
    });

    async function syncCorporateMail(silent = false) {
      const btn = document.getElementById('btnSyncMail');
      const originalText = btn ? btn.innerHTML : '';
      if (btn && !silent) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Checking Mail...';
      }

      try {
        const res = await fetch(silent ? 'mail-sync.php?action=sync' : 'mail-sync.php?action=sync&manual=1');
        const data = await res.json();

        const syncedTotal = Number(data.synced_count ?? ((data.synced_inbound || 0) + (data.synced_sent || 0)));

        if (data.success) {
          const hasWarnings = Array.isArray(data.warnings) && data.warnings.length > 0;
          if (syncedTotal > 0) {
            showLiveToast('Mail Sync Complete', data.message || 'New inbound messages ingested into pipeline.', null, 'success');
            await checkLiveUpdates(true);
          } else if (!silent) {
            showLiveToast('Mailbox Up to Date', data.message || data.info || 'No new unread messages.', null, 'info');
          }
          if (hasWarnings) {
            showLiveToast('Mailbox Sync Notice (Saved in Bell)', data.warnings.join(' | '), null, 'warning', true);
          }
        } else if (!silent) {
          showLiveToast('Mail Sync Notice', data.warning || data.error || 'Unable to sync mailbox.', null, 'warning', true);
        }
        if (window.BMNotify) window.BMNotify.refresh();
      } catch (err) {
        if (!silent) {
          showLiveToast('Sync Error', 'Failed to connect to mail sync service: ' + err.message, null, 'error', true);
        }
      } finally {
        if (btn && !silent) {
          btn.disabled = false;
          btn.innerHTML = originalText;
        }
      }
    }

    function loadReplyTemplate(select, inquiryId) {
      if (window.BMReply && typeof window.BMReply.applyTemplate === 'function') {
        window.BMReply.applyTemplate(select, inquiryId);
      }
    }

    function copyToClipboard(text, btn) {
      if (!navigator.clipboard) {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
      } else {
        navigator.clipboard.writeText(text);
      }
      if (btn) {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2 text-success"></i>';
        setTimeout(() => { btn.innerHTML = orig; }, 1800);
      }
    }

    function toggleTableHeight() {
      const container = document.getElementById('rfqTableContainer');
      const btn = document.getElementById('btnToggleTableHeight');
      if (!container || !btn) return;

      if (container.style.maxHeight === 'none') {
        container.style.maxHeight = '520px';
        btn.innerHTML = '<i class="bi bi-arrows-expand me-1"></i> Expand';
        try { localStorage.setItem('bm_table_expanded', '0'); } catch(e) {}
      } else {
        container.style.maxHeight = 'none';
        btn.innerHTML = '<i class="bi bi-arrows-collapse me-1"></i> Scroll View';
        try { localStorage.setItem('bm_table_expanded', '1'); } catch(e) {}
      }
    }

    function updateLiveClock() {
      const clockEl = document.getElementById('livePktClock');
      if (!clockEl) return;
      try {
        const now = new Date();
        const options = { timeZone: 'Asia/Karachi', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        clockEl.textContent = new Intl.DateTimeFormat('en-US', options).format(now);
      } catch(e) {
        const now = new Date();
        clockEl.textContent = now.toLocaleTimeString('en-US', { hour12: true });
      }
    }
    setInterval(updateLiveClock, 1000);

    document.addEventListener('DOMContentLoaded', () => {
      try {
        updateChimeUi();
        startHeartbeatLoop();
        const maybeAutoSyncMail = () => {
          try {
            const lastTs = Number(localStorage.getItem('bm_last_auto_mail_sync') || 0);
            if (Date.now() - lastTs < 110000) return;
            localStorage.setItem('bm_last_auto_mail_sync', String(Date.now()));
          } catch (err) {}
          syncCorporateMail(true);
        };
        setTimeout(() => { maybeAutoSyncMail(); }, 3500);
        setInterval(() => {
          if (document.visibilityState === 'visible') {
            maybeAutoSyncMail();
          }
        }, 120000);
        if (localStorage.getItem('bm_table_expanded') === '1') {
          const container = document.getElementById('rfqTableContainer');
          const btn = document.getElementById('btnToggleTableHeight');
          if (container && btn) {
            container.style.maxHeight = 'none';
            btn.innerHTML = '<i class="bi bi-arrows-collapse me-1"></i> Scroll View';
          }
        }
        initRfqColumnResizer();
      } catch(e) {}
    });

    /* ============================================================
       RFQ TABLE HEADER COLUMN RESIZER (PERSISTENT DATA-GRID CONTROL)
       ============================================================ */
    const RFQ_COL_STORAGE_KEY = 'bm_rfq_col_widths_v2';
    const RFQ_DEFAULT_COLS = {
      buyer: 258,
      commodity: 'auto',
      port: 112,
      received: 112,
      stage: 118,
      quote: 108,
      actions: 296
    };
    const RFQ_COL_BOUNDS = {
      buyer: [215, 440],
      commodity: [160, 900],
      port: [90, 240],
      received: [100, 180],
      stage: [100, 210],
      quote: [96, 180],
      actions: [288, 360]
    };
    let rfqCurrentWidths = { ...RFQ_DEFAULT_COLS };

    function getSavedRfqColWidths() {
      const merged = { ...RFQ_DEFAULT_COLS };
      try {
        const raw = localStorage.getItem(RFQ_COL_STORAGE_KEY);
        if (!raw) return merged;
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') {
          Object.keys(RFQ_DEFAULT_COLS).forEach(key => {
            if (key === 'commodity' && parsed[key] === 'auto') {
              merged[key] = 'auto';
              return;
            }
            const n = Number(parsed[key]);
            if (Number.isFinite(n)) {
              const [minW, maxW] = RFQ_COL_BOUNDS[key];
              merged[key] = Math.min(maxW, Math.max(minW, Math.round(n)));
            }
          });
        }
      } catch (e) {}
      return merged;
    }

    function applyRfqColumnWidths(widths) {
      const colGroup = document.getElementById('rfqColGroup');
      const table = document.getElementById('rfqDataGrid');
      const resetBtn = document.getElementById('btnResetRfqCols');
      if (!colGroup || !table) return;

      let fixedSum = 0;
      let isCustomized = false;

      Object.keys(RFQ_DEFAULT_COLS).forEach(key => {
        const colEl = colGroup.querySelector(`col[data-col="${key}"]`);
        const val = widths[key];
        if (colEl) {
          // Keep Commodity always fluid ('auto') so 100% of container / collapsed sidebar fills edge-to-edge
          colEl.style.width = (key === 'commodity' || val === 'auto') ? 'auto' : (val + 'px');
        }
        if (val !== RFQ_DEFAULT_COLS[key]) isCustomized = true;
        if (key !== 'commodity' && Number.isFinite(Number(val))) {
          fixedSum += Number(val);
        }
      });

      const commodityFloor = (widths.commodity === 'auto' || !Number.isFinite(Number(widths.commodity)))
        ? 160
        : Number(widths.commodity);

      // Always keep table width 100% so closing/opening sidebar or resizing viewport never leaves a blank card gap
      table.style.width = '100%';
      table.style.minWidth = Math.max(1080, fixedSum + commodityFloor) + 'px';

      if (resetBtn) {
        resetBtn.classList.toggle('d-none', !isCustomized);
      }
    }

    function resetRfqColumnWidths() {
      rfqCurrentWidths = { ...RFQ_DEFAULT_COLS };
      try {
        localStorage.removeItem(RFQ_COL_STORAGE_KEY);
      } catch (e) {}
      applyRfqColumnWidths(rfqCurrentWidths);
    }

    function initRfqColumnResizer() {
      const table = document.getElementById('rfqDataGrid');
      if (!table) return;

      rfqCurrentWidths = getSavedRfqColWidths();
      applyRfqColumnWidths(rfqCurrentWidths);

      table.querySelectorAll('.col-resize-handle').forEach(handle => {
        handle.addEventListener('dblclick', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const colKey = handle.dataset.resizeCol;
          if (!colKey) return;
          rfqCurrentWidths[colKey] = RFQ_DEFAULT_COLS[colKey];
          try {
            localStorage.setItem(RFQ_COL_STORAGE_KEY, JSON.stringify(rfqCurrentWidths));
          } catch (err) {}
          applyRfqColumnWidths(rfqCurrentWidths);
        });

        handle.addEventListener('pointerdown', (e) => {
          if (e.button !== 0) return;
          e.preventDefault();
          e.stopPropagation();
          const colKey = handle.dataset.resizeCol;
          const th = handle.closest('th');
          if (!colKey || !th) return;

          const startX = e.clientX;
          const startWidth = th.getBoundingClientRect().width;
          const [minW, maxW] = RFQ_COL_BOUNDS[colKey];

          handle.classList.add('is-resizing');
          document.body.classList.add('bm-col-resizing');

          const onMove = (moveEv) => {
            const dx = moveEv.clientX - startX;
            const nextW = Math.min(maxW, Math.max(minW, Math.round(startWidth + dx)));
            if (colKey === 'commodity' && nextW <= 165) {
              rfqCurrentWidths[colKey] = 'auto';
            } else {
              rfqCurrentWidths[colKey] = nextW;
            }
            applyRfqColumnWidths(rfqCurrentWidths);
          };

          const onUp = () => {
            handle.classList.remove('is-resizing');
            document.body.classList.remove('bm-col-resizing');
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
            window.removeEventListener('pointercancel', onUp);
            try {
              localStorage.setItem(RFQ_COL_STORAGE_KEY, JSON.stringify(rfqCurrentWidths));
            } catch (err) {}
          };

          window.addEventListener('pointermove', onMove);
          window.addEventListener('pointerup', onUp);
          window.addEventListener('pointercancel', onUp);
        });
      });
    }

    /* ============================================================
       360° BUYER DOSSIER & CONVERSATION THREAD CONTROLLER
       ============================================================ */
    let currentDossierId = 0;
    let dossierSelectedFiles = [];

    function formatDossierFileSize(bytes) {
      const b = Number(bytes) || 0;
      if (b < 1024) return b + ' B';
      if (b < 1048576) return Math.round(b / 1024) + ' KB';
      return (b / 1048576).toFixed(1) + ' MB';
    }

    function renderDossierAttachmentChips() {
      const box = document.getElementById('dossierAttachmentList');
      const hint = document.getElementById('dossierAttachMeta');
      if (!box) return;
      if (!dossierSelectedFiles.length) {
        box.innerHTML = '';
        if (hint) hint.textContent = 'PDF, Word, Excel, Images, ZIP • Max 10 MB/file (20 MB total)';
        return;
      }
      let totalBytes = 0;
      box.innerHTML = dossierSelectedFiles.map((f, idx) => {
        totalBytes += f.size;
        return `<span class="dossier-att-pill" title="${escapeDossierHtml(f.name)}">
          <i class="bi bi-paperclip text-primary"></i>
          <span class="dossier-att-name">${escapeDossierHtml(f.name)}</span>
          <span class="text-muted font-monospace" style="font-size: 0.65rem;">(${formatDossierFileSize(f.size)})</span>
          <button type="button" class="dossier-att-remove" onclick="removeDossierFile(${idx})" aria-label="Remove attachment">&times;</button>
        </span>`;
      }).join('');
      if (hint) {
        hint.textContent = `${dossierSelectedFiles.length} file(s) • ${formatDossierFileSize(totalBytes)} / 20 MB`;
      }
    }

    function addDossierFiles(fileList) {
      if (!fileList || !fileList.length) return;
      const maxPerFile = 10 * 1024 * 1024;
      const maxTotal   = 20 * 1024 * 1024;
      let currentTotal = dossierSelectedFiles.reduce((acc, f) => acc + f.size, 0);
      const errors = [];

      Array.from(fileList).forEach(file => {
        if (dossierSelectedFiles.length >= 10) {
          errors.push('Maximum 10 attachments allowed per email.');
          return;
        }
        if (file.size > maxPerFile) {
          errors.push(`${file.name} exceeds 10 MB per-file limit.`);
          return;
        }
        if (currentTotal + file.size > maxTotal) {
          errors.push(`Adding ${file.name} exceeds 20 MB total limit.`);
          return;
        }
        const duplicate = dossierSelectedFiles.some(x => x.name === file.name && x.size === file.size);
        if (!duplicate) {
          dossierSelectedFiles.push(file);
          currentTotal += file.size;
        }
      });

      if (errors.length && window.BMNotify) {
        window.BMNotify.toast('warning', 'Attachment Notice', errors[0]);
      } else if (errors.length) {
        alert(errors.join('\n'));
      }
      renderDossierAttachmentChips();
    }

    function removeDossierFile(index) {
      dossierSelectedFiles.splice(index, 1);
      renderDossierAttachmentChips();
    }

    function clearDossierFiles() {
      dossierSelectedFiles = [];
      const inp = document.getElementById('dossierFilesInput');
      if (inp) inp.value = '';
      renderDossierAttachmentChips();
    }

    document.addEventListener('DOMContentLoaded', () => {
      const fileInp = document.getElementById('dossierFilesInput');
      const dropzone = document.getElementById('dossierAttachDropzone');
      if (fileInp) {
        fileInp.addEventListener('change', (ev) => {
          addDossierFiles(ev.target.files);
          fileInp.value = '';
        });
      }
      if (dropzone) {
        ['dragenter', 'dragover'].forEach(evt => {
          dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            dropzone.classList.add('is-dragover');
          });
        });
        ['dragleave', 'drop'].forEach(evt => {
          dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            dropzone.classList.remove('is-dragover');
          });
        });
        dropzone.addEventListener('drop', (e) => {
          if (e.dataTransfer && e.dataTransfer.files) {
            addDossierFiles(e.dataTransfer.files);
          }
        });
      }
    });

    function escapeDossierHtml(text) {
      if (!text) return '';
      const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
      return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    async function openBuyerDossier(inquiryId) {
      currentDossierId = inquiryId;
      clearDossierFiles();
      const drawer = document.getElementById('buyerDossierDrawer');
      const backdrop = document.getElementById('dossierBackdrop');
      if (!drawer || !backdrop) return;

      backdrop.classList.add('active');
      drawer.classList.add('active');
      drawer.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';

      const threadStream = document.getElementById('dossierThreadStream');
      threadStream.innerHTML = '<div class="p-4 text-center text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading 360° buyer intelligence dossier...</div>';

      try {
        const res = await fetch('api-dossier.php?inquiry_id=' + inquiryId);
        const data = await res.json();

        if (!data.success) {
          threadStream.innerHTML = '<div class="alert alert-danger small p-2">' + (data.error || 'Failed to load dossier') + '</div>';
          return;
        }

        const inq = data.inquiry;
        document.getElementById('dossierAvatar').textContent = inq.initials || 'BM';
        document.getElementById('dossierBuyerName').textContent = inq.buyer_name;
        document.getElementById('dossierCompany').textContent = inq.company_name;
        document.getElementById('dossierFlagChip').innerHTML = data.flag_svg || '';
        document.getElementById('dossierCountry').textContent = inq.country;

        // Ball in court
        const bic = data.ball_in_court;
        const bicBadge = document.getElementById('dossierBicBadge');
        bicBadge.className = bic.badgeClass;
        bicBadge.textContent = bic.label;
        bicBadge.title = bic.sub || '';

        // KPI cards
        document.getElementById('dossierMineral').textContent = inq.mineral_type;
        document.getElementById('dossierQty').textContent = inq.quantity;
        const portEl = document.getElementById('dossierPort');
        if (portEl) portEl.textContent = inq.destination_port || 'FOB Karachi';
        const dealEl = document.getElementById('dossierDealVal');
        if (inq.estimated_deal_usd && Number(inq.estimated_deal_usd) > 0) {
          dealEl.textContent = '$' + Number(inq.estimated_deal_usd).toLocaleString();
          dealEl.style.color = '#15803D';
        } else {
          dealEl.textContent = 'Upon Quote';
          dealEl.style.color = '#64748B';
        }

        // Quick links
        document.getElementById('dossierWaBtn').href = inq.wa_url || '#';
        document.getElementById('dossierQuoteBtn').href = 'generate-quote.php?id=' + inq.id;
        const dealBtn = document.getElementById('dossierDealBtn');
        if (dealBtn) dealBtn.href = 'deals.php?from_inquiry=' + inq.id;
        document.getElementById('dossierStageText').textContent = inq.status;
        const fullReplyBtn = document.getElementById('dossierFullReplyBtn');
        if (fullReplyBtn) {
          fullReplyBtn.onclick = () => {
            closeBuyerDossier();
            const replyModalEl = document.getElementById('replyModal' + inq.id);
            if (replyModalEl && typeof bootstrap !== 'undefined') {
              bootstrap.Modal.getOrCreateInstance(replyModalEl).show();
            }
          };
        }

        // Composer inputs
        document.getElementById('dossierInquiryId').value = inq.id;
        document.getElementById('dossierBuyerEmail').value = inq.email;
        document.getElementById('dossierBuyerEmailDisplay').textContent = inq.email || 'No email on file';
        const fromSel = document.getElementById('dossierFromEmail');
        if (fromSel) {
          const srcLower = (inq.source || '').toLowerCase();
          const matchedOpt = Array.from(fromSel.options).find(opt => {
            const val = opt.value.toLowerCase();
            return val && srcLower.includes(val);
          });
          fromSel.value = matchedOpt ? matchedOpt.value : 'sales@balochistanminerals.com';
        }
        document.getElementById('dossierSubject').value = 'RE: Balochistan Minerals - ' + inq.mineral_type + ' Export Order';
        document.getElementById('dossierBody').value = 'Dear ' + inq.buyer_name + ',\n\nThank you for reaching out regarding ' + inq.mineral_type + ' (' + inq.quantity + '). Balochistan Minerals (Pvt) Ltd has verified port stockpile allocations available.\n\nPlease find our commercial specifications attached for review.\n\nBest regards,\nExport Commercial Desk\nBalochistan Minerals (Pvt) Ltd\nWeb: https://balochistanminerals.com';

        const sendBtnEl = document.getElementById('dossierSendBtn');
        if (sendBtnEl) {
          sendBtnEl.dataset.directSend = inq.can_send_direct_email ? '1' : '0';
          sendBtnEl.innerHTML = inq.can_send_direct_email
            ? '<i class="bi bi-send-fill"></i> Dispatch Reply'
            : '<i class="bi bi-hourglass-split"></i> Submit for Approval';
        }

        const assignWrap = document.getElementById('dossierAssignWrap');
        const assignSel = document.getElementById('dossierAssignSelect');
        if (assignWrap && assignSel) {
          if (inq.can_assign && Array.isArray(inq.staff_list) && inq.staff_list.length) {
            assignSel.innerHTML = '<option value="">— Unassigned (Shared Desk) —</option>' +
              inq.staff_list.map(u => `<option value="${Number(u.id)}">${escapeDossierHtml(u.full_name)} (${escapeDossierHtml(u.role)})</option>`).join('');
            assignSel.value = inq.assigned_user_id ? String(inq.assigned_user_id) : '';
            assignWrap.classList.remove('d-none');
          } else {
            assignWrap.classList.add('d-none');
          }
        }

        // Scratchpad notes
        document.getElementById('dossierNotes').value = inq.internal_notes || '';
        document.getElementById('dossierNotesFeedback').textContent = '';

        // Render Messages Stream
        document.getElementById('dossierMsgCount').textContent = data.message_count;
        if (!data.messages || data.messages.length === 0) {
          const fallbackHtml = inq.message_html || ('<p>' + escapeDossierHtml(inq.message || 'Direct lead inquiry registered on trade portal.') + '</p>');
          threadStream.innerHTML = '<div class="p-3 text-center text-muted small bg-light rounded border"><i class="bi bi-inbox text-secondary fs-4 d-block mb-1"></i>No prior email exchanges logged.<div class="mt-2 text-start p-3 bg-white rounded border text-dark thread-rich-body"><strong>Inquiry Lead Text:</strong><br>' + fallbackHtml + '</div></div>';
        } else {
          let html = '';
          data.messages.forEach(m => {
            const isInbound = m.direction === 'inbound';
            const bubbleClass = isInbound ? 'thread-bubble-inbound' : 'thread-bubble-outbound';
            const dirPill = isInbound
              ? `<span class="thread-dir-pill thread-dir-inbound"><i class="bi bi-arrow-down-left"></i> Inbound</span>`
              : `<span class="thread-dir-pill thread-dir-outbound"><i class="bi bi-arrow-up-right"></i> Desk Reply</span>`;
            const counterpartyLine = isInbound
              ? `<span class="text-secondary text-truncate" style="max-width: 340px;">${escapeDossierHtml(m.from_email || inq.buyer_name)}</span>`
              : `<span class="text-secondary text-truncate" style="max-width: 340px;">From ${escapeDossierHtml(m.from_email || 'Desk')}</span>`;

            const renderedBody = m.body_html
              ? `<div class="thread-email-frame"><div class="thread-rich-body">${m.body_html}</div></div>`
              : `<div class="thread-email-frame"><div class="thread-body">${escapeDossierHtml(m.body_text)}</div></div>`;

            let attHtml = '';
            if (Array.isArray(m.attachments) && m.attachments.length > 0) {
              attHtml = '<div class="thread-attachments-list">' + m.attachments.map(att => {
                const icon = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(att.ext) ? 'bi-file-earmark-image' : (att.ext === 'pdf' ? 'bi-file-earmark-pdf text-danger' : 'bi-file-earmark-arrow-down');
                const sizeTag = att.size_label ? `<span class="text-muted ms-1">(${escapeDossierHtml(att.size_label)})</span>` : '';
                if (att.available && att.url) {
                  const viewBtn = att.can_preview && att.preview_url
                    ? `<a href="${escapeDossierHtml(att.preview_url)}" target="_blank" class="thread-att-action" title="Preview"><i class="bi bi-eye"></i></a>`
                    : '';
                  return `<div class="thread-att-chip">
                    <i class="bi ${icon}"></i>
                    <a href="${escapeDossierHtml(att.url)}" class="thread-att-link" title="Download ${escapeDossierHtml(att.name)}">${escapeDossierHtml(att.name)}</a>
                    ${sizeTag}
                    ${viewBtn}
                  </div>`;
                }
                return `<div class="thread-att-chip"><i class="bi ${icon}"></i><span>${escapeDossierHtml(att.name)}</span>${sizeTag}</div>`;
              }).join('') + '</div>';
            }

            html += `
              <div class="thread-bubble ${bubbleClass}">
                <div class="thread-card-header">
                  <div class="d-flex align-items-center gap-2 flex-wrap thread-meta" style="min-width: 0;">
                    ${dirPill}
                    ${counterpartyLine}
                  </div>
                  <span class="text-muted font-monospace flex-shrink-0" style="font-size: 0.70rem;">${m.sent_at}</span>
                </div>
                <div class="thread-subject-line">${escapeDossierHtml(m.subject)}</div>
                ${renderedBody}
                ${attHtml}
              </div>
            `;
          });
          threadStream.innerHTML = html;
        }
      } catch (err) {
        threadStream.innerHTML = '<div class="alert alert-danger small p-2">Error connecting to dossier API: ' + err.message + '</div>';
      }
    }

    function toggleDossierExpand() {
      const drawer = document.getElementById('buyerDossierDrawer');
      const btn = document.getElementById('btnDossierExpand');
      if (!drawer || !btn) return;
      const expanded = drawer.classList.toggle('dossier-expanded');
      btn.innerHTML = expanded ? '<i class="bi bi-arrows-angle-contract"></i>' : '<i class="bi bi-arrows-angle-expand"></i>';
      btn.title = expanded ? 'Restore default dossier width' : 'Expand dossier wider';
    }

    function closeBuyerDossier() {
      const drawer = document.getElementById('buyerDossierDrawer');
      const backdrop = document.getElementById('dossierBackdrop');
      if (drawer) {
        drawer.classList.remove('active');
        drawer.setAttribute('aria-hidden', 'true');
      }
      if (backdrop) {
        backdrop.classList.remove('active');
      }
      document.body.style.overflow = '';
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const drawer = document.getElementById('buyerDossierDrawer');
        if (drawer && drawer.classList.contains('active')) {
          closeBuyerDossier();
        }
      }
    });

    async function saveDossierNotes() {
      if (!currentDossierId) return;
      const notes = document.getElementById('dossierNotes').value;
      const feedback = document.getElementById('dossierNotesFeedback');
      feedback.textContent = 'Saving...';
      try {
        const formData = new FormData();
        formData.append('action', 'save_notes');
        formData.append('inquiry_id', currentDossierId);
        formData.append('notes', notes);

        const res = await fetch('api-dossier.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          feedback.innerHTML = '<span class="text-success"><i class="bi bi-check2"></i> Notes saved to dossier database.</span>';
          setTimeout(() => { feedback.textContent = ''; }, 2500);
        } else {
          feedback.innerHTML = '<span class="text-danger">' + (data.error || 'Failed to save') + '</span>';
        }
      } catch(e) {
        feedback.innerHTML = '<span class="text-danger">' + e.message + '</span>';
      }
    }

    async function updateDossierStage(stage) {
      if (!currentDossierId) return;
      try {
        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('inquiry_id', currentDossierId);
        formData.append('status', stage);

        const res = await fetch('api-dossier.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          document.getElementById('dossierStageText').textContent = stage;
          // Refresh current dossier view
          openBuyerDossier(currentDossierId);
        }
      } catch(e) {
        alert('Error updating status: ' + e.message);
      }
    }

    async function handleDossierReply(e) {
      e.preventDefault();
      if (!currentDossierId) return;
      const sendBtn = document.getElementById('dossierSendBtn');
      const alertEl = document.getElementById('dossierReplyAlert');
      const toEmail = document.getElementById('dossierBuyerEmail').value;
      const fromEmail = document.getElementById('dossierFromEmail')?.value || 'sales@balochistanminerals.com';
      const subject = document.getElementById('dossierSubject').value;
      const body = document.getElementById('dossierBody').value;

      if (!toEmail || !body) {
        alert('Please provide recipient email and body text.');
        return;
      }

      sendBtn.disabled = true;
      sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Dispatching...';
      alertEl.textContent = 'Relaying through Hostinger SMTP...';

      try {
        const formData = new FormData();
        formData.append('action', 'send_reply');
        formData.append('inquiry_id', currentDossierId);
        formData.append('to_email', toEmail);
        formData.append('from_email', fromEmail);
        formData.append('subject', subject);
        formData.append('body', body);
        dossierSelectedFiles.forEach(file => {
          formData.append('attachments[]', file, file.name);
        });

        const res = await fetch('api-dossier.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
          const attLabel = data.attachment_count > 0 ? ` (${data.attachment_count} attachment${data.attachment_count > 1 ? 's' : ''})` : '';
          alertEl.innerHTML = data.queued_for_approval
            ? '<span class="text-warning-emphasis fw-semibold"><i class="bi bi-hourglass-split"></i> Draft queued for Manager/Owner approval' + attLabel + '</span>'
            : '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Dispatched' + attLabel + '!</span>';
          document.getElementById('dossierBody').value = '';
          clearDossierFiles();
          setTimeout(() => {
            alertEl.textContent = '';
            openBuyerDossier(currentDossierId);
          }, 1000);
        } else {
          alertEl.innerHTML = '<span class="text-danger">' + (data.error || 'Send failed') + '</span>';
        }
      } catch(err) {
        alertEl.innerHTML = '<span class="text-danger">' + err.message + '</span>';
      } finally {
        sendBtn.disabled = false;
        sendBtn.innerHTML = sendBtn.dataset.directSend === '0'
          ? '<i class="bi bi-hourglass-split"></i> Submit for Approval'
          : '<i class="bi bi-send-fill"></i> Dispatch Reply';
      }
    }

    async function assignDossierStaff(assignedUserId) {
      if (!currentDossierId) return;
      const fd = new FormData();
      fd.append('action', 'assign_inquiry');
      fd.append('inquiry_id', currentDossierId);
      fd.append('assigned_user_id', assignedUserId);
      try {
        const res = await fetch('api-dossier.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (window.BMNotify) {
          window.BMNotify.toast(data.success ? 'success' : 'danger', 'Lead Assignment', data.message || data.error || 'Updated');
        }
      } catch (err) {
        alert('Error assigning lead: ' + err.message);
      }
    }
  </script>

  <!-- 360° Buyer Dossier Slide-Out Side Panel -->
  <div id="dossierBackdrop" class="dossier-backdrop" onclick="closeBuyerDossier()"></div>
  <aside id="buyerDossierDrawer" class="buyer-dossier-drawer" aria-label="Buyer Dossier" role="dialog" aria-hidden="true">
    <!-- Drawer Header -->
    <div class="dossier-header d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-3" style="min-width: 0; flex: 1;">
        <div class="dossier-avatar" id="dossierAvatar">BM</div>
        <div style="min-width: 0; flex: 1;">
          <h6 class="mb-0 fw-bold brand-font text-truncate" id="dossierBuyerName" style="color: #0F172A; font-size: 1.05rem;">Buyer Name</h6>
          <div class="text-muted d-flex align-items-center gap-2 text-nowrap mt-0.5" style="font-size: 0.78rem; overflow: hidden;">
            <span id="dossierCompany" class="text-truncate fw-semibold text-dark" style="max-width: 340px;">Company</span>
            <span class="text-muted" style="font-size: 0.6rem;">&bull;</span>
            <span id="dossierFlagChip" class="dossier-flag-chip" style="width: 18px; height: 12px; min-width: 18px; min-height: 12px; max-width: 18px; max-height: 12px; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; border: none !important; border-radius: 2px; flex-shrink: 0;"></span>
            <span id="dossierCountry" class="text-secondary fw-medium text-truncate" style="max-width: 220px;">Country</span>
          </div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-2">
        <span id="dossierBicBadge" class="badge-vel-desk">Desk Turn</span>
        <button type="button" id="btnDossierExpand" class="btn-window-control" onclick="toggleDossierExpand()" title="Expand / Restore Dossier Width" aria-label="Toggle wide dossier"><i class="bi bi-arrows-angle-expand"></i></button>
        <button type="button" class="btn-close" onclick="closeBuyerDossier()" aria-label="Close" style="font-size: 0.8rem;"></button>
      </div>
    </div>

    <!-- Drawer Body -->
    <div class="dossier-body">
      <!-- Section 1: Deal Intelligence KPI Cards -->
      <div class="p-3 bg-light border-bottom">
        <div class="dossier-kpi-grid">
          <div class="dossier-kpi-card">
            <div class="dossier-kpi-label">Commodity</div>
            <div class="dossier-kpi-value" id="dossierMineral">-</div>
          </div>
          <div class="dossier-kpi-card">
            <div class="dossier-kpi-label">Volume</div>
            <div class="dossier-kpi-value font-monospace" id="dossierQty">-</div>
          </div>
          <div class="dossier-kpi-card">
            <div class="dossier-kpi-label">Port / Terms</div>
            <div class="dossier-kpi-value" id="dossierPort">-</div>
          </div>
          <div class="dossier-kpi-card">
            <div class="dossier-kpi-label">Est. Deal</div>
            <div class="dossier-kpi-value font-monospace" id="dossierDealVal" style="color: #64748B;">Upon Quote</div>
          </div>
        </div>

        <!-- Quick Action Pill Bar -->
        <div class="d-flex align-items-center gap-2 mt-2.5 flex-wrap">
          <button type="button" id="dossierFullReplyBtn" class="btn btn-sm btn-dark dossier-action-btn flex-fill">
            <i class="bi bi-reply-fill"></i> Full Reply Editor
          </button>
          <a href="#" id="dossierWaBtn" target="_blank" class="btn btn-sm btn-success dossier-action-btn flex-fill">
            <i class="bi bi-whatsapp"></i> WhatsApp
          </a>
          <a href="#" id="dossierQuoteBtn" target="_blank" class="btn btn-sm btn-outline-dark dossier-action-btn flex-fill" style="background: #FFFFFF; color: #0F172A; border-color: #CBD5E1;">
            <i class="bi bi-file-earmark-pdf"></i> PDF Quote
          </a>
          <a href="#" id="dossierDealBtn" target="_blank" class="btn btn-sm btn-warning fw-bold dossier-action-btn flex-fill" style="color: #0F172A;">
            <i class="bi bi-shield-plus"></i> Convert to Deal
          </a>
          <div class="dropdown flex-fill">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 dossier-action-btn" type="button" data-bs-toggle="dropdown" style="background: #FFFFFF; color: #0F172A; border-color: #CBD5E1;">
              <i class="bi bi-tag"></i> <span id="dossierStageText">Stage</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-none border" style="font-size: 0.78rem;">
              <li><a class="dropdown-item" href="javascript:void(0)" onclick="updateDossierStage('New')"><i class="bi bi-sparkles text-primary me-1.5"></i> New</a></li>
              <li><a class="dropdown-item" href="javascript:void(0)" onclick="updateDossierStage('Contacted')"><i class="bi bi-envelope text-info me-1.5"></i> Contacted</a></li>
              <li><a class="dropdown-item" href="javascript:void(0)" onclick="updateDossierStage('Quoted')"><i class="bi bi-file-text text-secondary me-1.5"></i> Quoted</a></li>
              <li><a class="dropdown-item" href="javascript:void(0)" onclick="updateDossierStage('Assay Sent')"><i class="bi bi-clipboard-check text-warning me-1.5"></i> Assay Sent</a></li>
              <li><a class="dropdown-item" href="javascript:void(0)" onclick="updateDossierStage('Sample Sent')"><i class="bi bi-box-seam text-primary me-1.5"></i> Sample Sent</a></li>
              <li><a class="dropdown-item text-success fw-bold" href="javascript:void(0)" onclick="updateDossierStage('Contract Signed')"><i class="bi bi-check-circle me-1.5"></i> Contract Signed</a></li>
              <li><hr class="dropdown-divider my-1"></li>
              <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="updateDossierStage('Closed/Lost')"><i class="bi bi-x-circle me-1.5"></i> Closed / Lost</a></li>
            </ul>
          </div>
        </div>

        <div id="dossierAssignWrap" class="mt-2.5 d-flex align-items-center justify-content-between gap-2 pt-2 border-top d-none">
          <span class="small fw-semibold text-muted"><i class="bi bi-person-check me-1"></i>Assigned Officer:</span>
          <select id="dossierAssignSelect" class="form-select form-select-sm" style="width:auto;min-width:200px;font-size:0.75rem;" onchange="assignDossierStaff(this.value)">
            <option value="">— Unassigned (Shared) —</option>
          </select>
        </div>
      </div>

      <!-- Section 2: Chronological Email Thread Stream -->
      <div class="p-3">
        <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
          <h6 class="mb-0 fw-bold brand-font" style="font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569;">
            <i class="bi bi-chat-left-text me-1 text-primary"></i> Negotiation Thread (<span id="dossierMsgCount">0</span>)
          </h6>
          <span class="badge bg-light text-muted border font-monospace" id="dossierBuyerEmailDisplay" style="font-size: 0.68rem;"></span>
        </div>

        <div id="dossierThreadStream" class="dossier-thread-stream">
          <!-- Populated via api-dossier.php -->
        </div>
      </div>

      <!-- Section 3: Direct Reply via Hostinger SMTP -->
      <div class="p-3 bg-light border-top">
        <div class="fw-bold mb-2 brand-font d-flex align-items-center justify-content-between gap-2 flex-wrap" style="font-size: 0.78rem; color: #0F172A;">
          <span><i class="bi bi-reply-fill text-primary me-1"></i> Quick Reply via Hostinger SMTP</span>
          <select id="dossierFromEmail" class="form-select form-select-sm font-monospace fw-bold py-0.5" style="width: auto; font-size: 0.68rem;">
            <?php foreach (getCorporateMailboxOptions() as $mbMeta): ?>
              <option value="<?= htmlspecialchars($mbMeta['user']) ?>"><?= htmlspecialchars($mbMeta['user']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <form id="dossierReplyForm" onsubmit="handleDossierReply(event)">
          <input type="hidden" id="dossierInquiryId" value="">
          <input type="hidden" id="dossierBuyerEmail" value="">
          <div class="mb-2">
            <input type="text" id="dossierSubject" class="form-control form-control-sm" placeholder="Subject line..." required style="font-size: 0.78rem; border-color: #CBD5E1; box-shadow: none !important;">
          </div>
          <div class="mb-2">
            <textarea id="dossierBody" class="form-control form-control-sm font-monospace" rows="4" placeholder="Type commercial desk response here..." required style="font-size: 0.78rem; border-color: #CBD5E1; box-shadow: none !important;"></textarea>
          </div>

          <!-- Attachments Uploader & Chips -->
          <div id="dossierAttachDropzone" class="dossier-attach-dropzone mb-2">
            <input type="file" id="dossierFilesInput" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.gif,.webp,.zip" class="d-none">
            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
              <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="document.getElementById('dossierFilesInput').click()" style="font-size: 0.72rem; background: #FFFFFF; border-color: #CBD5E1; color: #0F172A;">
                <i class="bi bi-paperclip text-primary"></i> Attach Documents
              </button>
              <span id="dossierAttachMeta" class="text-muted" style="font-size: 0.67rem;">PDF, Word, Excel, Images, ZIP • Max 10 MB/file (20 MB total)</span>
            </div>
            <div id="dossierAttachmentList" class="dossier-attachment-chips"></div>
          </div>

          <div class="d-flex justify-content-end align-items-center gap-2">
            <span id="dossierReplyAlert" class="small text-muted" style="font-size: 0.72rem;"></span>
            <button type="submit" id="dossierSendBtn" class="btn btn-sm btn-dark d-inline-flex align-items-center gap-1.5" style="font-size: 0.76rem; font-weight: 600; box-shadow: none !important;">
              <i class="bi bi-send-fill"></i> Dispatch Reply
            </button>
          </div>
        </form>
      </div>

      <!-- Section 4: Private Desk Scratchpad Notes -->
      <div class="p-3 border-top">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <label class="form-label fw-bold mb-0 brand-font" style="font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569;">
            <i class="bi bi-journal-text text-warning me-1"></i> Private Desk Scratchpad
          </label>
          <button type="button" class="btn btn-sm btn-outline-secondary py-0.5 px-2" style="font-size: 0.70rem; box-shadow: none !important;" onclick="saveDossierNotes()">
            <i class="bi bi-check2"></i> Save Notes
          </button>
        </div>
        <textarea id="dossierNotes" class="form-control form-control-sm font-monospace" rows="3" placeholder="Private internal notes (payment terms, counter-offers, assay SGS checks)..." style="font-size: 0.76rem; background: #FFFDF5; border-color: #CBD5E1; box-shadow: none !important;"></textarea>
        <div id="dossierNotesFeedback" class="small text-muted mt-1" style="font-size: 0.70rem;"></div>
      </div>
    </div>
  </aside>
  <?php if (!empty($flashMessage)): ?>
  <div class="live-toast-container" id="flashToastContainer">
    <div class="live-toast" id="flashToast">
      <div class="live-toast-icon" style="background: <?= $flashType === 'danger' ? '#EF4444' : ($flashType === 'warning' ? '#F59E0B' : '#10B981') ?>;">
        <i class="bi <?= $flashType === 'danger' ? 'bi-x-circle-fill' : ($flashType === 'warning' ? 'bi-trash-fill' : 'bi-check2-circle') ?>"></i>
      </div>
      <div class="live-toast-body">
        <div class="live-toast-title"><?= $flashType === 'danger' ? 'Notice' : ($flashType === 'warning' ? 'Record Deleted' : 'Action Completed') ?></div>
        <div class="live-toast-text"><?= htmlspecialchars($flashMessage) ?></div>
      </div>
      <button type="button" class="live-toast-close" onclick="this.closest('.live-toast').remove()">&times;</button>
    </div>
  </div>
  <script>
    setTimeout(function() {
      const ft = document.getElementById('flashToast');
      if (ft) {
        ft.classList.add('hiding');
        setTimeout(() => ft.remove(), 350);
      }
    }, 3200);
  </script>
  <?php endif; ?>

  <script src="admin-layout.js?v=<?= filemtime(__DIR__ . '/admin-layout.js') ?>"></script>
</body>
</html>
