<?php
/**
 * Balochistan Minerals - Live Visitor Radar & Global Demand Tracker
 * Real-time monitoring of international commodity buyers browsing mineral assets.
 */

require_once __DIR__ . '/auth.php';
requireAuth();

$user = getLoggedInUser();
$pdo  = getDB();

$flashMessage = '';
$flashType    = 'success';

// Handle simulated visitor ping for testing
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $pdo !== null) {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (verifyCsrfToken($csrf)) {
        if ($action === 'simulate_ping') {
            $sims = [
                ['114.242.25.10', 'China', 'Tianjin', '/products.html#chromite', 'Chromite Ore', 'Baidu Search'],
                ['185.100.87.12', 'United Arab Emirates', 'Dubai', '/products.html#barite', 'Barite (Barytes)', 'Direct / Bookmark'],
                ['133.200.41.90', 'Japan', 'Tokyo', '/products.html#antimony', 'Antimony Ore', 'Google Japan'],
                ['94.200.15.77', 'Saudi Arabia', 'Jubail', '/products.html#fluorite', 'Fluorite (Fluorspar)', 'Google Saudi'],
                ['80.150.12.33', 'Germany', 'Duisburg', '/products.html#chromite', 'Chromite Ore', 'Commodity Bulletin'],
                ['14.192.11.45', 'South Korea', 'Pohang', '/products.html#iron-ore', 'Iron Ore', 'Naver']
            ];
            $chosen = $sims[array_rand($sims)];
            $stmt = $pdo->prepare("INSERT INTO visitor_radar (ip_address, country, city, page_url, mineral_interest, referrer, user_agent) VALUES (?, ?, ?, ?, ?, ?, 'Mozilla/5.0 Commercial Browser Simulator')");
            $stmt->execute([$chosen[0], $chosen[1], $chosen[2], $chosen[3], $chosen[4], $chosen[5]]);
            $flashMessage = 'Simulated live visit from ' . $chosen[1] . ' (' . $chosen[4] . ') recorded.';
        } elseif ($action === 'clear_radar') {
            $pdo->exec("DELETE FROM visitor_radar WHERE visited_at < NOW() - INTERVAL 30 DAY");
            $flashMessage = 'Old radar logs cleared.';
        }
    }
}

// Fetch Radar Intelligence
$totalPings = 0;
$todayPings = 0;
$topCountry = 'China';
$topMineral = 'Chromite Ore';
$recentVisits = [];
$countryStats = [];
$mineralStats = [];
$newLeads = 0;
$totalProspects = 0;
$pendingReviews = 0;

if ($pdo !== null) {
    $newLeads = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'New'")->fetchColumn();
    $totalProspects = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects")->fetchColumn();
    $pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'Pending'")->fetchColumn();

    $totalPings = (int)$pdo->query("SELECT COUNT(*) FROM visitor_radar")->fetchColumn();
    $todayPings = (int)$pdo->query("SELECT COUNT(*) FROM visitor_radar WHERE visited_at >= CURDATE()")->fetchColumn();

    // Country stats
    $countryStats = $pdo->query("SELECT country, COUNT(*) as c FROM visitor_radar GROUP BY country ORDER BY c DESC LIMIT 6")->fetchAll();
    if (!empty($countryStats)) {
        $topCountry = $countryStats[0]['country'] . ' (' . $countryStats[0]['c'] . ')';
    }

    // Mineral demand stats
    $mineralStats = $pdo->query("SELECT mineral_interest, COUNT(*) as c FROM visitor_radar GROUP BY mineral_interest ORDER BY c DESC LIMIT 6")->fetchAll();
    if (!empty($mineralStats)) {
        $topMineral = $mineralStats[0]['mineral_interest'] . ' (' . $mineralStats[0]['c'] . ')';
    }

    // Recent visitor feed
    $recentVisits = $pdo->query("SELECT * FROM visitor_radar ORDER BY visited_at DESC LIMIT 50")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Live Visitor Radar & Demand Tracker | Balochistan Minerals</title>
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
    .sidebar-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--brand-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .sidebar-logo { height: 32px; width: auto; object-fit: contain; }
    .sidebar-badge {
      font-size: 0.65rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      padding: 3px 8px;
      border-radius: 999px;
      background: #E65C1A;
      color: #FFFFFF !important;
      border: 1px solid #D45A15;
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

    /* Compact KPI Strip */
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

    /* Radar Table */
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
      max-height: 520px;
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
      padding: 11px 14px;
      border-bottom: 1px solid #F1F5F9;
      vertical-align: middle;
      font-size: 0.84rem;
    }
    .table-clean tr:hover td { background-color: #FAFCFE; }

    /* Live Blinking Indicator */
    .radar-pulse {
      display: inline-block;
      width: 9px;
      height: 9px;
      background-color: #10B981;
      border-radius: 50%;
      box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
      animation: pulse 1.8s infinite;
    }
    @keyframes pulse {
      0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
      70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
      100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
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
          <div><i class="bi bi-envelope-paper-fill"></i> Email Reply Desk</div>
          <span class="badge bg-primary font-monospace" style="font-size: 0.65rem;">HOSTINGER</span>
        </a>
        <a href="prospects.php" class="sidebar-link">
          <div><i class="bi bi-bullseye"></i> Buyer Directory</div>
          <span class="badge font-monospace" style="background: rgba(230,92,26,0.15); color: #E65C1A; font-size: 0.7rem;"><?= $totalProspects ?></span>
        </a>
        <a href="radar.php" class="sidebar-link active">
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
            <h1 class="topbar-title">Live Visitor Radar & Global Demand Tracker</h1>
            <div class="topbar-subtitle">Real-Time Surveillance of International Buyer Sessions</div>
          </div>
        </div>
        <div class="topbar-right">
          <div class="pill-status d-none d-md-inline-flex" title="Live Surveillance Active">
            <span class="radar-pulse me-1"></span>
            <span>Radar Active</span>
          </div>
          <form method="POST" action="radar.php" class="d-inline">
            <input type="hidden" name="action" value="simulate_ping">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1.5 fw-semibold px-2.5 py-1.5 text-dark" style="border-radius: 8px; font-size: 0.78rem; background: #FFFFFF;">
              <i class="bi bi-send-plus text-primary"></i> <span>Simulate Ping</span>
            </button>
          </form>
          <a href="radar.php" class="btn btn-sm btn-dark d-flex align-items-center gap-1.5 fw-bold px-3 py-1.5 shadow-sm text-white" style="border-radius: 8px; background: #0F172A; font-size: 0.78rem;">
            <i class="bi bi-arrow-clockwise"></i> <span>Refresh Feed</span>
          </a>
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

        <!-- KPI Summary Strip -->
        <div class="stat-strip">
          <div class="stat-strip-item">
            <i class="bi bi-eye-fill text-primary fs-3"></i>
            <div>
              <div class="stat-strip-val"><?= $totalPings ?></div>
              <div class="stat-strip-label">Total Buyer Pageviews</div>
            </div>
          </div>
          <div class="stat-strip-item">
            <i class="bi bi-lightning-charge-fill text-warning fs-3"></i>
            <div>
              <div class="stat-strip-val"><?= $todayPings ?></div>
              <div class="stat-strip-label">Activity Today</div>
            </div>
          </div>
          <div class="stat-strip-item">
            <i class="bi bi-globe-americas text-info fs-3"></i>
            <div>
              <div class="stat-strip-val" style="font-size: 1.05rem;"><?= htmlspecialchars($topCountry) ?></div>
              <div class="stat-strip-label">Top Visiting Corridor</div>
            </div>
          </div>
          <div class="stat-strip-item">
            <i class="bi bi-fire text-danger fs-3"></i>
            <div>
              <div class="stat-strip-val" style="font-size: 1.05rem;"><?= htmlspecialchars($topMineral) ?></div>
              <div class="stat-strip-label">Top Demanded Mineral</div>
            </div>
          </div>
        </div>

        <!-- Demand Distribution Breakdown -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <div class="card p-3 bg-white border h-100" style="border-radius: 10px;">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-bold brand-font" style="font-size: 0.88rem; color: var(--brand-navy);">
                  <i class="bi bi-globe me-1 text-primary"></i> International Buyer Corridors
                </span>
                <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.70rem;">Top Destinations</span>
              </div>
              <div class="d-flex flex-wrap gap-2 pt-1">
                <?php foreach ($countryStats as $cs): ?>
                  <div class="p-2 px-3 rounded bg-light border d-flex align-items-center justify-content-between flex-grow-1">
                    <span class="fw-bold small text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($cs['country']) ?></span>
                    <span class="badge bg-white text-dark border font-monospace"><?= $cs['c'] ?> views</span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="card p-3 bg-white border h-100" style="border-radius: 10px;">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-bold brand-font" style="font-size: 0.88rem; color: var(--brand-navy);">
                  <i class="bi bi-gem me-1 text-warning"></i> Active Commodity Interest
                </span>
                <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.70rem;">Demand Heatmap</span>
              </div>
              <div class="d-flex flex-wrap gap-2 pt-1">
                <?php foreach ($mineralStats as $ms): ?>
                  <div class="p-2 px-3 rounded bg-light border d-flex align-items-center justify-content-between flex-grow-1">
                    <span class="fw-bold small text-dark"><i class="bi bi-tag-fill me-1" style="color: #E65C1A;"></i><?= htmlspecialchars($ms['mineral_interest']) ?></span>
                    <span class="badge bg-white text-dark border font-monospace"><?= $ms['c'] ?> views</span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Live Visitor Feed Table -->
        <div class="table-panel">
          <div class="table-panel-header">
            <div class="d-flex align-items-center gap-2">
              <span class="radar-pulse"></span>
              <span class="fw-bold brand-font" style="color: var(--brand-navy); font-size: 0.95rem;">
                Real-Time Buyer Pageview Telemetry (Latest <?= count($recentVisits) ?> Pings)
              </span>
            </div>
            <span class="small text-muted font-monospace">Auto-refreshes every 20 seconds</span>
          </div>

          <div class="table-scroll-container">
            <table class="table-clean">
              <thead>
                <tr>
                  <th>Timestamp</th>
                  <th>Origin & Corridor</th>
                  <th>Commodity Interest</th>
                  <th>Page / Asset Viewed</th>
                  <th>Referral Channel</th>
                  <th>IP / Client</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recentVisits)): ?>
                  <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                      No visitor telemetry logged yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recentVisits as $rv): 
                    $timeAgo = date('H:i - M d, Y', strtotime($rv['visited_at']));
                  ?>
                    <tr>
                      <td class="font-monospace small text-muted">
                        <i class="bi bi-clock me-1"></i><?= $timeAgo ?>
                      </td>
                      <td>
                        <span class="badge bg-light text-dark border fw-bold" style="font-size: 0.75rem;">
                          <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($rv['country']) ?> (<?= htmlspecialchars($rv['city']) ?>)
                        </span>
                      </td>
                      <td>
                        <span class="badge" style="background: #FFF7ED; color: #C2410C; border: 1px solid #FFEDD5; font-size: 0.75rem;">
                          <i class="bi bi-gem me-1"></i><?= htmlspecialchars($rv['mineral_interest']) ?>
                        </span>
                      </td>
                      <td class="font-monospace small text-dark">
                        <?= htmlspecialchars($rv['page_url']) ?>
                      </td>
                      <td class="small text-muted">
                        <?= htmlspecialchars($rv['referrer'] ?: 'Direct Visit') ?>
                      </td>
                      <td class="font-monospace small text-muted">
                        <?= htmlspecialchars(substr($rv['ip_address'], 0, 12)) ?>...
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Auto-refresh feed every 20 seconds
    setTimeout(() => {
      window.location.reload();
    }, 20000);
  </script>
  <script src="admin-layout.js"></script>
</body>
</html>
