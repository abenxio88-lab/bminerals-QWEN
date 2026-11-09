<?php
/**
 * Balochistan Minerals - Executive Trade Desk Portal
 * Ultra-Refined Corporate Aesthetic (Deep Navy, Slate & Mineral Bronze)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/smtp-mailer.php';
require_once __DIR__ . '/flag-helper.php';
require_once __DIR__ . '/inquiry-row-helper.php';
requireAuth();

$user = getLoggedInUser();
$pdo  = getDB();

$flashMessage = $_SESSION['bm_flash_msg'] ?? '';
$flashType    = $_SESSION['bm_flash_type'] ?? 'success';
unset($_SESSION['bm_flash_msg'], $_SESSION['bm_flash_type']);

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

            $stmt = $pdo->prepare('UPDATE inquiries SET status = ?, spot_price_quoted = ?, internal_notes = ? WHERE id = ?');
            $stmt->execute([$status, $spotPrice, $notes, $id]);
            $_SESSION['bm_flash_msg'] = 'Inquiry #' . $id . ' updated successfully.';
            $_SESSION['bm_flash_type'] = 'success';
        } elseif ($action === 'send_reply') {
            $id          = (int)($_POST['inquiry_id'] ?? 0);
            $toEmail     = filter_var(trim($_POST['to_email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $fromEmail   = cleanInput($_POST['from_email'] ?? 'sales@balochistanminerals.com');
            $subject     = cleanInput($_POST['subject'] ?? 'Quotation & Export Inquiry Follow-up');
            $replyBody   = cleanInput($_POST['reply_body'] ?? '');

            // Log email reply into internal notes / timeline
            $logEntry = "[" . date('M d, Y H:i') . "] Email sent from " . $fromEmail . " to " . $toEmail . ":\nSubject: " . $subject . "\n" . $replyBody . "\n-------------------\n";
            
            $stmt = $pdo->prepare('UPDATE inquiries SET internal_notes = CONCAT(?, COALESCE(internal_notes, "")), status = CASE WHEN status = "New" THEN "Contacted" ELSE status END WHERE id = ?');
            $stmt->execute([$logEntry, $id]);

            // Dispatch via Hostinger SMTP engine
            $sendRes = sendHostingerMail($toEmail, $subject, $replyBody, $fromEmail);
            if ($sendRes['success']) {
                $_SESSION['bm_flash_msg'] = 'Official email reply dispatched to ' . $toEmail . ' and logged to Buyer Dossier #' . $id . '.';
                $_SESSION['bm_flash_type'] = 'success';
            } else {
                $_SESSION['bm_flash_msg'] = 'Reply logged to Dossier #' . $id . ', but live SMTP reported: ' . $sendRes['error'];
                $_SESSION['bm_flash_type'] = 'warning';
            }
        } elseif ($action === 'save_mail_settings') {
            $smtpHost = cleanInput($_POST['smtp_host'] ?? 'smtp.hostinger.com');
            $smtpPort = (int)($_POST['smtp_port'] ?? 465);
            $smtpUser = cleanInput($_POST['smtp_user'] ?? 'sales@balochistanminerals.com');
            $smtpPass = trim($_POST['smtp_pass'] ?? '');

            $_SESSION['bm_mail_pass'] = $smtpPass;
            $confContent = "<?php\ndefine('BM_SMTP_HOST', " . var_export($smtpHost, true) . ");\ndefine('BM_SMTP_PORT', " . var_export($smtpPort, true) . ");\ndefine('BM_SMTP_USER', " . var_export($smtpUser, true) . ");\ndefine('BM_SMTP_PASS', " . var_export($smtpPass, true) . ");\n";
            @file_put_contents(__DIR__ . '/mail-config.php', $confContent);

            $_SESSION['bm_flash_msg'] = 'Hostinger mail server configuration saved successfully.';
            $_SESSION['bm_flash_type'] = 'success';
        } elseif ($action === 'test_smtp') {
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
        } elseif ($action === 'delete_inquiry') {
            $id = (int)($_POST['inquiry_id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM inquiries WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['bm_flash_msg'] = 'Inquiry #' . $id . ' permanently deleted.';
            $_SESSION['bm_flash_type'] = 'warning';
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

    $sql .= " ORDER BY i.created_at DESC LIMIT 100";
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
      --sidebar-width: 255px;
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

    /* Custom Luxury Slate Scrollbar */
    .table-scroll-container::-webkit-scrollbar {
      width: 7px;
      height: 7px;
    }
    .table-scroll-container::-webkit-scrollbar-track {
      background: #F8FAFC;
    }
    .table-scroll-container::-webkit-scrollbar-thumb {
      background: #CBD5E1;
      border-radius: 4px;
    }
    .table-scroll-container::-webkit-scrollbar-thumb:hover {
      background: #94A3B8;
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
      max-width: 280px;
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
          <img src="../images/new-logo.png" alt="Balochistan Minerals" class="sidebar-logo" onerror="this.style.display='none'">
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
          <button type="button" class="btn-topbar-action" data-bs-toggle="modal" data-bs-target="#mailConfigModal">
            <i class="bi bi-gear"></i> <span>Mail Settings</span>
          </button>
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
              <button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1.5 fw-semibold px-3 py-1.5" style="border-radius: 6px;" id="btnSyncMail" onclick="syncCorporateMail(false)" title="Check Hostinger IMAP Inboxes (sales@ and info@)">
                <i class="bi bi-arrow-repeat"></i> <span>Sync Emails</span>
              </button>
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary py-1.5" onclick="syncCorporateMail(true)" title="Simulate a Test Foreign Buyer Email">
                  <i class="bi bi-envelope-plus"></i> <span class="d-none d-sm-inline ms-1">Test Mail</span>
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
            <table class="table-clean">
              <thead>
                <tr>
                  <th style="min-width: 250px;">Buyer & Company</th>
                  <th style="min-width: 210px;">Commodity & Specs</th>
                  <th style="min-width: 140px;">Tonnage & Port</th>
                  <th style="min-width: 115px;">Received</th>
                  <th style="min-width: 130px;">Deal Stage</th>
                  <th style="min-width: 110px;">Spot Quote</th>
                  <th style="min-width: 175px;" class="text-end">Actions</th>
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

    // Floating Toast Notification
    function showLiveToast(title, message, inquiryId) {
      let container = document.getElementById('liveToastContainer');
      if (!container) {
        container = document.createElement('div');
        container.id = 'liveToastContainer';
        container.className = 'live-toast-container';
        document.body.appendChild(container);
      }

      const toast = document.createElement('div');
      toast.className = 'live-toast';
      toast.innerHTML = `
        <div class="live-toast-icon"><i class="bi bi-bell-fill"></i></div>
        <div class="live-toast-body">
          <div class="live-toast-title">${escapeDossierHtml(title)}</div>
          <div class="live-toast-text">${escapeDossierHtml(message)}</div>
          ${inquiryId ? `<button type="button" class="live-toast-btn" onclick="openBuyerDossier(${inquiryId}); this.closest('.live-toast').remove();"><i class="bi bi-folder2-open me-1"></i> Open Dossier</button>` : ''}
        </div>
        <button type="button" class="live-toast-close" onclick="this.closest('.live-toast').remove()">&times;</button>
      `;
      container.appendChild(toast);

      setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => { toast.remove(); }, 350);
      }, 7000);
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
                data.previews.forEach(p => {
                  showLiveToast(
                    'New RFQ Received #' + p.id,
                    `${p.buyer_name} (${p.company_name}) • ${p.mineral_type} • ${p.country}`,
                    p.id
                  );
                });
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

    async function syncCorporateMail(isSimulate) {
      const btn = document.getElementById('btnSyncMail');
      const originalText = btn ? btn.innerHTML : '';
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Checking Mail...';
      }

      try {
        const url = isSimulate ? 'mail-sync.php?action=simulate' : 'mail-sync.php?action=sync';
        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {
          if (data.synced_count > 0) {
            showLiveToast('Mail Sync Successful', data.message || 'New inbound messages ingested into pipeline.');
            await checkLiveUpdates(true);
          } else {
            showLiveToast('Mailbox Up to Date', data.message || (data.info ? data.info : 'Mailbox is up to date. No new unread messages.'));
          }
        } else {
          showLiveToast('Mail Sync Notice', data.warning || data.error || 'Unable to sync mailbox.');
        }
      } catch (err) {
        showLiveToast('Sync Error', 'Failed to connect to mail sync service: ' + err.message);
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = originalText;
        }
      }
    }

    function loadReplyTemplate(select, inquiryId) {
      const val = select.value;
      const textarea = document.getElementById('replyBody' + inquiryId);
      if (!textarea) return;

      if (val === 'assay') {
        textarea.value = "Dear Buyer,\n\nIn reference to your technical inquiry, we have verified that our current stockpiles conform to your purity requirements.\n\n• Chemical Assay: Sampled & verified by SGS Pakistan at Gwadar Port / KPT / Port Qasim\n• Certificate of Analysis (COA): Available upon final lot inspection and contract signing\n• Moisture & Sizing: Guaranteed export grade per contract terms\n\nPlease let us know if you require a formal sample dispatch or full chemical spec sheet.\n\nSincerely,\nTechnical & Quality Control Desk\nBalochistan Minerals (Pvt) Ltd\nWeb: https://balochistanminerals.com";
      } else if (val === 'lc') {
        textarea.value = "Dear Buyer,\n\nRegarding commercial payment terms for international export from Pakistan:\n\n• Payment Instrument: 100% Irrevocable Letter of Credit (L/C at Sight) or SBP-approved TT terms\n• Advising Bank: Habib Bank Limited (HBL) / Meezan Bank, Karachi Corporate Branch\n• Inspection: Final weight and quality certificate issued by SGS at port of loading\n• Shipping Terms: FOB Gwadar / Karachi (KPT / Port Qasim) or CIF designated discharge port (Incoterms 2020)\n\nPlease provide your corporate details and issuing bank name to initiate our formal draft contract.\n\nSincerely,\nCommercial Finance Desk\nBalochistan Minerals (Pvt) Ltd\nWeb: https://balochistanminerals.com";
      } else if (val === 'custom') {
        textarea.value = "Dear Buyer,\n\nThank you for reaching out to Balochistan Minerals (Pvt) Ltd.\n\n[Please enter your custom message here]\n\nBest regards,\nExport Commercial Desk\nBalochistan Minerals (Pvt) Ltd\nWeb: https://balochistanminerals.com";
      } else {
        // default quote
        textarea.value = textarea.defaultValue;
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
        if (localStorage.getItem('bm_table_expanded') === '1') {
          const container = document.getElementById('rfqTableContainer');
          const btn = document.getElementById('btnToggleTableHeight');
          if (container && btn) {
            container.style.maxHeight = 'none';
            btn.innerHTML = '<i class="bi bi-arrows-collapse me-1"></i> Scroll View';
          }
        }
      } catch(e) {}
    });

    /* ============================================================
       360° BUYER DOSSIER & CONVERSATION THREAD CONTROLLER
       ============================================================ */
    let currentDossierId = 0;

    function escapeDossierHtml(text) {
      if (!text) return '';
      const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
      return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    async function openBuyerDossier(inquiryId) {
      currentDossierId = inquiryId;
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
        document.getElementById('dossierDealVal').textContent = '$' + Number(inq.estimated_deal_usd || 0).toLocaleString();

        // Quick links
        document.getElementById('dossierWaBtn').href = inq.wa_url || '#';
        document.getElementById('dossierQuoteBtn').href = 'generate-quote.php?id=' + inq.id;
        document.getElementById('dossierStageText').textContent = inq.status;

        // Composer inputs
        document.getElementById('dossierInquiryId').value = inq.id;
        document.getElementById('dossierBuyerEmail').value = inq.email;
        document.getElementById('dossierBuyerEmailDisplay').textContent = inq.email || 'No email on file';
        document.getElementById('dossierSubject').value = 'RE: Balochistan Minerals - ' + inq.mineral_type + ' Export Order';
        document.getElementById('dossierBody').value = 'Dear ' + inq.buyer_name + ',\n\nThank you for reaching out regarding ' + inq.mineral_type + ' (' + inq.quantity + '). Balochistan Minerals (Pvt) Ltd has verified port stockpile allocations available.\n\nPlease find our commercial specifications attached for review.\n\nBest regards,\nExport Commercial Desk\nBalochistan Minerals (Pvt) Ltd\nWeb: https://balochistanminerals.com';

        // Scratchpad notes
        document.getElementById('dossierNotes').value = inq.internal_notes || '';
        document.getElementById('dossierNotesFeedback').textContent = '';

        // Render Messages Stream
        document.getElementById('dossierMsgCount').textContent = data.message_count;
        if (!data.messages || data.messages.length === 0) {
          threadStream.innerHTML = '<div class="p-3 text-center text-muted small bg-light rounded border"><i class="bi bi-inbox text-secondary fs-4 d-block mb-1"></i>No prior email exchanges logged.<div class="mt-2 text-start p-2 bg-white rounded border font-monospace text-dark" style="font-size: 0.74rem;"><strong>Inquiry Lead Text:</strong><br>' + escapeDossierHtml(inq.message || 'Direct lead inquiry registered on trade portal.') + '</div></div>';
        } else {
          let html = '';
          data.messages.forEach(m => {
            const isInbound = m.direction === 'inbound';
            const bubbleClass = isInbound ? 'thread-bubble-inbound' : 'thread-bubble-outbound';
            const senderBadge = isInbound 
              ? '<span class="text-primary"><i class="bi bi-arrow-down-left me-1"></i>Inbound: ' + escapeDossierHtml(m.from_email || inq.buyer_name) + '</span>'
              : '<span class="text-success"><i class="bi bi-arrow-up-right me-1"></i>Desk Reply &bull; ' + escapeDossierHtml(m.from_email || 'Desk') + '</span>';

            html += `
              <div class="thread-bubble ${bubbleClass}">
                <div class="thread-meta d-flex justify-content-between align-items-center">
                  ${senderBadge}
                  <span class="text-muted font-monospace" style="font-size: 0.68rem;">${m.sent_at}</span>
                </div>
                <div class="fw-bold text-dark small mb-1" style="font-size: 0.78rem;">${escapeDossierHtml(m.subject)}</div>
                <div class="thread-body">${escapeDossierHtml(m.body_text)}</div>
              </div>
            `;
          });
          threadStream.innerHTML = html;
        }
      } catch (err) {
        threadStream.innerHTML = '<div class="alert alert-danger small p-2">Error connecting to dossier API: ' + err.message + '</div>';
      }
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
        formData.append('subject', subject);
        formData.append('body', body);

        const res = await fetch('api-dossier.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
          alertEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Dispatched!</span>';
          document.getElementById('dossierBody').value = '';
          setTimeout(() => {
            alertEl.textContent = '';
            openBuyerDossier(currentDossierId);
          }, 1200);
        } else {
          alertEl.innerHTML = '<span class="text-danger">' + (data.error || 'Send failed') + '</span>';
        }
      } catch(err) {
        alertEl.innerHTML = '<span class="text-danger">' + err.message + '</span>';
      } finally {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="bi bi-send-fill"></i> Dispatch Reply';
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
          <div class="text-muted d-flex align-items-center gap-2 text-nowrap mt-0.5" style="font-size: 0.76rem; overflow: hidden;">
            <span id="dossierCompany" class="text-truncate fw-semibold text-dark" style="max-width: 160px;">Company</span>
            <span class="text-muted" style="font-size: 0.6rem;">&bull;</span>
            <span id="dossierFlagChip" class="dossier-flag-chip" style="width: 18px; height: 12px; min-width: 18px; min-height: 12px; max-width: 18px; max-height: 12px; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; border: none !important; border-radius: 2px; flex-shrink: 0;"></span>
            <span id="dossierCountry" class="text-secondary fw-medium text-truncate" style="max-width: 120px;">Country</span>
          </div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-2">
        <span id="dossierBicBadge" class="badge-vel-desk">Desk Turn</span>
        <button type="button" class="btn-close" onclick="closeBuyerDossier()" aria-label="Close" style="font-size: 0.8rem;"></button>
      </div>
    </div>

    <!-- Drawer Body -->
    <div class="dossier-body">
      <!-- Section 1: Deal Intelligence KPI Cards -->
      <div class="p-3 bg-light border-bottom">
        <div class="row g-2 text-center">
          <div class="col-4">
            <div class="bg-white p-2 rounded border">
              <div class="text-muted" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">Mineral</div>
              <div class="fw-bold text-truncate" id="dossierMineral" style="font-size: 0.82rem; color: #0F172A;">-</div>
            </div>
          </div>
          <div class="col-4">
            <div class="bg-white p-2 rounded border">
              <div class="text-muted" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">Volume</div>
              <div class="fw-bold font-monospace text-truncate" id="dossierQty" style="font-size: 0.82rem; color: #0F172A;">-</div>
            </div>
          </div>
          <div class="col-4">
            <div class="bg-white p-2 rounded border">
              <div class="text-muted" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">Est. Deal</div>
              <div class="fw-bold font-monospace text-truncate" id="dossierDealVal" style="font-size: 0.82rem; color: #15803D;">-</div>
            </div>
          </div>
        </div>

        <!-- Quick Action Pill Bar -->
        <div class="d-flex align-items-center gap-2 mt-2.5">
          <a href="#" id="dossierWaBtn" target="_blank" class="btn btn-sm btn-success dossier-action-btn flex-fill">
            <i class="bi bi-whatsapp"></i> WhatsApp
          </a>
          <a href="#" id="dossierQuoteBtn" target="_blank" class="btn btn-sm btn-outline-dark dossier-action-btn flex-fill" style="background: #FFFFFF; color: #0F172A; border-color: #CBD5E1;">
            <i class="bi bi-file-earmark-pdf"></i> PDF Quote
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
      </div>
              <li><hr class="dropdown-divider my-1"></li>
              <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="updateDossierStage('Closed/Lost')"><i class="bi bi-x-circle me-1.5"></i> Closed / Lost</a></li>
            </ul>
          </div>
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
        <div class="fw-bold mb-2 brand-font d-flex align-items-center justify-content-between" style="font-size: 0.78rem; color: #0F172A;">
          <span><i class="bi bi-reply-fill text-primary me-1"></i> Quick Reply via Hostinger SMTP</span>
          <span class="badge bg-white text-secondary font-monospace" style="font-size: 0.65rem; border: none !important; box-shadow: none !important;">sales@balochistanminerals.com</span>
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

  <script src="admin-layout.js"></script>
</body>
</html>
