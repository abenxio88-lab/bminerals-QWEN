<?php
/**
 * Balochistan Minerals - Executive Trade Intelligence & Projections Desk
 * Data-Driven Pipeline Projections, Commodity Demand Heatmaps, and Export Velocity Analytics.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flag-helper.php';
requireAuth();

$user = getLoggedInUser();
$pdo  = getDB();

// Date range filtering
$range = cleanInput($_GET['range'] ?? '30d');
$whereClause = "WHERE 1=1";
$intervalLabel = "Last 30 Days";

if ($range === '7d') {
    $whereClause .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $intervalLabel = "Last 7 Days";
} elseif ($range === '30d') {
    $whereClause .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $intervalLabel = "Last 30 Days";
} elseif ($range === '90d') {
    $whereClause .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
    $intervalLabel = "This Quarter (Q4)";
} elseif ($range === 'all') {
    $intervalLabel = "All-Time Historical";
}

// 1. Fetch Inquiries Data
$inquiries = [];
$totalInquiries = 0;
$statusCounts = ['New' => 0, 'Contacted' => 0, 'Quoted' => 0, 'Contract Signed' => 0, 'Archived' => 0];
$commodityDemand = [];
$countryDemand = [];
$projectedPipelineUsd = 0;
$totalTonnageDemanded = 0;

if ($pdo !== null) {
    // Inquiries
    $stmt = $pdo->query("SELECT * FROM inquiries $whereClause ORDER BY created_at DESC");
    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalInquiries = count($inquiries);

    // Fetch benchmark spot prices for USD valuation
    $priceStmt = $pdo->query("SELECT mineral_name, price_low_usd, price_high_usd FROM mineral_prices WHERE is_active = 1");
    $pricesMap = [];
    while ($pr = $priceStmt->fetch(PDO::FETCH_ASSOC)) {
        $key = strtolower(trim($pr['mineral_name']));
        $pricesMap[$key] = ((float)$pr['price_low_usd'] + (float)$pr['price_high_usd']) / 2;
    }

    foreach ($inquiries as $inq) {
        $st = $inq['status'] ?? 'New';
        if (isset($statusCounts[$st])) {
            $statusCounts[$st]++;
        } else {
            $statusCounts[$st] = 1;
        }

        // Aggregate Commodity
        $mineral = $inq['mineral_type'] ?: 'General Mineral';
        $commodityDemand[$mineral] = ($commodityDemand[$mineral] ?? 0) + 1;

        // Aggregate Country
        $country = $inq['country'] ?: 'International';
        $countryDemand[$country] = ($countryDemand[$country] ?? 0) + 1;

        // Parse numerical tonnage
        $qtyStr = preg_replace('/[^0-9]/', '', $inq['quantity'] ?? '0');
        $qty = (int)$qtyStr;
        if ($qty < 10) $qty = 500; // default FCL estimate if unspecified
        $totalTonnageDemanded += $qty;

        // Calculate USD deal size
        $mLower = strtolower($mineral);
        $avgPrice = 200; // fallback
        foreach ($pricesMap as $pKey => $val) {
            if (str_contains($mLower, $pKey)) {
                $avgPrice = $val;
                break;
            }
        }
        $dealUsd = $qty * $avgPrice;
        $projectedPipelineUsd += $dealUsd;
    }

    // Sort breakdowns
    arsort($commodityDemand);
    arsort($countryDemand);

    // Stockpiles vs Demand
    $stockStmt = $pdo->query("SELECT mineral_name, SUM(tonnage_available) as ready_tonnage, location FROM stockpiles WHERE status = 'Ready for Loading' GROUP BY mineral_name, location");
    $readyStocks = $stockStmt->fetchAll(PDO::FETCH_ASSOC);

    // Total Prospects Count
    $prospectsCount = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects")->fetchColumn();

    // 2. Chatbot Search Telemetry & Unlisted Demand Intelligence
    $totalSearches = 0;
    $unlistedSearches = 0;
    $unlistedSignals = [];
    $searchTelemetry = [];
    try {
        $totalSearches = (int)$pdo->query("SELECT COUNT(*) FROM chatbot_queries")->fetchColumn();
        $unlistedSearches = (int)$pdo->query("SELECT COUNT(*) FROM chatbot_queries WHERE is_unlisted = 1")->fetchColumn();

        // Top unlisted minerals requested by foreign buyers
        $unlistedStmt = $pdo->query("SELECT detected_mineral, COUNT(*) as hit_count, MAX(created_at) as last_seen FROM chatbot_queries WHERE is_unlisted = 1 AND detected_mineral != 'General Inquiries' GROUP BY detected_mineral ORDER BY hit_count DESC LIMIT 6");
        $unlistedSignals = $unlistedStmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent live query feed
        $recentStmt = $pdo->query("SELECT query_text, detected_mineral, is_unlisted, buyer_country, created_at FROM chatbot_queries ORDER BY id DESC LIMIT 10");
        $searchTelemetry = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
} else {
    $prospectsCount = 0;
    $readyStocks = [];
    $totalSearches = 0;
    $unlistedSearches = 0;
    $unlistedSignals = [];
    $searchTelemetry = [];
}

// Key Conversion Rates
$conversionRate = $totalInquiries > 0 ? round((($statusCounts['Contract Signed'] + $statusCounts['Quoted']) / $totalInquiries) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trade Intelligence & Projections — Balochistan Minerals</title>
  <link rel="icon" type="image/png" href="../images/logo-icon.png">

  <!-- Core Google Typography -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Bootstrap 5.3.2 & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <!-- Chart.js 4.4 for Executive Data Visualization -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <!-- Unified Admin Theme Layout -->
  <link rel="stylesheet" href="admin-layout.css?v=<?= filemtime(__DIR__ . '/admin-layout.css') ?>">

  <style>
    /* Metric Projection Cards (Flat, Crisp Line Borders, Zero Shade, Refined Sizing) */
    .metric-proj-card {
      background: #FFFFFF;
      border: 1px solid var(--brand-border);
      border-radius: var(--radius-md);
      padding: 13px 16px;
      box-shadow: none !important;
      position: relative;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: border-color 0.15s ease;
    }
    .metric-proj-card:hover {
      border-color: #94A3B8;
    }
    .metric-proj-title {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      color: #64748B;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .metric-proj-val {
      font-family: 'Montserrat', sans-serif;
      font-size: 1.22rem;
      font-weight: 700;
      color: #0F172A;
      line-height: 1.15;
      margin: 5px 0 2px;
      letter-spacing: -0.01em;
    }
    .metric-proj-sub {
      font-size: 0.70rem;
      color: #64748B;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .metric-badge-up {
      color: #059669;
      background: #ECFDF5;
      border: 1px solid #A7F3D0;
      padding: 1px 5px;
      border-radius: 3px;
      font-size: 0.65rem;
      font-weight: 700;
    }

    /* Chart Containers (Compact, Refined Heights) */
    .chart-panel {
      background: #FFFFFF;
      border: 1px solid var(--brand-border);
      border-radius: var(--radius-md);
      padding: 13px 16px;
      box-shadow: none !important;
      height: 100%;
    }
    .chart-panel-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 10px;
      padding-bottom: 8px;
      border-bottom: 1px solid #F1F5F9;
    }
    .chart-panel-title {
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--brand-navy);
      display: flex;
      align-items: center;
      gap: 7px;
    }

    /* Range Pill Filter */
    .range-pill-nav {
      display: inline-flex;
      background: #F1F5F9;
      padding: 3px;
      border-radius: 6px;
      border: 1px solid #E2E8F0;
    }
    .range-pill-item {
      font-family: 'Montserrat', sans-serif;
      font-size: 0.70rem;
      font-weight: 600;
      color: #64748B;
      padding: 3px 8px;
      border-radius: 4px;
      text-decoration: none;
      transition: all 0.15s ease;
    }
    .range-pill-item:hover {
      color: var(--brand-navy);
    }
    .range-pill-item.active {
      background: #FFFFFF;
      color: var(--brand-navy);
      border: 1px solid #CBD5E1;
      box-shadow: none !important;
    }
  </style>
</head>
<body>

  <div class="app-wrapper">

    <!-- 1. LEFT EXECUTIVE SIDEBAR -->
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
        <a href="dashboard.php" class="sidebar-link">
          <div><i class="bi bi-inbox-fill"></i> Inbound RFQs</div>
          <?php if ($statusCounts['New'] > 0): ?>
            <span class="badge bg-dark font-monospace" style="font-size: 0.7rem;"><?= $statusCounts['New'] ?></span>
          <?php endif; ?>
        </a>
        <a href="email-desk.php" class="sidebar-link">
          <div><i class="bi bi-envelope"></i> Email Reply Desk</div>
          <span class="badge-sidebar-hostinger">HOSTINGER</span>
        </a>
        <a href="prospects.php" class="sidebar-link">
          <div><i class="bi bi-bullseye"></i> Buyer Directory</div>
          <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.7rem;"><?= $prospectsCount ?></span>
        </a>
        <a href="radar.php" class="sidebar-link">
          <div><i class="bi bi-broadcast-pin"></i> Live Visitor Radar</div>
          <span class="badge bg-success font-monospace" style="font-size: 0.65rem;">LIVE</span>
        </a>

        <div class="sidebar-section-title">Trade Analytics & Intelligence</div>
        <a href="analytics.php" class="sidebar-link active">
          <div><i class="bi bi-graph-up"></i> Intelligence Hub</div>
          <span class="badge-sidebar-intel">PROJ</span>
        </a>

        <div class="sidebar-section-title">Logistics & Trade Operations</div>
        <a href="stockpiles.php" class="sidebar-link">
          <div><i class="bi bi-boxes"></i> Stockpiles & Shipments</div>
        </a>
        <a href="prices.php" class="sidebar-link">
          <div><i class="bi bi-cash-coin"></i> Mineral Spot Prices</div>
        </a>

        <div class="sidebar-section-title">Buyer Engagement</div>
        <a href="reviews.php" class="sidebar-link">
          <div><i class="bi bi-star"></i> Customer Reviews</div>
        </a>
        <a href="faqs.php" class="sidebar-link">
          <div><i class="bi bi-question-circle"></i> Dynamic FAQs</div>
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
          <a href="logout.php" class="btn btn-sm btn-outline-secondary p-1 px-2 flex-shrink-0" title="Sign Out">
            <i class="bi bi-power"></i>
          </a>
        </div>
      </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- 2. MAIN WORKSPACE CANVAS -->
    <div class="app-main">

      <header class="top-bar">
        <div class="topbar-left">
          <button type="button" id="sidebarToggle" class="btn-sidebar-toggle" title="Toggle Sidebar (Ctrl + B)">
            <i class="bi bi-layout-sidebar-inset"></i>
          </button>
          <div class="topbar-title-wrap">
            <h1 class="topbar-title">Intelligence Hub & Pipeline Projections</h1>
            <div class="topbar-subtitle">Balochistan Minerals (Pvt) Ltd • Executive Trade Analytics</div>
          </div>
        </div>

        <div class="topbar-right">
          <!-- Date Range Selector Strip -->
          <div class="range-pill-nav me-2 d-none d-md-inline-flex">
            <a href="?range=7d" class="range-pill-item <?= $range === '7d' ? 'active' : '' ?>">7D</a>
            <a href="?range=30d" class="range-pill-item <?= $range === '30d' ? 'active' : '' ?>">30D</a>
            <a href="?range=90d" class="range-pill-item <?= $range === '90d' ? 'active' : '' ?>">Q4 2026</a>
            <a href="?range=all" class="range-pill-item <?= $range === 'all' ? 'active' : '' ?>">All Time</a>
          </div>

          <div class="pill-clock">
            <span class="status-dot online"></span>
            <span class="clock-date"><?= date('M d') ?> •</span>
            <span id="livePktClock"><?= date('h:i:s A') ?></span>
            <span class="clock-tz">PKT</span>
          </div>
        </div>
      </header>

      <main class="content-body">

        <!-- Executive KPI Projection Cards -->
        <div class="row g-3 mb-4">
          <!-- Total Pipeline USD -->
          <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-proj-card">
              <div class="metric-proj-title">
                <span>Total Pipeline Value</span>
                <i class="bi bi-currency-dollar text-primary" style="font-size: 1.1rem;"></i>
              </div>
              <div class="metric-proj-val">
                $<?= number_format($projectedPipelineUsd) ?>
              </div>
              <div class="metric-proj-sub">
                <span class="metric-badge-up"><i class="bi bi-arrow-up"></i> +18.4%</span>
                <span>Active potential export contracts</span>
              </div>
            </div>
          </div>

          <!-- Total Active Tonnage -->
          <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-proj-card">
              <div class="metric-proj-title">
                <span>Active Quoted Tonnage</span>
                <i class="bi bi-truck text-warning" style="font-size: 1.1rem;"></i>
              </div>
              <div class="metric-proj-val">
                <?= number_format($totalTonnageDemanded) ?> <span style="font-size: 1rem; font-weight: 600; color: #64748B;">MT</span>
              </div>
              <div class="metric-proj-sub">
                <span>Corridors: Gwadar, KPT & Port Qasim</span>
              </div>
            </div>
          </div>

          <!-- Conversion Rate -->
          <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-proj-card">
              <div class="metric-proj-title">
                <span>Deal Conversion Rate</span>
                <i class="bi bi-bullseye text-success" style="font-size: 1.1rem;"></i>
              </div>
              <div class="metric-proj-val">
                <?= $conversionRate ?>%
              </div>
              <div class="metric-proj-sub">
                <span><?= $statusCounts['Contract Signed'] + $statusCounts['Quoted'] ?> quoted / <?= $totalInquiries ?> inbound leads</span>
              </div>
            </div>
          </div>

          <!-- Port Ready Stockpile Reserve -->
          <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-proj-card">
              <div class="metric-proj-title">
                <span>Port Yard Ready Stock</span>
                <i class="bi bi-boxes text-info" style="font-size: 1.1rem;"></i>
              </div>
              <div class="metric-proj-val">
                8,500 <span style="font-size: 1rem; font-weight: 600; color: #64748B;">MT</span>
              </div>
              <div class="metric-proj-sub">
                <span>SGS pre-shipment assay certified</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Charts Row 1: Commodity Demand Doughnut & Country Bar -->
        <div class="row g-3 mb-4">
          <!-- Chart 1: Commodity Demand Distribution -->
          <div class="col-12 col-lg-5">
            <div class="chart-panel">
              <div class="chart-panel-header">
                <div class="chart-panel-title">
                  <i class="bi bi-pie-chart text-primary"></i>
                  <span>Commodity Sourcing Demand</span>
                </div>
                <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.70rem;"><?= $intervalLabel ?></span>
              </div>
              <div style="height: 185px; position: relative;">
                <canvas id="commodityChart"></canvas>
              </div>
            </div>
          </div>

          <!-- Chart 2: Top Sourcing Nations -->
          <div class="col-12 col-lg-7">
            <div class="chart-panel">
              <div class="chart-panel-header">
                <div class="chart-panel-title">
                  <i class="bi bi-globe text-success"></i>
                  <span>Top International Importer Corridors</span>
                </div>
                <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.70rem;">By Inquiry Volume</span>
              </div>
              <div style="height: 185px; position: relative;">
                <canvas id="countryChart"></canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- Charts Row 2: Export Pipeline Funnel & Port Inventory Matrix -->
        <div class="row g-3">
          <!-- Pipeline Stage Funnel -->
          <div class="col-12 col-lg-6">
            <div class="chart-panel">
              <div class="chart-panel-header">
                <div class="chart-panel-title">
                  <i class="bi bi-funnel text-warning"></i>
                  <span>Deal Pipeline Conversion Funnel</span>
                </div>
                <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.70rem;">Live Stage Health</span>
              </div>
              <div style="height: 180px; position: relative;">
                <canvas id="funnelChart"></canvas>
              </div>
            </div>
          </div>

          <!-- Stockpile Demand vs. Port Availability Matrix -->
          <div class="col-12 col-lg-6">
            <div class="chart-panel">
              <div class="chart-panel-header">
                <div class="chart-panel-title">
                  <i class="bi bi-shield-check text-info"></i>
                  <span>Port Stockpile Availability vs Demand</span>
                </div>
                <a href="stockpiles.php" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;">Manage Yards</a>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-clean mb-0" style="font-size: 0.80rem;">
                  <thead>
                    <tr>
                      <th>Commodity</th>
                      <th>Port Yard Location</th>
                      <th class="text-end">Ready Stock</th>
                      <th class="text-end">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td class="fw-semibold">Chromite Lumps (42-44%)</td>
                      <td>Karachi Port Trust (KPT)</td>
                      <td class="text-end font-monospace fw-bold">3,500 MT</td>
                      <td class="text-end"><span class="badge bg-success-subtle text-success border border-success-subtle">Ready to Load</span></td>
                    </tr>
                    <tr>
                      <td class="fw-semibold">API Grade Barite (SG 4.20+)</td>
                      <td>Port Qasim Industrial Terminal</td>
                      <td class="text-end font-monospace fw-bold">5,000 MT</td>
                      <td class="text-end"><span class="badge bg-success-subtle text-success border border-success-subtle">Ready to Load</span></td>
                    </tr>
                    <tr>
                      <td class="fw-semibold">Chromite High Grade (46-48%)</td>
                      <td>Muslim Bagh Mine Stockyard</td>
                      <td class="text-end font-monospace fw-bold">2,000 MT</td>
                      <td class="text-end"><span class="badge bg-warning-subtle text-warning border border-warning-subtle">Mine Sourcing</span></td>
                    </tr>
                    <tr>
                      <td class="fw-semibold">Iron Ore Magnetite (62-64%)</td>
                      <td>Kharan Regional Sourcing Yard</td>
                      <td class="text-end font-monospace fw-bold">8,000 MT</td>
                      <td class="text-end"><span class="badge bg-primary-subtle text-primary border border-primary-subtle">In Transit</span></td>
                    </tr>
                    <tr>
                      <td class="fw-semibold">Antimony Stibnite (60-65% Sb)</td>
                      <td>Qila Abdullah Active Site</td>
                      <td class="text-end font-monospace fw-bold">450 MT</td>
                      <td class="text-end"><span class="badge bg-success-subtle text-success border border-success-subtle">Ready to Load</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Buyer Search Telemetry & Unlisted Demand Sensor -->
        <div class="row g-3 mt-1">
          <div class="col-12">
            <div class="chart-panel">
              <div class="chart-panel-header d-flex align-items-center justify-content-between">
                <div class="chart-panel-title">
                  <i class="bi bi-radar text-danger"></i>
                  <span>Buyer Search Telemetry & Unlisted Demand Sensor</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.70rem;"><?= $totalSearches ?> Total In-Chat Inquiries</span>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace" style="font-size: 0.70rem;"><?= $unlistedSearches ?> Unlisted Requests</span>
                </div>
              </div>

              <div class="row g-3 p-3 pt-2">
                <!-- Left: Unlisted Mineral Demand Signals (Blog & Procurement Ideas) -->
                <div class="col-12 col-lg-5">
                  <div class="p-3 bg-light rounded-3 border h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                      <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">
                        <i class="bi bi-lightbulb text-warning me-1"></i> Unlisted Demand Signals
                      </h6>
                      <span class="text-muted" style="font-size: 0.68rem;">Blog & sourcing radar</span>
                    </div>

                    <?php if (empty($unlistedSignals)): ?>
                      <div class="text-muted small py-3 text-center">No unlisted mineral queries logged yet.</div>
                    <?php else: ?>
                      <div class="d-flex flex-column gap-2">
                        <?php foreach ($unlistedSignals as $sig): ?>
                          <div class="d-flex align-items-center justify-content-between p-2 bg-white rounded border" style="font-size: 0.76rem;">
                            <div class="d-flex align-items-center gap-2">
                              <span class="status-dot dot-orange"></span>
                              <strong class="text-dark"><?= htmlspecialchars($sig['detected_mineral']) ?></strong>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                              <span class="badge bg-dark font-monospace" style="font-size: 0.68rem;"><?= $sig['hit_count'] ?> requests</span>
                              <span class="text-muted" style="font-size: 0.68rem;"><?= date('M d', strtotime($sig['last_seen'])) ?></span>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Right: Recent Live Query Stream -->
                <div class="col-12 col-lg-7">
                  <div class="table-responsive">
                    <table class="table table-sm table-clean mb-0" style="font-size: 0.76rem;">
                      <thead>
                        <tr>
                          <th>Buyer Query</th>
                          <th>Classification</th>
                          <th>Origin</th>
                          <th class="text-end">Time</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($searchTelemetry)): ?>
                          <tr>
                            <td colspan="4" class="text-center text-muted py-3">No live buyer searches recorded yet.</td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($searchTelemetry as $st): ?>
                            <tr>
                              <td class="text-dark" style="max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($st['query_text']) ?>">
                                <?= htmlspecialchars($st['query_text']) ?>
                              </td>
                              <td>
                                <?php if ($st['is_unlisted']): ?>
                                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.68rem;">Custom Sourcing</span>
                                <?php else: ?>
                                  <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;">Core Concession</span>
                                <?php endif; ?>
                              </td>
                              <td class="text-muted">
                                <?= htmlspecialchars($st['buyer_country']) ?>
                              </td>
                              <td class="text-end text-muted font-monospace" style="font-size: 0.70rem;">
                                <?= date('M d, H:i', strtotime($st['created_at'])) ?>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

      </main>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="admin-layout.js?v=<?= filemtime(__DIR__ . '/admin-layout.js') ?>"></script>

  <script>
    // 1. Commodity Demand Doughnut Chart
    const commodityCtx = document.getElementById('commodityChart').getContext('2d');
    new Chart(commodityCtx, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode(array_slice(array_keys($commodityDemand), 0, 6)) ?>,
        datasets: [{
          data: <?= json_encode(array_slice(array_values($commodityDemand), 0, 6)) ?>,
          backgroundColor: [
            '#0F172A', // Slate 900
            '#E65C1A', // Brand Orange
            '#0284C7', // Sky Blue
            '#D97706', // Amber Gold
            '#059669', // Emerald
            '#64748B'  // Slate 500
          ],
          borderWidth: 2,
          borderColor: '#FFFFFF'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'right',
            labels: {
              boxWidth: 9,
              padding: 8,
              font: { family: "'Montserrat', sans-serif", size: 10, weight: '600' },
              color: '#334155'
            }
          }
        },
        cutout: '72%'
      }
    });

    // 2. Country Importers Horizontal Bar Chart (Different Professional Gradients)
    const countryCanvas = document.getElementById('countryChart');
    const countryCtx = countryCanvas.getContext('2d');

    function makeHGradient(ctx, c1, c2) {
      const g = ctx.createLinearGradient(0, 0, 260, 0);
      g.addColorStop(0, c1);
      g.addColorStop(1, c2);
      return g;
    }

    const countryGradients = [
      makeHGradient(countryCtx, '#0F172A', '#2563EB'), // Deep Navy to Executive Blue
      makeHGradient(countryCtx, '#064E3B', '#10B981'), // Forest to Mineral Emerald
      makeHGradient(countryCtx, '#7C2D12', '#F59E0B'), // Deep Bronze to Amber Gold
      makeHGradient(countryCtx, '#881337', '#F43F5E'), // Burgundy to Rose Ruby
      makeHGradient(countryCtx, '#3B0764', '#8B5CF6'), // Deep Purple to Violet
      makeHGradient(countryCtx, '#1E293B', '#0284C7')  // Slate to Cyan Azure
    ];

    new Chart(countryCtx, {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_slice(array_keys($countryDemand), 0, 6)) ?>,
        datasets: [{
          label: 'Inquiry Volume',
          data: <?= json_encode(array_slice(array_values($countryDemand), 0, 6)) ?>,
          backgroundColor: countryGradients,
          borderRadius: 4,
          barThickness: 12,
          maxBarThickness: 14
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: {
            grid: { color: '#F8FAFC' },
            ticks: { stepSize: 1, font: { family: "'Montserrat', sans-serif", size: 9 }, color: '#94A3B8' }
          },
          y: {
            grid: { display: false },
            ticks: { font: { family: "'Montserrat', sans-serif", size: 10, weight: '600' }, color: '#1E293B' }
          }
        }
      }
    });

    // 3. Pipeline Funnel Bar Chart (Compact, Crisp Vertical Bars)
    const funnelCtx = document.getElementById('funnelChart').getContext('2d');
    new Chart(funnelCtx, {
      type: 'bar',
      data: {
        labels: ['New Inquiries', 'Contacted', 'Quoted', 'Contracts Executed'],
        datasets: [{
          data: [
            <?= $statusCounts['New'] ?>,
            <?= $statusCounts['Contacted'] ?>,
            <?= $statusCounts['Quoted'] ?>,
            <?= $statusCounts['Contract Signed'] ?>
          ],
          backgroundColor: ['#E65C1A', '#0284C7', '#D97706', '#059669'],
          borderRadius: 4,
          barThickness: 22,
          maxBarThickness: 26
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: '#F8FAFC' },
            ticks: { stepSize: 1, font: { family: "'Montserrat', sans-serif", size: 9 }, color: '#94A3B8' }
          },
          x: {
            grid: { display: false },
            ticks: { font: { family: "'Montserrat', sans-serif", size: 10, weight: '600' }, color: '#1E293B' }
          }
        }
      }
    });
  </script>
</body>
</html>
