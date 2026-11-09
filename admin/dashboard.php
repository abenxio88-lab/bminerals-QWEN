<?php
/**
 * Balochistan Minerals - Executive Trade Desk Portal
 * Ultra-Refined Corporate Aesthetic (Deep Navy, Slate & Mineral Bronze)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/smtp-mailer.php';
requireAuth();

$user = getLoggedInUser();
$pdo  = getDB();

$flashMessage = '';
$flashType    = 'success';

// Handle quick updates (Status change, spot quote, internal notes, or delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
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
            $flashMessage = 'Inquiry #' . $id . ' updated successfully.';
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
                $flashMessage = 'Official email reply dispatched to ' . $toEmail . ' and logged to Buyer Dossier #' . $id . '.';
            } else {
                $flashMessage = 'Reply logged to Dossier #' . $id . ', but live SMTP reported: ' . $sendRes['error'];
                $flashType = 'warning';
            }
        } elseif ($action === 'save_mail_settings') {
            $smtpHost = cleanInput($_POST['smtp_host'] ?? 'smtp.hostinger.com');
            $smtpPort = (int)($_POST['smtp_port'] ?? 465);
            $smtpUser = cleanInput($_POST['smtp_user'] ?? 'sales@balochistanminerals.com');
            $smtpPass = trim($_POST['smtp_pass'] ?? '');

            $_SESSION['bm_mail_pass'] = $smtpPass;
            $confContent = "<?php\ndefine('BM_SMTP_HOST', " . var_export($smtpHost, true) . ");\ndefine('BM_SMTP_PORT', " . var_export($smtpPort, true) . ");\ndefine('BM_SMTP_USER', " . var_export($smtpUser, true) . ");\ndefine('BM_SMTP_PASS', " . var_export($smtpPass, true) . ");\n";
            @file_put_contents(__DIR__ . '/mail-config.php', $confContent);

            $flashMessage = 'Hostinger mail server configuration saved successfully.';
        } elseif ($action === 'test_smtp') {
            $smtpHost = cleanInput($_POST['smtp_host'] ?? 'smtp.hostinger.com');
            $smtpPort = (int)($_POST['smtp_port'] ?? 465);
            $smtpUser = cleanInput($_POST['smtp_user'] ?? 'sales@balochistanminerals.com');
            $smtpPass = trim($_POST['smtp_pass'] ?? '');

            $testRes = testHostingerSmtp($smtpHost, $smtpPort, $smtpUser, $smtpPass);
            if ($testRes['success']) {
                $flashMessage = '✓ ' . $testRes['message'];
            } else {
                $flashMessage = 'SMTP Test Error: ' . $testRes['error'];
                $flashType = 'danger';
            }
        } elseif ($action === 'delete_inquiry') {
            $id = (int)($_POST['inquiry_id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM inquiries WHERE id = ?');
            $stmt->execute([$id]);
            $flashMessage = 'Inquiry #' . $id . ' permanently deleted.';
            $flashType = 'warning';
        }
    } else {
        $flashMessage = 'Session expired. Please try again.';
        $flashType = 'danger';
    }
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
    $totalProspects = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects")->fetchColumn();

    $sql = "SELECT i.*, 
            (SELECT COUNT(*) FROM email_messages em WHERE em.inquiry_id = i.id OR (em.to_email = i.email AND i.email != '')) AS email_count 
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
  <link rel="stylesheet" href="admin-layout.css">
  <style>
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
      word-break: break-all;
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
        <button type="button" id="sidebarCloseBtn" class="btn btn-sm btn-light border d-lg-none" aria-label="Close Sidebar" title="Close Sidebar">
          <i class="bi bi-x-lg"></i>
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
          <div><i class="bi bi-envelope-paper-fill"></i> Email Reply Desk</div>
          <span class="badge font-monospace" style="background: rgba(2,132,199,0.10); color: #0284C7; border: 1px solid rgba(2,132,199,0.22); font-size: 0.62rem; padding: 2px 7px; border-radius: 4px; letter-spacing: 0.04em;">HOSTINGER</span>
        </a>
        <a href="prospects.php" class="sidebar-link">
          <div><i class="bi bi-bullseye"></i> Buyer Directory</div>
          <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.7rem;"><?= $totalProspects ?></span>
        </a>
        <a href="radar.php" class="sidebar-link">
          <div><i class="bi bi-broadcast-pin"></i> Live Visitor Radar</div>
          <span class="badge bg-success font-monospace" style="font-size: 0.65rem;">LIVE</span>
        </a>

        <div class="sidebar-section-title">Logistics & Trade Operations</div>
        <a href="stockpiles.php" class="sidebar-link">
          <div><i class="bi bi-boxes"></i> Stockpiles & Shipments</div>
        </a>
        <a href="prices.php" class="sidebar-link">
          <div><i class="bi bi-cash-stack"></i> Mineral Spot Prices</div>
        </a>

        <div class="sidebar-section-title">Buyer Engagement</div>
        <a href="reviews.php" class="sidebar-link">
          <div><i class="bi bi-star-fill"></i> Customer Reviews</div>
          <?php if ($pendingReviews > 0): ?>
            <span class="badge bg-secondary font-monospace" style="font-size: 0.7rem;"><?= $pendingReviews ?></span>
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
          <div class="pill-status d-none d-xl-inline-flex" title="Port Terminals Active">
            <span class="status-dot dot-green"></span>
            <span>Port Corridors: KPT & Port Qasim</span>
          </div>
          <div class="pill-status pill-clock" title="Official Pakistan Standard Time (PKT, UTC+5)">
            <span class="status-dot dot-green me-1"></span>
            <span class="d-none d-md-inline text-muted"><?= date('M d') ?> • </span>
            <span id="livePktClock" class="fw-bold" style="color: #0F172A;"><?= date('h:i:s A') ?></span>
            <span class="text-muted ms-1" style="font-size: 0.7rem;">PKT</span>
          </div>
        </div>
      </header>

      <main class="content-canvas">

        <?php if (!empty($flashMessage)): ?>
          <div class="alert alert-<?= $flashType ?> alert-dismissible fade show shadow-sm py-2 px-3 small mb-3" role="alert">
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
                <h3 class="metric-card-value"><?= $totalLeads ?></h3>
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
                <h3 class="metric-card-value"><?= $newLeads ?></h3>
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
                <h3 class="metric-card-value"><?= $quotedLeads ?></h3>
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
                <h3 class="metric-card-value"><?= $closedLeads ?></h3>
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
            <a href="dashboard.php" class="quick-filter-pill <?= empty($filterStatus) ? 'active' : '' ?>">All (<?= $totalLeads ?>)</a>
            <a href="dashboard.php?status=New" class="quick-filter-pill <?= $filterStatus === 'New' ? 'active' : '' ?>">New (<?= $newLeads ?>)</a>
            <a href="dashboard.php?status=Contacted" class="quick-filter-pill <?= $filterStatus === 'Contacted' ? 'active' : '' ?>">Contacted (<?= $contactedLeads ?>)</a>
            <a href="dashboard.php?status=Quoted" class="quick-filter-pill <?= $filterStatus === 'Quoted' ? 'active' : '' ?>">Quoted (<?= $quotedLeads ?>)</a>
            <a href="dashboard.php?status=Contract+Signed" class="quick-filter-pill <?= $filterStatus === 'Contract Signed' ? 'active' : '' ?>">Executed (<?= $closedLeads ?>)</a>
          </div>
        </div>

        <!-- Inbound RFQ Pipeline Table -->
        <div class="table-panel">
          <div class="table-panel-header">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <h2 class="table-panel-title">
                <i class="bi bi-table text-secondary"></i> Commercial Inquiries & RFQs
              </h2>
              <span class="badge rounded-pill bg-dark font-monospace px-2.5 py-1"><?= count($inquiries) ?></span>
              <span class="table-scroll-hint" title="Use mouse wheel or drag horizontally to view all columns">
                <i class="bi bi-arrows-expand-vertical"></i> Scroll ↔
              </span>
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
                  <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                      No inquiries found matching your filter criteria.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($inquiries as $row): ?>
                    <?php
                      $statusClean = str_replace(' ', '_', $row['status']);
                      $waMsg = "Hello " . $row['buyer_name'] . ", this is Balochistan Minerals (Pvt) Ltd regarding your export inquiry for " . $row['mineral_type'];
                      if (!empty($row['quantity'])) {
                          $waMsg .= " (" . $row['quantity'] . ")";
                      }
                      $waMsg .= ". We have verified stockpiles available. How may we assist with your specifications and assay requirements?";
                      $waUrl = generateWhatsAppLink($row['whatsapp'], $waMsg);
                    ?>
                    <tr>
                      <!-- Buyer -->
                      <td>
                        <?php
                          $nameWords = preg_split('/\s+/', trim($row['buyer_name'] ?? 'Buyer'));
                          $initials = '';
                          foreach ($nameWords as $w) {
                            if (!empty($w)) $initials .= mb_substr($w, 0, 1);
                            if (strlen($initials) >= 2) break;
                          }
                          $initials = strtoupper($initials ?: 'BM');
                        ?>
                        <div class="d-flex align-items-start" style="gap: 16px;">
                          <div class="buyer-avatar" title="<?= htmlspecialchars($row['buyer_name']) ?>"><?= htmlspecialchars($initials) ?></div>
                          <div style="min-width: 0; flex: 1;">
                            <div class="fw-semibold" style="color: var(--brand-navy); font-size: 0.92rem; line-height: 1.3;">
                              <?= htmlspecialchars($row['buyer_name']) ?>
                            </div>
                            <div class="text-muted" style="font-size: 0.78rem; margin-top: 1px;">
                              <i class="bi bi-building me-1 text-secondary" style="font-size: 0.72rem;"></i><?= htmlspecialchars($row['company_name'] ?: 'Private Trader') ?>
                            </div>

                            <!-- Buyer Direct Email + Country -->
                            <?php if (!empty($row['email'])): ?>
                              <div class="d-flex align-items-center gap-1.5 mt-1">
                                <a href="mailto:<?= htmlspecialchars($row['email']) ?>" class="buyer-email-link" title="Send direct email to <?= htmlspecialchars($row['email']) ?>">
                                  <i class="bi bi-envelope me-1 text-secondary"></i><?= htmlspecialchars($row['email']) ?>
                                </a>
                                <button type="button" class="btn-copy-clip" onclick="copyToClipboard('<?= htmlspecialchars($row['email']) ?>', this)" title="Copy email address">
                                  <i class="bi bi-clipboard"></i>
                                </button>
                                <span class="text-muted mx-0.5">•</span>
                                <span class="country-pill">
                                  <?= htmlspecialchars($row['country'] ?? 'International') ?>
                                </span>
                              </div>
                            <?php endif; ?>

                            <!-- Source & Mailbox Channel -->
                            <div class="mt-1.5 d-flex gap-1.5 flex-wrap align-items-center">
                              <span class="badge bg-light text-secondary border fw-medium" style="font-size: 0.68rem; padding: 3px 8px;">
                                <i class="bi bi-inbox-fill text-primary me-1"></i><?= !empty($row['source']) && strpos($row['source'], '@') !== false ? htmlspecialchars($row['source']) : 'sales@balochistanminerals.com' ?>
                              </span>

                              <?php if (!empty($row['source']) && $row['source'] === 'Direct Email Modal'): ?>
                                <span class="badge bg-light text-dark border fw-medium" style="font-size: 0.68rem; padding: 3px 8px;">
                                  <i class="bi bi-chat-left-dots text-warning me-1"></i>Direct Desk
                                </span>
                              <?php endif; ?>

                              <?php if (!empty($row['email_count']) && (int)$row['email_count'] > 0): ?>
                                <span class="badge bg-light text-primary border fw-semibold" style="font-size: 0.68rem; padding: 3px 8px;">
                                  <i class="bi bi-chat-dots me-1"></i><?= $row['email_count'] ?> Email<?= (int)$row['email_count'] > 1 ? 's' : '' ?>
                                </span>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      </td>

                      <!-- Mineral & Message Snippet -->
                      <td>
                        <div class="mineral-spec-tag mb-1"><?= htmlspecialchars($row['mineral_type']) ?></div>
                        <?php if (!empty($row['grade_spec'])): ?>
                          <div class="small text-muted font-monospace"><?= htmlspecialchars($row['grade_spec']) ?></div>
                        <?php endif; ?>

                        <!-- Buyer Inquiry Message / Email Content Preview -->
                        <?php if (!empty($row['message'])): ?>
                          <div class="inquiry-message-snippet mt-1.5 pt-1 border-top" style="border-color: #F1F5F9 !important;">
                            <a href="javascript:void(0)" class="text-decoration-none d-block" data-bs-toggle="modal" data-bs-target="#replyModal<?= $row['id'] ?>" title="Click to read full message & reply">
                              <span class="text-muted small fst-italic" style="font-size: 0.73rem; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: #475569 !important;">
                                <i class="bi bi-chat-left-quote-fill me-1 text-secondary" style="font-size: 0.68rem;"></i>&ldquo;<?= htmlspecialchars($row['message']) ?>&rdquo;
                              </span>
                            </a>
                          </div>
                        <?php endif; ?>
                      </td>

                      <!-- Quantity & Port -->
                      <td>
                        <div class="fw-bold font-monospace"><?= htmlspecialchars($row['quantity'] ?: 'Unspecified') ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($row['destination_port'] ?: 'FOB Karachi') ?></div>
                      </td>

                      <!-- Date -->
                      <td>
                        <div class="small fw-semibold"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                        <div class="text-muted" style="font-size: 0.72rem;"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
                      </td>

                      <!-- Status Badge -->
                      <td>
                        <span class="status-badge status-<?= $statusClean ?>">
                          <?= htmlspecialchars($row['status']) ?>
                        </span>
                      </td>

                      <!-- Spot Quote -->
                      <td>
                        <?php if ($row['spot_price_quoted'] !== null): ?>
                          <span class="fw-bold font-monospace" style="color: #15803D;">
                            $<?= number_format($row['spot_price_quoted'], 2) ?>
                          </span>
                          <span class="text-muted" style="font-size: 0.7rem;">/ MT</span>
                        <?php else: ?>
                          <span class="text-muted small fst-italic">Pending quote</span>
                        <?php endif; ?>
                      </td>

                      <!-- Actions -->
                      <td class="text-end">
                        <div class="d-inline-flex gap-2">
                          <button type="button" class="btn-action-email" data-bs-toggle="modal" data-bs-target="#replyModal<?= $row['id'] ?>" title="Send Official Email Reply">
                            <i class="bi bi-reply-fill"></i> Reply
                          </button>

                          <a href="<?= $waUrl ?>" target="_blank" class="btn-action-wa" title="Launch chat on WhatsApp">
                            <i class="bi bi-whatsapp"></i> Chat
                          </a>

                          <a href="generate-quote.php?id=<?= $row['id'] ?>" target="_blank" class="btn-action-quote" title="Generate Official Corporate PDF Quote">
                            <i class="bi bi-file-earmark-pdf"></i> Quote
                          </a>

                          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>" title="Edit Dossier">
                            <i class="bi bi-pencil"></i>
                          </button>
                        </div>

                        <!-- Email Reply Modal (Executive Stretchable & Resizable Split View) -->
                        <div class="modal fade text-start" id="replyModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered modal-dialog-resizable" style="max-width: 1040px;">
                            <div class="modal-content">
                              <form method="POST" action="dashboard.php">
                                <input type="hidden" name="action" value="send_reply">
                                <input type="hidden" name="inquiry_id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                                <div class="modal-header py-2.5 px-3">
                                  <div class="d-flex align-items-center gap-2">
                                    <span class="badge-rfq-mild">
                                      <i class="bi bi-reply-fill"></i> RFQ #<?= $row['id'] ?>
                                    </span>
                                    <h6 class="modal-title fw-bold brand-font mb-0" style="color: var(--brand-navy); font-size: 0.98rem;">
                                      Reply to <?= htmlspecialchars($row['buyer_name']) ?> <span class="text-muted fw-normal fs-6">(<?= htmlspecialchars($row['company_name'] ?: 'Buyer') ?>)</span>
                                    </h6>
                                  </div>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body p-3 p-md-3.5">
                                  <div class="row g-3">
                                    <!-- Left Column: Inbound Inquiry & Thread Context -->
                                    <div class="col-md-5 border-end pe-md-3">
                                      <div class="d-flex align-items-center text-uppercase fw-bold text-muted mb-2 pb-1 border-bottom" style="font-size: 0.70rem; letter-spacing: 0.05em;">
                                        <i class="bi bi-inbox-fill text-primary me-1.5"></i> Buyer Inquiry Context
                                      </div>

                                      <div class="p-2.5 rounded bg-light border mb-2.5">
                                        <div class="d-flex justify-content-between align-items-start">
                                          <div>
                                            <div class="fw-bold text-dark" style="font-size: 0.88rem;"><?= htmlspecialchars($row['buyer_name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($row['company_name'] ?: 'Independent Buyer') ?> • <?= htmlspecialchars($row['country']) ?></div>
                                          </div>
                                          <span class="badge bg-white text-dark border font-monospace" style="font-size: 0.68rem;"><?= date('M d, Y', strtotime($row['created_at'])) ?></span>
                                        </div>

                                        <div class="mt-2 pt-2 border-top">
                                          <div class="text-xs fw-bold text-muted text-uppercase" style="font-size: 0.65rem;">Landed In Mailbox:</div>
                                          <div class="font-monospace fw-bold small text-dark mt-0.5">
                                            <i class="bi bi-mailbox2 text-primary me-1"></i><?= strpos($row['source'], '@') !== false ? htmlspecialchars($row['source']) : 'sales@balochistanminerals.com' ?>
                                          </div>
                                        </div>

                                        <div class="mt-2 pt-2 border-top">
                                          <div class="d-flex justify-content-between small">
                                            <span class="text-muted">Commodity:</span>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($row['mineral_type']) ?></span>
                                          </div>
                                          <div class="d-flex justify-content-between small mt-1">
                                            <span class="text-muted">Port / Terms:</span>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($row['destination_port'] ?: 'FOB Karachi') ?></span>
                                          </div>
                                          <?php if (!empty($row['spot_price_quoted'])): ?>
                                            <div class="d-flex justify-content-between small mt-1">
                                              <span class="text-muted">Spot Quoted:</span>
                                              <span class="fw-bold text-success font-monospace">$<?= number_format($row['spot_price_quoted'], 2) ?> USD</span>
                                            </div>
                                          <?php endif; ?>
                                        </div>
                                      </div>

                                      <div>
                                        <label class="form-label mb-1">Original Buyer Message</label>
                                        <div class="p-2.5 rounded bg-white border small" style="max-height: 180px; overflow-y: auto; font-size: 0.78rem; line-height: 1.45; white-space: pre-wrap; color: #334155;">
<?= htmlspecialchars($row['message'] ?: 'No message body recorded with this inquiry.') ?>
                                        </div>
                                      </div>

                                      <?php if (!empty($row['internal_notes'])): ?>
                                        <div class="mt-2">
                                          <label class="form-label mb-1">Previous Interaction Timeline</label>
                                          <div class="p-2 rounded bg-light border font-monospace text-muted" style="max-height: 90px; overflow-y: auto; font-size: 0.68rem; line-height: 1.35; white-space: pre-wrap;">
<?= htmlspecialchars($row['internal_notes']) ?>
                                          </div>
                                        </div>
                                      <?php endif; ?>
                                    </div>

                                    <!-- Right Column: Compose & Dispatch Engine -->
                                    <div class="col-md-7 ps-md-3 d-flex flex-column justify-content-between">
                                      <div>
                                        <div class="d-flex align-items-center justify-content-between text-uppercase fw-bold text-muted mb-2 pb-1 border-bottom" style="font-size: 0.70rem; letter-spacing: 0.05em;">
                                          <span><i class="bi bi-envelope-paper-fill text-warning me-1.5"></i> Compose Official Reply</span>
                                          <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.67rem;">Hostinger SMTP Relay</span>
                                        </div>

                                        <div class="row g-2 mb-2">
                                          <div class="col-6">
                                            <label class="form-label mb-1">From Mailbox</label>
                                            <select name="from_email" class="form-select form-select-sm font-monospace fw-bold">
                                              <option value="sales@balochistanminerals.com" <?= strpos($row['source'], 'info@') === false ? 'selected' : '' ?>>sales@balochistanminerals.com</option>
                                              <option value="info@balochistanminerals.com" <?= strpos($row['source'], 'info@') !== false ? 'selected' : '' ?>>info@balochistanminerals.com</option>
                                            </select>
                                          </div>
                                          <div class="col-6">
                                            <label class="form-label mb-1">To Recipient</label>
                                            <input type="email" name="to_email" class="form-control form-control-sm font-monospace fw-bold" value="<?= htmlspecialchars($row['email']) ?>" required>
                                          </div>
                                        </div>

                                        <div class="mb-2">
                                          <label class="form-label mb-1">Subject Line</label>
                                          <input type="text" name="subject" class="form-control form-control-sm fw-semibold" value="Re: Inquiry for <?= htmlspecialchars($row['mineral_type']) ?> - Balochistan Minerals (Pvt) Ltd" required>
                                        </div>

                                        <div class="mb-2">
                                          <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0">Select B2B Commodity Template</label>
                                            <span class="text-muted" style="font-size: 0.68rem;">Auto-fills body below</span>
                                          </div>
                                          <select class="form-select form-select-sm" onchange="loadReplyTemplate(this, <?= $row['id'] ?>)">
                                            <option value="quote">Template 1: Formal Price Quotation & FOB Karachi Terms</option>
                                            <option value="assay">Template 2: SGS Chemical Assay & Purity Verification</option>
                                            <option value="lc">Template 3: Payment Terms & SBP Letter of Credit (L/C)</option>
                                            <option value="custom">Template 4: Custom Executive Message</option>
                                          </select>
                                        </div>

                                        <div class="mb-1">
                                          <label class="form-label mb-1">Message Body</label>
                                          <textarea id="replyBody<?= $row['id'] ?>" name="reply_body" class="form-control form-control-sm font-monospace" rows="8" required style="font-size: 0.80rem; line-height: 1.45;">Dear <?= htmlspecialchars($row['buyer_name']) ?>,

Thank you for contacting Balochistan Minerals (Pvt) Ltd regarding your inquiry for <?= htmlspecialchars($row['mineral_type']) ?>.

We are pleased to provide you with our current commercial indicative terms for <?= htmlspecialchars($row['quantity'] ?: 'your required tonnage') ?>:

• Commodity: <?= htmlspecialchars($row['mineral_type']) ?><?= !empty($row['grade_spec']) ? ' (' . htmlspecialchars($row['grade_spec']) . ')' : '' ?>

• Delivery Terms: <?= htmlspecialchars($row['destination_port'] ?: 'FOB Karachi Port (KPT / QICT)') ?>

• Current Spot Indicative Rate: $<?= !empty($row['spot_price_quoted']) ? number_format($row['spot_price_quoted'], 2) : '185.00' ?> USD / 1 Ton
• Packing: Bulk in vessel hold or 1.5 MT heavy-duty jumbo bags in 20ft containers
• Quality Control: SGS / Alfred H. Knight sampling & assay at Karachi port prior to loading

Please advise your target delivery timeline so we may issue our official Corporate Proforma Offer.

Sincerely,
Export Commercial Desk
Balochistan Minerals (Pvt) Ltd
Karachi / Quetta, Pakistan
Web: https://balochistanminerals.com</textarea>
                                        </div>

                                        <?php
                                          $cleanMailto = "mailto:" . rawurlencode($row['email']) . "?subject=" . rawurlencode("Re: Inquiry for " . $row['mineral_type'] . " - Balochistan Minerals") . "&body=" . rawurlencode("Dear " . $row['buyer_name'] . ",\n\nRegarding your inquiry for " . $row['mineral_type'] . "...\n\nBalochistan Minerals Export Desk");
                                        ?>
                                        <div class="text-muted text-end mt-1" style="font-size: 0.68rem;">
                                          Prefer external mail client? <a href="<?= $cleanMailto ?>" class="text-decoration-underline text-secondary">Open in Outlook / Apple Mail</a>
                                        </div>
                                      </div>

                                      <div class="form-check p-2 bg-light rounded border d-flex align-items-center mb-0 mt-2">
                                        <input type="checkbox" class="form-check-input ms-0 me-2 my-0" checked disabled style="width: 1rem; height: 1rem;">
                                        <label class="form-check-label small fw-semibold text-dark mb-0" style="font-size: 0.72rem;">
                                          Auto-log email transcript into Buyer Dossier & advance stage to "Contacted"
                                        </label>
                                      </div>
                                    </div>
                                  </div>
                                </div>

                                <div class="modal-footer d-flex justify-content-between py-2 px-3">
                                  <div class="small text-muted font-monospace" style="font-size: 0.72rem;">
                                    <i class="bi bi-shield-check text-success me-1"></i>Hostinger SMTP Relay Active
                                  </div>
                                  <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-1.5" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-dark px-3.5 py-1.5 fw-bold text-white shadow-sm" style="background: #0F172A; border-color: #0F172A; border-radius: 6px;">
                                      <i class="bi bi-send-fill me-1"></i> Send Official Reply
                                    </button>
                                  </div>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>
                        <div class="modal fade text-start" id="editModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                              <form method="POST" action="dashboard.php">
                                <input type="hidden" name="action" value="update_inquiry">
                                <input type="hidden" name="inquiry_id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                                <div class="modal-header">
                                  <h6 class="modal-title fw-bold brand-font" style="color: var(--brand-navy);">
                                    Buyer Dossier #<?= $row['id'] ?>: <?= htmlspecialchars($row['buyer_name']) ?>
                                  </h6>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body">
                                  <div class="row g-3 p-3 bg-light rounded-2 mb-3">
                                    <div class="col-md-6">
                                      <div class="text-muted small">Company / Country</div>
                                      <div class="fw-bold"><?= htmlspecialchars($row['company_name'] ?: 'Private Trader') ?> (<?= htmlspecialchars($row['country']) ?>)</div>
                                      <div class="small text-muted"><i class="bi bi-envelope"></i> <?= htmlspecialchars($row['email']) ?></div>
                                      <div class="small text-muted"><i class="bi bi-whatsapp text-success"></i> <?= htmlspecialchars($row['whatsapp']) ?></div>
                                      <div class="small text-muted mt-2 p-2 bg-white rounded border">
                                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.05em;"><i class="bi bi-mailbox2 me-1 text-primary"></i> Intake Route & Landing Box:</div>
                                        <div class="font-monospace fw-bold mt-1" style="color: #0F172A; font-size: 0.8rem;">
                                          <?php if (strpos($row['source'], '@') !== false): ?>
                                            <i class="bi bi-envelope-check-fill text-success me-1"></i>Delivered to Hostinger Mailbox: <span class="badge bg-dark"><?= htmlspecialchars($row['source']) ?></span>
                                          <?php elseif ($row['source'] === 'Direct Email Modal'): ?>
                                            <i class="bi bi-chat-left-dots-fill text-warning me-1"></i>Website Direct Desk ➔ <span class="badge bg-dark">sales@balochistanminerals.com</span>
                                          <?php else: ?>
                                            <i class="bi bi-globe text-secondary me-1"></i><?= htmlspecialchars($row['source']) ?>
                                          <?php endif; ?>
                                        </div>
                                      </div>
                                    </div>
                                    <div class="col-md-6">
                                      <div class="text-muted small">Commodity Requested</div>
                                      <div class="fw-bold text-dark"><?= htmlspecialchars($row['mineral_type']) ?></div>
                                      <div class="small">Quantity: <strong><?= htmlspecialchars($row['quantity'] ?: 'Open') ?></strong></div>
                                      <div class="small">Port: <strong><?= htmlspecialchars($row['destination_port'] ?: 'FOB Karachi') ?></strong></div>
                                    </div>
                                    <div class="col-12 mt-2">
                                      <div class="text-muted small mb-1">Inbound Message:</div>
                                      <div class="p-2 bg-white rounded border small" style="white-space: pre-wrap;"><?= htmlspecialchars($row['message'] ?: 'No message provided.') ?></div>
                                    </div>
                                  </div>

                                  <div class="row g-3">
                                    <div class="col-md-6">
                                      <label class="form-label fw-bold small">Commercial Deal Stage</label>
                                      <select name="status" class="form-select form-select-sm">
                                        <option value="New" <?= $row['status'] === 'New' ? 'selected' : '' ?>>New</option>
                                        <option value="Contacted" <?= $row['status'] === 'Contacted' ? 'selected' : '' ?>>Contacted (Discussion on WA)</option>
                                        <option value="Assay Sent" <?= $row['status'] === 'Assay Sent' ? 'selected' : '' ?>>Assay / Lab Certificate Sent</option>
                                        <option value="Quoted" <?= $row['status'] === 'Quoted' ? 'selected' : '' ?>>Spot Quoted</option>
                                        <option value="Sample Sent" <?= $row['status'] === 'Sample Sent' ? 'selected' : '' ?>>Sample Dispatched</option>
                                        <option value="Contract Signed" <?= $row['status'] === 'Contract Signed' ? 'selected' : '' ?>>Contract Signed / L/C Opened</option>
                                        <option value="Closed" <?= $row['status'] === 'Closed' ? 'selected' : '' ?>>Closed (Completed)</option>
                                        <option value="Archived" <?= $row['status'] === 'Archived' ? 'selected' : '' ?>>Archived</option>
                                      </select>
                                    </div>
                                    <div class="col-md-6">
                                      <label class="form-label fw-bold small">Spot Price ($ USD / Metric Ton)</label>
                                      <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" name="spot_price" class="form-control" placeholder="185.00" value="<?= $row['spot_price_quoted'] ?>">
                                        <span class="input-group-text">USD / MT</span>
                                      </div>
                                    </div>
                                    <div class="col-12">
                                      <label class="form-label fw-bold small">Internal Trade Desk Notes (Private)</label>
                                      <textarea name="internal_notes" class="form-control form-control-sm" rows="3" placeholder="Private notes on trucking, weighbridge results, L/C bank requirements, etc."><?= htmlspecialchars($row['internal_notes'] ?? '') ?></textarea>
                                    </div>
                                  </div>
                                </div>

                                <div class="modal-footer d-flex justify-content-between">
                                  <button type="button" class="btn btn-sm btn-outline-danger" onclick="if(confirm('Delete inquiry permanently?')) { document.getElementById('delForm<?= $row['id'] ?>').submit(); }">
                                    <i class="bi bi-trash"></i> Delete
                                  </button>
                                  <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-dark" style="background: var(--brand-navy);">Save Changes</button>
                                  </div>
                                </div>
                              </form>

                              <form id="delForm<?= $row['id'] ?>" method="POST" action="dashboard.php" style="display:none;">
                                <input type="hidden" name="action" value="delete_inquiry">
                                <input type="hidden" name="inquiry_id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                              </form>
                            </div>
                          </div>
                        </div>

                      </td>
                    </tr>
                  <?php endforeach; ?>
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
    async function syncCorporateMail(isSimulate) {
      const btn = document.getElementById('btnSyncMail');
      const originalText = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Checking Mail...';

      try {
        const url = isSimulate ? 'mail-sync.php?action=simulate' : 'mail-sync.php?action=sync';
        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {
          if (data.synced_count > 0) {
            alert('Mail Sync Result:\n\n' + data.message + '\n\nRefreshing table...');
            window.location.reload();
          } else {
            alert(data.message || (data.info ? data.info : 'Mailbox is up to date. No new unread messages.'));
          }
        } else {
          alert('Mail Sync Notice:\n\n' + (data.warning || data.error || 'Unable to sync mailbox.'));
        }
      } catch (err) {
        alert('Failed to connect to mail sync service: ' + err.message);
      } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
      }
    }

    function loadReplyTemplate(select, inquiryId) {
      const val = select.value;
      const textarea = document.getElementById('replyBody' + inquiryId);
      if (!textarea) return;

      if (val === 'assay') {
        textarea.value = "Dear Buyer,\n\nIn reference to your technical inquiry, we have verified that our current stockpiles conform to your purity requirements.\n\n• Chemical Assay: Sampled & verified by SGS Pakistan at KPT / Port Qasim\n• Certificate of Analysis (COA): Available upon final lot inspection and contract signing\n• Moisture & Sizing: Guaranteed export grade per contract terms\n\nPlease let us know if you require a formal sample dispatch or full chemical spec sheet.\n\nSincerely,\nTechnical & Quality Control Desk\nBalochistan Minerals (Pvt) Ltd\nWeb: https://balochistanminerals.com";
      } else if (val === 'lc') {
        textarea.value = "Dear Buyer,\n\nRegarding commercial payment terms for international export from Pakistan:\n\n• Payment Instrument: 100% Irrevocable Letter of Credit (L/C at Sight) or SBP-approved TT terms\n• Advising Bank: Habib Bank Limited (HBL) / Meezan Bank, Karachi Corporate Branch\n• Inspection: Final weight and quality certificate issued by SGS at port of loading\n• Shipping Terms: FOB Karachi or CIF designated discharge port (Incoterms 2020)\n\nPlease provide your corporate details and issuing bank name to initiate our formal draft contract.\n\nSincerely,\nCommercial Finance Desk\nBalochistan Minerals (Pvt) Ltd\nWeb: https://balochistanminerals.com";
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
  </script>
  <script src="admin-layout.js"></script>
</body>
</html>
