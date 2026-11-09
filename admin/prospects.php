<?php
/**
 * Balochistan Minerals - Global Buyer Prospector & Outbound Directory
 * Directory of verified international smelters, refineries, and trading houses with 1-click outreach.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flag-helper.php';
requireAuth();

$user = getLoggedInUser();
$pdo  = getDB();

$flashMessage = '';
$flashType    = 'success';

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export_csv' && $pdo !== null) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Balochistan_Minerals_Buyer_Prospects_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Company Name', 'Contact Person', 'Country', 'City', 'Email', 'Phone', 'WhatsApp', 'Target Mineral', 'Buyer Type', 'Annual Demand', 'Target Grade', 'Status', 'Notes', 'Created At']);
    
    $rows = $pdo->query("SELECT * FROM buyer_prospects ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        fputcsv($output, [
            $r['id'], $r['company_name'], $r['contact_person'], $r['country'], $r['city'],
            $r['email'], $r['phone'], $r['whatsapp'], $r['target_mineral'], $r['buyer_type'],
            $r['annual_demand'], $r['target_grade'], $r['status'], $r['notes'], $r['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $pdo !== null) {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (verifyCsrfToken($csrf)) {
        if ($action === 'save_prospect') {
            $id          = (int)($_POST['prospect_id'] ?? 0);
            $company     = cleanInput($_POST['company_name'] ?? '');
            $contact     = cleanInput($_POST['contact_person'] ?? '');
            $country     = cleanInput($_POST['country'] ?? '');
            $city        = cleanInput($_POST['city'] ?? '');
            $email       = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $phone       = cleanInput($_POST['phone'] ?? '');
            $whatsapp    = cleanInput($_POST['whatsapp'] ?? '');
            $mineral     = cleanInput($_POST['target_mineral'] ?? '');
            $buyerType   = cleanInput($_POST['buyer_type'] ?? 'Smelter / End User');
            $demand      = cleanInput($_POST['annual_demand'] ?? '');
            $grade       = cleanInput($_POST['target_grade'] ?? '');
            $status      = cleanInput($_POST['status'] ?? 'Identified');
            $notes       = cleanInput($_POST['notes'] ?? '');

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE buyer_prospects SET 
                    company_name = ?, contact_person = ?, country = ?, city = ?, email = ?, 
                    phone = ?, whatsapp = ?, target_mineral = ?, buyer_type = ?, 
                    annual_demand = ?, target_grade = ?, status = ?, notes = ? 
                    WHERE id = ?");
                $stmt->execute([$company, $contact, $country, $city, $email, $phone, $whatsapp, $mineral, $buyerType, $demand, $grade, $status, $notes, $id]);
                $flashMessage = 'Prospect "' . $company . '" updated successfully.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO buyer_prospects 
                    (company_name, contact_person, country, city, email, phone, whatsapp, target_mineral, buyer_type, annual_demand, target_grade, status, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$company, $contact, $country, $city, $email, $phone, $whatsapp, $mineral, $buyerType, $demand, $grade, $status, $notes]);
                $flashMessage = 'New buyer prospect "' . $company . '" added to directory.';
            }
        } elseif ($action === 'quick_status') {
            $id     = (int)($_POST['prospect_id'] ?? 0);
            $status = cleanInput($_POST['status'] ?? 'Identified');
            $stmt = $pdo->prepare("UPDATE buyer_prospects SET status = ?, last_contacted_at = CASE WHEN ? != 'Identified' THEN NOW() ELSE last_contacted_at END WHERE id = ?");
            $stmt->execute([$status, $status, $id]);
            $flashMessage = 'Status updated to "' . $status . '".';
        } elseif ($action === 'send_prospect_email') {
            $id         = (int)($_POST['prospect_id'] ?? 0);
            $toEmail    = filter_var(trim($_POST['to_email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $subject    = cleanInput($_POST['subject'] ?? 'Commodity Supply Inquiry');
            $body       = cleanInput($_POST['email_body'] ?? '');
            $fromEmail  = 'sales@balochistanminerals.com';

            $log = "\n[" . date('M d, Y H:i') . "] Outbound Pitch Sent to " . $toEmail . " (Subject: " . $subject . ")\n";
            $stmt = $pdo->prepare("UPDATE buyer_prospects SET notes = CONCAT(COALESCE(notes, ''), ?), status = 'Outreach Sent', last_contacted_at = NOW() WHERE id = ?");
            $stmt->execute([$log, $id]);

            @mail($toEmail, $subject, $body, "From: Balochistan Minerals <" . $fromEmail . ">\r\nReply-To: " . $fromEmail);
            $flashMessage = 'Outbound trade email dispatched to ' . $toEmail . ' and status moved to "Outreach Sent".';
        } elseif ($action === 'delete_prospect') {
            $id = (int)($_POST['prospect_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM buyer_prospects WHERE id = ?");
            $stmt->execute([$id]);
            $flashMessage = 'Prospect entry deleted.';
            $flashType = 'warning';
        }
    } else {
        $flashMessage = 'Session expired. Please try again.';
        $flashType = 'danger';
    }
}

// Fetch Filters
$filterMineral = cleanInput($_GET['mineral'] ?? '');
$filterCountry = cleanInput($_GET['country'] ?? '');
$filterStatus  = cleanInput($_GET['status'] ?? '');
$searchQuery   = cleanInput($_GET['q'] ?? '');

$prospects = [];
$totalCount = 0;
$outreachCount = 0;
$negotiatingCount = 0;
$contractCount = 0;
$newLeads = 0;
$pendingReviews = 0;

if ($pdo !== null) {
    $newLeads = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'New'")->fetchColumn();
    $pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'Pending'")->fetchColumn();

    $totalCount       = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects")->fetchColumn();
    $outreachCount    = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects WHERE status = 'Outreach Sent'")->fetchColumn();
    $negotiatingCount = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects WHERE status IN ('Assay Shared', 'Negotiating')")->fetchColumn();
    $contractCount    = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects WHERE status = 'Contract Executed'")->fetchColumn();

    $sql = "SELECT * FROM buyer_prospects WHERE 1=1";
    $params = [];

    if (!empty($filterMineral)) {
        list($mClause, $mParams) = buildMineralFilterClause($filterMineral, 'target_mineral');
        $sql .= $mClause;
        $params = array_merge($params, $mParams);
    }
    if (!empty($filterCountry)) {
        $sql .= " AND country = ?";
        $params[] = $filterCountry;
    }
    if (!empty($filterStatus)) {
        $sql .= " AND status = ?";
        $params[] = $filterStatus;
    }
    if (!empty($searchQuery)) {
        $sql .= " AND (company_name LIKE ? OR contact_person LIKE ? OR city LIKE ? OR notes LIKE ?)";
        $term = '%' . $searchQuery . '%';
        $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
    }

    $sql .= " ORDER BY CASE status 
        WHEN 'Negotiating' THEN 1 
        WHEN 'Assay Shared' THEN 2 
        WHEN 'Outreach Sent' THEN 3 
        WHEN 'Identified' THEN 4 
        ELSE 5 END, id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $prospects = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Global Buyer Prospector | Balochistan Minerals</title>
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
      --navy-900: #0F172A;
      --brand-gold: #B48C36;
      --brand-canvas: #F8FAFC;
      --brand-surface: #FFFFFF;
      --brand-border: #E2E8F0;
      --brand-orange: #E65C1A;
      --text-dark: #0F172A;
      --text-muted: #64748B;
      --sidebar-width: 255px;
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
      padding: 8px 12px;
      border-radius: 6px;
      color: #475569;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.82rem;
      font-weight: 600;
      text-decoration: none;
      margin-bottom: 2px;
      transition: all 0.15s ease;
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
      background: var(--brand-gold);
      border-radius: 0 3px 3px 0;
    }
    .sidebar-link.active i { color: var(--brand-gold); }
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
    }
    .top-bar {
      background: var(--brand-surface);
      border-bottom: 1px solid var(--brand-border);
      padding: 12px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky; top: 0; z-index: 1030;
    }
    .content-canvas { padding: 16px 24px; flex: 1; }

    /* Compact Executive Stat Strip */
    .stat-strip {
      background: #FFFFFF;
      border: 1px solid var(--brand-border);
      border-radius: 8px;
      padding: 8px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 16px;
      box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }
    .stat-strip-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 4px 16px;
      border-right: 1px solid var(--brand-border);
      flex: 1;
    }
    .stat-strip-item:last-child { border-right: none; }
    .stat-strip-val {
      font-family: 'Montserrat', sans-serif;
      font-size: 1.35rem;
      font-weight: 600;
      color: var(--brand-navy);
      line-height: 1.1;
      letter-spacing: -0.02em;
      font-variant-numeric: tabular-nums;
    }
    .stat-strip-label {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #64748B;
    }

    /* Table Container */
    .table-panel {
      background: #FFFFFF;
      border: 1px solid var(--brand-border);
      border-radius: 10px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.02);
      overflow: hidden;
    }
    .table-panel-header {
      padding: 12px 20px;
      border-bottom: 1px solid var(--brand-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #FFFFFF;
    }
    .table-scroll-container {
      max-height: 600px;
      overflow-y: auto;
      overflow-x: auto;
    }
    .table-clean { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 0; }
    .table-clean thead th {
      background: #F8FAFC;
      color: #475569;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 10px 14px;
      border-bottom: 1px solid var(--brand-border);
      white-space: nowrap;
      position: sticky; top: 0; z-index: 10;
    }
    .table-clean tbody td {
      padding: 12px 14px;
      border-bottom: 1px solid #F1F5F9;
      vertical-align: middle;
      font-size: 0.85rem;
    }
    .table-clean tr:hover td { background-color: #FAFCFE; }

    /* Crisp Luxury Modals */
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
      padding: 7px 12px;
      font-size: 0.86rem;
      color: #0F172A;
      font-weight: 500;
    }
    .form-control:focus, .form-select:focus {
      border-color: #E65C1A;
      box-shadow: 0 0 0 3px rgba(230, 92, 26, 0.15);
      outline: none;
    }
    .form-label {
      font-size: 0.70rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #475569;
      margin-bottom: 4px;
    }

    /* Buttons */
    .btn-action-wa {
      background: #15803D;
      color: #FFFFFF !important;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.76rem;
      font-weight: 600;
      height: 32px;
      padding: 0 12px;
      border-radius: 6px;
      border: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      transition: background 0.15s ease;
      white-space: nowrap;
    }
    .btn-action-wa:hover { background: #166534; }

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
      cursor: pointer;
      text-decoration: none;
      box-shadow: none !important;
      transition: all 0.15s ease;
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
      transition: background 0.15s ease;
      white-space: nowrap;
    }
    .btn-action-wa:hover {
      background: #166534;
      border-color: #166534;
      box-shadow: none !important;
    }

    .btn-save-action {
      background: #0F172A !important;
      color: #FFFFFF !important;
      border: 1px solid #0F172A !important;
      padding: 8px 22px !important;
      font-weight: 700 !important;
      font-size: 0.84rem !important;
      border-radius: 6px !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 6px !important;
      cursor: pointer !important;
    }
    .btn-save-action:hover { background: #1E293B !important; }

    .btn-cancel-action {
      background: #FFFFFF !important;
      color: #475569 !important;
      border: 1.5px solid #CBD5E1 !important;
      padding: 8px 18px !important;
      font-weight: 600 !important;
      font-size: 0.84rem !important;
      border-radius: 6px !important;
      cursor: pointer !important;
    }

    /* Status Badges */
    .status-badge {
      font-size: 0.72rem;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 4px;
      display: inline-block;
      white-space: nowrap;
    }
    .status-Identified { background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; }
    .status-Outreach_Sent { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
    .status-Assay_Shared { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
    .status-Negotiating { background: #FFF7ED; color: #C2410C; border: 1px solid #FED7AA; }
    .status-Contract_Executed { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }
  </style>
</head>
<body>

  <div class="app-wrapper">

    <!-- 1. LEFT EXECUTIVE SIDEBAR (Slideable & Collapsible) -->
    <aside class="app-sidebar" id="appSidebar">
      <div class="sidebar-header">
        <a href="../" target="_blank" class="sidebar-logo-wrap" title="Balochistan Minerals">
          <img src="../images/new-logo.png" alt="Balochistan Minerals" class="sidebar-logo" onerror="this.style.display='none'">
          <span class="sidebar-badge">TRADE DESK PORTAL</span>
        </a>
        <button type="button" id="sidebarCloseBtn" class="btn btn-sm btn-light border d-lg-none" aria-label="Close Sidebar" title="Close Sidebar">
          <i class="bi bi-x-lg"></i>
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
        <a href="email-desk.php" class="sidebar-link">
          <div><i class="bi bi-envelope"></i> Email Reply Desk</div>
          <span class="badge-sidebar-hostinger">HOSTINGER</span>
        </a>
        <a href="prospects.php" class="sidebar-link active">
          <div><i class="bi bi-bullseye"></i> Buyer Directory</div>
          <span class="badge font-monospace" style="background: rgba(230,92,26,0.15); color: #E65C1A; font-size: 0.7rem;"><?= $totalCount ?></span>
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
            <h1 class="topbar-title">Global Buyer Prospector & Outbound Directory</h1>
            <div class="topbar-subtitle">Verified Smelters, Refineries & International Trading Houses</div>
          </div>
        </div>
        <div class="topbar-right">
          <div class="pill-status d-none d-xl-inline-flex" title="Active Importers in Directory">
            <span class="status-dot dot-green"></span>
            <span><?= $totalCount ?> Verified Importers</span>
          </div>
          <a href="prospects.php?action=export_csv" class="btn-topbar-action">
            <i class="bi bi-file-earmark-spreadsheet"></i> <span>Export CSV</span>
          </a>
          <button type="button" class="btn-topbar-primary" data-bs-toggle="modal" data-bs-target="#addProspectModal">
            <i class="bi bi-plus-lg"></i> <span>Add New Buyer</span>
          </button>
        </div>
      </header>

      <main class="content-canvas">

        <?php if (!empty($flashMessage)): ?>
          <div class="alert alert-<?= $flashType ?> alert-dismissible fade show d-flex align-items-center gap-2 py-2.5 px-3 mb-3" role="alert">
            <i class="bi bi-check-circle-fill"></i>
            <div><?= htmlspecialchars($flashMessage) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <!-- Compact KPI Strip -->
        <div class="stat-strip">
          <div class="stat-strip-item">
            <i class="bi bi-building-check text-primary fs-3"></i>
            <div>
              <div class="stat-strip-val"><?= $totalCount ?></div>
              <div class="stat-strip-label">Verified Importers</div>
            </div>
          </div>
          <div class="stat-strip-item">
            <i class="bi bi-send-check text-info fs-3"></i>
            <div>
              <div class="stat-strip-val"><?= $outreachCount ?></div>
              <div class="stat-strip-label">Outreach Initiated</div>
            </div>
          </div>
          <div class="stat-strip-item">
            <i class="bi bi-chat-quote-fill text-warning fs-3"></i>
            <div>
              <div class="stat-strip-val"><?= $negotiatingCount ?></div>
              <div class="stat-strip-label">Assay / Negotiating</div>
            </div>
          </div>
          <div class="stat-strip-item">
            <i class="bi bi-award-fill text-success fs-3"></i>
            <div>
              <div class="stat-strip-val"><?= $contractCount ?></div>
              <div class="stat-strip-label">Active Contracts</div>
            </div>
          </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <form method="GET" action="prospects.php" class="card p-2.5 bg-white border mb-3" style="border-radius: 8px;">
          <div class="row g-2 align-items-center">
            <div class="col-md-4">
              <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Search company, contact, city, notes..." value="<?= htmlspecialchars($searchQuery) ?>">
              </div>
            </div>
            <div class="col-md-3">
              <select name="mineral" class="form-select form-select-sm" onchange="this.form.submit()" title="Filter by Commodity">
                <?= renderCommodityOptions($filterMineral, true, 'All Commodities') ?>
              </select>
            </div>
            <div class="col-md-3">
              <select name="country" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Buyer Corridors</option>
                <option value="China" <?= $filterCountry === 'China' ? 'selected' : '' ?>>China</option>
                <option value="United Arab Emirates" <?= $filterCountry === 'United Arab Emirates' ? 'selected' : '' ?>>UAE (Dubai / Jebel Ali)</option>
                <option value="Saudi Arabia" <?= $filterCountry === 'Saudi Arabia' ? 'selected' : '' ?>>Saudi Arabia</option>
                <option value="Japan" <?= $filterCountry === 'Japan' ? 'selected' : '' ?>>Japan</option>
                <option value="South Korea" <?= $filterCountry === 'South Korea' ? 'selected' : '' ?>>South Korea</option>
                <option value="Germany" <?= $filterCountry === 'Germany' ? 'selected' : '' ?>>Germany</option>
                <option value="Turkey" <?= $filterCountry === 'Turkey' ? 'selected' : '' ?>>Turkey</option>
                <option value="India" <?= $filterCountry === 'India' ? 'selected' : '' ?>>India</option>
              </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
              <button type="submit" class="btn-filter-action">
                <i class="bi bi-funnel"></i> <span>Filter</span>
              </button>
              <?php if (!empty($filterMineral) || !empty($filterCountry) || !empty($searchQuery)): ?>
                <a href="prospects.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
              <?php endif; ?>
            </div>
          </div>
        </form>

        <!-- Prospects Table -->
        <div class="table-panel">
          <div class="table-panel-header">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-journal-text text-warning fs-5"></i>
              <span class="fw-bold brand-font" style="color: var(--brand-navy); font-size: 0.95rem;">
                Commercial Buyer Register (<?= count($prospects) ?> Matching Targets)
              </span>
            </div>
            <span class="small text-muted font-monospace">Verified FOB Gwadar, KPT & Port Qasim Importers</span>
          </div>

          <div class="table-scroll-container">
            <table class="table-clean">
              <thead>
                <tr>
                  <th>Target Company & Corridor</th>
                  <th>Target Mineral & Spec</th>
                  <th>Buyer Profile & Demand</th>
                  <th>Direct Contact</th>
                  <th>Pipeline Stage</th>
                  <th class="text-end" style="min-width: 235px;">1-Click Outreach</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($prospects)): ?>
                  <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                      <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
                      No buyer prospects match your selected filters.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($prospects as $b): 
                    $statusClass = 'status-' . str_replace([' ', '/'], '_', $b['status']);

                    // Pre-generate professional WhatsApp pitch tailored to this prospect
                    $cleanWa = preg_replace('/[^0-9]/', '', $b['whatsapp']);
                    $waPitch = "Dear " . ($b['contact_person'] ?: 'Procurement Team') . " (" . $b['company_name'] . "),\n\n"
                      . "Balochistan Minerals (Pvt) Ltd connects directly to your demand for " . $b['target_mineral'] . " (" . ($b['target_grade'] ?: 'High Grade') . ").\n\n"
                      . "We have active mine stockpiles ready for prompt FOB Gwadar / Karachi (KPT / Port Qasim) shipment with recent SGS chemical assay certificates.\n\n"
                      . "Would you like us to dispatch our current specification assay sheet and indicative spot quote for your review?\n\n"
                      . "Best regards,\nBalochistan Minerals Commercial Export Desk\nWeb: https://balochistanminerals.com";
                    $waUrl = "https://wa.me/" . $cleanWa . "?text=" . urlencode($waPitch);
                  ?>
                    <tr>
                      <!-- Company & Corridor -->
                      <td>
                        <div class="fw-bold" style="color: var(--brand-navy); font-size: 0.90rem;">
                          <?= htmlspecialchars($b['company_name']) ?>
                        </div>
                        <div class="d-flex align-items-center gap-1.5 mt-0.5">
                          <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1.5" style="font-size: 0.70rem;">
                            <span class="buyer-flag-chip" style="width: 16px; height: 11px; border-radius: 2px;"><?= getCountryFlagSvg($b['country'] ?? '') ?></span>
                            <?= htmlspecialchars($b['country']) ?> (<?= htmlspecialchars($b['city']) ?>)
                          </span>
                        </div>
                      </td>

                      <!-- Mineral & Spec -->
                      <td>
                        <div class="d-flex align-items-center gap-1.5">
                          <span class="badge fw-bold" style="background: #FFF7ED; color: #C2410C; border: 1px solid #FFEDD5; font-size: 0.74rem;">
                            <i class="bi bi-gem me-1"></i><?= htmlspecialchars($b['target_mineral']) ?>
                          </span>
                        </div>
                        <div class="small text-muted font-monospace mt-1" style="font-size: 0.76rem;">
                          <?= htmlspecialchars($b['target_grade']) ?>
                        </div>
                      </td>

                      <!-- Buyer Profile & Demand -->
                      <td>
                        <div class="fw-semibold small text-dark"><?= htmlspecialchars($b['buyer_type']) ?></div>
                        <div class="small text-muted font-monospace" style="font-size: 0.75rem;">
                          <i class="bi bi-box-seam me-1 text-secondary"></i>Est: <?= htmlspecialchars($b['annual_demand'] ?: 'Spot Bulk') ?>
                        </div>
                      </td>

                      <!-- Direct Contact -->
                      <td>
                        <div class="fw-semibold small text-dark"><?= htmlspecialchars($b['contact_person'] ?: 'Procurement Dept') ?></div>
                        <div class="d-flex align-items-center gap-1.5 mt-0.5">
                          <a href="mailto:<?= htmlspecialchars($b['email']) ?>" class="text-decoration-none font-monospace small" style="color: #0284C7; font-size: 0.74rem;" title="Email <?= htmlspecialchars($b['email']) ?>">
                            <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($b['email']) ?>
                          </a>
                          <button type="button" class="btn btn-link p-0 text-muted" onclick="copyToClipboard('<?= htmlspecialchars($b['email']) ?>', this)" title="Copy email address" style="font-size: 0.70rem; line-height: 1;">
                            <i class="bi bi-clipboard"></i>
                          </button>
                        </div>
                        <?php if (!empty($b['phone'])): ?>
                          <div class="small font-monospace text-muted mt-0.5" style="font-size: 0.72rem;">
                            <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($b['phone']) ?>
                          </div>
                        <?php endif; ?>
                      </td>

                      <!-- Pipeline Stage Dropdown -->
                      <td>
                        <form method="POST" action="prospects.php" class="d-inline">
                          <input type="hidden" name="action" value="quick_status">
                          <input type="hidden" name="prospect_id" value="<?= $b['id'] ?>">
                          <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                          <select name="status" class="form-select form-select-sm fw-bold <?= $statusClass ?>" style="font-size: 0.72rem; padding: 3px 20px 3px 8px; width: 135px;" onchange="this.form.submit()">
                            <option value="Identified" <?= $b['status'] === 'Identified' ? 'selected' : '' ?>>Identified</option>
                            <option value="Outreach Sent" <?= $b['status'] === 'Outreach Sent' ? 'selected' : '' ?>>Outreach Sent</option>
                            <option value="Assay Shared" <?= $b['status'] === 'Assay Shared' ? 'selected' : '' ?>>Assay Shared</option>
                            <option value="Negotiating" <?= $b['status'] === 'Negotiating' ? 'selected' : '' ?>>Negotiating</option>
                            <option value="Contract Executed" <?= $b['status'] === 'Contract Executed' ? 'selected' : '' ?>>Contract Executed</option>
                          </select>
                        </form>
                      </td>

                      <!-- 1-Click Outreach Buttons -->
                      <td class="text-end">
                        <div class="d-inline-flex align-items-center justify-content-end" style="gap: 8px;">
                          <!-- 1-Click WhatsApp Pitch -->
                          <a href="<?= $waUrl ?>" target="_blank" class="btn-action-wa" title="Open pre-formatted WhatsApp trade offer">
                            <i class="bi bi-whatsapp"></i> <span>WhatsApp</span>
                          </a>

                          <!-- 1-Click Email Pitch -->
                          <button type="button" class="btn-action-email" data-bs-toggle="modal" data-bs-target="#emailModal<?= $b['id'] ?>" title="Compose official B2B trade introduction email">
                            <i class="bi bi-send-fill"></i> <span>Pitch</span>
                          </button>

                          <!-- Edit Dossier -->
                          <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: 6px; padding: 0;" data-bs-toggle="modal" data-bs-target="#editModal<?= $b['id'] ?>" title="Edit prospect details & notes">
                            <i class="bi bi-pencil"></i>
                          </button>
                        </div>
                      </td>
                    </tr>

                    <!-- Modal: 1-Click Email Outreach -->
                    <div class="modal fade" id="emailModal<?= $b['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-dialog-resizable" style="max-width: 880px;">
                        <div class="modal-content">
                          <form method="POST" action="prospects.php">
                            <input type="hidden" name="action" value="send_prospect_email">
                            <input type="hidden" name="prospect_id" value="<?= $b['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                            <div class="modal-header py-2.5 px-3">
                              <div class="d-flex align-items-center gap-2">
                                <span class="badge-rfq-mild">
                                  <i class="bi bi-send-fill"></i> PITCH
                                </span>
                                <h6 class="modal-title fw-bold brand-font mb-0" style="color: var(--brand-navy); font-size: 0.98rem;">
                                  Outbound Commodity Pitch: <?= htmlspecialchars($b['company_name']) ?>
                                </h6>
                              </div>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>

                            <div class="modal-body p-3.5">
                              <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                  <label class="form-label">To Recipient</label>
                                  <input type="email" name="to_email" class="form-control form-control-sm font-monospace fw-bold" value="<?= htmlspecialchars($b['email']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                  <label class="form-label">Target Commodity</label>
                                  <input type="text" class="form-control form-control-sm bg-light" value="<?= htmlspecialchars($b['target_mineral']) ?> (<?= htmlspecialchars($b['target_grade']) ?>)" readonly>
                                </div>
                              </div>

                              <div class="mb-2">
                                <label class="form-label">Subject</label>
                                <input type="text" name="subject" class="form-control form-control-sm fw-semibold" value="FOB Karachi Supply Inquiry: <?= htmlspecialchars($b['target_mineral']) ?> (Balochistan Minerals)">
                              </div>

                              <div class="mb-2">
                                <label class="form-label">Trade Offer Letter Body</label>
                                <textarea name="email_body" class="form-control form-control-sm font-monospace" rows="8">Dear <?= htmlspecialchars($b['contact_person'] ?: 'Procurement Director') ?>,

We at Balochistan Minerals (Pvt) Ltd understand that <?= htmlspecialchars($b['company_name']) ?> has recurring procurement requirements for <?= htmlspecialchars($b['target_mineral']) ?> (<?= htmlspecialchars($b['target_grade']) ?>).

As a direct extraction and export operator headquartered in Balochistan with logistical corridors through Karachi Port Trust (KPT) and Port Muhammad Bin Qasim, we are prepared to offer:

- Commodity: <?= htmlspecialchars($b['target_mineral']) ?>
- Specification: <?= htmlspecialchars($b['target_grade']) ?>
- Origin: Muslim Bagh / Khuzdar / Chagai, Balochistan
- Delivery Basis: FOB Karachi or CIF <?= htmlspecialchars($b['city'] ?: $b['country']) ?>
- Inspection: 100% Certified by SGS / Alfred H Knight / PCSIR at load port
- Payment Terms: Irrevocable Letter of Credit (L/C) at sight / TT

We would be pleased to forward our recent chemical assay certificate and arrange a sample shipment for laboratory verification.

Sincerely,
Commercial Export Desk
Balochistan Minerals (Pvt) Ltd
Email: sales@balochistanminerals.com
WhatsApp: +92 334 8888104
Web: https://balochistanminerals.com</textarea>
                              </div>
                            </div>

                            <div class="modal-footer d-flex justify-content-between py-2 px-3">
                              <span class="small text-muted font-monospace">Auto-logs into prospect timeline & advances stage</span>
                              <div class="d-flex gap-2">
                                <button type="button" class="btn-cancel-action" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn-save-action">
                                  <i class="bi bi-send-check me-1"></i> Send Official Pitch
                                </button>
                              </div>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                    <!-- Modal: Edit Prospect Dossier (Compact 2-Column) -->
                    <div class="modal fade" id="editModal<?= $b['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                          <form method="POST" action="prospects.php">
                            <input type="hidden" name="action" value="save_prospect">
                            <input type="hidden" name="prospect_id" value="<?= $b['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                            <div class="modal-header py-2.5 px-3">
                              <h6 class="modal-title fw-bold brand-font" style="color: var(--brand-navy);">
                                <i class="bi bi-pencil-square me-2 text-warning"></i>Edit Buyer: <?= htmlspecialchars($b['company_name']) ?>
                              </h6>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>

                            <div class="modal-body p-3.5">
                              <div class="row g-3">
                                <!-- Left Column -->
                                <div class="col-md-6 border-end pe-md-3">
                                  <div class="d-flex align-items-center text-uppercase fw-bold text-muted mb-2 pb-1 border-bottom" style="font-size: 0.70rem;">
                                    <i class="bi bi-building me-1.5 text-primary"></i> Company & Contact
                                  </div>
                                  <div class="mb-2">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" name="company_name" class="form-control form-control-sm" value="<?= htmlspecialchars($b['company_name']) ?>" required>
                                  </div>
                                  <div class="mb-2">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" name="contact_person" class="form-control form-control-sm" value="<?= htmlspecialchars($b['contact_person']) ?>">
                                  </div>
                                  <div class="row g-2 mb-2">
                                    <div class="col-6">
                                      <label class="form-label">Country</label>
                                      <input type="text" name="country" class="form-control form-control-sm" value="<?= htmlspecialchars($b['country']) ?>" required>
                                    </div>
                                    <div class="col-6">
                                      <label class="form-label">City / Port</label>
                                      <input type="text" name="city" class="form-control form-control-sm" value="<?= htmlspecialchars($b['city']) ?>">
                                    </div>
                                  </div>
                                  <div class="row g-2 mb-2">
                                    <div class="col-6">
                                      <label class="form-label">Email</label>
                                      <input type="email" name="email" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($b['email']) ?>">
                                    </div>
                                    <div class="col-6">
                                      <label class="form-label">WhatsApp</label>
                                      <input type="text" name="whatsapp" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($b['whatsapp']) ?>">
                                    </div>
                                  </div>
                                </div>

                                <!-- Right Column -->
                                <div class="col-md-6 ps-md-3">
                                  <div class="d-flex align-items-center text-uppercase fw-bold text-muted mb-2 pb-1 border-bottom" style="font-size: 0.70rem;">
                                    <i class="bi bi-gem me-1.5 text-success"></i> Commodity Requirements
                                  </div>
                                  <div class="row g-2 mb-2">
                                    <div class="col-6">
                                      <label class="form-label">Target Mineral</label>
                                      <input type="text" name="target_mineral" list="mineralList" class="form-control form-control-sm" value="<?= htmlspecialchars($b['target_mineral']) ?>" required>
                                    </div>
                                    <div class="col-6">
                                      <label class="form-label">Buyer Type</label>
                                      <input type="text" name="buyer_type" class="form-control form-control-sm" value="<?= htmlspecialchars($b['buyer_type']) ?>">
                                    </div>
                                  </div>
                                  <div class="row g-2 mb-2">
                                    <div class="col-6">
                                      <label class="form-label">Annual Demand</label>
                                      <input type="text" name="annual_demand" class="form-control form-control-sm" value="<?= htmlspecialchars($b['annual_demand']) ?>">
                                    </div>
                                    <div class="col-6">
                                      <label class="form-label">Target Grade / Spec</label>
                                      <input type="text" name="target_grade" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($b['target_grade']) ?>">
                                    </div>
                                  </div>
                                  <div class="mb-2">
                                    <label class="form-label">Internal Dossier Notes</label>
                                    <textarea name="notes" class="form-control form-control-sm font-monospace" rows="4"><?= htmlspecialchars($b['notes'] ?? '') ?></textarea>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <div class="modal-footer d-flex justify-content-between py-2 px-3">
                              <button type="button" class="btn btn-sm btn-outline-danger" onclick="if(confirm('Delete this buyer prospect?')) { document.getElementById('delBuyer<?= $b['id'] ?>').submit(); }">
                                <i class="bi bi-trash3 me-1"></i> Delete
                              </button>
                              <div class="d-flex gap-2">
                                <button type="button" class="btn-cancel-action" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn-save-action">Save Changes</button>
                              </div>
                            </div>
                          </form>

                          <form id="delBuyer<?= $b['id'] ?>" method="POST" action="prospects.php" style="display:none;">
                            <input type="hidden" name="action" value="delete_prospect">
                            <input type="hidden" name="prospect_id" value="<?= $b['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                          </form>
                        </div>
                      </div>
                    </div>

                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Modal: Add New Buyer Prospect (Compact 2-Column) -->
        <div class="modal fade" id="addProspectModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
              <form method="POST" action="prospects.php">
                <input type="hidden" name="action" value="save_prospect">
                <input type="hidden" name="prospect_id" value="0">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <div class="modal-header py-2.5 px-3">
                  <h6 class="modal-title fw-bold brand-font" style="color: var(--brand-navy);">
                    <i class="bi bi-plus-circle-fill text-warning me-2"></i>Add New International Buyer Prospect
                  </h6>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-3.5">
                  <div class="row g-3">
                    <div class="col-md-6 border-end pe-md-3">
                      <div class="d-flex align-items-center text-uppercase fw-bold text-muted mb-2 pb-1 border-bottom" style="font-size: 0.70rem;">
                        <i class="bi bi-building me-1.5 text-primary"></i> Company & Contact
                      </div>
                      <div class="mb-2">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control form-control-sm" placeholder="e.g. Hebei Steel & Ore Corp" required>
                      </div>
                      <div class="mb-2">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control form-control-sm" placeholder="e.g. Mr. Zhang Wei">
                      </div>
                      <div class="row g-2 mb-2">
                        <div class="col-6">
                          <label class="form-label">Country</label>
                          <input type="text" name="country" class="form-control form-control-sm" placeholder="e.g. China" required>
                        </div>
                        <div class="col-6">
                          <label class="form-label">City / Port</label>
                          <input type="text" name="city" class="form-control form-control-sm" placeholder="e.g. Tangshan / Tianjin">
                        </div>
                      </div>
                      <div class="row g-2 mb-2">
                        <div class="col-6">
                          <label class="form-label">Email</label>
                          <input type="email" name="email" class="form-control form-control-sm font-monospace" placeholder="buyer@company.com">
                        </div>
                        <div class="col-6">
                          <label class="form-label">WhatsApp</label>
                          <input type="text" name="whatsapp" class="form-control form-control-sm font-monospace" placeholder="+86 139...">
                        </div>
                      </div>
                    </div>

                    <div class="col-md-6 ps-md-3">
                      <div class="d-flex align-items-center text-uppercase fw-bold text-muted mb-2 pb-1 border-bottom" style="font-size: 0.70rem;">
                        <i class="bi bi-gem me-1.5 text-success"></i> Commodity Requirements
                      </div>
                      <div class="row g-2 mb-2">
                        <div class="col-6">
                          <label class="form-label">Target Mineral</label>
                          <input type="text" name="target_mineral" list="mineralList" class="form-control form-control-sm" placeholder="e.g. Chromite Ore" required>
                        </div>
                        <div class="col-6">
                          <label class="form-label">Buyer Type</label>
                          <select name="buyer_type" class="form-select form-select-sm">
                            <option value="Smelter / End User">Smelter / End User</option>
                            <option value="Stainless Steel Producer">Stainless Steel Producer</option>
                            <option value="Commodity Trading House">Commodity Trading House</option>
                            <option value="Chemical Refinery">Chemical Refinery</option>
                            <option value="Oilfield Services">Oilfield Services</option>
                          </select>
                        </div>
                      </div>
                      <div class="row g-2 mb-2">
                        <div class="col-6">
                          <label class="form-label">Annual Demand</label>
                          <input type="text" name="annual_demand" class="form-control form-control-sm" placeholder="e.g. 40,000 MT / yr">
                        </div>
                        <div class="col-6">
                          <label class="form-label">Target Grade / Spec</label>
                          <input type="text" name="target_grade" class="form-control form-control-sm font-monospace" placeholder="e.g. 46-48% Cr2O3">
                        </div>
                      </div>
                      <div class="mb-2">
                        <label class="form-label">Internal Dossier Notes</label>
                        <textarea name="notes" class="form-control form-control-sm font-monospace" rows="4" placeholder="Origin preferences, payment terms, trade fair meeting notes..."></textarea>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="modal-footer d-flex justify-content-end gap-2 py-2 px-3">
                  <button type="button" class="btn-cancel-action" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn-save-action" style="background: #E65C1A !important; border-color: #D45A15 !important;">
                    <i class="bi bi-plus-lg me-1"></i> Add to Directory
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
  </script>
  <?= renderCommodityDatalist('mineralList') ?>
  <script src="admin-layout.js"></script>
</body>
</html>
