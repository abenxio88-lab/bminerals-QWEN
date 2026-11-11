<?php
/**
 * Balochistan Minerals - Owner Master Control, Team Oversight, Approvals & 2FA Security
 */

require_once __DIR__ . '/auth.php';
requireAuth();

if (!hasPermission('manage_team') && !hasPermission('approve_emails')) {
    requirePermission('manage_team');
}

$user = getLoggedInUser();
$pdo  = getDB();

$newLeads = 0;
$totalProspects = 0;
$pendingReviews = 0;
if ($pdo !== null) {
    ensureTeamSecuritySchema($pdo);
    try { $newLeads = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'New'")->fetchColumn(); } catch (Exception $e) {}
    try { $totalProspects = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects")->fetchColumn(); } catch (Exception $e) {}
    try { $pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'Pending'")->fetchColumn(); } catch (Exception $e) {}
}

$canManageTeam    = hasPermission('manage_team');
$canApproveEmails = hasPermission('approve_emails');
$csrfToken        = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Team Oversight, Approvals &amp; Security — Balochistan Minerals</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="admin-layout.css?v=<?= filemtime(__DIR__ . '/admin-layout.css') ?>">
  <style>
    .team-card {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 10px;
      padding: 20px 22px;
      box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
    }
    .team-tabs {
      display: inline-flex;
      flex-wrap: wrap;
      gap: 4px;
      padding: 4px;
      background: #F1F5F9;
      border: 1px solid #E2E8F0;
      border-radius: 9px;
    }
    .team-tab-btn {
      border: 1px solid transparent;
      background: transparent;
      color: #475569;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.78rem;
      font-weight: 600;
      padding: 7px 14px;
      border-radius: 6px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.15s ease;
    }
    .team-tab-btn:hover { color: #0F172A; }
    .team-tab-btn.active {
      background: #FFFFFF;
      color: #0F172A;
      border-color: #CBD5E1;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.07);
    }
    .live-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      display: inline-block;
      margin-right: 5px;
    }
    .live-dot.online { background: #10B981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18); }
    .live-dot.idle   { background: #F59E0B; }
    .live-dot.stale  { background: #94A3B8; }
    .tier-badge {
      font-family: 'Montserrat', sans-serif;
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      padding: 3px 8px;
      border-radius: 5px;
      display: inline-block;
    }
    .tier-Owner   { background: #0F172A; color: #FFFFFF; }
    .tier-Manager { background: #FFF7ED; color: #C2410C; border: 1px solid #FDBA74; }
    .tier-Junior  { background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1; }
    .tier-Yard    { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
    .secret-box {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 1.05rem;
      font-weight: 700;
      letter-spacing: 0.16em;
      background: #F8FAFC;
      border: 1px dashed #94A3B8;
      padding: 12px 16px;
      border-radius: 8px;
      color: #0F172A;
      text-align: center;
      user-select: all;
    }
    .email-preview-frame {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      padding: 14px;
      max-height: 300px;
      overflow-y: auto;
      font-size: 0.85rem;
    }
  </style>
</head>
<body>
  <div class="admin-wrapper">
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
        <a href="deals.php" class="sidebar-link">
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
        <a href="team.php" class="sidebar-link active">
          <div><i class="bi bi-shield-lock-fill"></i> Team &amp; Security</div>
          <span class="badge bg-dark font-monospace js-team-approval-badge" style="font-size:0.68rem;display:none;">0</span>
        </a>
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
          <button type="button" id="sidebarToggle" class="btn-sidebar-toggle" aria-label="Menu">
            <i class="bi bi-list"></i>
          </button>
          <div>
            <h1 class="topbar-title">Team Oversight, Approvals &amp; Access Control</h1>
            <div class="topbar-subtitle">Live staff sessions, Maker-Checker outbound approvals, Authenticator 2FA &amp; company audit ledger</div>
          </div>
        </div>
        <div class="topbar-actions">
          <button type="button" id="btnRefreshTeam" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
          <?php if ($canManageTeam): ?>
            <button type="button" id="btnOpenCreateStaff" class="btn btn-sm btn-dark fw-semibold"><i class="bi bi-person-plus-fill me-1"></i> New Staff Account</button>
            <button type="button" id="btnPanicSignoutAll" class="btn btn-sm btn-outline-danger fw-semibold"><i class="bi bi-shield-exclamation me-1"></i> Kick All Other Sessions</button>
          <?php endif; ?>
        </div>
      </header>

      <div class="main-content">
        <div id="teamFlashContainer" class="mb-3"></div>

        <!-- KPI Strip -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-3">
            <div class="kpi-card h-100">
              <div class="kpi-title">Pending Email Approvals</div>
              <div class="kpi-value" id="kpiPendingApprovals">0</div>
              <div class="kpi-meta text-muted">Junior drafts awaiting review</div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="kpi-card h-100">
              <div class="kpi-title">Active Login Sessions</div>
              <div class="kpi-value text-success" id="kpiLiveSessions">—</div>
              <div class="kpi-meta text-muted" id="kpiLiveSessionsSub">Tracked browser devices</div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="kpi-card h-100">
              <div class="kpi-title">Team Roster &amp; 2FA</div>
              <div class="kpi-value" id="kpiActiveStaff">—</div>
              <div class="kpi-meta text-muted" id="kpi2faSub">Authenticator-secured accounts</div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="kpi-card h-100">
              <div class="kpi-title">Avg First Response SLA</div>
              <div class="kpi-value" id="kpiAvgSla">—</div>
              <div class="kpi-meta text-muted">Lead intake to first dispatch</div>
            </div>
          </div>
        </div>

        <!-- Tabs -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
          <div class="team-tabs" id="teamTabTrack">
            <button type="button" class="team-tab-btn active" data-tab="approvals"><i class="bi bi-envelope-check"></i> Reply Approvals <span class="badge bg-dark ms-1" id="tabBadgeApprovals">0</span></button>
            <?php if ($canManageTeam): ?>
              <button type="button" class="team-tab-btn" data-tab="sessions"><i class="bi bi-laptop"></i> Live Sessions <span class="badge bg-success ms-1" id="tabBadgeSessions">0</span></button>
              <button type="button" class="team-tab-btn" data-tab="staff"><i class="bi bi-people-fill"></i> Staff Accounts &amp; Roles</button>
              <button type="button" class="team-tab-btn" data-tab="audit"><i class="bi bi-journal-text"></i> Login &amp; Audit Ledger</button>
            <?php endif; ?>
            <button type="button" class="team-tab-btn" data-tab="security"><i class="bi bi-key-fill"></i> My 2FA &amp; Team SLA</button>
          </div>
          <div class="small text-muted" id="lastSyncedLabel">Live sync active</div>
        </div>

        <!-- TAB: Maker-Checker Email Approvals -->
        <div id="pane-approvals" class="team-pane">
          <div class="team-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
              <div>
                <h2 class="h6 fw-bold mb-0">Maker-Checker Outbound Approval Queue</h2>
                <div class="small text-muted">Replies drafted by Junior staff wait here until reviewed, optionally edited, and approved by Owner or Manager.</div>
              </div>
            </div>
            <div id="approvalsListContainer">
              <div class="text-center py-4 text-muted small">Loading approval queue…</div>
            </div>
          </div>
        </div>

        <?php if ($canManageTeam): ?>
        <!-- TAB: Live Sessions & Devices -->
        <div id="pane-sessions" class="team-pane d-none">
          <div class="team-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
              <div>
                <h2 class="h6 fw-bold mb-0">Live Online Staff &amp; Active Browser Sessions (Last 24h)</h2>
                <div class="small text-muted">See who is signed in, their IP address, current screen, idle duration, and force sign out any device instantly.</div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle small mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Status</th>
                    <th>Staff Member</th>
                    <th>Role</th>
                    <th>Current Page</th>
                    <th>IP Address &amp; Device</th>
                    <th>Idle / Last Seen</th>
                    <th class="text-end">Control</th>
                  </tr>
                </thead>
                <tbody id="sessionsTableBody"></tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB: Staff Roster & Role Matrix -->
        <div id="pane-staff" class="team-pane d-none">
          <div class="team-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h2 class="h6 fw-bold mb-0">Executive &amp; Operations Staff Accounts</h2>
                <div class="small text-muted">Owner retains sole control over Analytics, Pricing, Buyer CSV Export, Mail Settings, Force Close, Backups &amp; Hard Deletes.</div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle small mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Account</th>
                    <th>Role Tier</th>
                    <th>Status</th>
                    <th>Direct Email</th>
                    <th>2FA</th>
                    <th>Workload (Leads / Deals)</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody id="staffTableBody"></tbody>
              </table>
            </div>
          </div>

          <div class="team-card">
            <h3 class="h6 fw-bold mb-2">Role Authority Matrix Reference</h3>
            <div class="table-responsive">
              <table class="table table-bordered table-sm small mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Capability</th>
                    <th>Owner</th>
                    <th>Manager</th>
                    <th>Junior</th>
                    <th>Yard</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td>Inbound RFQs &amp; CRM Dossier</td><td class="text-success fw-semibold">Full</td><td class="text-success fw-semibold">Full</td><td>Assigned / Unassigned</td><td class="text-muted">Blocked</td></tr>
                  <tr><td>Outbound Buyer Emails</td><td class="text-success fw-semibold">Direct Send</td><td class="text-success fw-semibold">Direct Send + Approver</td><td class="text-warning fw-semibold">Queued for Approval (Default)</td><td class="text-muted">Blocked</td></tr>
                  <tr><td>Export Deals &amp; Evidence Vault</td><td class="text-success fw-semibold">Full + Force Close + Reopen</td><td class="text-success fw-semibold">Verify &amp; Standard Close</td><td>Upload Staging Photos / Non-Price Fields</td><td>Photos &amp; Weight Proof Only</td></tr>
                  <tr><td>FOB Prices, Margins &amp; Payments Ledger</td><td class="text-success fw-semibold">Visible</td><td class="text-success fw-semibold">Visible</td><td class="text-danger fw-semibold">Hidden</td><td class="text-danger fw-semibold">Hidden</td></tr>
                  <tr><td>Analytics, Spot Prices, CSV Export, Backups, SMTP Config, Hard Delete</td><td class="text-success fw-semibold">Owner Only</td><td class="text-danger fw-semibold">Blocked</td><td class="text-danger fw-semibold">Blocked</td><td class="text-danger fw-semibold">Blocked</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB: Login Events & Audit Ledger -->
        <div id="pane-audit" class="team-pane d-none">
          <div class="row g-4">
            <div class="col-12 col-xl-5">
              <div class="team-card h-100">
                <h2 class="h6 fw-bold mb-1">Recent Login &amp; Security Events</h2>
                <div class="small text-muted mb-3">All logins, failed passwords, lockouts, 2FA failures, and session revocations.</div>
                <div class="table-responsive" style="max-height:520px;overflow-y:auto;">
                  <table class="table table-sm align-middle small mb-0">
                    <thead class="table-light"><tr><th>Time</th><th>User</th><th>Event</th><th>IP / Device</th></tr></thead>
                    <tbody id="loginEventsBody"></tbody>
                  </table>
                </div>
              </div>
            </div>
            <div class="col-12 col-xl-7">
              <div class="team-card h-100">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                  <div>
                    <h2 class="h6 fw-bold mb-0">Immutable Company Audit Trail</h2>
                    <div class="small text-muted">Every lead assignment, approval, price change, stage edit, close &amp; block logged forever.</div>
                  </div>
                  <div class="d-flex gap-2">
                    <select id="auditModuleFilter" class="form-select form-select-sm" style="width:auto;">
                      <option value="">All Modules</option>
                      <option value="team">Team</option>
                      <option value="email">Email</option>
                      <option value="deal">Deals</option>
                      <option value="rfq">RFQs</option>
                      <option value="prospect">Prospects</option>
                      <option value="stockpile">Stockpiles</option>
                      <option value="price">Spot Prices</option>
                      <option value="backup">Backups</option>
                      <option value="security">Security</option>
                    </select>
                    <input type="text" id="auditSearchInput" class="form-control form-control-sm" placeholder="Search log…" style="width:150px;">
                  </div>
                </div>
                <div class="table-responsive" style="max-height:520px;overflow-y:auto;">
                  <table class="table table-sm align-middle small mb-0">
                    <thead class="table-light"><tr><th>When</th><th>Actor</th><th>Module</th><th>Action Summary</th></tr></thead>
                    <tbody id="auditLogsBody"></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- TAB: Personal 2FA & SLA Scorecard -->
        <div id="pane-security" class="team-pane d-none">
          <div class="row g-4">
            <div class="col-12 col-lg-5">
              <div class="team-card h-100">
                <h2 class="h6 fw-bold mb-1">Two-Factor Authentication (Authenticator App)</h2>
                <div class="small text-muted mb-3">Protect your account with free Google Authenticator, Microsoft Authenticator, or Authy TOTP 6-digit codes.</div>
                <div id="own2faContainer">Loading 2FA status…</div>
              </div>
            </div>
            <div class="col-12 col-lg-7">
              <div class="team-card h-100">
                <h2 class="h6 fw-bold mb-1">Team Lead Responsiveness &amp; SLA Scorecard</h2>
                <div class="small text-muted mb-3">Track assigned RFQs, active vessel shipments, outbound emails dispatched, and average response speed per staff member.</div>
                <div class="table-responsive">
                  <table class="table table-sm align-middle small mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Staff</th>
                        <th>Role</th>
                        <th>Assigned Leads</th>
                        <th>Active Deals</th>
                        <th>Emails Sent</th>
                        <th>Avg First Reply</th>
                      </tr>
                    </thead>
                    <tbody id="slaScorecardBody"></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Review & Approve Email Modal -->
  <div class="modal fade" id="draftReviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Review Queued Outbound Reply</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="reviewDraftId">
          <div class="row g-2 mb-3 small">
            <div class="col-md-6"><strong>From Mailbox:</strong> <span id="reviewFromEmail">—</span></div>
            <div class="col-md-6"><strong>Recipient:</strong> <span id="reviewToEmail">—</span></div>
            <div class="col-md-6"><strong>Drafted By:</strong> <span id="reviewAuthor">—</span></div>
            <div class="col-md-6"><strong>Linked Buyer:</strong> <span id="reviewBuyer">—</span></div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Subject Line (Editable)</label>
            <input type="text" id="reviewSubjectInput" class="form-control form-control-sm">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Plain-Text Reply Body (Editable before dispatch)</label>
            <textarea id="reviewBodyInput" class="form-control form-control-sm" rows="6"></textarea>
          </div>
          <div class="mb-2">
            <div class="small fw-semibold mb-1">Attachments (<span id="reviewAttCount">0</span>)</div>
            <div id="reviewAttachmentsList" class="d-flex flex-wrap gap-2"></div>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-between">
          <button type="button" id="btnRejectDraft" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i> Reject &amp; Note Feedback</button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" id="btnApproveSendDraft" class="btn btn-sm btn-success fw-semibold"><i class="bi bi-send-check me-1"></i> Approve &amp; Send Now</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($canManageTeam): ?>
  <!-- Create / Edit Staff Modal -->
  <div class="modal fade" id="staffFormModal" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content" id="staffModalForm">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="staffModalTitle">New Staff Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="staffFormMode" value="create">
          <input type="hidden" id="staffFormUserId" value="0">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Full Name</label>
            <input type="text" id="staffFullName" class="form-control form-control-sm" required placeholder="e.g. Tariq Mahmood">
          </div>
          <div class="mb-3" id="staffUsernameGroup">
            <label class="form-label small fw-semibold">Login Username</label>
            <input type="text" id="staffUsername" class="form-control form-control-sm" required placeholder="e.g. tariq_mgr">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Work Email</label>
            <input type="email" id="staffEmail" class="form-control form-control-sm" placeholder="sales@balochistanminerals.com">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Access Role Tier</label>
            <select id="staffRole" class="form-select form-select-sm">
              <option value="Manager">Manager (Operations, Direct Email, Approvals &amp; Standard Close)</option>
              <option value="Junior" selected>Junior (RFQs, Draft Replies Queued for Approval, Non-Financial Deal Upload)</option>
              <option value="Yard">Yard / Port Supervisor (Field Weight, Photos &amp; Stage Checklists Only)</option>
              <option value="Owner">Co-Owner / Master Admin (Unrestricted Full Access)</option>
            </select>
          </div>
          <div class="mb-3" id="staffPasswordGroup">
            <label class="form-label small fw-semibold">Initial Password (min 8 chars)</label>
            <input type="password" id="staffPassword" class="form-control form-control-sm" placeholder="Strong initial password">
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="staffDirectEmailToggle">
            <label class="form-check-label small" for="staffDirectEmailToggle">Allow vetted Junior to send outbound emails directly (skip Maker-Checker queue)</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-dark fw-semibold" id="btnSaveStaffModal">Save Account</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="admin-layout.js?v=<?= filemtime(__DIR__ . '/admin-layout.js') ?>"></script>
  <script>
    (function () {
      'use strict';
      const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
      const CAN_MANAGE = <?= json_encode($canManageTeam) ?>;
      let latestState = null;

      function esc(str) {
        return String(str ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
      }

      function showFlash(msg, type = 'success') {
        const host = document.getElementById('teamFlashContainer');
        if (!host) return;
        host.innerHTML = `<div class="alert alert-${esc(type)} alert-dismissible fade show py-2 px-3 small mb-0">${esc(msg)}<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button></div>`;
      }

      function fmtIdle(secs) {
        const n = Number(secs || 0);
        if (n < 90) return '<span class="live-dot online"></span><span class="text-success fw-semibold">Online now</span>';
        if (n < 900) return `<span class="live-dot idle"></span>${Math.round(n / 60)}m idle`;
        return `<span class="live-dot stale"></span>${Math.round(n / 60)}m ago`;
      }

      async function postTeamAction(payload) {
        const res = await fetch('api-team.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(Object.assign({ csrf_token: CSRF_TOKEN }, payload))
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
          throw new Error(data.error || 'Operation failed');
        }
        return data;
      }

      function switchTab(tabName) {
        document.querySelectorAll('.team-tab-btn').forEach((b) => {
          b.classList.toggle('active', b.dataset.tab === tabName);
        });
        document.querySelectorAll('.team-pane').forEach((p) => {
          p.classList.toggle('d-none', p.id !== 'pane-' + tabName);
        });
      }

      document.getElementById('teamTabTrack').addEventListener('click', (e) => {
        const btn = e.target.closest('.team-tab-btn');
        if (!btn) return;
        switchTab(btn.dataset.tab);
      });

      function renderApprovals(drafts) {
        const box = document.getElementById('approvalsListContainer');
        document.getElementById('kpiPendingApprovals').textContent = drafts.length;
        document.getElementById('tabBadgeApprovals').textContent = drafts.length;

        if (!drafts.length) {
          box.innerHTML = '<div class="text-center py-4 text-muted small"><i class="bi bi-check2-circle fs-4 d-block mb-1 text-success"></i>No Junior reply drafts waiting for approval right now.</div>';
          return;
        }

        box.innerHTML = drafts.map((d) => `
          <div class="border rounded p-3 mb-2 bg-light">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
              <div>
                <div class="fw-bold text-dark">${esc(d.subject)}</div>
                <div class="small text-muted">
                  To: <strong>${esc(d.to_email)}</strong>
                  ${d.buyer_company ? ` (${esc(d.buyer_company)})` : ''}
                  • Drafted by <span class="badge bg-secondary">${esc(d.author_name || d.author_username || 'Junior')}</span>
                  • <span class="font-monospace">${esc(d.created_at)}</span>
                </div>
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-dark js-review-draft" data-id="${Number(d.id)}"><i class="bi bi-eye me-1"></i>Review / Edit</button>
                <button type="button" class="btn btn-sm btn-success fw-semibold js-quick-approve" data-id="${Number(d.id)}"><i class="bi bi-send-check me-1"></i>Approve &amp; Send</button>
              </div>
            </div>
            <div class="small text-secondary mt-2 bg-white border rounded p-2" style="white-space:pre-line;max-height:95px;overflow:hidden;">${esc((d.body_text || '').slice(0, 320))}</div>
          </div>
        `).join('');
      }

      function renderSessions(sessions) {
        const tbody = document.getElementById('sessionsTableBody');
        const activeNow = sessions.filter((s) => Number(s.idle_seconds || 9999) < 90).length;
        document.getElementById('kpiLiveSessions').textContent = activeNow + ' Online';
        document.getElementById('kpiLiveSessionsSub').textContent = sessions.length + ' active device(s) in 24h';
        const tabBadge = document.getElementById('tabBadgeSessions');
        if (tabBadge) tabBadge.textContent = sessions.length;

        if (!tbody) return;
        if (!sessions.length) {
          tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No active sessions logged yet.</td></tr>';
          return;
        }
        tbody.innerHTML = sessions.map((s) => `
          <tr>
            <td>${fmtIdle(s.idle_seconds)}</td>
            <td><strong>${esc(s.full_name)}</strong><div class="text-muted font-monospace" style="font-size:0.72rem;">@${esc(s.username)}</div></td>
            <td><span class="tier-badge tier-${esc(s.role)}">${esc(s.role)}</span></td>
            <td><code>${esc(s.current_page || 'dashboard.php')}</code></td>
            <td><div class="font-monospace">${esc(s.ip_address)}</div><div class="text-muted" style="font-size:0.74rem;">${esc(s.device_label)}</div></td>
            <td><div style="font-size:0.76rem;">${esc(s.last_seen_at)}</div></td>
            <td class="text-end">
              ${s.is_current_device
                ? '<span class="badge bg-dark">This Device</span>'
                : `<button type="button" class="btn btn-sm btn-outline-danger js-kick-session" data-sid="${esc(s.session_id)}">Force Sign Out</button>`}
            </td>
          </tr>
        `).join('');
      }

      function renderStaff(staff) {
        const tbody = document.getElementById('staffTableBody');
        const slaBody = document.getElementById('slaScorecardBody');
        const activeCount = staff.filter((u) => Number(u.is_active) === 1).length;
        const totpCount = staff.filter((u) => Number(u.totp_enabled) === 1).length;
        document.getElementById('kpiActiveStaff').textContent = activeCount + ' / ' + staff.length;
        document.getElementById('kpi2faSub').textContent = totpCount + ' of ' + staff.length + ' enrolled in 2FA';

        const validSlas = staff.map((u) => Number(u.avg_response_mins)).filter((n) => !Number.isNaN(n) && n > 0);
        document.getElementById('kpiAvgSla').textContent = validSlas.length
          ? Math.round(validSlas.reduce((a, b) => a + b, 0) / validSlas.length) + ' mins'
          : 'Ready';

        if (slaBody) {
          slaBody.innerHTML = staff.map((u) => `
            <tr>
              <td><strong>${esc(u.full_name)}</strong></td>
              <td><span class="tier-badge tier-${esc(u.role)}">${esc(u.role)}</span></td>
              <td>${Number(u.assigned_leads || 0)}</td>
              <td>${Number(u.active_deals || 0)}</td>
              <td>${Number(u.emails_sent || 0)} ${Number(u.emails_pending || 0) > 0 ? `<span class="text-warning small">(${Number(u.emails_pending)} pending)</span>` : ''}</td>
              <td class="fw-semibold">${u.avg_response_mins !== null ? esc(u.avg_response_mins) + 'm' : '—'}</td>
            </tr>
          `).join('');
        }

        if (!tbody) return;
        tbody.innerHTML = staff.map((u) => {
          const isMe = latestState && latestState.me && Number(latestState.me.id) === Number(u.id);
          return `
            <tr class="${Number(u.is_active) === 1 ? '' : 'table-danger'}">
              <td><strong>${esc(u.full_name)}</strong><div class="text-muted font-monospace" style="font-size:0.74rem;">@${esc(u.username)} • ${esc(u.email || 'No email')}</div></td>
              <td><span class="tier-badge tier-${esc(u.role)}">${esc(u.role)}</span></td>
              <td>${Number(u.is_active) === 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Frozen</span>'}</td>
              <td>${u.role === 'Junior' ? (Number(u.can_send_direct_email) === 1 ? '<span class="badge bg-info text-dark">Direct Allowed</span>' : '<span class="badge bg-warning text-dark">Approval Required</span>') : (u.role === 'Yard' ? '<span class="text-muted">Blocked</span>' : '<span class="text-success">Yes</span>')}</td>
              <td>${Number(u.totp_enabled) === 1 ? '<span class="badge bg-dark">Enabled</span>' : '<span class="text-muted">Off</span>'}</td>
              <td>${Number(u.assigned_leads || 0)} RFQs • ${Number(u.active_deals || 0)} Deals</td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-secondary js-edit-staff" data-id="${Number(u.id)}" title="Edit Role / Details"><i class="bi bi-pencil"></i></button>
                  <button type="button" class="btn btn-outline-secondary js-reset-pass" data-id="${Number(u.id)}" title="Reset Password"><i class="bi bi-key"></i></button>
                  ${Number(u.totp_enabled) === 1 ? `<button type="button" class="btn btn-outline-warning js-reset-2fa" data-id="${Number(u.id)}" title="Reset 2FA Lockout"><i class="bi bi-shield-x"></i></button>` : ''}
                  ${!isMe ? `<button type="button" class="btn btn-outline-${Number(u.is_active) === 1 ? 'danger' : 'success'} js-freeze-staff" data-id="${Number(u.id)}" title="${Number(u.is_active) === 1 ? 'Freeze Account' : 'Unfreeze Account'}"><i class="bi bi-${Number(u.is_active) === 1 ? 'pause-circle' : 'play-circle'}"></i></button>` : ''}
                </div>
              </td>
            </tr>
          `;
        }).join('');
      }

      function renderAuditAndLogins(events, logs) {
        const evBody = document.getElementById('loginEventsBody');
        if (evBody) {
          evBody.innerHTML = events.length ? events.map((ev) => {
            const cls = ev.event_type === 'Success' ? 'bg-success' : (ev.event_type === 'Logout' ? 'bg-secondary' : 'bg-danger');
            return `
              <tr>
                <td class="font-monospace" style="font-size:0.7rem;">${esc(ev.created_at)}</td>
                <td><strong>${esc(ev.username_tried)}</strong></td>
                <td><span class="badge ${cls}">${esc(ev.event_type)}</span></td>
                <td><div class="font-monospace" style="font-size:0.72rem;">${esc(ev.ip_address)}</div><div class="text-muted" style="font-size:0.68rem;">${esc(ev.details || ev.device_label)}</div></td>
              </tr>
            `;
          }).join('') : '<tr><td colspan="4" class="text-center py-3 text-muted">No login events recorded yet.</td></tr>';
        }

        const audBody = document.getElementById('auditLogsBody');
        if (audBody) {
          audBody.innerHTML = logs.length ? logs.map((l) => `
            <tr>
              <td class="font-monospace" style="font-size:0.7rem;">${esc(l.created_at)}</td>
              <td><strong>${esc(l.actor_name)}</strong> <span class="badge bg-light text-dark border">${esc(l.actor_role)}</span></td>
              <td><code>${esc(l.module)}</code></td>
              <td>${esc(l.summary)}</td>
            </tr>
          `).join('') : '<tr><td colspan="4" class="text-center py-3 text-muted">No audit logs matching filter.</td></tr>';
        }
      }

      function renderOwn2fa(me) {
        const host = document.getElementById('own2faContainer');
        if (!host || !me) return;
        const enabled = Number(me.totp_enabled || 0) === 1;
        if (enabled) {
          host.innerHTML = `
            <div class="alert alert-success py-2 px-3 small mb-3"><i class="bi bi-shield-fill-check me-1"></i> Authenticator 2FA is <strong>ENABLED</strong> on <strong>@${esc(me.username)}</strong>.</div>
            <div class="input-group input-group-sm">
              <input type="password" id="disable2faPassword" class="form-control" placeholder="Confirm current password to disable">
              <button type="button" id="btnDisableOwn2fa" class="btn btn-outline-danger">Disable 2FA</button>
            </div>
          `;
        } else {
          host.innerHTML = `
            <div class="alert alert-warning py-2 px-3 small mb-3"><i class="bi bi-shield-exclamation me-1"></i> Authenticator 2FA is currently <strong>OFF</strong> for <strong>@${esc(me.username)}</strong>.</div>
            <button type="button" id="btnStart2faSetup" class="btn btn-sm btn-dark fw-semibold"><i class="bi bi-qr-code me-1"></i> Generate Authenticator Secret Key</button>
            <div id="setup2faPanel" class="mt-3 d-none">
              <div class="small fw-semibold mb-1">1. Open Google / Microsoft Authenticator → Enter Setup Key (Time-Based):</div>
              <div class="secret-box mb-2" id="setup2faSecretText"></div>
              <div class="d-flex gap-2 mb-3">
                <button type="button" id="btnCopy2faSecret" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clipboard me-1"></i> Copy Setup Key</button>
                <button type="button" id="btnCopy2faUri" class="btn btn-sm btn-outline-secondary"><i class="bi bi-link-45deg me-1"></i> Copy otpauth:// URI</button>
              </div>
              <div class="small fw-semibold mb-1">2. Enter the 6-digit code shown on your phone to activate:</div>
              <div class="input-group input-group-sm">
                <input type="text" id="confirm2faCodeInput" maxlength="6" inputmode="numeric" class="form-control font-monospace" placeholder="000000">
                <button type="button" id="btnConfirm2faSetup" class="btn btn-success fw-semibold">Verify &amp; Activate 2FA</button>
              </div>
            </div>
          `;
        }
      }

      async function loadTeamState() {
        const mod = document.getElementById('auditModuleFilter')?.value || '';
        const q   = document.getElementById('auditSearchInput')?.value || '';
        const url = `api-team.php?module=${encodeURIComponent(mod)}&q=${encodeURIComponent(q)}`;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
        if (res.status === 401) {
          window.location.href = 'login.php?revoked=revoked';
          return;
        }
        const data = await res.json();
        if (!data.success) return;
        latestState = data;

        renderApprovals(data.pending_drafts || []);
        renderStaff(data.sla_stats || []);
        renderOwn2fa(data.me);
        if (CAN_MANAGE) {
          renderSessions(data.sessions || []);
          renderAuditAndLogins(data.login_events || [], data.audit_logs || []);
        }
        document.getElementById('lastSyncedLabel').textContent = 'Updated ' + new Date().toLocaleTimeString();
      }

      // Approvals Click Handlers
      document.getElementById('approvalsListContainer').addEventListener('click', async (e) => {
        const reviewBtn = e.target.closest('.js-review-draft');
        const approveBtn = e.target.closest('.js-quick-approve');
        if (reviewBtn && latestState) {
          const id = Number(reviewBtn.dataset.id);
          const draft = (latestState.pending_drafts || []).find((x) => Number(x.id) === id);
          if (!draft) return;
          document.getElementById('reviewDraftId').value = id;
          document.getElementById('reviewFromEmail').textContent = draft.from_email;
          document.getElementById('reviewToEmail').textContent = draft.to_email;
          document.getElementById('reviewAuthor').textContent = draft.author_name || draft.author_username || 'Junior';
          document.getElementById('reviewBuyer').textContent = draft.buyer_company || draft.buyer_name || 'General Outbound';
          document.getElementById('reviewSubjectInput').value = draft.subject || '';
          document.getElementById('reviewBodyInput').value = draft.body_text || '';
          const atts = draft.attachments_list || [];
          document.getElementById('reviewAttCount').textContent = atts.length;
          document.getElementById('reviewAttachmentsList').innerHTML = atts.length
            ? atts.map((a) => `<a href="${esc(a.download_url)}" target="_blank" class="badge bg-light text-dark border text-decoration-none p-2"><i class="bi bi-paperclip me-1"></i>${esc(a.name)}</a>`).join('')
            : '<span class="small text-muted">No attachments</span>';
          bootstrap.Modal.getOrCreateInstance(document.getElementById('draftReviewModal')).show();
        } else if (approveBtn) {
          const id = Number(approveBtn.dataset.id);
          approveBtn.disabled = true;
          try {
            const res = await postTeamAction({ action: 'approve_email', email_id: id });
            showFlash(res.message, 'success');
            await loadTeamState();
          } catch (err) {
            showFlash(err.message, 'danger');
            approveBtn.disabled = false;
          }
        }
      });

      document.getElementById('btnApproveSendDraft').addEventListener('click', async () => {
        const id = Number(document.getElementById('reviewDraftId').value);
        try {
          const res = await postTeamAction({
            action: 'approve_email',
            email_id: id,
            subject: document.getElementById('reviewSubjectInput').value,
            body_text: document.getElementById('reviewBodyInput').value
          });
          bootstrap.Modal.getOrCreateInstance(document.getElementById('draftReviewModal')).hide();
          showFlash(res.message, 'success');
          await loadTeamState();
        } catch (err) {
          showFlash(err.message, 'danger');
        }
      });

      document.getElementById('btnRejectDraft').addEventListener('click', async () => {
        const id = Number(document.getElementById('reviewDraftId').value);
        const reason = prompt('Optional reason to note on Buyer Dossier for Junior:', 'Please adjust pricing/wording before dispatch.');
        if (reason === null) return;
        try {
          const res = await postTeamAction({ action: 'reject_email', email_id: id, reason });
          bootstrap.Modal.getOrCreateInstance(document.getElementById('draftReviewModal')).hide();
          showFlash(res.message, 'warning');
          await loadTeamState();
        } catch (err) {
          showFlash(err.message, 'danger');
        }
      });

      // Personal 2FA Click Handlers
      let pending2faUri = '';
      document.getElementById('own2faContainer').addEventListener('click', async (e) => {
        if (e.target.closest('#btnStart2faSetup')) {
          try {
            const data = await postTeamAction({ action: 'begin_2fa_setup' });
            document.getElementById('setup2faSecretText').textContent = data.secret;
            pending2faUri = data.otpauth;
            document.getElementById('setup2faPanel').classList.remove('d-none');
          } catch (err) {
            showFlash(err.message, 'danger');
          }
        } else if (e.target.closest('#btnCopy2faSecret')) {
          navigator.clipboard.writeText(document.getElementById('setup2faSecretText').textContent.trim());
          showFlash('Copied 2FA setup key cleanly.');
        } else if (e.target.closest('#btnCopy2faUri')) {
          navigator.clipboard.writeText(pending2faUri);
          showFlash('Copied otpauth:// URI.');
        } else if (e.target.closest('#btnConfirm2faSetup')) {
          const code = document.getElementById('confirm2faCodeInput').value.trim();
          try {
            const data = await postTeamAction({ action: 'confirm_2fa_setup', totp_code: code });
            showFlash(data.message, 'success');
            await loadTeamState();
          } catch (err) {
            showFlash(err.message, 'danger');
          }
        } else if (e.target.closest('#btnDisableOwn2fa')) {
          const pwd = document.getElementById('disable2faPassword').value;
          try {
            const data = await postTeamAction({ action: 'disable_own_2fa', confirm_password: pwd });
            showFlash(data.message, 'warning');
            await loadTeamState();
          } catch (err) {
            showFlash(err.message, 'danger');
          }
        }
      });

      // Owner-Only Controls
      if (CAN_MANAGE) {
        const staffModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('staffFormModal'));

        document.getElementById('btnOpenCreateStaff').addEventListener('click', () => {
          document.getElementById('staffModalTitle').textContent = 'Create New Staff Account';
          document.getElementById('staffFormMode').value = 'create';
          document.getElementById('staffFormUserId').value = '0';
          document.getElementById('staffFullName').value = '';
          document.getElementById('staffUsername').value = '';
          document.getElementById('staffEmail').value = '';
          document.getElementById('staffRole').value = 'Junior';
          document.getElementById('staffPassword').value = '';
          document.getElementById('staffPassword').required = true;
          document.getElementById('staffUsernameGroup').classList.remove('d-none');
          document.getElementById('staffPasswordGroup').classList.remove('d-none');
          document.getElementById('staffDirectEmailToggle').checked = false;
          staffModal.show();
        });

        document.getElementById('staffModalForm').addEventListener('submit', async (e) => {
          e.preventDefault();
          const mode = document.getElementById('staffFormMode').value;
          const payload = {
            action: mode === 'create' ? 'create_staff' : 'update_staff',
            user_id: Number(document.getElementById('staffFormUserId').value),
            full_name: document.getElementById('staffFullName').value,
            username: document.getElementById('staffUsername').value,
            email: document.getElementById('staffEmail').value,
            role: document.getElementById('staffRole').value,
            password: document.getElementById('staffPassword').value,
            can_send_direct_email: document.getElementById('staffDirectEmailToggle').checked ? 1 : 0
          };
          try {
            const res = await postTeamAction(payload);
            staffModal.hide();
            showFlash(res.message, 'success');
            await loadTeamState();
          } catch (err) {
            showFlash(err.message, 'danger');
          }
        });

        document.getElementById('staffTableBody').addEventListener('click', async (e) => {
          const editBtn = e.target.closest('.js-edit-staff');
          const passBtn = e.target.closest('.js-reset-pass');
          const twoFaBtn = e.target.closest('.js-reset-2fa');
          const freezeBtn = e.target.closest('.js-freeze-staff');

          if (editBtn && latestState) {
            const id = Number(editBtn.dataset.id);
            const u = (latestState.staff || []).find((x) => Number(x.id) === id);
            if (!u) return;
            document.getElementById('staffModalTitle').textContent = 'Edit Staff Permissions — @' + u.username;
            document.getElementById('staffFormMode').value = 'update';
            document.getElementById('staffFormUserId').value = id;
            document.getElementById('staffFullName').value = u.full_name || '';
            document.getElementById('staffUsername').value = u.username || '';
            document.getElementById('staffEmail').value = u.email || '';
            document.getElementById('staffRole').value = u.role || 'Junior';
            document.getElementById('staffPassword').required = false;
            document.getElementById('staffUsernameGroup').classList.add('d-none');
            document.getElementById('staffPasswordGroup').classList.add('d-none');
            document.getElementById('staffDirectEmailToggle').checked = Number(u.can_send_direct_email) === 1;
            staffModal.show();
          } else if (passBtn) {
            const id = Number(passBtn.dataset.id);
            const pwd = prompt('Enter new strong password (min 8 chars) for staff #' + id + ':');
            if (!pwd) return;
            try {
              const res = await postTeamAction({ action: 'reset_password', user_id: id, new_password: pwd });
              showFlash(res.message, 'success');
            } catch (err) { showFlash(err.message, 'danger'); }
          } else if (twoFaBtn) {
            if (!confirm('Reset 2FA for this staff member? Only do this if they lost access to their Authenticator device.')) return;
            try {
              const res = await postTeamAction({ action: 'reset_user_2fa', user_id: Number(twoFaBtn.dataset.id) });
              showFlash(res.message, 'warning');
              await loadTeamState();
            } catch (err) { showFlash(err.message, 'danger'); }
          } else if (freezeBtn) {
            if (!confirm('Toggle suspension / active access for this staff account?')) return;
            try {
              const res = await postTeamAction({ action: 'toggle_freeze', user_id: Number(freezeBtn.dataset.id) });
              showFlash(res.message, 'warning');
              await loadTeamState();
            } catch (err) { showFlash(err.message, 'danger'); }
          }
        });

        document.getElementById('sessionsTableBody').addEventListener('click', async (e) => {
          const btn = e.target.closest('.js-kick-session');
          if (!btn) return;
          if (!confirm('Sign out this device immediately?')) return;
          try {
            const res = await postTeamAction({ action: 'revoke_session', session_id: btn.dataset.sid });
            showFlash(res.message, 'warning');
            await loadTeamState();
          } catch (err) { showFlash(err.message, 'danger'); }
        });

        document.getElementById('btnPanicSignoutAll').addEventListener('click', async () => {
          if (!confirm('EMERGENCY ACTION: Force sign out ALL other logged-in staff devices across the company right now?')) return;
          try {
            const res = await postTeamAction({ action: 'revoke_other_sessions' });
            showFlash(res.message, 'danger');
            await loadTeamState();
          } catch (err) { showFlash(err.message, 'danger'); }
        });

        document.getElementById('auditModuleFilter').addEventListener('change', loadTeamState);
        document.getElementById('auditSearchInput').addEventListener('input', loadTeamState);
      }

      document.getElementById('btnRefreshTeam').addEventListener('click', loadTeamState);
      loadTeamState();
      setInterval(loadTeamState, 20000);
    })();
  </script>
</body>
</html>
