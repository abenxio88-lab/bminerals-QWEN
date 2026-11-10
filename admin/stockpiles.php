<?php
/**
 * Balochistan Minerals - Port & Mine Stockpiles & Maritime Shipment Operations
 * Ultra-Refined Corporate Aesthetic
 */

require_once __DIR__ . '/auth.php';
requireAuth();

$user = getLoggedInUser();
$pdo  = getDB();

$flashMessage = '';
$flashType    = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (verifyCsrfToken($csrf)) {
        if ($action === 'save_stockpile') {
            $id       = (int)($_POST['stockpile_id'] ?? 0);
            $mineral  = cleanInput($_POST['mineral_name'] ?? '');
            $location = cleanInput($_POST['location'] ?? 'Karachi Port');
            $tonnage  = (int)($_POST['tonnage_available'] ?? 0);
            $grade    = cleanInput($_POST['grade_spec'] ?? '');
            $status   = cleanInput($_POST['status'] ?? 'Ready for Loading');

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE stockpiles SET mineral_name = ?, location = ?, tonnage_available = ?, grade_spec = ?, status = ?, last_survey_date = CURDATE() WHERE id = ?');
                $stmt->execute([$mineral, $location, $tonnage, $grade, $status, $id]);
                $flashMessage = 'Stockpile updated successfully.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO stockpiles (mineral_name, location, tonnage_available, grade_spec, status, last_survey_date) VALUES (?, ?, ?, ?, ?, CURDATE())');
                $stmt->execute([$mineral, $location, $tonnage, $grade, $status]);
                $flashMessage = 'New stockpile location logged.';
            }
        } elseif ($action === 'delete_stockpile') {
            $id = (int)($_POST['stockpile_id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM stockpiles WHERE id = ?');
            $stmt->execute([$id]);
            $flashMessage = 'Stockpile entry deleted.';
            $flashType = 'warning';
        } elseif ($action === 'update_shipment') {
            $id     = (int)($_POST['shipment_id'] ?? 0);
            $stage  = cleanInput($_POST['status_stage'] ?? 'Contract & L/C Verified');
            $vessel = cleanInput($_POST['vessel_name'] ?? '');
            $notes  = cleanInput($_POST['notes'] ?? '');

            $stmt = $pdo->prepare('UPDATE shipments SET status_stage = ?, vessel_name = ?, notes = ? WHERE id = ?');
            $stmt->execute([$stage, $vessel, $notes, $id]);
            $flashMessage = 'Shipment stage updated successfully.';
        }
    } else {
        $flashMessage = 'Session expired. Please try again.';
        $flashType = 'danger';
    }
}

$stockpiles = [];
$shipments  = [];
$newLeads   = 0;
$pendingReviews = 0;

if ($pdo !== null) {
    $newLeads = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'New'")->fetchColumn();
    $pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'Pending'")->fetchColumn();
    $totalProspects = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects")->fetchColumn();
    $stockpiles = $pdo->query('SELECT * FROM stockpiles ORDER BY tonnage_available DESC')->fetchAll();
    $shipments  = $pdo->query('SELECT * FROM shipments ORDER BY created_at DESC')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stockpiles & Export Shipments | Balochistan Minerals</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="admin-layout.css?v=<?= filemtime(__DIR__ . '/admin-layout.css') ?>">
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
      --sidebar-width: 280px;
    }
    body { font-family: 'Poppins', sans-serif; font-size: 0.88rem; color: var(--text-dark); background-color: var(--brand-canvas); margin: 0; }
    h1, h2, h3, h4, h5, h6, .brand-font { font-family: 'Montserrat', sans-serif; letter-spacing: -0.015em; }

    /* Crisp Luxury Modal & Button Styles */
    .modal-content {
      border: 1px solid rgba(15, 23, 42, 0.1) !important;
      border-radius: 14px !important;
      box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25) !important;
      overflow: hidden;
    }
    .modal-header {
      background: #F8FAFC;
      border-bottom: 1px solid #E2E8F0;
      padding: 18px 24px;
    }
    .modal-body {
      padding: 24px;
      background: #FFFFFF;
    }
    .modal-footer {
      background: #F8FAFC;
      border-top: 1px solid #E2E8F0;
      padding: 16px 24px;
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
      box-shadow: none !important;
      cursor: pointer !important;
    }
    .btn-save-action:hover {
      background: #1E293B !important;
      border-color: #1E293B !important;
      box-shadow: none !important;
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
      box-shadow: none !important;
      transition: all 0.15s ease;
    }
    .btn-cancel-action:hover {
      background: #F8FAFC !important;
      color: #0F172A !important;
      border-color: #0F172A !important;
      box-shadow: none !important;
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
      box-shadow: none !important;
      transition: all 0.15s ease;
    }
    .btn-delete-action:hover {
      background: #FEE2E2 !important;
      color: #991B1B !important;
      border-color: #F87171 !important;
      box-shadow: none !important;
    }
    .app-wrapper { display: flex; min-height: 100vh; }
    .app-sidebar {
      width: var(--sidebar-width); background: var(--brand-surface); border-right: 1px solid var(--brand-border);
      display: flex; flex-direction: column; position: fixed; top: 0; bottom: 0; left: 0; z-index: 1040;
    }

    .sidebar-nav { padding: 16px 12px; flex: 1; }
    .sidebar-section-title { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94A3B8; padding: 12px 10px 6px; }
    .sidebar-link {
      display: flex; align-items: center; justify-content: space-between; padding: 9px 12px; border-radius: 6px;
      color: #475569; font-family: 'Montserrat', sans-serif; font-size: 0.82rem; font-weight: 600; text-decoration: none; margin-bottom: 2px; transition: all 0.15s ease;
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
    
    /* Refined Luxury Badges */
    .status-pill {
      font-family: 'Montserrat', sans-serif;
      font-size: 0.73rem;
      font-weight: 700;
      padding: 4px 11px;
      border-radius: 6px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      letter-spacing: 0.02em;
    }
    .status-pill-ready { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
    .status-pill-transit { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
    .status-pill-mine { background: #F8FAFC; color: #334155; border: 1px solid #CBD5E1; }
    .status-pill-assay { background: #F1F5F9; color: #1E293B; border: 1px solid #94A3B8; }

    .milestone-badge {
      background: #0F172A;
      color: #FFFFFF;
      border: 1px solid #0F172A;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.74rem;
      font-weight: 700;
      padding: 5px 12px;
      border-radius: 6px;
      letter-spacing: 0.03em;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.15);
    }
  </style>
</head>
<body>

  <div class="app-wrapper">
    <!-- LEFT SIDEBAR (Slideable & Collapsible) -->
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
        <a href="email-desk.php" class="sidebar-link">
          <div><i class="bi bi-envelope"></i> Email Reply Desk</div>
          <span class="badge-sidebar-hostinger">HOSTINGER</span>
        </a>
        <a href="prospects.php" class="sidebar-link">
          <div><i class="bi bi-bullseye"></i> Buyer Directory</div>
          <span class="badge font-monospace" style="background: rgba(230,92,26,0.15); color: #E65C1A; font-size: 0.7rem;"><?= $totalProspects ?></span>
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
        <a href="stockpiles.php" class="sidebar-link active">
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
          <div class="fw-bold small" style="color: var(--brand-navy);"><?= htmlspecialchars($user['full_name']) ?></div>
          <a href="logout.php" class="btn btn-sm btn-outline-secondary p-1 px-2" title="Sign Out"><i class="bi bi-power"></i></a>
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
            <h1 class="topbar-title">Stockpiles & Export Cargo Logistics</h1>
            <div class="topbar-subtitle">Gwadar Port, KPT & Port Qasim Terminals & Regional Mining Yards</div>
          </div>
        </div>
        <div class="topbar-right">
          <div class="pill-status d-none d-md-inline-flex" title="Port Logistics Active">
            <span class="status-dot dot-green"></span>
            <span>Port Logistics Active</span>
          </div>
          <button type="button" class="btn-topbar-primary" data-bs-toggle="modal" data-bs-target="#stockModal0">
            <i class="bi bi-plus-lg"></i> <span>Log Stockpile Lot</span>
          </button>
        </div>
      </header>

      <main class="content-canvas">
        <?php if (!empty($flashMessage)): ?>
          <div class="alert alert-<?= $flashType ?> alert-dismissible fade show py-2 px-3 small" role="alert">
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <!-- Active Stockpiles Section -->
        <div class="card shadow-sm border-0 rounded-2 mb-4">
          <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between border-bottom">
            <h6 class="mb-0 fw-bold brand-font" style="color: var(--brand-navy);">
              <i class="bi bi-geo-alt-fill text-muted me-1"></i> Active Stockpiles (Port & Mine Origin)
            </h6>
            <span class="text-muted small">Updated on port survey</span>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Commodity</th>
                  <th>Location Yard</th>
                  <th>Physical Specification</th>
                  <th>Available Tonnage</th>
                  <th>Status</th>
                  <th>Survey Date</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($stockpiles)): ?>
                  <tr><td colspan="7" class="text-center py-5 text-muted">No stockpile records found.</td></tr>
                <?php else: ?>
                  <?php foreach ($stockpiles as $stk): ?>
                    <tr>
                      <td><strong style="color: var(--brand-navy);"><?= htmlspecialchars($stk['mineral_name']) ?></strong></td>
                      <td><i class="bi bi-building me-1 text-muted"></i><?= htmlspecialchars($stk['location']) ?></td>
                      <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($stk['grade_spec']) ?></span></td>
                      <td>
                        <span class="fw-bold font-monospace" style="font-size: 1.05rem; color: var(--brand-navy);">
                          <?= number_format($stk['tonnage_available']) ?>
                        </span>
                        <span class="text-muted small">MT</span>
                      </td>
                      <td>
                        <?php if ($stk['status'] === 'Ready for Loading'): ?>
                          <span class="status-pill status-pill-ready">Ready for Loading</span>
                        <?php elseif ($stk['status'] === 'In Transit to Port'): ?>
                          <span class="status-pill status-pill-transit">In Transit to Port</span>
                        <?php elseif ($stk['status'] === 'Mine Sourcing'): ?>
                          <span class="status-pill status-pill-mine">Mine Sourcing</span>
                        <?php else: ?>
                          <span class="status-pill status-pill-assay"><?= htmlspecialchars($stk['status']) ?></span>
                        <?php endif; ?>
                      </td>
                      <td class="small text-muted"><?= date('M d, Y', strtotime($stk['last_survey_date'] ?? 'now')) ?></td>
                      <td class="text-end">
                        <button type="button" class="btn-table-edit" data-bs-toggle="modal" data-bs-target="#editStock<?= $stk['id'] ?>">
                          <i class="bi bi-pencil-square"></i> <span>Edit</span>
                        </button>
                      </td>
                    </tr>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editStock<?= $stk['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                          <form method="POST" action="stockpiles.php">
                            <input type="hidden" name="action" value="save_stockpile">
                            <input type="hidden" name="stockpile_id" value="<?= $stk['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                            <div class="modal-header">
                              <h6 class="modal-title fw-bold">Update Stockpile: <?= htmlspecialchars($stk['mineral_name']) ?></h6>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>

                            <div class="modal-body">
                              <div class="mb-3">
                                <label class="form-label small fw-semibold">Mineral Name</label>
                                <input type="text" name="mineral_name" list="mineralList" class="form-control form-control-sm" value="<?= htmlspecialchars($stk['mineral_name']) ?>" required>
                              </div>
                              <div class="mb-3">
                                <label class="form-label small fw-semibold">Location / Yard</label>
                                <input type="text" name="location" class="form-control form-control-sm" value="<?= htmlspecialchars($stk['location']) ?>" required>
                              </div>
                              <div class="mb-3">
                                <label class="form-label small fw-semibold">Available Metric Tons (MT)</label>
                                <input type="number" name="tonnage_available" class="form-control form-control-sm" value="<?= $stk['tonnage_available'] ?>" required>
                              </div>
                              <div class="mb-3">
                                <label class="form-label small fw-semibold">Grade Spec</label>
                                <input type="text" name="grade_spec" class="form-control form-control-sm" value="<?= htmlspecialchars($stk['grade_spec']) ?>" required>
                              </div>
                              <div class="mb-3">
                                <label class="form-label small fw-semibold">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                  <option value="Ready for Loading" <?= $stk['status'] === 'Ready for Loading' ? 'selected' : '' ?>>Ready for Loading (At Port)</option>
                                  <option value="In Transit to Port" <?= $stk['status'] === 'In Transit to Port' ? 'selected' : '' ?>>In Transit to Port (Trucking/Rail)</option>
                                  <option value="Mine Sourcing" <?= $stk['status'] === 'Mine Sourcing' ? 'selected' : '' ?>>Mine Sourcing / Crushing</option>
                                  <option value="Assay Testing" <?= $stk['status'] === 'Assay Testing' ? 'selected' : '' ?>>Assay Testing (SGS Lot Test)</option>
                                </select>
                              </div>
                            </div>

                            <div class="modal-footer d-flex justify-content-between">
                              <button type="button" class="btn-delete-action" onclick="if(confirm('Delete stockpile record?')) { document.getElementById('delStock<?= $stk['id'] ?>').submit(); }">
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

                          <form id="delStock<?= $stk['id'] ?>" method="POST" action="stockpiles.php" style="display:none;">
                            <input type="hidden" name="action" value="delete_stockpile">
                            <input type="hidden" name="stockpile_id" value="<?= $stk['id'] ?>">
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

        <!-- Maritime Export Shipments Tracker Section -->
        <div class="card shadow-sm border-0 rounded-2">
          <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between border-bottom">
            <div>
              <h6 class="mb-0 fw-bold brand-font" style="color: var(--brand-navy);">
                <i class="bi bi-ship me-1 text-muted"></i> Active Maritime Export Contracts & Consignments
              </h6>
              <div class="small text-muted">Tracked live by international buyers via tracking codes</div>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Tracking Code</th>
                  <th>Buyer & Company</th>
                  <th>Commodity & Tonnage</th>
                  <th>Discharge Port</th>
                  <th>Current Milestone</th>
                  <th>Vessel / B/L</th>
                  <th class="text-end">Update</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($shipments as $sh): ?>
                  <tr>
                    <td><strong class="font-monospace" style="color: var(--brand-navy);"><?= htmlspecialchars($sh['tracking_code']) ?></strong></td>
                    <td>
                      <div class="fw-bold"><?= htmlspecialchars($sh['buyer_name']) ?></div>
                      <div class="small text-muted"><?= htmlspecialchars($sh['company_name']) ?></div>
                    </td>
                    <td>
                      <div><?= htmlspecialchars($sh['commodity']) ?></div>
                      <div class="small fw-bold text-muted font-monospace"><?= htmlspecialchars($sh['tonnage']) ?></div>
                    </td>
                    <td><i class="bi bi-geo-alt text-muted"></i> <?= htmlspecialchars($sh['port_of_discharge']) ?></td>
                    <td>
                      <span class="milestone-badge">
                        <?= htmlspecialchars($sh['status_stage']) ?>
                      </span>
                    </td>
                    <td class="small">
                      <div><?= htmlspecialchars($sh['vessel_name'] ?: 'Vessel Pending') ?></div>
                      <div class="text-muted font-monospace"><?= htmlspecialchars($sh['bill_of_lading'] ?: 'B/L Pending') ?></div>
                    </td>
                    <td class="text-end">
                      <button type="button" class="btn-table-edit" data-bs-toggle="modal" data-bs-target="#editShip<?= $sh['id'] ?>">
                        <i class="bi bi-pencil-square"></i> <span>Update</span>
                      </button>
                    </td>
                  </tr>

                  <!-- Edit Shipment Modal -->
                  <div class="modal fade" id="editShip<?= $sh['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content">
                        <form method="POST" action="stockpiles.php">
                          <input type="hidden" name="action" value="update_shipment">
                          <input type="hidden" name="shipment_id" value="<?= $sh['id'] ?>">
                          <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                          <div class="modal-header">
                            <h6 class="modal-title fw-bold">Update Shipment: <?= htmlspecialchars($sh['tracking_code']) ?></h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>

                          <div class="modal-body">
                            <div class="mb-3">
                              <label class="form-label small fw-semibold">Milestone Stage</label>
                              <select name="status_stage" class="form-select form-select-sm">
                                <option value="Contract & L/C Verified" <?= $sh['status_stage'] === 'Contract & L/C Verified' ? 'selected' : '' ?>>Contract & L/C Verified</option>
                                <option value="Mine Sourcing & Crushing" <?= $sh['status_stage'] === 'Mine Sourcing & Crushing' ? 'selected' : '' ?>>Mine Sourcing & Crushing</option>
                                <option value="Transit to Karachi Port" <?= $sh['status_stage'] === 'Transit to Karachi Port' ? 'selected' : '' ?>>Transit to Karachi Port</option>
                                <option value="Port Warehouse Stacking" <?= $sh['status_stage'] === 'Port Warehouse Stacking' ? 'selected' : '' ?>>Port Warehouse Stacking</option>
                                <option value="SGS / Assay Inspection" <?= $sh['status_stage'] === 'SGS / Assay Inspection' ? 'selected' : '' ?>>SGS / Assay Inspection</option>
                                <option value="Customs Clearance" <?= $sh['status_stage'] === 'Customs Clearance' ? 'selected' : '' ?>>Customs Clearance</option>
                                <option value="Vessel Loaded" <?= $sh['status_stage'] === 'Vessel Loaded' ? 'selected' : '' ?>>Vessel Loaded</option>
                                <option value="In Maritime Transit" <?= $sh['status_stage'] === 'In Maritime Transit' ? 'selected' : '' ?>>In Maritime Transit</option>
                                <option value="Discharged & Completed" <?= $sh['status_stage'] === 'Discharged & Completed' ? 'selected' : '' ?>>Discharged & Completed</option>
                              </select>
                            </div>
                            <div class="mb-3">
                              <label class="form-label small fw-semibold">Vessel Name</label>
                              <input type="text" name="vessel_name" class="form-control form-control-sm" value="<?= htmlspecialchars($sh['vessel_name'] ?? '') ?>" placeholder="e.g. MV PACIFIC TRADER">
                            </div>
                            <div class="mb-3">
                              <label class="form-label small fw-semibold">Notes / Status Details</label>
                              <textarea name="notes" class="form-control form-control-sm" rows="3"><?= htmlspecialchars($sh['notes'] ?? '') ?></textarea>
                            </div>
                          </div>

                          <div class="modal-footer d-flex justify-content-end gap-2">
                            <button type="button" class="btn-cancel-action" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-save-action">
                              <i class="bi bi-check2-circle me-1"></i> Update Milestone
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Add Stockpile Modal -->
        <div class="modal fade" id="stockModal0" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <form method="POST" action="stockpiles.php">
                <input type="hidden" name="action" value="save_stockpile">
                <input type="hidden" name="stockpile_id" value="0">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <div class="modal-header">
                  <h6 class="modal-title fw-bold">Log New Stockpile Entry</h6>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Mineral / Material</label>
                    <input type="text" name="mineral_name" list="mineralList" class="form-control form-control-sm" placeholder="e.g. Chromite Lumps, Barite Powder" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Yard / Location</label>
                    <input type="text" name="location" class="form-control form-control-sm" placeholder="e.g. Karachi Port Trust (KPT) Yard" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Tonnage (MT)</label>
                    <input type="number" name="tonnage_available" class="form-control form-control-sm" placeholder="2500" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Specification</label>
                    <input type="text" name="grade_spec" class="form-control form-control-sm" placeholder="e.g. 42-44% Cr2O3" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select form-select-sm">
                      <option value="Ready for Loading">Ready for Loading (At Port)</option>
                      <option value="In Transit to Port">In Transit to Port</option>
                      <option value="Mine Sourcing">Mine Sourcing</option>
                      <option value="Assay Testing">Assay Testing</option>
                    </select>
                  </div>
                </div>

                <div class="modal-footer d-flex justify-content-end gap-2">
                  <button type="button" class="btn-cancel-action" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn-save-action">
                    <i class="bi bi-plus-lg me-1"></i> Create Stockpile
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
  <?= renderCommodityDatalist('mineralList') ?>
  <script src="admin-layout.js?v=<?= filemtime(__DIR__ . '/admin-layout.js') ?>"></script>
</body>
</html>
