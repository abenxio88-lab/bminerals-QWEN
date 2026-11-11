<?php
/**
 * Balochistan Minerals - Export Deals, Stage Measurements & Evidence Vault
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/deal-helpers.php';
requirePermission('view_deals');

$user = getLoggedInUser();
$pdo  = getDB();

$newLeads = 0;
$totalProspects = 0;
$pendingReviews = 0;
if ($pdo !== null) {
    ensureDealVaultSchema($pdo);
    try { $newLeads = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'New'")->fetchColumn(); } catch (Exception $e) {}
    try { $totalProspects = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects")->fetchColumn(); } catch (Exception $e) {}
    try { $pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'Pending'")->fetchColumn(); } catch (Exception $e) {}
}

$initialDealId    = (int)($_GET['id'] ?? 0);
$fromInquiryId    = (int)($_GET['from_inquiry'] ?? 0);
$csrfToken        = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Export Deals & Evidence Vault — Balochistan Minerals</title>
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
      --brand-gold: #B48C36;
      --brand-orange: #E65C1A;
      --brand-border: #E2E8F0;
      --brand-canvas: #F8FAFC;
      --sidebar-width: 280px;
    }
    body { font-family: 'Poppins', sans-serif; font-size: 0.86rem; background: var(--brand-canvas); color: var(--brand-navy); margin: 0; }
    h1, h2, h3, h4, h5, h6, .brand-font { font-family: 'Montserrat', sans-serif; letter-spacing: -0.015em; }

    .kpi-card {
      background: #FFF;
      border: 1px solid var(--brand-border);
      border-top: 3px solid #E2E8F0;
      border-radius: 10px;
      padding: 15px 18px;
      height: 100%;
      transition: border-color 0.15s ease;
    }
    .kpi-card:hover { border-top-color: #0F172A; }
    .kpi-label { font-size: 0.69rem; text-transform: uppercase; letter-spacing: 0.07em; color: #64748B; font-weight: 700; }
    .kpi-val { font-family: 'Montserrat', sans-serif; font-size: 1.42rem; font-weight: 800; color: var(--brand-navy); margin: 4px 0 2px; line-height: 1.15; }

    .deal-list-card {
      background: #FFF;
      border: 1px solid var(--brand-border);
      border-radius: 10px;
      overflow: hidden;
    }
    .deal-item-btn {
      width: 100%;
      text-align: left;
      background: #FFF;
      border: 0;
      border-left: 3px solid transparent;
      border-bottom: 1px solid #F1F5F9;
      padding: 13px 15px;
      transition: background 0.15s ease, border-color 0.15s ease;
    }
    .deal-item-btn:hover { background: #F8FAFC; }
    .deal-item-btn.active {
      background: #F1F5F9;
      border-left: 3px solid #0F172A;
      box-shadow: inset 0 1px 0 #E2E8F0, inset 0 -1px 0 #E2E8F0;
    }
    .badge-neutral-pill {
      display: inline-flex;
      align-items: center;
      background: #FFFFFF;
      color: #475569;
      border: 1px solid #CBD5E1;
      border-radius: 5px;
      padding: 2px 7px;
      font-size: 0.63rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }
    .badge-stage-chip {
      display: inline-flex;
      align-items: center;
      background: #FFFFFF;
      color: #334155;
      border: 1px solid #CBD5E1;
      border-radius: 5px;
      padding: 2px 8px;
      font-size: 0.65rem;
      font-weight: 600;
    }

    /* Luxury Segmented Control (Stripe/Linear style — replaces clunky Bootstrap btn-group) */
    .bm-seg-track {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 3px;
      background: #F1F5F9;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      padding: 3px;
    }
    .bm-seg-track--auto {
      display: inline-flex;
      flex-wrap: wrap;
      grid-template-columns: none;
    }
    .bm-seg-btn {
      border: 1px solid transparent;
      background: transparent;
      color: #64748B;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.73rem;
      font-weight: 600;
      border-radius: 6px;
      padding: 6px 10px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .bm-seg-btn:hover { color: #0F172A; }
    .bm-seg-btn.active {
      background: #FFFFFF;
      color: #0F172A;
      border-color: #CBD5E1;
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    }

    /* Refined Buyer Share Utility Toolbar */
    .bm-link-row {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .bm-link-input {
      height: 34px;
      font-size: 0.77rem;
      background: #FFFFFF !important;
      border: 1px solid #CBD5E1 !important;
      border-radius: 7px !important;
      padding: 0 10px;
      color: #334155;
      flex: 1;
      min-width: 0;
    }
    .bm-icon-btn {
      width: 34px;
      height: 34px;
      border-radius: 7px;
      border: 1px solid #CBD5E1;
      background: #FFFFFF;
      color: #475569;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      cursor: pointer;
      flex-shrink: 0;
      transition: all 0.15s ease;
    }
    .bm-icon-btn:hover {
      border-color: #0F172A;
      color: #0F172A;
      background: #F8FAFC;
    }
    .bm-icon-btn--wa { color: #15803D; }
    .bm-icon-btn--wa:hover {
      background: #ECFDF5;
      border-color: #10B981;
      color: #065F46;
    }
    .bm-icon-btn--warn { color: #64748B; }
    .bm-icon-btn--warn:hover {
      background: #FEF2F2;
      border-color: #FCA5A5;
      color: #B91C1C;
    }

    /* Single-row Desktop 7-Stage Progress Bar */
    .stage-tabs-grid {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      gap: 7px;
    }
    @media (max-width: 1279px) {
      .stage-tabs-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      }
    }
    .stage-pill {
      border: 1px solid #E2E8F0;
      background: #FFFFFF;
      color: #334155;
      border-radius: 8px;
      padding: 9px 10px;
      font-size: 0.73rem;
      font-weight: 600;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 5px;
      text-align: left;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .stage-pill:hover {
      border-color: #94A3B8;
      background: #F8FAFC;
    }
    .stage-pill.active {
      background: #0F172A;
      border-color: #0F172A;
      color: #FFFFFF;
      box-shadow: 0 2px 6px rgba(15, 23, 42, 0.12);
    }
    .stage-pill__top {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .stage-pill__num {
      font-family: 'Montserrat', sans-serif;
      font-size: 0.64rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      color: #64748B;
      text-transform: uppercase;
    }
    .stage-pill.active .stage-pill__num { color: rgba(255, 255, 255, 0.72); }
    .stage-pill__name {
      line-height: 1.25;
      font-weight: 600;
      width: 100%;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .stage-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
    .dot-Pending { background: #CBD5E1; }
    .dot-InProgress { background: #F59E0B; }
    .dot-AwaitingVerification { background: #3B82F6; }
    .dot-VerifiedComplete { background: #10B981; }

    .vault-card {
      background: #FFF;
      border: 1px solid var(--brand-border);
      border-radius: 10px;
      padding: 20px 22px;
    }
    .reconcile-tile {
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
      border-left: 3px solid #94A3B8;
      border-radius: 7px;
      padding: 10px 12px;
      height: 100%;
    }
    .reconcile-tile.is-alert { border-left-color: #DC2626; background: #FEF2F2; }
    .utility-strip-box {
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      padding: 12px 14px;
    }
    .doc-checklist-chip {
      font-size: 0.72rem;
      padding: 4px 10px;
      border-radius: 999px;
      border: 1px solid #CBD5E1;
      background: #F8FAFC;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .doc-checklist-chip.is-verified { background: #ECFDF5; border-color: #6EE7B7; color: #065F46; }
    .doc-checklist-chip.is-draft { background: #FFFBEB; border-color: #FCD34D; color: #92400E; }

    .evidence-thumb-box {
      width: 64px;
      height: 52px;
      border-radius: 6px;
      border: 1px solid #E2E8F0;
      object-fit: cover;
      background: #F1F5F9;
      cursor: pointer;
    }
    .upload-dropzone {
      border: 2px dashed #CBD5E1;
      border-radius: 10px;
      padding: 16px;
      background: #F8FAFC;
      text-align: center;
      transition: border-color 0.2s, background 0.2s;
      cursor: pointer;
    }
    .upload-dropzone.dragover { border-color: var(--brand-orange); background: #FFF7ED; }
  </style>
</head>
<body>

  <div class="app-wrapper">
    <aside class="app-sidebar" id="appSidebar">
      <div class="sidebar-header">
        <a href="../" target="_blank" class="sidebar-logo-wrap" title="Balochistan Minerals">
          <picture style="display: contents;"><source media="(min-width: 769px)" srcset="../images/new-logo-dekstop.png" width="723" height="278"><img src="../images/new-logo.png" alt="Balochistan Minerals" class="sidebar-logo" onerror="this.style.display='none'"></picture>
          <span class="sidebar-badge">TRADE DESK PORTAL</span>
        </a>
        <button type="button" id="sidebarCloseBtn" class="btn-sidebar-panel-toggle" aria-label="Toggle Sidebar">
          <i class="bi bi-layout-sidebar-inset"></i>
        </button>
      </div>

      <nav class="sidebar-nav">
        <div class="sidebar-section-title">Lead &amp; Deal Engine</div>
        <a href="dashboard.php" class="sidebar-link">
          <div><i class="bi bi-inbox-fill"></i> Inbound RFQs</div>
          <?php if ($newLeads > 0): ?><span class="badge bg-dark font-monospace" style="font-size: 0.7rem;"><?= $newLeads ?></span><?php endif; ?>
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

        <div class="sidebar-section-title">Trade Analytics &amp; Intelligence</div>
        <a href="analytics.php" class="sidebar-link">
          <div><i class="bi bi-graph-up"></i> Intelligence Hub</div>
          <span class="badge-sidebar-intel">PROJ</span>
        </a>

        <div class="sidebar-section-title">Logistics &amp; Trade Operations</div>
        <a href="deals.php" class="sidebar-link active">
          <div><i class="bi bi-shield-check"></i> Deals &amp; Evidence Vault</div>
          <span class="badge-sidebar-intel">VAULT</span>
        </a>
        <a href="stockpiles.php" class="sidebar-link">
          <div><i class="bi bi-boxes"></i> Stockpiles &amp; Shipments</div>
        </a>
        <a href="prices.php" class="sidebar-link">
          <div><i class="bi bi-cash-stack"></i> Mineral Spot Prices</div>
        </a>

        <div class="sidebar-section-title">Buyer Engagement</div>
        <a href="reviews.php" class="sidebar-link">
          <div><i class="bi bi-star-fill"></i> Customer Reviews</div>
          <?php if ($pendingReviews > 0): ?><span class="badge bg-secondary font-monospace" style="font-size: 0.7rem;"><?= $pendingReviews ?></span><?php endif; ?>
        </a>
        <a href="faqs.php" class="sidebar-link">
          <div><i class="bi bi-question-circle-fill"></i> Dynamic FAQs</div>
        </a>

        <div class="sidebar-section-title">System &amp; Links</div>
        <a href="../tracking" target="_blank" class="sidebar-link">
          <div><i class="bi bi-truck"></i> Buyer Tracking Portal</div>
          <i class="bi bi-box-arrow-up-right small text-muted"></i>
        </a>
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

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="app-main">
      <header class="top-bar">
        <div class="topbar-left">
          <button type="button" id="sidebarToggle" class="btn-sidebar-toggle" title="Toggle Sidebar (Ctrl + B)">
            <i class="bi bi-list"></i>
          </button>
          <div>
            <div class="brand-font fw-bold" style="font-size: 1rem;">Export Deals &amp; Evidence Vault</div>
            <div class="text-muted" style="font-size: 0.72rem;">Stage-measured export compliance, WebP compressed site proof &amp; buyer packs</div>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <?php if (canSealOrBackupDeals()): ?>
          <div class="dropdown">
            <button class="btn-topbar-action dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-cloud-download"></i>
              <span>System Backup</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
              <li><a class="dropdown-item" href="api-backup.php?mode=full_zip"><i class="bi bi-file-zip text-primary me-1"></i> Full Recovery Archive (SQL + Uploads)</a></li>
              <li><a class="dropdown-item" href="api-backup.php?mode=sql"><i class="bi bi-database-down text-success me-1"></i> MySQL Database Only (.sql)</a></li>
            </ul>
          </div>
          <?php endif; ?>
          <?php if (hasPermission('create_deal')): ?>
          <button type="button" class="btn-topbar-primary" onclick="openNewDealModal()">
            <i class="bi bi-plus-lg"></i>
            <span>New Export Deal</span>
          </button>
          <?php endif; ?>
        </div>
      </header>

      <main class="content-canvas">
        <div id="vaultAlertHost" class="mb-3" style="display:none;"></div>

        <!-- Executive Exception Radar -->
        <div id="exceptionRadarBanner" class="mb-3" style="display:none;"></div>

        <!-- KPI Strip -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-lg-3">
            <div class="kpi-card">
              <div class="kpi-label">Active Export Deals</div>
              <div class="kpi-val" id="kpiDealCount">—</div>
              <div class="small text-muted">Linked CRM → Vault pipelines</div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="kpi-card">
              <div class="kpi-label">Contracted Tonnage</div>
              <div class="kpi-val" id="kpiTotalMt">—</div>
              <div class="small text-muted">Across tracked contracts</div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="kpi-card">
              <div class="kpi-label">Avg Docs Readiness</div>
              <div class="kpi-val" id="kpiReadiness">—</div>
              <div class="small text-muted">Verified mandatory filings</div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="kpi-card">
              <div class="kpi-label">Vault Storage Used</div>
              <div class="kpi-val" id="kpiStorageUsed">—</div>
              <div class="small text-success fw-semibold" id="kpiStorageSaved">WebP + SHA-256 Dedupe</div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <!-- Left Column: Deal List -->
          <div class="col-lg-3">
            <div class="deal-list-card">
              <div class="p-3 border-bottom bg-light">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="fw-bold small brand-font text-uppercase" style="letter-spacing:0.05em;">Consignments</span>
                  <input type="text" id="dealSearchInput" class="form-control form-control-sm" style="max-width: 135px;" placeholder="Filter deals...">
                </div>
                <div class="bm-seg-track" role="group" aria-label="Filter consignments by status">
                  <button type="button" class="bm-seg-btn active" id="tabStateActive" onclick="setStateFilter('Active', this)">Active</button>
                  <button type="button" class="bm-seg-btn" id="tabStateClosed" onclick="setStateFilter('Closed', this)">Closed</button>
                  <button type="button" class="bm-seg-btn" id="tabStateAll" onclick="setStateFilter('All', this)">All</button>
                </div>
              </div>
              <div id="dealsSidebarList" style="max-height: 70vh; overflow-y: auto;">
                <div class="p-4 text-center text-muted small">Loading export deals...</div>
              </div>
            </div>
          </div>

          <!-- Right Column: Deal Command Canvas -->
          <div class="col-lg-9">
            <div id="dealWorkspaceEmpty" class="vault-card text-center py-5">
              <i class="bi bi-shield-lock fs-1 text-muted"></i>
              <h5 class="brand-font fw-bold mt-2">Select an Export Deal or Create a New Contract</h5>
              <p class="text-muted small mb-3">Record weighed tonnage, lab assays, compressed site photography, and Pakistan customs filings per stage.</p>
              <button type="button" class="btn btn-dark btn-sm fw-semibold px-3" onclick="openNewDealModal()">
                <i class="bi bi-plus-lg me-1"></i> Launch First Deal
              </button>
            </div>

            <div id="dealWorkspaceMain" style="display:none;">
              <!-- Deal Summary & Quick Actions -->
              <div class="vault-card mb-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap flex-xxl-nowrap gap-3">
                  <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <span class="badge bg-dark font-monospace" id="hdrDealCode">—</span>
                      <span class="badge bg-light text-dark border font-monospace" id="hdrTrackCode">—</span>
                      <span class="badge" id="hdrStateBadge">Active</span>
                      <span class="badge-neutral-pill" id="hdrDemoBadge" style="display:none;">Public Showcase</span>
                    </div>
                    <h4 class="brand-font fw-bold mt-2 mb-1" id="hdrTitle">—</h4>
                    <div class="small text-muted" id="hdrSubtitle">—</div>
                    <div id="dealAssigneeWrap" class="mt-2 d-flex align-items-center gap-2 d-none">
                      <span class="small fw-semibold text-muted"><i class="bi bi-person-badge me-1"></i>Assigned Officer:</span>
                      <select id="dealAssigneeSelect" class="form-select form-select-sm" style="width:auto;min-width:190px;font-size:0.76rem;" onchange="assignActiveDealStaff(this.value)">
                        <option value="">— Unassigned (Shared) —</option>
                      </select>
                    </div>
                  </div>
                  <div class="d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
                    <button type="button" id="btnCloseDealTrigger" class="btn-topbar-action" style="display:none; border-color:#059669; color:#065F46 !important;" onclick="openFinalizeDealModal()">
                      <i class="bi bi-lock-fill" style="color:#059669;"></i> Finalize &amp; Close
                    </button>
                    <button type="button" id="btnReopenDealTrigger" class="btn-topbar-action" style="display:none;" onclick="reopenSealedDeal()">
                      <i class="bi bi-unlock"></i> Reopen Deal
                    </button>
                    <a href="#" id="btnPrintInvoice" target="_blank" class="btn-topbar-action">
                      <i class="bi bi-file-earmark-text"></i> Invoice
                    </a>
                    <a href="#" id="btnPrintPacking" target="_blank" class="btn-topbar-action">
                      <i class="bi bi-box-seam"></i> Packing List
                    </a>
                    <div class="dropdown">
                      <button class="btn-topbar-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-file-zip"></i> ZIP Proof Pack
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="#" id="btnZipBuyer"><i class="bi bi-shield-check text-success me-1"></i> Buyer-Safe Proof Pack (.zip)</a></li>
                        <li><a class="dropdown-item" href="#" id="btnZipAll" style="display:none;"><i class="bi bi-lock text-primary me-1"></i> Full Internal Compliance Vault (.zip)</a></li>
                      </ul>
                    </div>
                  </div>
                </div>

                <!-- Auto Weight & Financial Reconciliation Strip -->
                <div class="row g-2 mt-3" id="reconcileStripRow"></div>

                <!-- Compliance & Buyer Tracking Link Utility Strip -->
                <div class="utility-strip-box mt-3">
                  <div class="row g-3 align-items-center">
                    <div class="col-md-5">
                      <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold text-dark">Mandatory Docs Verified</span>
                        <span class="font-monospace fw-bold text-dark" id="hdrDocsCounter">0 / 0</span>
                      </div>
                      <div class="progress bg-white border" style="height: 8px;">
                        <div id="hdrDocsBar" class="progress-bar bg-success" role="progressbar" style="width: 0%;"></div>
                      </div>
                    </div>
                    <div class="col-md-7">
                      <div class="small fw-semibold text-dark mb-1">Private Buyer Tracking Link <span class="text-muted fw-normal">(No login needed; unguessable token)</span></div>
                      <div class="bm-link-row">
                        <input type="text" id="buyerShareLink" class="form-control font-monospace bm-link-input" readonly>
                        <button class="bm-icon-btn" type="button" onclick="copyBuyerShareLink()" title="Copy Buyer Link"><i class="bi bi-clipboard"></i></button>
                        <a href="#" id="buyerWaShareBtn" target="_blank" class="bm-icon-btn bm-icon-btn--wa" title="Share via WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        <button class="bm-icon-btn bm-icon-btn--warn" type="button" onclick="rotateBuyerToken()" title="Rotate &amp; Revoke Previous Token"><i class="bi bi-arrow-repeat"></i></button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Yard / Stockpile Allocations -->
                <div class="mt-3 pt-3 border-top">
                  <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <span class="small fw-bold text-uppercase text-muted" style="letter-spacing:0.05em;">
                      <i class="bi bi-boxes me-1"></i> Linked Yard Stockpile Lots
                    </span>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                      <select id="allocStockSelect" class="form-select form-select-sm" style="width: auto; max-width: 240px;"></select>
                      <input type="number" step="0.01" id="allocMtInput" class="form-control form-control-sm" style="width: 90px;" placeholder="MT">
                      <input type="text" id="allocLotInput" class="form-control form-control-sm" style="width: 110px;" placeholder="Lot Code">
                      <button type="button" class="btn-topbar-action" style="height:31px; padding: 0 12px;" onclick="addStockpileAllocation()">Link Lot</button>
                    </div>
                  </div>
                  <div id="allocationsList" class="d-flex flex-wrap gap-2"></div>
                </div>
              </div>

              <!-- 7-Stage Stepper Grid -->
              <div class="stage-tabs-grid mb-3" id="stageTabsRow"></div>

              <!-- Active Stage Form & Evidence Panel -->
              <div class="vault-card mb-3" id="activeStageCard"></div>

              <!-- Deal Operations Ledger (Partial Lots, Payments, Field Upload Links, Audit Trail) -->
              <div class="vault-card" id="dealOpsLedgerCard"></div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Finalize & Close Deal Modal -->
  <div class="modal fade" id="finalizeDealModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-light">
          <h6 class="modal-title fw-bold brand-font"><i class="bi bi-shield-lock text-success me-1"></i> Finalize &amp; Seal Export Deal</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="finalizeChecklistBox" class="mb-3"></div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="chkDeductStock" checked>
            <label class="form-check-label small fw-semibold" for="chkDeductStock">Auto-deduct allocated MT from Yard Stockpiles (once)</label>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="chkRetireToken">
            <label class="form-check-label small" for="chkRetireToken">Retire private buyer tracking link &amp; field links</label>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-semibold">Final Closeout Summary / Archive Note</label>
            <input type="text" id="finalizeNoteInput" class="form-control form-control-sm" value="Vessel discharged, PRC settled, documents locked.">
          </div>
          <div id="forceCloseConfirmWrap" class="form-check mt-2 p-2 rounded bg-warning-subtle border border-warning" style="display:none;">
            <input class="form-check-input" type="checkbox" id="chkForceClose">
            <label class="form-check-label small fw-bold text-dark" for="chkForceClose">Override missing/unverified docs &amp; seal deal anyway (Owner override)</label>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-sm btn-success fw-bold px-3" onclick="confirmFinalizeDeal()">
            <i class="bi bi-check2-all me-1"></i> Seal Deal &amp; Archive
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- New Export Deal Modal -->
  <div class="modal fade" id="newDealModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <form id="newDealForm" onsubmit="submitNewDeal(event)">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="action" value="create_deal">
          <input type="hidden" name="inquiry_id" id="ndInquiryId" value="0">
          <div class="modal-header">
            <h6 class="modal-title fw-bold brand-font"><i class="bi bi-shield-plus me-1"></i> Create Stage-Measured Export Deal</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Buyer Name *</label>
                <input type="text" name="buyer_name" id="ndBuyerName" class="form-control form-control-sm" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Company / Consignee</label>
                <input type="text" name="company_name" id="ndCompany" class="form-control form-control-sm">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Destination Country</label>
                <input type="text" name="country" id="ndCountry" class="form-control form-control-sm" placeholder="e.g. China, UAE">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Commodity *</label>
                <input type="text" name="commodity" id="ndCommodity" list="mineralList" class="form-control form-control-sm" required placeholder="Chromite Lumps">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Target Spec / Grade</label>
                <input type="text" name="grade_spec" id="ndGradeSpec" class="form-control form-control-sm" placeholder="44-46% Cr2O3">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Contracted MT</label>
                <input type="number" step="0.01" name="contracted_mt" id="ndContractMt" class="form-control form-control-sm" placeholder="2500">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Unit Price (USD/MT)</label>
                <input type="number" step="0.01" name="unit_price_usd" id="ndUnitPrice" class="form-control form-control-sm" placeholder="245">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Incoterm</label>
                <input type="text" name="incoterm" class="form-control form-control-sm" value="FOB Karachi">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Payment Terms</label>
                <input type="text" name="payment_terms" class="form-control form-control-sm" value="Irrevocable L/C at Sight">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Port of Discharge (POD)</label>
                <input type="text" name="port_of_discharge" id="ndPod" class="form-control form-control-sm" placeholder="e.g. Qingdao Port">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Initial Stockpile Reserve (Optional)</label>
                <select name="stockpile_id" id="ndStockpileSelect" class="form-select form-select-sm"></select>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Estimated Departure (ETD)</label>
                <input type="date" name="estimated_departure" class="form-control form-control-sm">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-dark fw-semibold px-3">Initialize Deal &amp; 7 Stages</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Image Lightbox Modal -->
  <div class="modal fade" id="vaultLightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h6 class="modal-title fw-bold small" id="lightboxTitle">Evidence Preview</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center bg-dark p-2">
          <img id="lightboxImg" src="" alt="Preview" style="max-width: 100%; max-height: 75vh; border-radius: 4px;">
        </div>
      </div>
    </div>
  </div>

  <?= renderCommodityDatalist('mineralList') ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="admin-layout.js?v=<?= filemtime(__DIR__ . '/admin-layout.js') ?>"></script>
  <script src="js/vault-compressor.js?v=<?= filemtime(__DIR__ . '/js/vault-compressor.js') ?>"></script>
  <script>
    const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
    const INITIAL_DEAL_ID = <?= (int)$initialDealId ?>;
    const FROM_INQUIRY_ID = <?= (int)$fromInquiryId ?>;

    let allDeals = [];
    let allStockpiles = [];
    let allStaffMembers = [];
    let activeDealData = null;
    let activeStageKey = 'contract_lc';
    let activeLedgerTab = 'lots';
    let dealStateFilter = 'Active';
    let roleFlags = { role: 'Owner', can_financials: true, can_verify_lock: true, can_standard_close: true, can_force_close: true, can_reopen: true, can_assign: true, can_create: true, can_seal: true };
    let queuedFiles = [];

    function esc(str) {
      return String(str == null ? '' : str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function showVaultAlert(msg, type = 'success') {
      const box = document.getElementById('vaultAlertHost');
      box.style.display = 'block';
      box.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show py-2 small mb-0">${esc(msg)}<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button></div>`;
      setTimeout(() => { if (box) box.style.display = 'none'; }, 7000);
    }

    function setStateFilter(stateVal, btnEl) {
      dealStateFilter = stateVal;
      ['tabStateActive', 'tabStateClosed', 'tabStateAll'].forEach(id => {
        const b = document.getElementById(id);
        if (!b) return;
        b.classList.remove('active');
      });
      if (btnEl) {
        btnEl.classList.add('active');
      }
      renderDealsSidebar();
    }

    async function loadDealsList(selectDealId = null) {
      const res = await fetch('api-deals.php?action=list');
      const data = await res.json();
      if (!data.success) return;

      allDeals = data.deals || [];
      allStockpiles = data.stockpiles || [];
      if (data.staff_list && data.staff_list.length) allStaffMembers = data.staff_list;
      if (data.role_flags) roleFlags = data.role_flags;

      // Render Executive Exception Radar
      const exList = data.exceptions || [];
      const radarBox = document.getElementById('exceptionRadarBanner');
      if (exList.length) {
        radarBox.style.display = 'block';
        radarBox.innerHTML = `
          <div class="p-3 rounded border bg-white shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="fw-bold small brand-font text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Executive Exception Radar (${exList.length} Alerts)</span>
              <span class="small text-muted">Click any alert to inspect &amp; resolve</span>
            </div>
            <div class="d-flex flex-wrap gap-2">
              ${exList.map(ex => `
                <button type="button" class="btn btn-xs btn-outline-${ex.level} py-1 px-2 text-start" style="font-size:0.74rem;" onclick="loadDealDetail(${ex.deal_id})">
                  <strong>${esc(ex.deal_code)}</strong> (${esc(ex.company)}): ${esc(ex.text)}
                </button>`).join('')}
            </div>
          </div>`;
      } else {
        radarBox.style.display = 'none';
      }

      // Update KPI cards
      const activeCount = allDeals.filter(d => (d.deal_state || 'Active') === 'Active').length;
      document.getElementById('kpiDealCount').textContent = `${activeCount} Active / ${allDeals.length}`;
      const totMt = allDeals.reduce((sum, d) => sum + Number(d.contracted_mt || 0), 0);
      document.getElementById('kpiTotalMt').textContent = totMt.toLocaleString() + ' MT';
      const avgPct = allDeals.length
        ? Math.round(allDeals.reduce((s, d) => s + (d.readiness.mandatory_total ? (d.readiness.mandatory_verified / d.readiness.mandatory_total) * 100 : 0), 0) / allDeals.length)
        : 0;
      document.getElementById('kpiReadiness').textContent = avgPct + '%';

      const st = data.vault_stats || {};
      const storedB = Number(st.stored_bytes || 0);
      const origB = Number(st.orig_bytes || 0);
      document.getElementById('kpiStorageUsed').textContent = BMVaultCompressor.formatBytes(storedB);
      if (origB > storedB && origB > 0) {
        const savedPct = Math.round(((origB - storedB) / origB) * 100);
        document.getElementById('kpiStorageSaved').textContent = `${st.unique_blobs} files • Saved ${savedPct}% (${BMVaultCompressor.formatBytes(origB)} raw)`;
      } else {
        document.getElementById('kpiStorageSaved').textContent = `${st.unique_blobs || 0} deduplicated assets`;
      }

      // Populate stockpile dropdowns (showing free vs committed tonnage)
      const stkOpts = '<option value="">-- Select Yard Stockpile --</option>' +
        allStockpiles.map(s => {
          const comm = Number(s.committed_mt || 0);
          const commTxt = comm > 0 ? ` • Reserved ${comm} MT` : '';
          return `<option value="${s.id}">${esc(s.mineral_name)} (${esc(s.tonnage_available)} MT${commTxt} — ${esc(s.location)})</option>`;
        }).join('');
      const ndSel = document.getElementById('ndStockpileSelect');
      const alSel = document.getElementById('allocStockSelect');
      if (ndSel) ndSel.innerHTML = stkOpts;
      if (alSel) alSel.innerHTML = stkOpts;

      renderDealsSidebar();

      const targetId = selectDealId || (activeDealData && activeDealData.deal ? activeDealData.deal.id : 0) || INITIAL_DEAL_ID || (allDeals[0] ? allDeals[0].id : 0);
      if (targetId > 0) {
        loadDealDetail(targetId);
      }
    }

    function renderDealsSidebar() {
      const q = (document.getElementById('dealSearchInput').value || '').toLowerCase();
      const filtered = allDeals.filter(d => {
        const st = d.deal_state || 'Active';
        if (dealStateFilter === 'Active' && (st === 'Closed Settled' || st === 'Archived')) return false;
        if (dealStateFilter === 'Closed' && st !== 'Closed Settled' && st !== 'Archived') return false;
        return `${d.deal_code} ${d.tracking_code} ${d.buyer_name} ${d.company_name} ${d.commodity}`.toLowerCase().includes(q);
      });
      const list = document.getElementById('dealsSidebarList');
      if (!filtered.length) {
        list.innerHTML = '<div class="p-4 text-center text-muted small">No deals in this view.</div>';
        return;
      }
      const activeId = activeDealData && activeDealData.deal ? Number(activeDealData.deal.id) : 0;
      list.innerHTML = filtered.map(d => {
        const isAct = Number(d.id) === activeId;
        const docsOk = d.readiness ? `${d.readiness.mandatory_verified}/${d.readiness.mandatory_total}` : '0/0';
        const isClosed = (d.deal_state === 'Closed Settled' || d.deal_state === 'Archived');
        const hasAlert = d.intelligence && d.intelligence.alerts && d.intelligence.alerts.length > 0;
        return `
          <button type="button" class="deal-item-btn ${isAct ? 'active' : ''}" onclick="loadDealDetail(${d.id})">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="font-monospace fw-semibold text-muted" style="font-size:0.71rem;">${esc(d.deal_code)}</span>
              <div class="d-flex gap-1 align-items-center">
                ${hasAlert ? '<span class="badge bg-danger" style="font-size:0.6rem;">!</span>' : ''}
                <span class="badge-stage-chip font-monospace" style="font-size:0.64rem;">Docs ${docsOk}</span>
              </div>
            </div>
            <div class="fw-bold text-dark text-truncate" style="font-size:0.9rem;">${esc(d.company_name || d.buyer_name)}</div>
            <div class="small text-muted text-truncate mb-2">${esc(d.commodity)} • ${Number(d.contracted_mt || 0).toLocaleString()} MT</div>
            <div class="d-flex justify-content-between align-items-center gap-1 flex-wrap">
              <span class="badge-stage-chip">${esc(isClosed ? d.deal_state : d.current_stage_label)}</span>
              ${Number(d.is_public_demo) === 1 ? '<span class="badge-neutral-pill">DEMO</span>' : ''}
            </div>
          </button>`;
      }).join('');
    }

    async function loadDealDetail(dealId, preserveStage = false) {
      const res = await fetch('api-deals.php?action=detail&id=' + encodeURIComponent(dealId));
      const data = await res.json();
      if (!data.success) return;

      activeDealData = data;
      if (data.staff_list && data.staff_list.length) allStaffMembers = data.staff_list;
      if (data.role_flags) roleFlags = data.role_flags;
      if (!preserveStage) {
        activeStageKey = data.deal.current_stage_key || 'contract_lc';
      }
      queuedFiles = [];
      document.getElementById('dealWorkspaceEmpty').style.display = 'none';
      document.getElementById('dealWorkspaceMain').style.display = 'block';
      renderDealsSidebar();
      renderActiveDealHeader();
      renderStageTabs();
      renderActiveStagePanel();
      renderDealOpsLedger();
    }

    function renderActiveDealHeader() {
      const d = activeDealData.deal;
      const r = activeDealData.readiness;
      const intel = activeDealData.intelligence || {};
      const tok = activeDealData.buyer_token ? activeDealData.buyer_token.token : '';
      const isSealed = (d.deal_state === 'Closed Settled' || d.deal_state === 'Archived');

      document.getElementById('hdrDealCode').textContent = d.deal_code;
      document.getElementById('hdrTrackCode').textContent = d.tracking_code;
      const stBadge = document.getElementById('hdrStateBadge');
      stBadge.textContent = d.deal_state || 'Active';
      stBadge.className = 'badge ' + (isSealed ? 'bg-success' : 'bg-primary');
      document.getElementById('hdrDemoBadge').style.display = Number(d.is_public_demo) === 1 ? 'inline-block' : 'none';
      document.getElementById('hdrTitle').textContent = `${d.company_name || d.buyer_name} — ${d.commodity}`;
      document.getElementById('hdrSubtitle').textContent =
        `${Number(d.contracted_mt || 0).toLocaleString()} MT • ${d.incoterm} • ${d.payment_terms} • ${d.port_of_loading} → ${d.port_of_discharge || 'POD TBD'}` +
        (isSealed && d.sealed_at ? ` • Sealed ${d.sealed_at} by ${d.sealed_by || 'Owner'}` : '');

      document.getElementById('btnCloseDealTrigger').style.display = (!isSealed && (roleFlags.can_standard_close || roleFlags.can_force_close)) ? 'inline-block' : 'none';
      document.getElementById('btnReopenDealTrigger').style.display = (isSealed && roleFlags.can_reopen) ? 'inline-block' : 'none';

      const assignWrap = document.getElementById('dealAssigneeWrap');
      const assignSel  = document.getElementById('dealAssigneeSelect');
      if (assignWrap && assignSel) {
        if (roleFlags.can_assign && allStaffMembers.length) {
          assignSel.innerHTML = '<option value="">— Unassigned (Shared Desk) —</option>' +
            allStaffMembers.map(u => `<option value="${Number(u.id)}">${esc(u.full_name)} (${esc(u.role)})</option>`).join('');
          assignSel.value = d.assigned_user_id ? String(d.assigned_user_id) : '';
          assignWrap.classList.remove('d-none');
        } else {
          assignWrap.classList.add('d-none');
        }
      }
      document.getElementById('btnPrintInvoice').href = `deal-print.php?id=${d.id}&doc=invoice`;
      document.getElementById('btnPrintInvoice').style.display = roleFlags.can_financials ? 'inline-flex' : 'none';
      document.getElementById('btnPrintPacking').href = `deal-print.php?id=${d.id}&doc=packing`;
      document.getElementById('btnZipBuyer').href = `api-deals.php?action=zip_pack&deal_id=${d.id}&scope=buyer`;
      const zipAllEl = document.getElementById('btnZipAll');
      zipAllEl.style.display = roleFlags.can_financials ? 'block' : 'none';
      zipAllEl.href = `api-deals.php?action=zip_pack&deal_id=${d.id}&scope=all`;

      // Cross-stage Weight & Financial Reconciliation Strip
      const shrinkTxt = intel.shrinkage_pct !== null
        ? `${intel.shrinkage_mt > 0 ? '+' : ''}${intel.shrinkage_mt} MT (${intel.shrinkage_pct}% loss)`
        : 'Pending Yard Weight';
      const shrinkCls = intel.shrink_alert ? 'text-danger fw-bold' : 'text-dark fw-semibold';
      const dmtTxt = intel.dry_mt > 0
        ? `${intel.dry_mt} DMT` + (intel.expected_dry_mt ? ` (Formula ~${intel.expected_dry_mt})` : '')
        : 'Pending Lab Assay';
      const outTxt = intel.outturn_mt > 0
        ? `${intel.outturn_mt} DMT (${intel.ocean_diff_mt >= 0 ? '+' : ''}${intel.ocean_diff_mt} MT vs Load)`
        : 'In Progress';

      let finHtml = '';
      if (roleFlags.can_financials) {
        const valUsd = Number(intel.contract_usd || 0);
        const recUsd = Number(intel.received_usd || 0);
        const balUsd = Number(intel.balance_due_usd || 0);
        finHtml = `
          <div class="col-md-4">
            <div class="reconcile-tile small">
              <div class="text-muted fw-semibold" style="font-size:0.66rem;letter-spacing:0.04em;">FINANCIAL SETTLEMENT</div>
              <div class="fw-bold mt-1 ${balUsd > 0 ? 'text-warning-emphasis' : 'text-success'}">
                Value $${valUsd.toLocaleString()} • Paid $${recUsd.toLocaleString()} • Due $${balUsd.toLocaleString()}
              </div>
            </div>
          </div>`;
      }
      document.getElementById('reconcileStripRow').innerHTML = `
        <div class="col-md-4">
          <div class="reconcile-tile ${intel.shrink_alert ? 'is-alert' : ''} small">
            <div class="text-muted fw-semibold" style="font-size:0.66rem;letter-spacing:0.04em;">MINE → YARD SHRINKAGE</div>
            <div class="mt-1 ${shrinkCls}">${esc(shrinkTxt)}</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="reconcile-tile ${intel.dry_mismatch ? 'is-alert' : ''} small">
            <div class="text-muted fw-semibold" style="font-size:0.66rem;letter-spacing:0.04em;">DRY TONNAGE &amp; OUTTURN</div>
            <div class="mt-1 fw-semibold text-dark">${esc(dmtTxt)} • Outturn: ${esc(outTxt)}</div>
          </div>
        </div>
        ${finHtml}`;

      const pct = r.mandatory_total ? Math.round((r.mandatory_verified / r.mandatory_total) * 100) : 0;
      document.getElementById('hdrDocsCounter').textContent = `${r.mandatory_verified} / ${r.mandatory_total} verified (${r.mandatory_uploaded} uploaded)`;
      document.getElementById('hdrDocsBar').style.width = pct + '%';

      const baseOrigin = window.location.href.replace(/\/admin\/.*$/, '');
      const buyerUrl = Number(d.is_public_demo) === 1
        ? `${baseOrigin}/tracking?code=${encodeURIComponent(d.tracking_code)}`
        : `${baseOrigin}/tracking?code=${encodeURIComponent(d.tracking_code)}&k=${encodeURIComponent(tok)}`;
      document.getElementById('buyerShareLink').value = buyerUrl;
      document.getElementById('buyerWaShareBtn').href = `https://wa.me/?text=${encodeURIComponent('Live Export Consignment Tracker (' + d.deal_code + '): ' + buyerUrl)}`;

      // Render stockpile allocations
      const allocs = activeDealData.allocations || [];
      const allocBox = document.getElementById('allocationsList');
      if (!allocs.length) {
        allocBox.innerHTML = '<span class="small text-muted">No yard stockpile lot allocated yet.</span>';
      } else {
        const dedTag = Number(d.stockpile_deducted) === 1 ? '<span class="badge bg-success ms-1">DEDUCTED FROM YARD</span>' : '';
        allocBox.innerHTML = allocs.map(a => `
          <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-2 px-2 py-1">
            <span><strong>${esc(a.mineral_name)}</strong> (${esc(a.allocated_mt)} MT${a.lot_code ? ' • ' + esc(a.lot_code) : ''}) — ${esc(a.location)}</span>
            ${!isSealed ? `<button type="button" class="btn-close" style="font-size:0.55rem;" onclick="removeAllocation(${a.id})" title="Unlink stockpile"></button>` : ''}
          </span>`).join('') + dedTag;
      }
    }

    function renderStageTabs() {
      const ms = activeDealData.milestones || [];
      document.getElementById('stageTabsRow').innerHTML = ms.map(m => {
        const dotCls = 'dot-' + m.status.replace(/\s+/g, '');
        const act = m.stage_key === activeStageKey ? 'active' : '';
        const stepNum = String(m.stage_order).padStart(2, '0');
        const shortLbl = m.short_title || m.stage_title;
        return `
          <button type="button" class="stage-pill ${act}" onclick="selectStage('${m.stage_key}')" title="${esc(m.stage_title)} (${esc(m.status)})">
            <div class="stage-pill__top">
              <span class="stage-pill__num">Step ${stepNum}</span>
              <span class="stage-dot ${dotCls}"></span>
            </div>
            <div class="stage-pill__name">${esc(shortLbl)}</div>
          </button>`;
      }).join('');
    }

    function selectStage(key) {
      activeStageKey = key;
      queuedFiles = [];
      renderStageTabs();
      renderActiveStagePanel();
    }

    function renderActiveStagePanel() {
      const m = (activeDealData.milestones || []).find(x => x.stage_key === activeStageKey);
      if (!m) return;

      const docs = m.documents || [];
      const metrics = m.measurements || [];
      const catEntries = Object.entries(m.doc_categories || {});
      const catOptions = catEntries.map(([k, lbl]) => `<option value="${esc(k)}">${esc(lbl)}</option>`).join('')
        + `<option value="general_evidence">Additional Photo / Compliance Document</option>`;

      const docStatusMap = {};
      docs.forEach(d => {
        if (d.doc_status === 'Superseded') return;
        const cur = docStatusMap[d.doc_category];
        if (!cur || d.doc_status === 'Verified Final') docStatusMap[d.doc_category] = d.doc_status;
      });

      const checklistHtml = (m.mandatory_docs || []).map(catKey => {
        const lbl = (m.doc_categories && m.doc_categories[catKey]) || catKey;
        const st = docStatusMap[catKey] || '';
        const cls = st === 'Verified Final' ? 'is-verified' : (st ? 'is-draft' : '');
        const ico = st === 'Verified Final' ? 'bi-check-circle-fill' : (st ? 'bi-clock-fill' : 'bi-circle');
        return `<span class="doc-checklist-chip ${cls}"><i class="bi ${ico}"></i> ${esc(lbl)}</span>`;
      }).join('');

      const metricRowsHtml = metrics.map((met, idx) => `
        <div class="col-md-6 col-xl-4 metric-row-item" data-metric-key="${esc(met.metric_key)}">
          <div class="p-2 border rounded bg-light-subtle h-100">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="small fw-semibold mb-0 text-truncate metric-label-el">${esc(met.metric_label)}${met.unit ? ' (' + esc(met.unit) + ')' : ''}</label>
              <label class="small text-muted d-flex align-items-center gap-1" style="font-size:0.68rem;" title="Visible on Buyer Tracking Portal">
                <input type="checkbox" class="form-check-input mt-0 metric-vis-chk" ${Number(met.is_buyer_visible) === 1 ? 'checked' : ''}> Buyer
              </label>
            </div>
            <input type="text" class="form-control form-control-sm metric-val-inp" value="${esc(met.metric_value)}" placeholder="Enter value...">
            <input type="hidden" class="metric-unit-inp" value="${esc(met.unit)}">
            <input type="hidden" class="metric-rawlabel-inp" value="${esc(met.metric_label)}">
          </div>
        </div>`).join('');

      const docRowsHtml = docs.length === 0
        ? '<tr><td colspan="6" class="text-center text-muted py-4 small">No photos or PDFs uploaded to this stage yet.</td></tr>'
        : docs.map(doc => {
            const isImg = /^image\//.test(doc.mime_type || '');
            const previewCell = isImg
              ? `<img src="${esc(doc.thumb_url || doc.file_url)}" class="evidence-thumb-box" onclick="openLightbox('${esc(doc.file_url)}','${esc(doc.title)}')" alt="Evidence">`
              : `<a href="${esc(doc.file_url)}" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-file-earmark-pdf"></i> View</a>`;

            const visBadge = doc.visibility === 'buyer_portal'
              ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Buyer Portal</span>'
              : (doc.visibility === 'public_sample'
                  ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Public COA Sample</span>'
                  : '<span class="badge bg-secondary-subtle text-dark border">Internal Only</span>');

            const stBadge = doc.doc_status === 'Verified Final'
              ? '<span class="badge bg-success">Verified Final</span>'
              : (doc.doc_status === 'Superseded'
                  ? '<span class="badge bg-secondary">Superseded</span>'
                  : `<span class="badge bg-warning text-dark">${esc(doc.doc_status)}</span>`);

            const savedPct = (doc.orig_client_bytes > doc.byte_size && doc.orig_client_bytes > 0)
              ? ` <span class="text-success" style="font-size:0.68rem;">(-${Math.round((1 - doc.byte_size / doc.orig_client_bytes) * 100)}%)</span>`
              : '';

            return `
              <tr class="${doc.doc_status === 'Superseded' ? 'opacity-50' : ''}">
                <td>${previewCell}</td>
                <td>
                  <div class="fw-bold text-dark">${esc(doc.title)} <span class="badge bg-light text-dark border font-monospace">v${doc.doc_version}</span></div>
                  <div class="small text-muted">${esc((m.doc_categories && m.doc_categories[doc.doc_category]) || doc.doc_category)} • ${esc(doc.original_filename)}</div>
                </td>
                <td>${stBadge} ${Number(doc.is_locked) === 1 ? '<i class="bi bi-lock-fill text-success ms-1" title="Locked Compliance Record"></i>' : ''}</td>
                <td>
                  <select class="form-select form-select-sm" style="width: 135px; font-size:0.74rem;" onchange="changeDocVisibility(${doc.id}, this.value)">
                    <option value="internal_only" ${doc.visibility === 'internal_only' ? 'selected' : ''}>Internal Only</option>
                    <option value="buyer_portal" ${doc.visibility === 'buyer_portal' ? 'selected' : ''}>Buyer Portal</option>
                    <option value="public_sample" ${doc.visibility === 'public_sample' ? 'selected' : ''}>Public Sample</option>
                  </select>
                </td>
                <td class="font-monospace small">
                  ${esc(doc.size_label)}${savedPct}
                  <div class="text-muted" style="font-size:0.65rem;">SHA:${esc((doc.sha256 || '').slice(0, 8))}</div>
                </td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <a href="${esc(doc.file_url)}&download=1" class="btn btn-outline-secondary" title="Download"><i class="bi bi-download"></i></a>
                    ${(Number(doc.is_locked) === 0 && roleFlags.can_verify_lock) ? `<button type="button" class="btn btn-outline-success" onclick="verifyLockDoc(${doc.id})" title="Verify & Lock Record"><i class="bi bi-shield-check"></i></button>` : ''}
                    ${(Number(doc.is_locked) === 0 && roleFlags.can_verify_lock) ? `<button type="button" class="btn btn-outline-danger" onclick="deleteDoc(${doc.id})" title="Delete unlocked file"><i class="bi bi-trash"></i></button>` : ''}
                  </div>
                </td>
              </tr>`;
          }).join('');

      document.getElementById('activeStageCard').innerHTML = `
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 pb-3 mb-3 border-bottom">
          <div>
            <h5 class="brand-font fw-bold mb-1">Stage ${m.stage_order}: ${esc(m.stage_title)}</h5>
            <div class="small text-muted">${esc(m.description)}</div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <select id="stageStatusSel" class="form-select form-select-sm fw-semibold" style="width: auto;">
              <option value="Pending" ${m.status === 'Pending' ? 'selected' : ''}>Pending</option>
              <option value="In Progress" ${m.status === 'In Progress' ? 'selected' : ''}>In Progress</option>
              <option value="Awaiting Verification" ${m.status === 'Awaiting Verification' ? 'selected' : ''}>Awaiting Verification</option>
              ${(roleFlags.can_verify_lock || m.status === 'Verified Complete') ? `<option value="Verified Complete" ${m.status === 'Verified Complete' ? 'selected' : ''}>Verified Complete</option>` : ''}
            </select>
            <button type="button" class="btn btn-sm btn-dark fw-semibold px-3" onclick="saveActiveMilestone(${m.id})">
              <i class="bi bi-check2-circle me-1"></i> Save Stage &amp; Metrics
            </button>
          </div>
        </div>

        <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
          <span class="small fw-bold text-muted me-1">Mandatory Docs:</span>
          ${checklistHtml || '<span class="small text-muted">Optional evidence stage</span>'}
        </div>

        <!-- Stage Measurements Grid -->
        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="brand-font fw-bold small text-uppercase text-muted mb-0">Measured Stage Specs &amp; Official Numbers</h6>
            <button type="button" class="btn btn-xs btn-outline-dark py-0 px-2" style="font-size:0.74rem;" onclick="appendCustomMetricRow()">
              <i class="bi bi-plus"></i> Add Custom Spec
            </button>
          </div>
          <div class="row g-2" id="stageMetricsGrid">${metricRowsHtml}</div>
        </div>

        <div class="row g-3 mb-4">
          <div class="${roleFlags.can_financials ? 'col-md-6' : 'col-12'}">
            <label class="form-label small fw-semibold">Buyer-Visible Stage Update</label>
            <textarea id="stageBuyerSummary" class="form-control form-control-sm" rows="2" placeholder="Clear update shown on buyer tracking link...">${esc(m.buyer_summary || '')}</textarea>
          </div>
          ${roleFlags.can_financials ? `
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Internal Compliance Notes (Private)</label>
            <textarea id="stageInternalRemarks" class="form-control form-control-sm" rows="2" placeholder="Mine supplier notes, L/C discrepancies, fee logs...">${esc(m.internal_remarks || '')}</textarea>
          </div>` : ''}
        </div>

        <!-- Evidence Compressor & Uploader -->
        <div class="p-3 border rounded bg-light mb-4">
          <h6 class="brand-font fw-bold small text-uppercase mb-2"><i class="bi bi-cloud-arrow-up me-1 text-primary"></i> Upload Stage Photos &amp; Documents (Auto WebP Compressed)</h6>
          <div class="row g-2 mb-2">
            <div class="col-md-4">
              <label class="form-label small fw-semibold mb-1">Document Category</label>
              <select id="upDocCategory" class="form-select form-select-sm">${catOptions}</select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold mb-1">Visibility</label>
              <select id="upDocVis" class="form-select form-select-sm">
                <option value="buyer_portal">Buyer Tracking Portal</option>
                <option value="internal_only">Internal Only (Private)</option>
                <option value="public_sample">Public Website Sample COA</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold mb-1">Approval Status</label>
              <select id="upDocStatus" class="form-select form-select-sm">
                <option value="Under Review">Under Review</option>
                <option value="Draft">Draft (Audit Pending)</option>
                ${roleFlags.can_verify_lock ? '<option value="Verified Final">Verified Final</option>' : ''}
              </select>
            </div>
          </div>

          <div id="vaultDropzone" class="upload-dropzone mb-2" onclick="document.getElementById('vaultFilePicker').click()">
            <input type="file" id="vaultFilePicker" multiple accept=".jpg,.jpeg,.png,.webp,.avif,.pdf,.doc,.docx,.xls,.xlsx,.csv" style="display:none;" onchange="handleSelectedFiles(this.files)">
            <div class="fw-semibold text-dark small"><i class="bi bi-images me-1"></i> Click or Drop Site Photos &amp; Compliance PDFs Here</div>
            <div class="text-muted" style="font-size:0.74rem;">Phone photos automatically shrink to 1600px WebP + 400px thumbnails right in your browser before upload.</div>
          </div>

          <div id="queuedFilesPreview" class="mb-2"></div>
          <div class="d-flex justify-content-end" id="uploadQueueActions" style="display:none !important;">
            <button type="button" id="btnRunQueueUpload" class="btn btn-sm btn-dark fw-semibold px-3" onclick="uploadQueuedFiles(${m.id})">
              <i class="bi bi-upload me-1"></i> Upload Compressed Files to Vault
            </button>
          </div>
        </div>

        <!-- Existing Stage Evidence Table -->
        <h6 class="brand-font fw-bold small text-uppercase text-muted mb-2">Stage Vault Files (${docs.length})</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr style="font-size:0.74rem;">
                <th>Preview</th>
                <th>Document / Category</th>
                <th>Status</th>
                <th>Visibility</th>
                <th>Compressed Size</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>${docRowsHtml}</tbody>
          </table>
        </div>`;

      setupDropzoneEvents();
    }

    function setupDropzoneEvents() {
      const dz = document.getElementById('vaultDropzone');
      if (!dz) return;
      ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('dragover'); }));
      ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('dragover'); }));
      dz.addEventListener('drop', e => {
        if (e.dataTransfer && e.dataTransfer.files) handleSelectedFiles(e.dataTransfer.files);
      });
    }

    async function handleSelectedFiles(fileList) {
      const preview = document.getElementById('queuedFilesPreview');
      const actions = document.getElementById('uploadQueueActions');
      if (!fileList || !fileList.length) return;

      preview.innerHTML = '<div class="small text-primary"><span class="spinner-border spinner-border-sm me-1"></span> Compressing photos into WebP inside browser...</div>';
      for (const rawFile of fileList) {
        const res = await BMVaultCompressor.compressFile(rawFile);
        queuedFiles.push(res);
      }

      preview.innerHTML = queuedFiles.map((q, idx) => {
        const saved = q.wasCompressed
          ? `<span class="badge bg-success-subtle text-success">${BMVaultCompressor.formatBytes(q.origBytes)} → ${BMVaultCompressor.formatBytes(q.finalBytes)}</span>`
          : `<span class="badge bg-light text-dark border">${BMVaultCompressor.formatBytes(q.finalBytes)}</span>`;
        const warn = q.warning ? `<div class="text-warning small">${esc(q.warning)}</div>` : '';
        const img = q.previewDataUrl ? `<img src="${q.previewDataUrl}" style="width:36px;height:30px;object-fit:cover;border-radius:4px;">` : '<i class="bi bi-file-earmark-text fs-5 text-secondary"></i>';
        return `
          <div class="d-flex align-items-center justify-content-between p-2 mb-1 bg-white border rounded">
            <div class="d-flex align-items-center gap-2">
              ${img}
              <div>
                <div class="small fw-semibold">${esc(q.filename)}</div>
                ${saved}${warn}
              </div>
            </div>
            <button type="button" class="btn-close small" onclick="removeQueuedFile(${idx})"></button>
          </div>`;
      }).join('');

      actions.style.setProperty('display', queuedFiles.length ? 'flex' : 'none', 'important');
    }

    function removeQueuedFile(idx) {
      queuedFiles.splice(idx, 1);
      handleSelectedFiles([]);
      const preview = document.getElementById('queuedFilesPreview');
      const actions = document.getElementById('uploadQueueActions');
      if (!queuedFiles.length) {
        preview.innerHTML = '';
        actions.style.setProperty('display', 'none', 'important');
      }
    }

    async function uploadQueuedFiles(milestoneId) {
      if (!queuedFiles.length || !activeDealData) return;
      const btn = document.getElementById('btnRunQueueUpload');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading...';

      const cat = document.getElementById('upDocCategory').value;
      const vis = document.getElementById('upDocVis').value;
      const st  = document.getElementById('upDocStatus').value;
      let okCount = 0;

      for (const item of queuedFiles) {
        const fd = new FormData();
        fd.append('csrf_token', CSRF_TOKEN);
        fd.append('action', 'upload_doc');
        fd.append('deal_id', activeDealData.deal.id);
        fd.append('milestone_id', milestoneId);
        fd.append('doc_category', cat);
        fd.append('title', item.filename.replace(/\.[^.]+$/, ''));
        fd.append('doc_status', st);
        fd.append('visibility', vis);
        fd.append('orig_client_bytes', item.origBytes);
        fd.append('keep_existing', '1');
        fd.append('file', item.file, item.filename);
        if (item.thumb) {
          fd.append('thumb', item.thumb, item.filename.replace(/\.[^.]+$/, '') + '_thumb.webp');
        }
        const res = await fetch('api-deals.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) okCount++;
        else showVaultAlert(data.error || 'Upload error', 'danger');
      }

      queuedFiles = [];
      showVaultAlert(`Uploaded ${okCount} file(s) securely into Deal Vault.`);
      await loadDealDetail(activeDealData.deal.id, true);
      await loadDealsList(activeDealData.deal.id);
    }

    function appendCustomMetricRow() {
      const label = prompt('Enter custom spec / measurement name (e.g. "Sieve < 75 micron", "P2O5", "Container Tare"):');
      if (!label || !label.trim()) return;
      const unit = prompt('Unit (optional, e.g. "%", "MT", "SG", or leave blank):') || '';
      const grid = document.getElementById('stageMetricsGrid');
      const div = document.createElement('div');
      div.className = 'col-md-6 col-xl-4 metric-row-item';
      div.dataset.metricKey = '';
      div.innerHTML = `
        <div class="p-2 border rounded bg-light-subtle h-100">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="small fw-semibold mb-0 text-truncate metric-label-el">${esc(label.trim())}${unit ? ' (' + esc(unit.trim()) + ')' : ''}</label>
            <label class="small text-muted d-flex align-items-center gap-1" style="font-size:0.68rem;">
              <input type="checkbox" class="form-check-input mt-0 metric-vis-chk" checked> Buyer
            </label>
          </div>
          <input type="text" class="form-control form-control-sm metric-val-inp" placeholder="Enter value...">
          <input type="hidden" class="metric-unit-inp" value="${esc(unit.trim())}">
          <input type="hidden" class="metric-rawlabel-inp" value="${esc(label.trim())}">
        </div>`;
      grid.appendChild(div);
    }

    async function saveActiveMilestone(milestoneId) {
      const metrics = [];
      document.querySelectorAll('#stageMetricsGrid .metric-row-item').forEach(el => {
        metrics.push({
          key: el.dataset.metricKey || '',
          label: el.querySelector('.metric-rawlabel-inp').value,
          value: el.querySelector('.metric-val-inp').value,
          unit: el.querySelector('.metric-unit-inp').value,
          is_buyer_visible: el.querySelector('.metric-vis-chk').checked ? 1 : 0,
        });
      });

      const fd = new FormData();
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('action', 'save_milestone');
      fd.append('deal_id', activeDealData.deal.id);
      fd.append('milestone_id', milestoneId);
      fd.append('status', document.getElementById('stageStatusSel').value);
      fd.append('buyer_summary', document.getElementById('stageBuyerSummary').value);
      const remEl = document.getElementById('stageInternalRemarks');
      fd.append('internal_remarks', remEl ? remEl.value : '');
      fd.append('metrics_json', JSON.stringify(metrics));

      const res = await fetch('api-deals.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        showVaultAlert('Stage measurements & milestone status saved.');
        await loadDealDetail(activeDealData.deal.id, true);
        await loadDealsList(activeDealData.deal.id);
      } else {
        showVaultAlert(data.error || 'Failed to save milestone', 'danger');
      }
    }

    async function docOp(docId, op, extra = {}) {
      const fd = new FormData();
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('action', 'update_doc');
      fd.append('doc_id', docId);
      fd.append('op', op);
      Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
      const res = await fetch('api-deals.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        await loadDealDetail(activeDealData.deal.id, true);
        await loadDealsList(activeDealData.deal.id);
      } else {
        showVaultAlert(data.error || 'Action failed', 'danger');
      }
    }

    function verifyLockDoc(docId) {
      if (confirm('Verify & Lock this document as Verified Final? Locked files cannot be deleted (only superseded by newer versions).')) {
        docOp(docId, 'verify_lock');
      }
    }
    function changeDocVisibility(docId, vis) { docOp(docId, 'set_visibility', { visibility: vis }); }
    function deleteDoc(docId) {
      if (confirm('Delete this unverified upload from the vault?')) docOp(docId, 'delete');
    }

    async function assignActiveDealStaff(assignedUserId) {
      if (!activeDealData || !activeDealData.deal) return;
      const fd = new FormData();
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('action', 'assign_deal');
      fd.append('deal_id', activeDealData.deal.id);
      fd.append('assigned_user_id', assignedUserId);
      const res = await fetch('api-deals.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        showVaultAlert('Deal assigned to ' + (data.assigned_name || 'Unassigned') + '.');
        await loadDealsList(activeDealData.deal.id);
      } else {
        showVaultAlert(data.error || 'Failed to assign deal', 'danger');
      }
    }

    async function addStockpileAllocation() {
      const stkId = document.getElementById('allocStockSelect').value;
      const mt = document.getElementById('allocMtInput').value;
      const lot = document.getElementById('allocLotInput').value;
      if (!stkId || !mt) return alert('Select a stockpile and enter tonnage (MT).');
      const fd = new FormData();
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('action', 'save_allocation');
      fd.append('deal_id', activeDealData.deal.id);
      fd.append('stockpile_id', stkId);
      fd.append('allocated_mt', mt);
      fd.append('lot_code', lot);
      const res = await fetch('api-deals.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) await loadDealDetail(activeDealData.deal.id, true);
    }

    async function removeAllocation(allocId) {
      const fd = new FormData();
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('action', 'remove_allocation');
      fd.append('allocation_id', allocId);
      await fetch('api-deals.php', { method: 'POST', body: fd });
      await loadDealDetail(activeDealData.deal.id, true);
    }

    async function rotateBuyerToken() {
      if (!confirm('Generate a fresh buyer tracking token? The previous tracking link will immediately stop working.')) return;
      const fd = new FormData();
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('action', 'rotate_token');
      fd.append('deal_id', activeDealData.deal.id);
      const res = await fetch('api-deals.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        showVaultAlert('Buyer link token rotated safely.');
        await loadDealDetail(activeDealData.deal.id, true);
      }
    }

    function copyBuyerShareLink() {
      const inp = document.getElementById('buyerShareLink');
      navigator.clipboard ? navigator.clipboard.writeText(inp.value) : (inp.select(), document.execCommand('copy'));
      showVaultAlert('Buyer portal tracking link copied to clipboard.');
    }

    function openLightbox(url, title) {
      document.getElementById('lightboxTitle').textContent = title || 'Evidence Preview';
      document.getElementById('lightboxImg').src = url;
      bootstrap.Modal.getOrCreateInstance(document.getElementById('vaultLightboxModal')).show();
    }

    function openNewDealModal(preData = null) {
      document.getElementById('newDealForm').reset();
      document.getElementById('ndInquiryId').value = preData ? preData.id : 0;
      if (preData) {
        document.getElementById('ndBuyerName').value = preData.buyer_name || '';
        document.getElementById('ndCompany').value = preData.company_name || '';
        document.getElementById('ndCountry').value = preData.country || '';
        document.getElementById('ndCommodity').value = preData.mineral_type || '';
        document.getElementById('ndGradeSpec').value = preData.grade_spec || '';
        document.getElementById('ndContractMt').value = preData.num_mt || '';
        document.getElementById('ndPod').value = preData.destination_port || '';
        document.getElementById('ndUnitPrice').value = preData.spot_price_quoted || '';
      }
      bootstrap.Modal.getOrCreateInstance(document.getElementById('newDealModal')).show();
    }

    async function submitNewDeal(e) {
      e.preventDefault();
      const fd = new FormData(e.target);
      const res = await fetch('api-deals.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('newDealModal')).hide();
        showVaultAlert(`Created Export Deal ${data.deal_code} (${data.tracking_code})`);
        await loadDealsList(data.deal_id);
        await loadDealDetail(data.deal_id);
      } else {
        showVaultAlert(data.error || 'Could not create deal', 'danger');
      }
    }

    document.getElementById('dealSearchInput').addEventListener('input', renderDealsSidebar);

    function renderDealOpsLedger() {
      const card = document.getElementById('dealOpsLedgerCard');
      if (!card || !activeDealData) return;
      const d = activeDealData.deal;
      const isSealed = (d.deal_state === 'Closed Settled' || d.deal_state === 'Archived');
      const lots = activeDealData.lots || [];
      const pays = activeDealData.payments || [];
      const fls = activeDealData.field_links || [];
      const logs = activeDealData.audit_logs || [];
      const baseOrigin = window.location.href.replace(/\/admin\/.*$/, '');

      let bodyHtml = '';
      if (activeLedgerTab === 'lots') {
        bodyHtml = `
          <div class="table-responsive mb-2">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light" style="font-size:0.74rem;"><tr><th>Lot Code</th><th>MT</th><th>Vessel &amp; B/L</th><th>ETD / Arrival</th><th>Outturn</th><th>Status</th><th></th></tr></thead>
              <tbody>
                ${lots.length ? lots.map(l => `
                  <tr>
                    <td class="font-monospace fw-bold">${esc(l.lot_code)}</td>
                    <td class="font-monospace">${Number(l.tonnage_mt).toLocaleString()} MT</td>
                    <td class="small">${esc(l.vessel_name || 'TBD')}<div class="text-muted font-monospace">${esc(l.bill_of_lading || '')}</div></td>
                    <td class="small">${esc(l.etd || '—')} → ${esc(l.arrival_date || '—')}</td>
                    <td class="font-monospace small">${Number(l.outturn_dmt) > 0 ? l.outturn_dmt + ' DMT' : '—'}</td>
                    <td><span class="badge bg-light text-dark border">${esc(l.lot_status)}</span></td>
                    <td class="text-end">${!isSealed ? `<button class="btn btn-xs btn-outline-danger py-0" onclick="deleteLot(${l.id})"><i class="bi bi-trash"></i></button>` : ''}</td>
                  </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted small py-3">Single shipment consignment (add partial vessel lots below if split).</td></tr>'}
              </tbody>
            </table>
          </div>
          ${!isSealed ? `
          <div class="row g-2 pt-2 border-top align-items-end">
            <div class="col-md-2"><input type="text" id="lotCodeInp" class="form-control form-control-sm" placeholder="Lot-A"></div>
            <div class="col-md-2"><input type="number" step="0.01" id="lotMtInp" class="form-control form-control-sm" placeholder="MT"></div>
            <div class="col-md-3"><input type="text" id="lotVesselInp" class="form-control form-control-sm" placeholder="Vessel Name"></div>
            <div class="col-md-2"><input type="text" id="lotBlInp" class="form-control form-control-sm" placeholder="B/L #"></div>
            <div class="col-md-2"><select id="lotStInp" class="form-select form-select-sm"><option>Staged</option><option>Loaded</option><option>In Transit</option><option>Discharged</option></select></div>
            <div class="col-md-1"><button class="btn btn-sm btn-dark w-100" onclick="addDealLot()">Add</button></div>
          </div>` : ''}`;
      } else if (activeLedgerTab === 'payments' && roleFlags.can_financials) {
        bodyHtml = `
          <div class="table-responsive mb-2">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light" style="font-size:0.74rem;"><tr><th>Tranche</th><th>Type</th><th>Expected USD</th><th>Received USD</th><th>SWIFT / PRC Ref</th><th>Status</th><th></th></tr></thead>
              <tbody>
                ${pays.length ? pays.map(p => `
                  <tr>
                    <td class="fw-semibold">${esc(p.tranche_label)}</td>
                    <td class="small">${esc(p.tranche_type)}</td>
                    <td class="font-monospace">$${Number(p.expected_usd).toLocaleString()}</td>
                    <td class="font-monospace fw-bold text-success">$${Number(p.received_usd).toLocaleString()}</td>
                    <td class="font-monospace small">${esc(p.payment_ref || '—')}${p.paid_date ? ' (' + esc(p.paid_date) + ')' : ''}</td>
                    <td><span class="badge ${p.status === 'Settled' || p.status === 'Received' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-dark'}">${esc(p.status)}</span></td>
                    <td class="text-end">${!isSealed ? `<button class="btn btn-xs btn-outline-danger py-0" onclick="deletePayment(${p.id})"><i class="bi bi-trash"></i></button>` : ''}</td>
                  </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted small py-3">No payment tranches logged yet.</td></tr>'}
              </tbody>
            </table>
          </div>
          ${!isSealed ? `
          <div class="row g-2 pt-2 border-top align-items-end">
            <div class="col-md-3"><input type="text" id="payLblInp" class="form-control form-control-sm" placeholder="e.g. 85% Provisional L/C"></div>
            <div class="col-md-2"><select id="payTypeInp" class="form-select form-select-sm"><option>Advance</option><option selected>Provisional</option><option>Final Settlement</option><option>Credit/Debit Note</option></select></div>
            <div class="col-md-2"><input type="number" step="0.01" id="payExpInp" class="form-control form-control-sm" placeholder="Expected $"></div>
            <div class="col-md-2"><input type="number" step="0.01" id="payRecInp" class="form-control form-control-sm" placeholder="Received $"></div>
            <div class="col-md-2"><input type="text" id="payRefInp" class="form-control form-control-sm" placeholder="SWIFT / PRC #"></div>
            <div class="col-md-1"><button class="btn btn-sm btn-dark w-100" onclick="addDealPayment()">Log</button></div>
          </div>` : ''}`;
      } else if (activeLedgerTab === 'field') {
        const msOpts = (activeDealData.milestones || []).map(x => `<option value="${esc(x.stage_key)}">${x.stage_order}. ${esc(x.stage_title)}</option>`).join('');
        bodyHtml = `
          <p class="small text-muted mb-2">Generate a mobile upload link for mine supervisors or clearing agents—they compress &amp; upload photos to only their assigned stage without seeing buyer names or USD prices.</p>
          <div class="mb-2">
            ${fls.length ? fls.map(fl => {
              const url = `${baseOrigin}/admin/field-upload.php?t=${encodeURIComponent(fl.token)}`;
              return `
                <div class="d-flex justify-content-between align-items-center p-2 border rounded bg-light mb-1 flex-wrap gap-2">
                  <div>
                    <div class="small fw-bold">${esc(fl.assignee_label)} <span class="badge bg-dark font-monospace">${esc(fl.stage_key)}</span> <span class="badge bg-secondary-subtle text-dark">${fl.uploads_count} uploads</span></div>
                    <div class="font-monospace text-muted" style="font-size:0.7rem;">${esc(url)}</div>
                  </div>
                  <div class="d-flex align-items-center gap-1">
                    <button type="button" class="bm-icon-btn" onclick="navigator.clipboard.writeText('${esc(url)}');showVaultAlert('Field upload link copied.')" title="Copy Field Link"><i class="bi bi-clipboard"></i></button>
                    <a href="https://wa.me/?text=${encodeURIComponent('Upload Stage Evidence (' + d.deal_code + '): ' + url)}" target="_blank" class="bm-icon-btn bm-icon-btn--wa" title="Share via WhatsApp"><i class="bi bi-whatsapp"></i></a>
                    <button type="button" class="bm-icon-btn bm-icon-btn--warn" onclick="revokeFieldLink(${fl.id})" title="Revoke Link"><i class="bi bi-x-lg"></i></button>
                  </div>
                </div>`;
            }).join('') : '<div class="small text-muted mb-2">No active field upload links.</div>'}
          </div>
          ${!isSealed ? `
          <div class="row g-2 pt-2 border-top">
            <div class="col-md-5"><input type="text" id="flAssignInp" class="form-control form-control-sm" placeholder="Supervisor / C&amp;F Agent Name"></div>
            <div class="col-md-5"><select id="flStageSel" class="form-select form-select-sm">${msOpts}</select></div>
            <div class="col-md-2"><button class="btn btn-sm btn-dark w-100" onclick="createFieldLink()">Generate</button></div>
          </div>` : ''}`;
      } else {
        bodyHtml = `
          <div style="max-height: 220px; overflow-y: auto;">
            ${logs.length ? logs.map(l => `
              <div class="d-flex justify-content-between border-bottom py-1 small">
                <div><span class="badge bg-light text-dark border me-1">${esc(l.actor_name)} (${esc(l.actor_role)})</span> ${esc(l.summary)}</div>
                <span class="font-monospace text-muted" style="font-size:0.7rem;">${esc(l.created_at)}</span>
              </div>`).join('') : '<div class="small text-muted">No audit records yet.</div>'}
          </div>`;
      }

      card.innerHTML = `
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-bottom pb-3 mb-3">
          <div class="bm-seg-track bm-seg-track--auto">
            <button type="button" class="bm-seg-btn ${activeLedgerTab === 'lots' ? 'active' : ''}" onclick="activeLedgerTab='lots';renderDealOpsLedger()"><i class="bi bi-ship"></i> Partial Vessel Lots (${lots.length})</button>
            ${roleFlags.can_financials ? `<button type="button" class="bm-seg-btn ${activeLedgerTab === 'payments' ? 'active' : ''}" onclick="activeLedgerTab='payments';renderDealOpsLedger()"><i class="bi bi-cash-coin"></i> Payment Tranches (${pays.length})</button>` : ''}
            <button type="button" class="bm-seg-btn ${activeLedgerTab === 'field' ? 'active' : ''}" onclick="activeLedgerTab='field';renderDealOpsLedger()"><i class="bi bi-phone"></i> Field Mobile Links (${fls.length})</button>
            <button type="button" class="bm-seg-btn ${activeLedgerTab === 'audit' ? 'active' : ''}" onclick="activeLedgerTab='audit';renderDealOpsLedger()"><i class="bi bi-journal-text"></i> Audit Trail (${logs.length})</button>
          </div>
        </div>
        ${bodyHtml}`;
    }

    async function addDealLot() {
      const fd = new FormData();
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('action', 'save_lot');
      fd.append('deal_id', activeDealData.deal.id);
      fd.append('lot_code', document.getElementById('lotCodeInp').value);
      fd.append('tonnage_mt', document.getElementById('lotMtInp').value);
      fd.append('vessel_name', document.getElementById('lotVesselInp').value);
      fd.append('bill_of_lading', document.getElementById('lotBlInp').value);
      fd.append('lot_status', document.getElementById('lotStInp').value);
      const res = await fetch('api-deals.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) await loadDealDetail(activeDealData.deal.id, true);
      else showVaultAlert(data.error || 'Error saving lot', 'danger');
    }
    async function deleteLot(lotId) {
      const fd = new FormData(); fd.append('csrf_token', CSRF_TOKEN); fd.append('action', 'delete_lot'); fd.append('lot_id', lotId);
      await fetch('api-deals.php', { method: 'POST', body: fd });
      await loadDealDetail(activeDealData.deal.id, true);
    }
    async function addDealPayment() {
      const fd = new FormData();
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('action', 'save_payment');
      fd.append('deal_id', activeDealData.deal.id);
      fd.append('tranche_label', document.getElementById('payLblInp').value);
      fd.append('tranche_type', document.getElementById('payTypeInp').value);
      fd.append('expected_usd', document.getElementById('payExpInp').value);
      fd.append('received_usd', document.getElementById('payRecInp').value);
      fd.append('payment_ref', document.getElementById('payRefInp').value);
      const res = await fetch('api-deals.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) { await loadDealDetail(activeDealData.deal.id, true); await loadDealsList(activeDealData.deal.id); }
      else showVaultAlert(data.error || 'Error saving payment', 'danger');
    }
    async function deletePayment(payId) {
      const fd = new FormData(); fd.append('csrf_token', CSRF_TOKEN); fd.append('action', 'delete_payment'); fd.append('payment_id', payId);
      await fetch('api-deals.php', { method: 'POST', body: fd });
      await loadDealDetail(activeDealData.deal.id, true);
    }
    async function createFieldLink() {
      const fd = new FormData();
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('action', 'create_field_link');
      fd.append('deal_id', activeDealData.deal.id);
      fd.append('assignee_label', document.getElementById('flAssignInp').value);
      fd.append('stage_key', document.getElementById('flStageSel').value);
      const res = await fetch('api-deals.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) { showVaultAlert('Scoped field upload link created.'); await loadDealDetail(activeDealData.deal.id, true); }
    }
    async function revokeFieldLink(linkId) {
      const fd = new FormData(); fd.append('csrf_token', CSRF_TOKEN); fd.append('action', 'revoke_field_link'); fd.append('link_id', linkId);
      await fetch('api-deals.php', { method: 'POST', body: fd });
      await loadDealDetail(activeDealData.deal.id, true);
    }

    function openFinalizeDealModal() {
      const r = activeDealData.readiness;
      const missing = r.missing_docs || [];
      const unver = r.unverified_docs || [];
      const box = document.getElementById('finalizeChecklistBox');
      const forceWrap = document.getElementById('forceCloseConfirmWrap');
      document.getElementById('chkForceClose').checked = false;

      if (!missing.length && !unver.length) {
        box.innerHTML = '<div class="alert alert-success py-2 small mb-0"><i class="bi bi-check-circle-fill me-1"></i> All mandatory compliance documents are uploaded and verified. Ready to seal!</div>';
        forceWrap.style.display = 'none';
      } else {
        box.innerHTML = `
          <div class="alert alert-warning py-2 small mb-0">
            <strong>Pre-Close Check Warning:</strong>
            ${missing.length ? `<div class="mt-1">Missing docs: ${missing.map(x => esc(x)).join(', ')}</div>` : ''}
            ${unver.length ? `<div class="mt-1">Pending final lock: ${unver.map(x => esc(x)).join(', ')}</div>` : ''}
            ${!roleFlags.can_force_close ? '<div class="mt-2 text-danger fw-bold">Manager Standard Close requires 100% verified docs (ask Owner for Force-Close override).</div>' : ''}
          </div>`;
        forceWrap.style.display = roleFlags.can_force_close ? 'block' : 'none';
      }
      bootstrap.Modal.getOrCreateInstance(document.getElementById('finalizeDealModal')).show();
    }

    async function confirmFinalizeDeal() {
      const force = (roleFlags.can_force_close && document.getElementById('chkForceClose').checked) ? 1 : 0;
      const fd = new FormData();
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('action', 'close_deal');
      fd.append('deal_id', activeDealData.deal.id);
      fd.append('force_close', force);
      fd.append('deduct_stockpile', document.getElementById('chkDeductStock').checked ? 1 : 0);
      fd.append('retire_token', document.getElementById('chkRetireToken').checked ? 1 : 0);
      fd.append('close_note', document.getElementById('finalizeNoteInput').value);

      const res = await fetch('api-deals.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.needs_confirmation) {
        if (roleFlags.can_force_close) {
          document.getElementById('forceCloseConfirmWrap').style.display = 'block';
          alert('Mandatory docs are incomplete. Tick "Override missing/unverified docs" to confirm early seal.');
        } else {
          showVaultAlert('All mandatory compliance documents must be Verified Final before Manager Standard Close.', 'warning');
        }
        return;
      }
      if (data.success) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('finalizeDealModal')).hide();
        showVaultAlert(data.summary || 'Deal sealed cleanly.');
        await loadDealsList(activeDealData.deal.id);
        await loadDealDetail(activeDealData.deal.id, true);
      } else {
        showVaultAlert(data.error || 'Close deal failed', 'danger');
      }
    }

    async function reopenSealedDeal() {
      const reason = prompt('Owner audit reason for reopening this sealed deal:');
      if (!reason) return;
      const fd = new FormData();
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('action', 'reopen_deal');
      fd.append('deal_id', activeDealData.deal.id);
      fd.append('reason', reason);
      const res = await fetch('api-deals.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        showVaultAlert('Deal reopened for amendments.');
        await loadDealsList(activeDealData.deal.id);
        await loadDealDetail(activeDealData.deal.id, true);
      }
    }

    document.addEventListener('DOMContentLoaded', async () => {
      await loadDealsList(INITIAL_DEAL_ID || null);
      if (FROM_INQUIRY_ID > 0) {
        const res = await fetch('api-deals.php?action=from_inquiry&inquiry_id=' + FROM_INQUIRY_ID);
        const data = await res.json();
        if (data.success && data.inquiry) {
          openNewDealModal(Object.assign({}, data.inquiry, { num_mt: data.num_mt }));
        }
      }
    });
  </script>
</body>
</html>
