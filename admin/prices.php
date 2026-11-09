<?php
/**
 * Balochistan Minerals - Indicative Mineral Pricing Manager
 * Executive Left Sidebar Theme with USD <-> PKR Live Currency Conversion & "1 Ton" Pricing
 */

require_once __DIR__ . '/auth.php';
requireAuth();

$user = getLoggedInUser();
$pdo  = getDB();

$flashMessage = '';
$flashType    = 'success';

// Default USD to PKR conversion rate (Interbank/Commercial baseline)
if (!isset($_SESSION['bm_usd_pkr_rate'])) {
    $_SESSION['bm_usd_pkr_rate'] = 278.50;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (verifyCsrfToken($csrf)) {
        if ($action === 'save_price') {
            $id          = (int)($_POST['price_id'] ?? 0);
            $mineral     = cleanInput($_POST['mineral_name'] ?? '');
            $grade       = cleanInput($_POST['grade_name'] ?? '');
            $purity      = cleanInput($_POST['purity_spec'] ?? '');
            $low         = (float)($_POST['price_low_usd'] ?? 0);
            $high        = (float)($_POST['price_high_usd'] ?? 0);
            $unit        = cleanInput($_POST['unit'] ?? '1 Ton');
            if (empty($unit)) $unit = '1 Ton';
            $terms       = cleanInput($_POST['delivery_terms'] ?? 'FOB Karachi');
            $notes       = cleanInput($_POST['notes'] ?? '');
            $isActive    = isset($_POST['is_active']) ? 1 : 0;

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE mineral_prices SET mineral_name = ?, grade_name = ?, purity_spec = ?, price_low_usd = ?, price_high_usd = ?, unit = ?, delivery_terms = ?, notes = ?, is_active = ? WHERE id = ?');
                $stmt->execute([$mineral, $grade, $purity, $low, $high, $unit, $terms, $notes, $isActive, $id]);
                $flashMessage = 'Price for ' . $mineral . ' updated successfully (' . $unit . ').';
            } else {
                $stmt = $pdo->prepare('INSERT INTO mineral_prices (mineral_name, grade_name, purity_spec, price_low_usd, price_high_usd, unit, delivery_terms, notes, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$mineral, $grade, $purity, $low, $high, $unit, $terms, $notes, $isActive]);
                $flashMessage = 'New mineral price entry created for ' . $mineral . ' (' . $unit . ').';
            }
        } elseif ($action === 'update_rate') {
            $newRate = (float)($_POST['usd_pkr_rate'] ?? 278.50);
            if ($newRate > 0) {
                $_SESSION['bm_usd_pkr_rate'] = $newRate;
                $flashMessage = 'USD to PKR conversion rate updated to ₨ ' . number_format($newRate, 2);
            }
        } elseif ($action === 'delete_price') {
            $id = (int)($_POST['price_id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM mineral_prices WHERE id = ?');
            $stmt->execute([$id]);
            $flashMessage = 'Price entry deleted.';
            $flashType = 'warning';
        }
    } else {
        $flashMessage = 'Session expired. Please try again.';
        $flashType = 'danger';
    }
}

$usdPkrRate = (float)($_SESSION['bm_usd_pkr_rate'] ?? 278.50);

$prices = [];
$newLeads = 0;
$pendingReviews = 0;

if ($pdo !== null) {
    $newLeads = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'New'")->fetchColumn();
    $pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'Pending'")->fetchColumn();
    $totalProspects = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects")->fetchColumn();
    $prices = $pdo->query('SELECT * FROM mineral_prices ORDER BY sort_order ASC, id ASC')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mineral Spot Prices | Balochistan Minerals</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="admin-layout.css">
  <style>
    :root {
      --navy-900: #0F172A;
      --brand-navy: #0F172A;
      --brand-slate: #1E293B;
      --brand-gold: #B48C36;
      --brand-gold-subtle: #F8F5EE;
      --brand-border: #E2E8F0;
      --brand-canvas: #F8FAFC;
      --brand-surface: #FFFFFF;
      --brand-orange: #E65C1A;
      --text-dark: #0F172A;
      --text-muted: #64748B;
      --sidebar-width: 255px;
    }
    body { font-family: 'Poppins', sans-serif; font-size: 0.88rem; color: var(--text-dark); background-color: var(--brand-canvas); margin: 0; }
    h1, h2, h3, h4, h5, h6, .brand-font { font-family: 'Montserrat', sans-serif; letter-spacing: -0.015em; }

    /* Crisp Luxury Modal & Button Styles */
    .modal-dialog.modal-lg {
      max-width: 820px;
    }
    .modal-content {
      border: 1px solid rgba(15, 23, 42, 0.12) !important;
      border-radius: 14px !important;
      box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25) !important;
      overflow: hidden;
    }
    .modal-header {
      background: #F8FAFC;
      border-bottom: 1px solid #E2E8F0;
      padding: 16px 22px;
    }
    .modal-body {
      padding: 22px;
      background: #FFFFFF;
    }
    .modal-footer {
      background: #F8FAFC;
      border-top: 1px solid #E2E8F0;
      padding: 14px 22px;
    }
    .form-control, .form-select {
      border: 1.5px solid #CBD5E1;
      border-radius: 8px;
      padding: 8px 12px;
      font-size: 0.88rem;
      color: #0F172A;
      font-weight: 500;
    }
    .form-control:focus, .form-select:focus {
      border-color: #E65C1A;
      box-shadow: 0 0 0 3px rgba(230, 92, 26, 0.15);
      outline: none;
    }
    .form-label {
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #475569;
      margin-bottom: 5px;
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
      box-shadow: 0 2px 6px rgba(15,23,42,0.2) !important;
      cursor: pointer !important;
      text-decoration: none !important;
    }
    .btn-save-action:hover {
      background: #1E293B !important;
      border-color: #1E293B !important;
    }
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
    .btn-cancel-action:hover {
      background: #F1F5F9 !important;
      color: #0F172A !important;
      border-color: #94A3B8 !important;
    }
    .btn-delete-action {
      background: #FEF2F2 !important;
      color: #DC2626 !important;
      border: 1.5px solid #FECACA !important;
      padding: 8px 16px !important;
      font-weight: 600 !important;
      font-size: 0.84rem !important;
      border-radius: 6px !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 6px !important;
      cursor: pointer !important;
    }
    .btn-delete-action:hover {
      background: #FEE2E2 !important;
      color: #991B1B !important;
      border-color: #F87171 !important;
    }
    .btn-table-edit {
      background: #F1F5F9;
      color: #0F172A;
      border: 1px solid #CBD5E1;
      padding: 5px 14px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.78rem;
      transition: all 0.15s;
    }
    .btn-table-edit:hover {
      background: #0F172A;
      color: #FFFFFF;
      border-color: #0F172A;
    }
    .status-badge-active {
      background: #ECFDF5;
      color: #047857;
      border: 1px solid #A7F3D0;
      font-weight: 700;
      font-size: 0.72rem;
      padding: 3px 8px;
      border-radius: 4px;
      display: inline-block;
    }
    .status-badge-hidden {
      background: #F1F5F9;
      color: #64748B;
      border: 1px solid #E2E8F0;
      font-weight: 600;
      font-size: 0.72rem;
      padding: 3px 8px;
      border-radius: 4px;
      display: inline-block;
    }

    /* Currency Conversion Elements */
    .rate-badge {
      background: #FFFFFF;
      border: 1px solid #CBD5E1;
      border-radius: 6px;
      padding: 6px 12px;
      font-size: 0.8rem;
    }
    .btn-view-toggle.active {
      background: #0F172A !important;
      color: #FFFFFF !important;
      border-color: #0F172A !important;
    }

    .table-scroll-container {
      max-height: 560px;
      overflow-y: auto;
      overflow-x: auto;
      position: relative;
    }
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
    .table thead th {
      position: sticky;
      top: 0;
      z-index: 10;
      background: #F8FAFC !important;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    }

    .app-wrapper { display: flex; min-height: 100vh; }
    .app-sidebar {
      width: var(--sidebar-width); background: var(--brand-surface); border-right: 1px solid var(--brand-border);
      display: flex; flex-direction: column; position: fixed; top: 0; bottom: 0; left: 0; z-index: 1040;
    }

    .sidebar-nav { padding: 16px 12px; flex: 1; }
    .sidebar-section-title { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94A3B8; padding: 12px 10px 6px; }
    .sidebar-link {
      display: flex; align-items: center; justify-content: space-between;
      padding: 9px 12px; border-radius: 6px; color: #475569; font-family: 'Montserrat', sans-serif; font-size: 0.82rem; font-weight: 600; text-decoration: none; margin-bottom: 2px; transition: all 0.15s ease;
    }
    .sidebar-link i { font-size: 1.05rem; margin-right: 10px; color: #64748B; }
    .sidebar-link:hover { background: #F1F5F9; color: var(--brand-navy); }
    .sidebar-link.active { background: #F8FAFC; color: var(--brand-navy); border: 1px solid var(--brand-border); position: relative; }
    .sidebar-link.active::before { content: ''; position: absolute; left: -12px; top: 6px; bottom: 6px; width: 3.5px; background: var(--brand-gold); border-radius: 0 3px 3px 0; }
    .sidebar-link.active i { color: var(--brand-gold); }
    .sidebar-footer { padding: 14px 18px; border-top: 1px solid var(--brand-border); }
    .app-main { margin-left: var(--sidebar-width); flex: 1; min-width: 0; }
    .top-bar { background: var(--brand-surface); border-bottom: 1px solid var(--brand-border); padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 1030; }
    .content-canvas { padding: 24px 28px; }
  </style>
</head>
<body>

  <div class="app-wrapper">
    <!-- LEFT SIDEBAR (Slideable & Collapsible) -->
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
          <div><i class="bi bi-envelope-paper-fill"></i> Email Reply Desk</div>
          <span class="badge bg-primary font-monospace" style="font-size: 0.65rem;">HOSTINGER</span>
        </a>
        <a href="prospects.php" class="sidebar-link">
          <div><i class="bi bi-bullseye"></i> Buyer Directory</div>
          <span class="badge font-monospace" style="background: rgba(230,92,26,0.15); color: #E65C1A; font-size: 0.7rem;"><?= $totalProspects ?></span>
        </a>
        <a href="radar.php" class="sidebar-link">
          <div><i class="bi bi-broadcast-pin"></i> Live Visitor Radar</div>
          <span class="badge bg-success font-monospace" style="font-size: 0.65rem;">LIVE</span>
        </a>

        <div class="sidebar-section-title">Logistics & Trade Operations</div>
        <a href="stockpiles.php" class="sidebar-link">
          <div><i class="bi bi-boxes"></i> Stockpiles & Shipments</div>
        </a>
        <a href="prices.php" class="sidebar-link active">
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
          <div class="fw-semibold small" style="color: var(--navy-900);"><?= htmlspecialchars($user['full_name']) ?></div>
          <a href="logout.php" class="btn btn-sm btn-outline-danger p-1 px-2" title="Sign Out"><i class="bi bi-power"></i></a>
        </div>
      </div>
    </aside>

    <!-- Mobile / Tablet Backdrop Overlay -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- MAIN AREA -->
    <div class="app-main">
      <header class="top-bar">
        <div class="topbar-left">
          <button type="button" id="sidebarToggle" class="btn-sidebar-toggle" title="Toggle Sidebar (Ctrl + B)">
            <i class="bi bi-layout-sidebar-inset"></i>
          </button>
          <div class="topbar-title-wrap">
            <h1 class="topbar-title">Indicative Mineral Spot Pricing</h1>
            <div class="topbar-subtitle">FOB Karachi Port Export Benchmarks & FX Conversion</div>
          </div>
        </div>

        <div class="topbar-right">
          <!-- USD to PKR Rate Pill -->
          <div class="pill-status d-flex align-items-center gap-2 shadow-sm" style="background: #FFFFFF;">
            <span class="text-muted fw-semibold" style="font-size: 0.74rem;"><i class="bi bi-currency-exchange text-secondary me-1"></i>USD / PKR:</span>
            <span class="fw-bold font-monospace text-dark" style="font-size: 0.8rem;">₨ <?= number_format($usdPkrRate, 2) ?></span>
            <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none text-primary fw-semibold" style="font-size: 0.72rem;" data-bs-toggle="modal" data-bs-target="#rateModal" title="Edit Exchange Rate">
              <i class="bi bi-pencil-square"></i> Change
            </button>
          </div>

          <!-- Currency View Toggle -->
          <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-secondary btn-view-toggle active" id="btnViewBoth" onclick="setCurrencyView('both')" style="font-size: 0.76rem;">USD + PKR</button>
            <button type="button" class="btn btn-outline-secondary btn-view-toggle" id="btnViewUsd" onclick="setCurrencyView('usd')" style="font-size: 0.76rem;">USD</button>
            <button type="button" class="btn btn-outline-secondary btn-view-toggle" id="btnViewPkr" onclick="setCurrencyView('pkr')" style="font-size: 0.76rem;">PKR</button>
          </div>

          <!-- Add Mineral Button -->
          <button type="button" class="btn-topbar-primary" data-bs-toggle="modal" data-bs-target="#priceModal0">
            <i class="bi bi-plus-lg"></i> <span>Add Mineral Grade</span>
          </button>
        </div>
      </header>

      <main class="content-canvas">
        <?php if (!empty($flashMessage)): ?>
          <div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-3">
          <div class="table-responsive table-scroll-container">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Mineral</th>
                  <th>Grade Name</th>
                  <th>Purity Spec</th>
                  <th>Indicative Price Range (USD / PKR)</th>
                  <th>Delivery Terms</th>
                  <th>Status</th>
                  <th>Updated</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($prices)): ?>
                  <tr><td colspan="8" class="text-center py-5 text-muted">No mineral pricing defined.</td></tr>
                <?php else: ?>
                  <?php foreach ($prices as $p): ?>
                    <?php 
                      $pUnit = !empty($p['unit']) ? $p['unit'] : '1 Ton';
                      $lowPkr = round($p['price_low_usd'] * $usdPkrRate);
                      $highPkr = round($p['price_high_usd'] * $usdPkrRate);
                    ?>
                    <tr>
                      <td><strong style="color: var(--navy-900);"><?= htmlspecialchars($p['mineral_name']) ?></strong></td>
                      <td><?= htmlspecialchars($p['grade_name']) ?></td>
                      <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['purity_spec']) ?></span></td>
                      <td>
                        <!-- USD Line -->
                        <div class="price-usd-line">
                          <span class="fw-bold font-monospace" style="color: #047857; font-size: 0.95rem;">
                            $<?= number_format($p['price_low_usd'], 2) ?> - $<?= number_format($p['price_high_usd'], 2) ?>
                          </span>
                          <span class="badge bg-light text-dark border ms-1 fw-bold" style="font-size: 0.72rem;">/ <?= htmlspecialchars($pUnit) ?></span>
                        </div>
                        <!-- PKR Line -->
                        <div class="price-pkr-line mt-1">
                          <span class="font-monospace fw-semibold" style="color: #0F172A; font-size: 0.84rem;">
                            ₨ <?= number_format($lowPkr) ?> - ₨ <?= number_format($highPkr) ?>
                          </span>
                          <span class="small text-muted font-monospace">PKR / <?= htmlspecialchars($pUnit) ?></span>
                        </div>
                      </td>
                      <td><span class="small text-muted"><?= htmlspecialchars($p['delivery_terms']) ?></span></td>
                      <td>
                        <?php if ($p['is_active']): ?>
                          <span class="status-badge-active"><i class="bi bi-dot"></i> Active (Live)</span>
                        <?php else: ?>
                          <span class="status-badge-hidden">Hidden</span>
                        <?php endif; ?>
                      </td>
                      <td class="small text-muted"><?= date('M d, Y', strtotime($p['updated_at'])) ?></td>
                      <td class="text-end">
                        <button type="button" class="btn-table-edit" data-bs-toggle="modal" data-bs-target="#priceModal<?= $p['id'] ?>">
                          <i class="bi bi-pencil-square me-1"></i> Edit
                        </button>
                      </td>
                    </tr>

                    <!-- Edit Modal (Compact 2-Column Architecture) -->
                    <div class="modal fade" id="priceModal<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                          <form method="POST" action="prices.php">
                            <input type="hidden" name="action" value="save_price">
                            <input type="hidden" name="price_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                            <div class="modal-header d-flex justify-content-between align-items-center py-2.5 px-3">
                              <div class="d-flex align-items-center gap-2">
                                <span class="badge" style="background: rgba(230,92,26,0.12); color: #E65C1A; font-weight: 700; font-size: 0.72rem; padding: 4px 8px;">
                                  <i class="bi bi-tag-fill me-1"></i> ID #<?= $p['id'] ?>
                                </span>
                                <h6 class="modal-title fw-bold brand-font mb-0" style="color: var(--brand-navy); font-size: 0.95rem;">
                                  Edit Price: <?= htmlspecialchars($p['mineral_name']) ?> <span class="text-muted fw-normal fs-6">(<?= htmlspecialchars($p['grade_name']) ?>)</span>
                                </h6>
                              </div>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>

                            <div class="modal-body p-3 p-md-3.5">
                              <div class="row g-3">
                                <!-- Left Column: Mineral Specifications & Logistics -->
                                <div class="col-md-6 border-end pe-md-3">
                                  <div class="d-flex align-items-center text-uppercase fw-bold text-muted mb-2 pb-1 border-bottom" style="font-size: 0.70rem; letter-spacing: 0.05em;">
                                    <i class="bi bi-layers-fill text-warning me-1.5"></i> Specifications & Logistics
                                  </div>

                                  <div class="mb-2">
                                    <label class="form-label mb-1">Mineral Name</label>
                                    <input type="text" name="mineral_name" class="form-control form-control-sm" value="<?= htmlspecialchars($p['mineral_name']) ?>" required>
                                  </div>
                                  <div class="mb-2">
                                    <label class="form-label mb-1">Grade Description</label>
                                    <input type="text" name="grade_name" class="form-control form-control-sm" value="<?= htmlspecialchars($p['grade_name']) ?>" required>
                                  </div>
                                  <div class="mb-2">
                                    <label class="form-label mb-1">Purity Spec</label>
                                    <input type="text" name="purity_spec" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($p['purity_spec']) ?>" required>
                                  </div>

                                  <div class="row g-2 mb-2">
                                    <div class="col-6">
                                      <label class="form-label mb-1">Price Unit</label>
                                      <select name="unit" class="form-select form-select-sm fw-semibold unit-select" onchange="syncUnitPreviews(this)">
                                        <option value="1 Ton" <?= $pUnit === '1 Ton' ? 'selected' : '' ?>>1 Ton (Default)</option>
                                        <option value="Ton" <?= $pUnit === 'Ton' ? 'selected' : '' ?>>Ton</option>
                                        <option value="MT" <?= $pUnit === 'MT' ? 'selected' : '' ?>>Metric Ton (MT)</option>
                                        <option value="Kg" <?= $pUnit === 'Kg' ? 'selected' : '' ?>>Kilogram (Kg)</option>
                                        <option value="Bag (50kg)" <?= $pUnit === 'Bag (50kg)' ? 'selected' : '' ?>>Bag (50kg)</option>
                                        <option value="Truck Load (~30T)" <?= $pUnit === 'Truck Load (~30T)' ? 'selected' : '' ?>>Truck Load (~30T)</option>
                                      </select>
                                    </div>
                                    <div class="col-6">
                                      <label class="form-label mb-1">Delivery Terms</label>
                                      <input type="text" name="delivery_terms" class="form-control form-control-sm" value="<?= htmlspecialchars($p['delivery_terms']) ?>" required>
                                    </div>
                                  </div>

                                  <div class="mb-0">
                                    <label class="form-label mb-1">Origin / Notes</label>
                                    <input type="text" name="notes" class="form-control form-control-sm" value="<?= htmlspecialchars($p['notes'] ?? '') ?>" placeholder="e.g. Muslim Bagh origin">
                                  </div>
                                </div>

                                <!-- Right Column: Commercial Pricing & Live PKR Engine -->
                                <div class="col-md-6 ps-md-3 d-flex flex-column justify-content-between">
                                  <div>
                                    <div class="d-flex align-items-center justify-content-between text-uppercase fw-bold text-muted mb-2 pb-1 border-bottom" style="font-size: 0.70rem; letter-spacing: 0.05em;">
                                      <span><i class="bi bi-cash-stack text-success me-1.5"></i> Spot Pricing</span>
                                      <span class="badge bg-light text-dark border font-monospace py-1" style="font-size: 0.67rem;">1 USD = ₨ <?= number_format($usdPkrRate, 2) ?></span>
                                    </div>

                                    <!-- Dual Currency Price Box -->
                                    <div class="card p-2.5 mb-2 bg-light border" style="border-radius: 8px;">
                                      <div class="row g-2 mb-2">
                                        <div class="col-6">
                                          <label class="form-label small text-muted mb-1" style="font-size: 0.68rem;">Min Price ($ USD)</label>
                                          <div class="input-group input-group-sm">
                                            <span class="input-group-text font-monospace fw-bold bg-white">$</span>
                                            <input type="number" step="0.01" name="price_low_usd" class="form-control form-control-sm font-monospace fw-bold usd-min-input" value="<?= $p['price_low_usd'] ?>" required oninput="calcPkrFromUsd(this)">
                                          </div>
                                        </div>
                                        <div class="col-6">
                                          <label class="form-label small text-muted mb-1" style="font-size: 0.68rem;">Max Price ($ USD)</label>
                                          <div class="input-group input-group-sm">
                                            <span class="input-group-text font-monospace fw-bold bg-white">$</span>
                                            <input type="number" step="0.01" name="price_high_usd" class="form-control form-control-sm font-monospace fw-bold usd-max-input" value="<?= $p['price_high_usd'] ?>" required oninput="calcPkrFromUsd(this)">
                                          </div>
                                        </div>
                                      </div>

                                      <!-- Real-time PKR Live Preview -->
                                      <div class="p-2 rounded bg-white border">
                                        <div class="d-flex justify-content-between align-items-center">
                                          <span class="fw-bold" style="color: #1E293B; font-size: 0.67rem; letter-spacing: 0.03em;">
                                            <i class="bi bi-arrow-left-right me-1 text-primary"></i> PAK RUPEES (PKR):
                                          </span>
                                          <button type="button" class="btn btn-xs btn-outline-dark py-0 px-1.5" style="font-size: 0.66rem; border-radius: 4px;" onclick="togglePkrHelper(this)">
                                            <i class="bi bi-calculator me-1"></i> Enter in PKR
                                          </button>
                                        </div>
                                        <div class="d-flex justify-content-between font-monospace fw-bold mt-1" style="font-size: 0.82rem; color: #1E293B;">
                                          <div>Min: <span class="pkr-min-preview" style="color: #047857;">₨ <?= number_format($lowPkr) ?></span> / <span class="unit-preview-text"><?= htmlspecialchars($pUnit) ?></span></div>
                                          <div>Max: <span class="pkr-max-preview" style="color: #047857;">₨ <?= number_format($highPkr) ?></span> / <span class="unit-preview-text"><?= htmlspecialchars($pUnit) ?></span></div>
                                        </div>

                                        <!-- PKR Input Drawer (Admin convenience for direct PKR entry) -->
                                        <div class="pkr-input-drawer mt-2 pt-2 border-top" style="display: none;">
                                          <div class="row g-2">
                                            <div class="col-6">
                                              <label class="form-label text-muted mb-1" style="font-size: 0.65rem;">Min PKR (₨)</label>
                                              <input type="number" step="1" class="form-control form-control-sm font-monospace pkr-min-direct" value="<?= $lowPkr ?>" placeholder="58000" oninput="calcUsdFromPkr(this)">
                                            </div>
                                            <div class="col-6">
                                              <label class="form-label text-muted mb-1" style="font-size: 0.65rem;">Max PKR (₨)</label>
                                              <input type="number" step="1" class="form-control form-control-sm font-monospace pkr-max-direct" value="<?= $highPkr ?>" placeholder="65000" oninput="calcUsdFromPkr(this)">
                                            </div>
                                          </div>
                                          <div class="text-muted small mt-1" style="font-size: 0.64rem;">Auto-calculates USD using ₨ <?= number_format($usdPkrRate, 2) ?>/USD</div>
                                        </div>
                                      </div>
                                    </div>
                                  </div>

                                  <!-- Visibility Checkbox -->
                                  <div class="form-check p-2 bg-light rounded border d-flex align-items-center mb-0 mt-2">
                                    <input type="checkbox" name="is_active" class="form-check-input ms-0 me-2 my-0" id="act<?= $p['id'] ?>" <?= $p['is_active'] ? 'checked' : '' ?> style="cursor: pointer; width: 1.15rem; height: 1.15rem;">
                                    <label class="form-check-label small fw-semibold text-dark cursor-pointer mb-0" for="act<?= $p['id'] ?>" style="cursor: pointer;">
                                      Show actively on website & price desk
                                    </label>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <div class="modal-footer d-flex justify-content-between py-2 px-3">
                              <button type="button" class="btn-delete-action" onclick="if(confirm('Delete this price entry?')) { document.getElementById('delPrice<?= $p['id'] ?>').submit(); }">
                                <i class="bi bi-trash3 me-1"></i> Delete
                              </button>
                              <div class="d-flex gap-2">
                                <button type="button" class="btn-cancel-action" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn-save-action">
                                  <i class="bi bi-check2-circle me-1"></i> Save Changes
                                </button>
                              </div>
                            </div>
                          </form>

                          <form id="delPrice<?= $p['id'] ?>" method="POST" action="prices.php" style="display:none;">
                            <input type="hidden" name="action" value="delete_price">
                            <input type="hidden" name="price_id" value="<?= $p['id'] ?>">
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

        <!-- Add Modal (Compact 2-Column Architecture) -->
        <div class="modal fade" id="priceModal0" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
              <form method="POST" action="prices.php">
                <input type="hidden" name="action" value="save_price">
                <input type="hidden" name="price_id" value="0">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <div class="modal-header d-flex justify-content-between align-items-center py-2.5 px-3">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge" style="background: rgba(230,92,26,0.12); color: #E65C1A; font-weight: 700; font-size: 0.72rem; padding: 4px 8px;">
                      <i class="bi bi-plus-circle me-1"></i> NEW ENTRY
                    </span>
                    <h6 class="modal-title fw-bold brand-font mb-0" style="color: var(--brand-navy); font-size: 0.95rem;">
                      Add New Mineral Grade
                    </h6>
                  </div>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-3 p-md-3.5">
                  <div class="row g-3">
                    <!-- Left Column: Mineral Specifications & Logistics -->
                    <div class="col-md-6 border-end pe-md-3">
                      <div class="d-flex align-items-center text-uppercase fw-bold text-muted mb-2 pb-1 border-bottom" style="font-size: 0.70rem; letter-spacing: 0.05em;">
                        <i class="bi bi-layers-fill text-warning me-1.5"></i> Specifications & Logistics
                      </div>

                      <div class="mb-2">
                        <label class="form-label mb-1">Mineral Name</label>
                        <input type="text" name="mineral_name" class="form-control form-control-sm" placeholder="e.g. Chromite, Iron Ore, Copper" required>
                      </div>
                      <div class="mb-2">
                        <label class="form-label mb-1">Grade Description</label>
                        <input type="text" name="grade_name" class="form-control form-control-sm" placeholder="e.g. Metallurgical Lumps" required>
                      </div>
                      <div class="mb-2">
                        <label class="form-label mb-1">Purity Spec</label>
                        <input type="text" name="purity_spec" class="form-control form-control-sm font-monospace" placeholder="e.g. 42% - 44% Cr2O3" required>
                      </div>

                      <div class="row g-2 mb-2">
                        <div class="col-6">
                          <label class="form-label mb-1">Price Unit</label>
                          <select name="unit" class="form-select form-select-sm fw-semibold unit-select" onchange="syncUnitPreviews(this)">
                            <option value="1 Ton" selected>1 Ton (Default / Standard)</option>
                            <option value="Ton">Ton</option>
                            <option value="MT">Metric Ton (MT)</option>
                            <option value="Kg">Kilogram (Kg)</option>
                            <option value="Bag (50kg)">Bag (50kg)</option>
                            <option value="Truck Load (~30T)">Truck Load (~30T)</option>
                          </select>
                        </div>
                        <div class="col-6">
                          <label class="form-label mb-1">Delivery Terms</label>
                          <input type="text" name="delivery_terms" class="form-control form-control-sm" value="FOB Karachi" required>
                        </div>
                      </div>

                      <div class="mb-0">
                        <label class="form-label mb-1">Origin / Notes</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Muslim Bagh site">
                      </div>
                    </div>

                    <!-- Right Column: Commercial Pricing & Live PKR Engine -->
                    <div class="col-md-6 ps-md-3 d-flex flex-column justify-content-between">
                      <div>
                        <div class="d-flex align-items-center justify-content-between text-uppercase fw-bold text-muted mb-2 pb-1 border-bottom" style="font-size: 0.70rem; letter-spacing: 0.05em;">
                          <span><i class="bi bi-cash-stack text-success me-1.5"></i> Spot Pricing</span>
                          <span class="badge bg-light text-dark border font-monospace py-1" style="font-size: 0.67rem;">1 USD = ₨ <?= number_format($usdPkrRate, 2) ?></span>
                        </div>

                        <!-- Dual Currency Price Box -->
                        <div class="card p-2.5 mb-2 bg-light border" style="border-radius: 8px;">
                          <div class="row g-2 mb-2">
                            <div class="col-6">
                              <label class="form-label small text-muted mb-1" style="font-size: 0.68rem;">Min Price ($ USD)</label>
                              <div class="input-group input-group-sm">
                                <span class="input-group-text font-monospace fw-bold bg-white">$</span>
                                <input type="number" step="0.01" name="price_low_usd" class="form-control form-control-sm font-monospace fw-bold usd-min-input" placeholder="180.00" required oninput="calcPkrFromUsd(this)">
                              </div>
                            </div>
                            <div class="col-6">
                              <label class="form-label small text-muted mb-1" style="font-size: 0.68rem;">Max Price ($ USD)</label>
                              <div class="input-group input-group-sm">
                                <span class="input-group-text font-monospace fw-bold bg-white">$</span>
                                <input type="number" step="0.01" name="price_high_usd" class="form-control form-control-sm font-monospace fw-bold usd-max-input" placeholder="200.00" required oninput="calcPkrFromUsd(this)">
                              </div>
                            </div>
                          </div>

                          <!-- Real-time PKR Live Preview -->
                          <div class="p-2 rounded bg-white border">
                            <div class="d-flex justify-content-between align-items-center">
                              <span class="fw-bold" style="color: #1E293B; font-size: 0.67rem; letter-spacing: 0.03em;">
                                <i class="bi bi-arrow-left-right me-1 text-primary"></i> PAK RUPEES (PKR):
                              </span>
                              <button type="button" class="btn btn-xs btn-outline-dark py-0 px-1.5" style="font-size: 0.66rem; border-radius: 4px;" onclick="togglePkrHelper(this)">
                                <i class="bi bi-calculator me-1"></i> Enter in PKR
                              </button>
                            </div>
                            <div class="d-flex justify-content-between font-monospace fw-bold mt-1" style="font-size: 0.82rem; color: #1E293B;">
                              <div>Min: <span class="pkr-min-preview" style="color: #047857;">₨ 0</span> / <span class="unit-preview-text">1 Ton</span></div>
                              <div>Max: <span class="pkr-max-preview" style="color: #047857;">₨ 0</span> / <span class="unit-preview-text">1 Ton</span></div>
                            </div>

                            <!-- PKR Input Drawer (Admin convenience for direct PKR entry) -->
                            <div class="pkr-input-drawer mt-2 pt-2 border-top" style="display: none;">
                              <div class="row g-2">
                                <div class="col-6">
                                  <label class="form-label text-muted mb-1" style="font-size: 0.65rem;">Min PKR (₨)</label>
                                  <input type="number" step="1" class="form-control form-control-sm font-monospace pkr-min-direct" placeholder="e.g. 50000" oninput="calcUsdFromPkr(this)">
                                </div>
                                <div class="col-6">
                                  <label class="form-label text-muted mb-1" style="font-size: 0.65rem;">Max PKR (₨)</label>
                                  <input type="number" step="1" class="form-control form-control-sm font-monospace pkr-max-direct" placeholder="e.g. 55000" oninput="calcUsdFromPkr(this)">
                                </div>
                              </div>
                              <div class="text-muted small mt-1" style="font-size: 0.64rem;">Typing here auto-updates USD export values using ₨ <?= number_format($usdPkrRate, 2) ?>/USD.</div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Visibility Checkbox -->
                      <div class="form-check p-2 bg-light rounded border d-flex align-items-center mb-0 mt-2">
                        <input type="checkbox" name="is_active" class="form-check-input ms-0 me-2 my-0" id="actNew" checked style="cursor: pointer; width: 1.15rem; height: 1.15rem;">
                        <label class="form-check-label small fw-semibold text-dark cursor-pointer mb-0" for="actNew" style="cursor: pointer;">
                          Show actively in Price Desk
                        </label>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="modal-footer d-flex justify-content-end gap-2 py-2 px-3">
                  <button type="button" class="btn-cancel-action" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn-save-action" style="background: #E65C1A !important; border-color: #D45A15 !important;">
                    <i class="bi bi-plus-lg me-1"></i> Create Price Entry
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Exchange Rate Settings Modal -->
        <div class="modal fade" id="rateModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
              <form method="POST" action="prices.php">
                <input type="hidden" name="action" value="update_rate">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <div class="modal-header py-3">
                  <h6 class="modal-title fw-bold brand-font" style="color: var(--brand-navy);">
                    <i class="bi bi-currency-exchange me-1 text-warning"></i> Set USD / PKR Rate
                  </h6>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body py-3">
                  <label class="form-label">1 USD in Pakistani Rupees (PKR)</label>
                  <div class="input-group">
                    <span class="input-group-text fw-bold">₨</span>
                    <input type="number" step="0.01" name="usd_pkr_rate" class="form-control font-monospace fw-bold fs-5" value="<?= $usdPkrRate ?>" required>
                  </div>
                  <div class="form-text small mt-2">
                    Used to automatically calculate and display Pakistani Rupee equivalents across all mineral grades.
                  </div>
                </div>

                <div class="modal-footer py-2 d-flex justify-content-end gap-2">
                  <button type="button" class="btn-cancel-action" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn-save-action">Update Rate</button>
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
    const USD_PKR_RATE = <?= json_encode($usdPkrRate) ?>;

    function calcPkrFromUsd(input) {
      const modal = input.closest('.modal-content');
      const minUsd = parseFloat(modal.querySelector('.usd-min-input').value) || 0;
      const maxUsd = parseFloat(modal.querySelector('.usd-max-input').value) || 0;

      const minPkr = Math.round(minUsd * USD_PKR_RATE);
      const maxPkr = Math.round(maxUsd * USD_PKR_RATE);

      const previewMin = modal.querySelector('.pkr-min-preview');
      const previewMax = modal.querySelector('.pkr-max-preview');
      if (previewMin) previewMin.textContent = '₨ ' + minPkr.toLocaleString();
      if (previewMax) previewMax.textContent = '₨ ' + maxPkr.toLocaleString();

      const pkrMinDirect = modal.querySelector('.pkr-min-direct');
      const pkrMaxDirect = modal.querySelector('.pkr-max-direct');
      if (pkrMinDirect && document.activeElement !== pkrMinDirect) {
        pkrMinDirect.value = minPkr > 0 ? minPkr : '';
      }
      if (pkrMaxDirect && document.activeElement !== pkrMaxDirect) {
        pkrMaxDirect.value = maxPkr > 0 ? maxPkr : '';
      }
    }

    function calcUsdFromPkr(input) {
      const modal = input.closest('.modal-content');
      const pkrVal = parseFloat(input.value) || 0;
      const usdVal = pkrVal > 0 ? (pkrVal / USD_PKR_RATE).toFixed(2) : '';

      if (input.classList.contains('pkr-min-direct')) {
        const usdMin = modal.querySelector('.usd-min-input');
        if (usdMin) {
          usdMin.value = usdVal;
          const previewMin = modal.querySelector('.pkr-min-preview');
          if (previewMin) previewMin.textContent = '₨ ' + Math.round(pkrVal).toLocaleString();
        }
      } else if (input.classList.contains('pkr-max-direct')) {
        const usdMax = modal.querySelector('.usd-max-input');
        if (usdMax) {
          usdMax.value = usdVal;
          const previewMax = modal.querySelector('.pkr-max-preview');
          if (previewMax) previewMax.textContent = '₨ ' + Math.round(pkrVal).toLocaleString();
        }
      }
    }

    function syncUnitPreviews(select) {
      const modal = select.closest('.modal-content');
      const val = select.value || '1 Ton';
      modal.querySelectorAll('.unit-preview-text').forEach(el => {
        el.textContent = val;
      });
    }

    function togglePkrHelper(btn) {
      const modal = btn.closest('.modal-content');
      const drawer = modal.querySelector('.pkr-input-drawer');
      if (drawer) {
        if (drawer.style.display === 'none' || !drawer.style.display) {
          drawer.style.display = 'block';
          btn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Close PKR Calculator';
        } else {
          drawer.style.display = 'none';
          btn.innerHTML = '<i class="bi bi-calculator me-1"></i> Enter in PKR instead';
        }
      }
    }

    function setCurrencyView(mode) {
      try {
        localStorage.setItem('bm_currency_view', mode);
      } catch (e) {}

      document.querySelectorAll('.btn-view-toggle').forEach(b => b.classList.remove('active'));
      const btn = document.getElementById('btnView' + mode.charAt(0).toUpperCase() + mode.slice(1));
      if (btn) btn.classList.add('active');

      const usdLines = document.querySelectorAll('.price-usd-line');
      const pkrLines = document.querySelectorAll('.price-pkr-line');

      if (mode === 'usd') {
        usdLines.forEach(el => el.style.display = 'block');
        pkrLines.forEach(el => el.style.display = 'none');
      } else if (mode === 'pkr') {
        usdLines.forEach(el => el.style.display = 'none');
        pkrLines.forEach(el => el.style.display = 'block');
      } else {
        usdLines.forEach(el => el.style.display = 'block');
        pkrLines.forEach(el => el.style.display = 'block');
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      let saved = 'both';
      try {
        saved = localStorage.getItem('bm_currency_view') || 'both';
      } catch (e) {}
      setCurrencyView(saved);
    });
  </script>
  <script src="admin-layout.js"></script>
</body>
</html>
