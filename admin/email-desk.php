<?php
/**
 * Balochistan Minerals - Dedicated Commercial Email Desk
 * Centralized B2B email terminal for managing corporate correspondence with foreign smelters & buyers.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/smtp-mailer.php';
require_once __DIR__ . '/mail-helpers.php';
requireAuth();

$user = getLoggedInUser();
$pdo  = getDB();

$flashMessage = '';
$flashType    = 'success';

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $pdo !== null) {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (verifyCsrfToken($csrf)) {
        if ($action === 'compose_outbound_email') {
            $toEmail     = filter_var(trim($_POST['to_email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $fromEmail   = cleanInput($_POST['from_email'] ?? 'sales@balochistanminerals.com');
            $ccEmail     = filter_var(trim($_POST['cc_email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $subject     = cleanInput($_POST['subject'] ?? 'Official Commercial Offer - Balochistan Minerals');
            $replyBody   = cleanInput($_POST['email_body'] ?? '');
            $inquiryId   = !empty($_POST['inquiry_id']) ? (int)$_POST['inquiry_id'] : null;

            $uploadRes = handleUploadedAttachments('attachments');
            if (!empty($uploadRes['errors'])) {
                $flashMessage = 'Email not sent: ' . implode(' ', $uploadRes['errors']);
                $flashType = 'danger';
            } else {
                $attachedFiles = $uploadRes['files'];
                $sendRes = sendHostingerMail($toEmail, $subject, $replyBody, $fromEmail, $ccEmail, $attachedFiles, $inquiryId);
                if ($sendRes['success']) {
                    $attSuffix = !empty($attachedFiles) ? (' (' . count($attachedFiles) . ' attachment(s))') : '';
                    $flashMessage = 'Official export email dispatched to ' . $toEmail . $attSuffix . ' via Hostinger SMTP relay.';
                } else {
                    $flashMessage = 'Email logged in outbox, but SMTP reported: ' . $sendRes['error'];
                    $flashType = 'warning';
                }
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
        }
    } else {
        $flashMessage = 'Session expired. Please try again.';
        $flashType = 'danger';
    }
}

// Fetch Messages & Sidebar Badges
$messages = [];
$totalMessages = 0;
$outboundCount = 0;
$inboundCount = 0;
$newLeads = 0;
$totalProspects = 0;
$pendingReviews = 0;

$filterDirection = cleanInput($_GET['direction'] ?? '');
$searchQuery = cleanInput($_GET['q'] ?? '');
$selectedMsgId = (int)($_GET['msg_id'] ?? 0);
$selectedMessage = null;

if ($pdo !== null) {
    $newLeads = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'New'")->fetchColumn();
    $totalProspects = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects")->fetchColumn();
    $pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'Pending'")->fetchColumn();

    $totalMessages = (int)$pdo->query("SELECT COUNT(*) FROM email_messages")->fetchColumn();
    $outboundCount = (int)$pdo->query("SELECT COUNT(*) FROM email_messages WHERE direction = 'outbound'")->fetchColumn();
    $inboundCount  = (int)$pdo->query("SELECT COUNT(*) FROM email_messages WHERE direction = 'inbound'")->fetchColumn();

    $sql = "SELECT m.*, i.buyer_name, i.company_name, i.country, i.mineral_type, i.whatsapp 
            FROM email_messages m 
            LEFT JOIN inquiries i ON m.inquiry_id = i.id 
            WHERE 1=1";
    $params = [];

    if (!empty($filterDirection)) {
        $sql .= " AND m.direction = ?";
        $params[] = $filterDirection;
    }
    if (!empty($searchQuery)) {
        $sql .= " AND (m.to_email LIKE ? OR m.from_email LIKE ? OR m.subject LIKE ? OR m.body_text LIKE ? OR i.buyer_name LIKE ?)";
        $term = '%' . $searchQuery . '%';
        $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
    }

    $sql .= " ORDER BY m.sent_at DESC, m.id DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    if ($selectedMsgId > 0) {
        $stmtSel = $pdo->prepare("SELECT m.*, i.buyer_name, i.company_name, i.country, i.mineral_type, i.whatsapp 
            FROM email_messages m 
            LEFT JOIN inquiries i ON m.inquiry_id = i.id 
            WHERE m.id = ?");
        $stmtSel->execute([$selectedMsgId]);
        $selectedMessage = $stmtSel->fetch();
    } elseif (!empty($messages)) {
        $selectedMessage = $messages[0];
    }

    // Also fetch prospects for autocomplete
    $prospectList = $pdo->query("SELECT id, company_name, contact_person, email, target_mineral FROM buyer_prospects ORDER BY company_name ASC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Commercial Email Desk | Balochistan Minerals</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Poppins:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="admin-layout.css?v=<?= filemtime(__DIR__ . '/admin-layout.css') ?>">
  <style>
    :root {
      --brand-navy: #0F172A;
      --navy-900: #0F172A;
      --brand-gold: #B48C36;
      --brand-canvas: #F8FAFC;
      --brand-surface: #FFFFFF;
      --brand-border: #E2E8F0;
      --brand-orange: #E65C1A;
      --text-dark: #0F172A;
      --text-muted: #64748B;
      --sidebar-width: 280px;
    }
    body { font-family: 'Poppins', sans-serif; font-size: 0.88rem; color: var(--text-dark); background-color: var(--brand-canvas); margin: 0; }
    h1, h2, h3, h4, h5, h6, .brand-font { font-family: 'Montserrat', sans-serif; letter-spacing: -0.015em; }

    .app-wrapper { display: flex; min-height: 100vh; }
    .app-sidebar {
      width: var(--sidebar-width);
      background: var(--brand-surface);
      border-right: 1px solid var(--brand-border);
      position: fixed;
      top: 0; bottom: 0; left: 0;
      z-index: 1040;
      display: flex;
      flex-direction: column;
    }

    .sidebar-nav { padding: 14px 12px; flex: 1; overflow-y: auto; }
    .sidebar-section-title {
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #94A3B8;
      padding: 10px 10px 4px;
    }
    .sidebar-link {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 6px;
      padding: 8px 12px;
      border-radius: 6px;
      color: #475569;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.80rem;
      font-weight: 600;
      text-decoration: none;
      margin-bottom: 2px;
      transition: all 0.15s ease;
      white-space: nowrap;
    }
    .sidebar-link i { font-size: 1.05rem; margin-right: 10px; color: #64748B; }
    .sidebar-link:hover { background: #F1F5F9; color: var(--brand-navy); }
    .sidebar-link.active {
      background: #F8FAFC;
      color: var(--brand-navy);
      border: 1px solid var(--brand-border);
      position: relative;
    }
    .sidebar-link.active::before {
      content: '';
      position: absolute;
      left: -12px; top: 6px; bottom: 6px; width: 3.5px;
      background: #0F172A;
      border-radius: 0 3px 3px 0;
    }
    .sidebar-link.active i { color: #0F172A !important; }
    .sidebar-footer {
      padding: 14px 18px;
      border-top: 1px solid var(--brand-border);
      background: #FAFCFE;
    }

    .app-main {
      margin-left: var(--sidebar-width);
      flex: 1;
      display: flex;
      flex-direction: column;
      min-width: 0;
      height: 100vh;
      overflow: hidden;
    }
    .top-bar {
      background: var(--brand-surface);
      border-bottom: 1px solid var(--brand-border);
      padding: 12px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }
    .content-canvas { padding: 16px 24px; flex: 1; display: flex; flex-direction: column; min-height: 0; }

    /* Email Split Desk Layout */
    .mail-split-container {
      display: flex;
      flex: 1;
      background: #FFFFFF;
      border: 1px solid var(--brand-border);
      border-radius: 10px;
      overflow: hidden;
      min-height: 0;
      box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .mail-thread-list {
      width: 360px;
      border-right: 1px solid var(--brand-border);
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
      background: #FAFCFE;
    }
    .mail-thread-toolbar {
      padding: 12px;
      border-bottom: 1px solid var(--brand-border);
      background: #FFFFFF;
    }
    .mail-thread-items {
      flex: 1;
      overflow-y: auto;
    }
    .mail-item {
      padding: 14px 16px;
      border-bottom: 1px solid #F1F5F9;
      cursor: pointer;
      transition: background 0.15s;
      text-decoration: none;
      display: block;
      color: inherit;
    }
    .mail-item:hover { background: #F1F5F9; }
    .mail-item.active { background: #FFFFFF; border-left: 3.5px solid #E65C1A; box-shadow: inset 0 0 10px rgba(0,0,0,0.02); }

    .mail-reading-pane {
      flex: 1;
      display: flex;
      flex-direction: column;
      min-width: 0;
      background: #FFFFFF;
    }
    .mail-pane-header {
      padding: 16px 24px;
      border-bottom: 1px solid var(--brand-border);
      background: #FFFFFF;
      flex-shrink: 0;
    }
    .mail-pane-body {
      padding: 24px;
      flex: 1;
      overflow-y: auto;
      background: #FAFCFE;
    }
    .mail-pane-compose {
      padding: 16px 24px;
      border-top: 1px solid var(--brand-border);
      background: #FFFFFF;
      flex-shrink: 0;
    }

    /* Buttons */
    .btn-save-action {
      background: #0F172A !important;
      color: #FFFFFF !important;
      border: 1px solid #0F172A !important;
      padding: 7px 18px !important;
      font-weight: 700 !important;
      font-size: 0.82rem !important;
      border-radius: 6px !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 6px !important;
      cursor: pointer !important;
      box-shadow: none !important;
    }
    .btn-save-action:hover {
      background: #1E293B !important;
      box-shadow: none !important;
    }

    .btn-cancel-action {
      background: #FFFFFF !important;
      color: #475569 !important;
      border: 1.5px solid #CBD5E1 !important;
      padding: 7px 16px !important;
      border-radius: 6px !important;
      font-size: 0.82rem !important;
      cursor: pointer !important;
      box-shadow: none !important;
    }
    .btn-cancel-action:hover {
      background: #F8FAFC !important;
      color: #0F172A !important;
      border-color: #0F172A !important;
      box-shadow: none !important;
    }

    .modal-dialog.modal-lg { max-width: 820px; }
    .modal-content {
      border: 1px solid rgba(15, 23, 42, 0.12) !important;
      border-radius: 14px !important;
      box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25) !important;
      overflow: hidden;
    }
    .modal-header { background: #F8FAFC; border-bottom: 1px solid #E2E8F0; padding: 16px 22px; }
    .modal-body { padding: 22px; background: #FFFFFF; }
    .modal-footer { background: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 14px 22px; }
    .form-control, .form-select {
      border: 1.5px solid #CBD5E1;
      border-radius: 8px;
      font-size: 0.86rem;
      color: #0F172A;
      font-weight: 500;
    }
    .form-control {
      padding: 7px 12px;
    }
    .form-select {
      padding: 7px 34px 7px 12px;
    }
    .form-select.form-select-sm {
      padding: 4px 32px 4px 10px;
      font-size: 0.78rem;
    }
    .form-label {
      font-size: 0.70rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #475569;
      margin-bottom: 4px;
    }

    /* Quick Reply From Sender & Clean Attachment Bar */
    .desk-from-wrap {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      flex-wrap: nowrap;
      max-width: 100%;
    }
    .desk-from-select {
      width: auto;
      min-width: 280px;
      max-width: 100%;
      height: 31px;
      padding: 4px 34px 4px 11px !important;
      font-size: 0.73rem !important;
      line-height: 1.35;
      background-position: right 10px center;
      border-radius: 7px !important;
      background-color: #FFFFFF;
      cursor: pointer;
    }
    .desk-attach-bar {
      margin-top: 10px;
      padding: 8px 11px;
      border: 1px dashed #CBD5E1;
      border-radius: 8px;
      background: #F8FAFC;
      transition: border-color 0.15s ease, background-color 0.15s ease;
    }
    .desk-attach-bar.is-dragover {
      border-color: #2563EB;
      background: #EFF6FF;
    }
    .desk-attach-bar-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px 12px;
      flex-wrap: wrap;
    }
    .desk-attach-trigger-group {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    .btn-desk-attach {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 11px;
      font-size: 0.73rem;
      font-weight: 600;
      color: #0F172A;
      background: #FFFFFF;
      border: 1px solid #CBD5E1;
      border-radius: 6px;
      cursor: pointer;
      line-height: 1.4;
      transition: background-color 0.15s ease, border-color 0.15s ease;
    }
    .btn-desk-attach:hover {
      background: #F1F5F9;
      border-color: #94A3B8;
    }
    .desk-attach-hint {
      font-size: 0.68rem;
      color: #64748B;
      line-height: 1.35;
    }
    @media (max-width: 575.98px) {
      .desk-from-wrap {
        width: 100%;
      }
      .desk-from-select {
        min-width: 0;
        flex: 1;
      }
    }
  </style>
</head>
<body>

  <div class="app-wrapper">

    <!-- 1. LEFT EXECUTIVE SIDEBAR (Slideable & Collapsible) -->
    <aside class="app-sidebar" id="appSidebar">
      <div class="sidebar-header">
        <a href="../" target="_blank" class="sidebar-logo-wrap" title="Balochistan Minerals">
          <picture style="display: contents;"><source media="(min-width: 769px)" srcset="../images/new-logo-dekstop.png" width="723" height="278"><img src="../images/new-logo.png" alt="Balochistan Minerals" class="sidebar-logo" onerror="this.style.display='none'"></picture>
          <span class="sidebar-badge">TRADE DESK PORTAL</span>
        </a>
        <button type="button" id="sidebarCloseBtn" class="btn-sidebar-panel-toggle" aria-label="Toggle Sidebar" title="Collapse Sidebar (Ctrl + B)">
          <i class="bi bi-layout-sidebar-inset"></i>
        </button>
      </div>

      <nav class="sidebar-nav">
        <div class="sidebar-section-title">Lead & Deal Engine</div>
        <a href="dashboard.php" class="sidebar-link">
          <div><i class="bi bi-inbox-fill"></i> Inbound RFQs</div>
          <?php if ($newLeads > 0): ?>
            <span class="badge bg-dark font-monospace" style="font-size: 0.7rem;"><?= $newLeads ?></span>
          <?php endif; ?>
        </a>
        <a href="email-desk.php" class="sidebar-link active">
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
            <h1 class="topbar-title">Commercial Email Reply Desk & Outbox</h1>
            <div class="topbar-subtitle">Hostinger Business Webmail & Trade Offer Dispatch</div>
          </div>
        </div>

        <div class="topbar-right">
          <div class="pill-status d-none d-md-inline-flex" title="SMTP & IMAP Connected">
            <span class="status-dot dot-green"></span>
            <span>Hostinger Relay Active</span>
          </div>
          <button type="button" id="btnDeskSyncMail" class="btn-topbar-action" onclick="syncDeskMailboxes()" title="Pull incoming & sent mail from sales@ and info@">
            <i class="bi bi-arrow-repeat"></i> <span>Sync Mail</span>
          </button>
          <button type="button" class="btn-topbar-action" data-bs-toggle="modal" data-bs-target="#mailConfigModal">
            <i class="bi bi-gear"></i> <span>Server Config</span>
          </button>
          <button type="button" class="btn-topbar-primary" data-bs-toggle="modal" data-bs-target="#newOfferModal">
            <i class="bi bi-pencil-square"></i> <span>Compose Trade Offer</span>
          </button>
        </div>
      </header>

      <main class="content-canvas">

        <?php if (!empty($flashMessage)): ?>
          <div class="alert alert-<?= $flashType ?> alert-dismissible fade show d-flex align-items-center gap-2 py-2 px-3 mb-2 small shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill"></i>
            <div><?= htmlspecialchars($flashMessage) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <!-- Split Desk -->
        <div class="mail-split-container">
          
          <!-- Left: Message Stream -->
          <div class="mail-thread-list">
            <div class="mail-thread-toolbar">
              <form method="GET" action="email-desk.php" class="mb-2">
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                  <input type="text" name="q" class="form-control" placeholder="Search email, buyer, subject..." value="<?= htmlspecialchars($searchQuery) ?>">
                </div>
              </form>
              <div class="d-flex" style="gap: 6px;">
                <a href="email-desk.php" class="btn btn-sm btn-outline-secondary flex-grow-1 py-1.5 <?= empty($filterDirection) ? 'active bg-dark text-white' : '' ?>" style="font-size: 0.74rem; font-weight: 600; border-radius: 6px; box-shadow: none !important;">All (<?= $totalMessages ?>)</a>
                <a href="email-desk.php?direction=outbound" class="btn btn-sm btn-outline-secondary flex-grow-1 py-1.5 <?= $filterDirection === 'outbound' ? 'active bg-dark text-white' : '' ?>" style="font-size: 0.74rem; font-weight: 600; border-radius: 6px; box-shadow: none !important;">Outbound (<?= $outboundCount ?>)</a>
                <a href="email-desk.php?direction=inbound" class="btn btn-sm btn-outline-secondary flex-grow-1 py-1.5 <?= $filterDirection === 'inbound' ? 'active bg-dark text-white' : '' ?>" style="font-size: 0.74rem; font-weight: 600; border-radius: 6px; box-shadow: none !important;">Inbound (<?= $inboundCount ?>)</a>
              </div>
            </div>

            <div class="mail-thread-items">
              <?php if (empty($messages)): ?>
                <div class="text-center py-5 text-muted small">
                  <i class="bi bi-envelope-x fs-2 d-block mb-1 text-secondary"></i>
                  No email messages match query.
                </div>
              <?php else: ?>
                <?php foreach ($messages as $m): 
                  $isActive = ($selectedMessage && $selectedMessage['id'] == $m['id']);
                  $corpMailbox = $m['direction'] === 'inbound' ? ($m['to_email'] ?? '') : ($m['from_email'] ?? '');
                  $matchedBox = matchCorporateMailboxFromString($corpMailbox, '');
                  $shortBox = $matchedBox !== '' ? (explode('@', $matchedBox)[0] . '@') : '';
                ?>
                  <a href="email-desk.php?msg_id=<?= $m['id'] ?><?= !empty($filterDirection) ? '&direction=' . $filterDirection : '' ?>" class="mail-item <?= $isActive ? 'active' : '' ?>">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <div class="d-flex align-items-center gap-1">
                        <span class="badge <?= $m['direction'] === 'outbound' ? 'bg-dark' : 'bg-success' ?> font-monospace" style="font-size: 0.65rem;">
                          <?= $m['direction'] === 'outbound' ? 'OUTBOUND' : 'INBOUND' ?>
                        </span>
                        <?php if ($shortBox !== ''): ?>
                          <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.62rem;">
                            <?= htmlspecialchars($shortBox) ?>
                          </span>
                        <?php endif; ?>
                      </div>
                      <span class="text-muted font-monospace" style="font-size: 0.68rem;">
                        <?= date('M d, H:i', strtotime($m['sent_at'])) ?>
                      </span>
                    </div>
                    <div class="fw-bold text-dark text-truncate" style="font-size: 0.85rem;">
                      <?= htmlspecialchars($m['direction'] === 'outbound' ? ($m['buyer_name'] ?: $m['to_email']) : $m['from_email']) ?>
                    </div>
                    <div class="text-muted small text-truncate" style="font-size: 0.76rem; font-weight: 500;">
                      <?= htmlspecialchars($m['subject']) ?>
                    </div>
                    <div class="text-muted text-truncate mt-1" style="font-size: 0.72rem; color: #64748B;">
                      <?= htmlspecialchars(substr(cleanEmailPlainText($m['body_text'] ?? ''), 0, 75)) ?>...
                    </div>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Right: Reading Pane & Reply Console -->
          <div class="mail-reading-pane">
            <?php if (!$selectedMessage): ?>
              <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                <div class="text-center">
                  <i class="bi bi-envelope-open fs-1 text-secondary d-block mb-2"></i>
                  Select an email thread from the left or compose a new trade offer.
                </div>
              </div>
            <?php else: ?>
              <!-- Message Header -->
              <div class="mail-pane-header">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 class="fw-bold brand-font mb-1" style="color: var(--brand-navy); font-size: 1.05rem;">
                      <?= htmlspecialchars($selectedMessage['subject']) ?>
                    </h6>
                    <div class="small text-muted d-flex align-items-center gap-3">
                      <span><strong>From:</strong> <?= htmlspecialchars($selectedMessage['from_email']) ?></span>
                      <span><strong>To:</strong> <?= htmlspecialchars($selectedMessage['to_email']) ?></span>
                      <?php if (!empty($selectedMessage['cc_email'])): ?>
                        <span><strong>CC:</strong> <?= htmlspecialchars($selectedMessage['cc_email']) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="text-end">
                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.72rem;">
                      <?= date('F d, Y • h:i A', strtotime($selectedMessage['sent_at'])) ?>
                    </span>
                    <div class="small text-muted font-monospace mt-1" style="font-size: 0.68rem;">
                      Status: <strong class="text-success"><?= htmlspecialchars($selectedMessage['status']) ?></strong>
                    </div>
                  </div>
                </div>

                <?php if (!empty($selectedMessage['inquiry_id'])): ?>
                  <div class="mt-2 pt-2 border-top d-flex align-items-center justify-content-between small">
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge bg-light text-dark border"><i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($selectedMessage['buyer_name']) ?> (<?= htmlspecialchars($selectedMessage['company_name'] ?: 'Buyer') ?>)</span>
                      <span class="badge bg-light text-dark border"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($selectedMessage['country']) ?></span>
                      <span class="badge" style="background: #FFF7ED; color: #C2410C; border: 1px solid #FFEDD5;"><i class="bi bi-gem me-1"></i><?= htmlspecialchars($selectedMessage['mineral_type']) ?></span>
                    </div>
                    <?php if (!empty($selectedMessage['whatsapp'])): ?>
                      <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $selectedMessage['whatsapp']) ?>" target="_blank" class="btn btn-xs btn-outline-success py-0.5 px-2" style="font-size: 0.72rem;">
                        <i class="bi bi-whatsapp me-1"></i> Connect on WhatsApp
                      </a>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Message Body -->
              <div class="mail-pane-body">
                <?php
                  $selMsgId   = (int)$selectedMessage['id'];
                  $selAttMeta = decodeAttachmentsMeta($selectedMessage['attachments'] ?? '');
                  $selAttList = formatPublicAttachmentsForMessage($selMsgId, $selectedMessage['attachments'] ?? '');
                  $selHtml    = buildRenderableEmailHtml($selectedMessage['body_html'] ?? null, $selectedMessage['body_text'] ?? '', $selMsgId, $selAttMeta);
                ?>
                <div class="p-3 bg-white rounded border shadow-sm thread-rich-body" style="font-size: 0.84rem; line-height: 1.65; color: #1E293B;">
                  <?= $selHtml ?>
                </div>

                <?php if (!empty($selAttList)): ?>
                  <div class="mt-3 p-2.5 bg-white rounded border">
                    <div class="small fw-bold text-uppercase text-muted mb-2" style="font-size: 0.68rem; letter-spacing: 0.05em;">
                      <i class="bi bi-paperclip me-1 text-primary"></i> Attached Documents (<?= count($selAttList) ?>)
                    </div>
                    <div class="thread-attachments-list">
                      <?php foreach ($selAttList as $att): ?>
                        <div class="thread-att-chip">
                          <i class="bi <?= $att['ext'] === 'pdf' ? 'bi-file-earmark-pdf text-danger' : (in_array($att['ext'], ['jpg','jpeg','png','gif','webp'], true) ? 'bi-file-earmark-image text-primary' : 'bi-file-earmark-text text-secondary') ?>"></i>
                          <?php if ($att['available'] && $att['url'] !== ''): ?>
                            <a href="<?= htmlspecialchars($att['url']) ?>" class="thread-att-link" title="Download <?= htmlspecialchars($att['name']) ?>">
                              <?= htmlspecialchars($att['name']) ?>
                            </a>
                            <?php if ($att['size_label'] !== ''): ?>
                              <span class="text-muted">(<?= htmlspecialchars($att['size_label']) ?>)</span>
                            <?php endif; ?>
                            <?php if ($att['can_preview'] && $att['preview_url'] !== ''): ?>
                              <a href="<?= htmlspecialchars($att['preview_url']) ?>" target="_blank" class="thread-att-action" title="Preview in new tab"><i class="bi bi-eye"></i></a>
                            <?php endif; ?>
                          <?php else: ?>
                            <span><?= htmlspecialchars($att['name']) ?></span>
                            <?php if ($att['size_label'] !== ''): ?>
                              <span class="text-muted">(<?= htmlspecialchars($att['size_label']) ?>)</span>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Quick Follow-up Composer -->
              <div class="mail-pane-compose">
                <form method="POST" action="email-desk.php" enctype="multipart/form-data">
                  <input type="hidden" name="action" value="compose_outbound_email">
                  <input type="hidden" name="inquiry_id" value="<?= $selectedMessage['inquiry_id'] ?? 0 ?>">
                  <input type="hidden" name="to_email" value="<?= htmlspecialchars($selectedMessage['direction'] === 'inbound' ? $selectedMessage['from_email'] : $selectedMessage['to_email']) ?>">
                  <input type="hidden" name="subject" value="Re: <?= htmlspecialchars($selectedMessage['subject']) ?>">
                  <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                  <?php
                    $replyMailbox = $selectedMessage['direction'] === 'inbound' ? ($selectedMessage['to_email'] ?? '') : ($selectedMessage['from_email'] ?? '');
                    $selectedSender = matchCorporateMailboxFromString($replyMailbox, 'sales@balochistanminerals.com');
                  ?>
                  <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
                    <span class="fw-semibold text-dark text-break" style="font-size: 0.78rem; min-width: 0;">
                      <i class="bi bi-reply me-1 text-secondary"></i> Quick Reply to <strong class="text-dark"><?= htmlspecialchars($selectedMessage['direction'] === 'inbound' ? $selectedMessage['from_email'] : $selectedMessage['to_email']) ?></strong>
                    </span>
                    <div class="desk-from-wrap">
                      <label for="deskQuickReplyFrom" class="text-muted fw-semibold mb-0 text-nowrap" style="font-size: 0.72rem;">From:</label>
                      <select id="deskQuickReplyFrom" name="from_email" class="form-select form-select-sm font-monospace fw-semibold desk-from-select">
                        <?php foreach (getCorporateMailboxOptions() as $mbMeta): ?>
                          <option value="<?= htmlspecialchars($mbMeta['user']) ?>" <?= $selectedSender === $mbMeta['user'] ? 'selected' : '' ?>><?= htmlspecialchars($mbMeta['user']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>

                  <div class="reply-compose-box">
                    <textarea name="email_body" class="form-control font-monospace reply-textarea" rows="3" placeholder="Type quick trade reply, SGS assay confirmation, or FOB delivery schedule..." required></textarea>

                    <div id="deskQuickReplyDropzone" class="desk-attach-bar">
                      <input type="file" id="deskQuickReplyFilesInput" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.gif,.webp,.zip" class="d-none">
                      <div class="desk-attach-bar-row">
                        <div class="desk-attach-trigger-group">
                          <button type="button" class="btn-desk-attach" onclick="document.getElementById('deskQuickReplyFilesInput').click()">
                            <i class="bi bi-paperclip text-primary"></i>
                            <span>Attach Files</span>
                          </button>
                          <span class="desk-attach-hint d-none d-sm-inline">or drop files here</span>
                        </div>
                        <span id="deskQuickReplyAttachMeta" class="desk-attach-hint">PDF, Word, Excel, Images, ZIP &bull; Max 10 MB/file, 20 MB total</span>
                      </div>
                      <div id="deskQuickReplyChips" class="dossier-attachment-chips"></div>
                    </div>

                    <div class="reply-compose-footer d-flex justify-content-between align-items-center">
                      <div class="text-muted small" style="font-size: 0.72rem;">
                        <i class="bi bi-lightning-charge me-1 text-secondary"></i> Dispatches via Hostinger SMTP TLS Relay
                      </div>
                      <button type="submit" class="btn-send-dispatch">
                        <i class="bi bi-send-fill"></i> <span>Send Reply</span>
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            <?php endif; ?>
          </div>

        </div>

      </main>
    </div>
  </div>

  <!-- Modal: Compose Fresh Outbound Trade Offer -->
  <div class="modal fade" id="newOfferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-resizable" style="max-width: 960px;">
      <div class="modal-content">
        <form method="POST" action="email-desk.php" enctype="multipart/form-data">
          <input type="hidden" name="action" value="compose_outbound_email">
          <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

          <div class="modal-header py-2 px-3">
            <div class="d-flex align-items-center gap-2">
              <span class="badge-rfq-mild">
                <i class="bi bi-pencil-square"></i> B2B OFFER
              </span>
              <h6 class="modal-title fw-bold brand-font mb-0" style="color: var(--brand-navy); font-size: 0.98rem;">
                Compose B2B Commodity Export Offer
              </h6>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body p-3">
            <!-- Autocomplete Select from Verified Buyer Directory -->
            <div class="mb-2 p-2 bg-light rounded border">
              <label class="form-label mb-1" style="font-size: 0.68rem;">Quick Select from Verified Buyer Directory (China / Dubai / Global)</label>
              <select class="form-select form-select-sm" onchange="autoFillBuyerOffer(this)">
                <option value="">-- Choose verified international buyer (optional) --</option>
                <?php foreach ($prospectList as $bp): ?>
                  <option value="<?= htmlspecialchars($bp['email']) ?>" 
                          data-company="<?= htmlspecialchars($bp['company_name']) ?>"
                          data-contact="<?= htmlspecialchars($bp['contact_person']) ?>"
                          data-mineral="<?= htmlspecialchars($bp['target_mineral']) ?>">
                    <?= htmlspecialchars($bp['company_name']) ?> (<?= htmlspecialchars($bp['target_mineral']) ?>) — <?= htmlspecialchars($bp['email']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-6">
                <label class="form-label">From Corporate Mailbox</label>
                <select name="from_email" class="form-select form-select-sm font-monospace fw-bold">
                  <?php foreach (getCorporateMailboxOptions() as $mbMeta): ?>
                    <option value="<?= htmlspecialchars($mbMeta['user']) ?>" <?= $mbMeta['short'] === 'sales@' ? 'selected' : '' ?>><?= htmlspecialchars($mbMeta['label']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">To Buyer Email</label>
                <input type="email" id="outboundToEmail" name="to_email" class="form-control form-control-sm font-monospace fw-bold" placeholder="buyer@smelter.com" required>
              </div>
            </div>

            <div class="mb-2">
              <label class="form-label">Subject Line</label>
              <input type="text" id="outboundSubject" name="subject" class="form-control form-control-sm fw-semibold" value="FOB Karachi Spot Offer & Specification Summary - Balochistan Minerals (Pvt) Ltd" required>
            </div>

            <div class="mb-2">
              <label class="form-label">Export Offer Letter Body</label>
              <textarea id="outboundBody" name="email_body" class="form-control form-control-sm font-monospace" rows="9" required style="font-size: 0.80rem; line-height: 1.45;">Dear Procurement Director,

Balochistan Minerals (Pvt) Ltd is pleased to present our official commercial export quotation for prompt shipment out of Gwadar Port, Karachi Port Trust (KPT) & Port Qasim corridors:

1. Commodity: High-Grade Lumpy Ore
2. Concessions: Muslim Bagh / Khuzdar mining concessions, Balochistan
3. Port Terms: FOB Gwadar / Karachi (KPT / Port Qasim) or CIF Asian/Middle Eastern Discharge Ports (Incoterms 2020)
4. Quality Verification: 100% Certified by SGS / Alfred H. Knight / PCSIR at load port
5. Payment Mechanism: 100% Irrevocable Letter of Credit (L/C at Sight) compliant with State Bank of Pakistan foreign exchange regulations

We welcome your inspection team to verify stockpiles prior to vessel loading. Please confirm your desired discharge port and volume allocation so we may issue the formal Proforma Invoice.

Sincerely,
Commercial Export Operations Desk
Balochistan Minerals (Pvt) Ltd
Email: sales@balochistanminerals.com
WhatsApp: +92 334 8888104
Web: https://balochistanminerals.com</textarea>
            </div>

            <div class="mb-2">
              <label class="form-label">Attach Trade Documents (PDF Quote, COA, Assay, Photos)</label>
              <div id="deskOfferDropzone" class="desk-attach-bar mt-0">
                <input type="file" id="deskOfferFilesInput" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.gif,.webp,.zip" class="d-none">
                <div class="desk-attach-bar-row">
                  <div class="desk-attach-trigger-group">
                    <button type="button" class="btn-desk-attach" onclick="document.getElementById('deskOfferFilesInput').click()">
                      <i class="bi bi-paperclip text-primary"></i>
                      <span>Attach Documents</span>
                    </button>
                    <span class="desk-attach-hint d-none d-sm-inline">or drop files here</span>
                  </div>
                  <span id="deskOfferAttachMeta" class="desk-attach-hint">Optional &bull; Up to 10 files (Max 10 MB/file, 20 MB total)</span>
                </div>
                <div id="deskOfferChips" class="dossier-attachment-chips"></div>
              </div>
            </div>

            <div class="p-2 bg-light rounded border d-flex align-items-center justify-content-between">
              <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input me-2" checked id="chkAssay">
                <label class="form-check-label small text-muted" for="chkAssay">Include SGS & PCSIR Chemical Assay Reference Link</label>
              </div>
              <span class="small text-muted font-monospace" style="font-size: 0.68rem;">Hostinger SMTP TLS Relay</span>
            </div>
          </div>

          <div class="modal-footer d-flex justify-content-end gap-2 py-2 px-3 bg-light border-top">
            <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-1" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-dark px-3 py-1 fw-bold text-white shadow-sm" style="background: #0F172A; border-color: #0F172A; border-radius: 6px;">
              <i class="bi bi-send-fill me-1"></i> Dispatch Trade Offer
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Hostinger Mail Server Settings -->
  <div class="modal fade" id="mailConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
      <div class="modal-content">
        <form method="POST" action="email-desk.php" enctype="multipart/form-data">
          <input type="hidden" name="action" value="save_mail_settings">
          <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

          <div class="modal-header py-2.5 px-3">
            <div class="d-flex align-items-center gap-2">
              <span class="badge-rfq-mild">
                <i class="bi bi-gear-fill"></i> HOSTINGER SMTP
              </span>
              <h6 class="modal-title fw-bold brand-font mb-0" style="color: var(--brand-navy); font-size: 0.95rem;">
                Hostinger Corporate Mailbox Credentials
              </h6>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body p-3.5">
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
              <label class="form-label">Corporate Email Address</label>
              <input type="email" name="smtp_user" class="form-control form-control-sm font-monospace fw-bold" value="<?= htmlspecialchars(defined('BM_SMTP_USER') ? BM_SMTP_USER : 'sales@balochistanminerals.com') ?>" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Mailbox Password</label>
              <input type="password" name="smtp_pass" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($_SESSION['bm_mail_pass'] ?? (defined('BM_SMTP_PASS') ? BM_SMTP_PASS : '')) ?>" placeholder="Enter Hostinger email password">
            </div>

            <div class="p-2 rounded bg-white border d-flex align-items-center justify-content-between">
              <span class="small text-muted font-monospace" style="font-size: 0.72rem;">Live Socket Handshake</span>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function autoFillBuyerOffer(select) {
      const opt = select.options[select.selectedIndex];
      if (!opt || !opt.value) return;

      const email = opt.value;
      const company = opt.getAttribute('data-company') || '';
      const contact = opt.getAttribute('data-contact') || 'Procurement Team';
      const mineral = opt.getAttribute('data-mineral') || 'High Grade Ore';

      document.getElementById('outboundToEmail').value = email;
      document.getElementById('outboundSubject').value = "FOB Gwadar / Karachi Spot Offer: " + mineral + " for " + company;
      
      const body = `Dear ${contact} (${company}),\n\n` +
        `Balochistan Minerals (Pvt) Ltd connects directly to your verified commercial requirements for ${mineral}.\n\n` +
        `We confirm available mine stockpiles ready for prompt FOB Gwadar / Karachi / Port Qasim shipment:\n\n` +
        `• Commodity: ${mineral}\n` +
        `• Inspection: 100% Verified by SGS / Alfred H. Knight at load port\n` +
        `• Payment: 100% Irrevocable Letter of Credit (L/C at Sight)\n\n` +
        `Would you like us to dispatch our latest composite laboratory assay report and indicative spot quote for your review?\n\n` +
        `Sincerely,\nCommercial Export Operations Desk\nBalochistan Minerals (Pvt) Ltd\nWeb: https://balochistanminerals.com`;

      document.getElementById('outboundBody').value = body;
    }

    async function syncDeskMailboxes() {
      const btn = document.getElementById('btnDeskSyncMail');
      const orig = btn ? btn.innerHTML : '';
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> <span>Syncing...</span>';
      }
      try {
        const res = await fetch('mail-sync.php?action=sync&manual=1');
        const data = await res.json();
        const syncedTotal = Number(data.synced_count ?? ((data.synced_inbound || 0) + (data.synced_sent || 0)));
        if (window.BMNotify) window.BMNotify.refresh();
        if (syncedTotal > 0) {
          window.location.reload();
        } else if (data.warnings && data.warnings.length) {
          window.BMNotify?.toast({ type: 'warning', title: 'Mailbox Sync Warning', message: data.warnings.join(' | '), openBell: true });
        } else {
          window.BMNotify?.toast({ type: 'info', title: 'Mailboxes Up to Date', message: data.message || data.info || 'All mailboxes are up to date.' });
        }
      } catch (err) {
        window.BMNotify?.toast({ type: 'error', title: 'Mail Sync Error', message: err.message });
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = orig;
        }
      }
    }

    function escapeDeskHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function formatDeskFileSize(bytes) {
      const b = Number(bytes) || 0;
      if (b < 1024) return b + ' B';
      if (b < 1048576) return Math.round(b / 1024) + ' KB';
      return (b / 1048576).toFixed(1) + ' MB';
    }

    /**
     * Reusable file-picker + drag/drop attachment controller backed by DataTransfer
     * so native multipart form submission receives every selected file after edits/removals.
     */
    function initDeskAttachDropzone({ dropzoneId, inputId, chipsId, metaId, defaultMeta }) {
      const dropzone = document.getElementById(dropzoneId);
      const input = document.getElementById(inputId);
      const chipsEl = document.getElementById(chipsId);
      const metaEl = document.getElementById(metaId);
      if (!dropzone || !input || !chipsEl) return;

      let selectedFiles = [];

      function syncInputFiles() {
        if (typeof DataTransfer !== 'function') return;
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        input.files = dt.files;
      }

      function renderChips() {
        if (!selectedFiles.length) {
          chipsEl.innerHTML = '';
          if (metaEl) metaEl.textContent = defaultMeta;
          return;
        }
        let totalBytes = 0;
        chipsEl.innerHTML = selectedFiles.map((file, idx) => {
          totalBytes += file.size;
          return `<span class="dossier-att-pill" title="${escapeDeskHtml(file.name)}">
            <i class="bi bi-paperclip text-primary"></i>
            <span class="dossier-att-name">${escapeDeskHtml(file.name)}</span>
            <span class="text-muted font-monospace" style="font-size: 0.65rem;">(${formatDeskFileSize(file.size)})</span>
            <button type="button" class="dossier-att-remove" data-remove-idx="${idx}" aria-label="Remove attachment">&times;</button>
          </span>`;
        }).join('');
        if (metaEl) {
          metaEl.textContent = `${selectedFiles.length} file(s) • ${formatDeskFileSize(totalBytes)} / 20 MB`;
        }
      }

      function addFiles(fileList) {
        if (!fileList || !fileList.length) return;
        const maxPerFile = 10 * 1024 * 1024;
        const maxTotal = 20 * 1024 * 1024;
        let currentTotal = selectedFiles.reduce((acc, f) => acc + f.size, 0);
        const errors = [];

        Array.from(fileList).forEach(file => {
          if (selectedFiles.length >= 10) {
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
          const dup = selectedFiles.some(x => x.name === file.name && x.size === file.size);
          if (!dup) {
            selectedFiles.push(file);
            currentTotal += file.size;
          }
        });

        if (errors.length && window.BMNotify) {
          window.BMNotify.toast({ type: 'warning', title: 'Attachment Notice', message: errors[0] });
        } else if (errors.length) {
          alert(errors[0]);
        }

        syncInputFiles();
        renderChips();
      }

      input.addEventListener('change', (ev) => {
        const picked = Array.from(ev.target.files || []);
        if (picked.length) addFiles(picked);
      });

      chipsEl.addEventListener('click', (ev) => {
        const btn = ev.target.closest('[data-remove-idx]');
        if (!btn) return;
        const idx = Number(btn.getAttribute('data-remove-idx'));
        if (!Number.isInteger(idx) || idx < 0 || idx >= selectedFiles.length) return;
        selectedFiles.splice(idx, 1);
        syncInputFiles();
        renderChips();
      });

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
          addFiles(e.dataTransfer.files);
        }
      });
    }

    document.addEventListener('DOMContentLoaded', () => {
      initDeskAttachDropzone({
        dropzoneId: 'deskQuickReplyDropzone',
        inputId: 'deskQuickReplyFilesInput',
        chipsId: 'deskQuickReplyChips',
        metaId: 'deskQuickReplyAttachMeta',
        defaultMeta: 'PDF, Word, Excel, Images, ZIP \u2022 Max 10 MB/file, 20 MB total'
      });
      initDeskAttachDropzone({
        dropzoneId: 'deskOfferDropzone',
        inputId: 'deskOfferFilesInput',
        chipsId: 'deskOfferChips',
        metaId: 'deskOfferAttachMeta',
        defaultMeta: 'Optional \u2022 Up to 10 files (Max 10 MB/file, 20 MB total)'
      });
    });
  </script>
  <script src="admin-layout.js?v=<?= filemtime(__DIR__ . '/admin-layout.js') ?>"></script>
</body>
</html>
