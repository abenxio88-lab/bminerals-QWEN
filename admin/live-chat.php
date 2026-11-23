<?php
/**
 * Balochistan Minerals - Real-Time Live Chat Desk & Buyer Handover Console
 * Multi-agent terminal for managing concurrent visitor inquiries, claiming chats, and issuing instant export guidance.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/chat-helpers.php';
requirePermission('draft_reply');

$user = getLoggedInUser();
$pdo  = getDB();

if ($pdo !== null) {
    ensureLiveChatSchema($pdo);
    touchAdminSession($pdo, (int)$user['id'], 'live-chat.php');
}

$currentUid  = (int)($user['id'] ?? 0);
$currentTier = $user['tier'] ?? 'Owner';
$agentName   = $user['full_name'] ?: ($user['username'] ?? 'Export Officer');

// KPI Metrics
$waitingCount = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status = 'waiting_human'")->fetchColumn();
$activeCount  = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status = 'agent_active'")->fetchColumn();
$myCount      = (int)$pdo->query("SELECT COUNT(*) FROM `chat_sessions` WHERE status = 'agent_active' AND assigned_admin_id = {$currentUid}")->fetchColumn();
$onlineCount  = countOnlineTradeDeskOfficers($pdo, 5);

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Live Chat Desk &bull; Balochistan Minerals Trade Desk</title>
  <link rel="icon" type="image/png" href="../images/favicon/favicon-32x32.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="admin-layout.css?v=<?= filemtime(__DIR__ . '/admin-layout.css') ?>">
  <style>
    :root {
      --brand-navy: #0F172A;
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
      z-index: 1050;
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
      left: -12px; top: 6px; bottom: 6px;
      width: 3.5px;
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
      height: 100vh;
      overflow: hidden;
      min-width: 0;
    }

    .top-bar {
      background: var(--brand-surface);
      border-bottom: 1px solid var(--brand-border);
      padding: 10px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
      z-index: 1030;
    }

    /* Live Chat Triple-Pane Terminal */
    .chat-terminal {
      display: flex;
      flex: 1;
      height: calc(100vh - 65px);
      overflow: hidden;
      background: #FFFFFF;
    }

    /* Column 1: Conversations List */
    .chat-conversations-pane {
      width: 330px;
      border-right: 1px solid var(--brand-border);
      display: flex;
      flex-direction: column;
      background: #FAFCFE;
      flex-shrink: 0;
    }

    .conversations-search {
      padding: 12px 14px;
      border-bottom: 1px solid var(--brand-border);
      background: #FFFFFF;
    }

    .chat-filter-tabs {
      display: flex;
      padding: 6px 12px;
      gap: 4px;
      border-bottom: 1px solid var(--brand-border);
      background: #F8FAFC;
      overflow-x: auto;
    }
    .chat-filter-btn {
      font-size: 0.72rem;
      font-weight: 600;
      padding: 4px 10px;
      border-radius: 4px;
      border: none;
      background: transparent;
      color: #64748B;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.15s ease;
    }
    .chat-filter-btn.active {
      background: #0F172A;
      color: #FFFFFF;
    }

    .conversations-list {
      flex: 1;
      overflow-y: auto;
      padding: 8px;
    }

    .conv-item {
      padding: 11px 12px;
      border-radius: 6px;
      background: #FFFFFF;
      border: 1px solid var(--brand-border);
      margin-bottom: 6px;
      cursor: pointer;
      transition: all 0.15s ease;
      position: relative;
    }
    .conv-item:hover {
      border-color: #94A3B8;
      box-shadow: 0 2px 5px rgba(0,0,0,0.03);
    }
    .conv-item.active {
      border-color: #0F172A;
      background: #F8FAFC;
      box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
    }
    .conv-item.is-waiting {
      border-left: 3.5px solid var(--brand-orange);
    }
    .conv-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 3px;
    }
    .conv-buyer-name {
      font-weight: 600;
      font-size: 0.82rem;
      color: #0F172A;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .conv-time {
      font-size: 0.68rem;
      color: #94A3B8;
    }
    .conv-page {
      font-size: 0.7rem;
      color: #64748B;
      margin-bottom: 4px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .conv-snippet {
      font-size: 0.76rem;
      color: #475569;
      line-height: 1.35;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .conv-badge-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 6px;
    }

    /* Column 2: Active Chat Window */
    .chat-active-pane {
      flex: 1;
      display: flex;
      flex-direction: column;
      background: #FFFFFF;
      min-width: 0;
      position: relative;
    }

    .active-chat-header {
      padding: 12px 20px;
      border-bottom: 1px solid var(--brand-border);
      background: #FFFFFF;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }

    .active-chat-body {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
      background: #F8FAFC;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    /* Message Bubbles */
    .chat-bubble-wrap {
      display: flex;
      flex-direction: column;
      max-width: 78%;
    }
    .chat-bubble-wrap.is-visitor {
      align-self: flex-start;
    }
    .chat-bubble-wrap.is-agent {
      align-self: flex-end;
    }
    .chat-bubble-wrap.is-bot {
      align-self: flex-start;
      max-width: 85%;
    }
    .chat-bubble-wrap.is-system {
      align-self: center;
      max-width: 90%;
    }

    .chat-bubble {
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 0.82rem;
      line-height: 1.45;
      position: relative;
      word-break: break-word;
    }
    .chat-bubble.bubble-visitor {
      background: #FFFFFF;
      color: #0F172A;
      border: 1px solid var(--brand-border);
      box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    .chat-bubble.bubble-agent {
      background: #0F172A;
      color: #FFFFFF;
    }
    .chat-bubble.bubble-bot {
      background: #F1F5F9;
      color: #334155;
      border: 1px dashed #CBD5E1;
      font-size: 0.78rem;
    }
    .chat-bubble.bubble-system {
      background: #EFF6FF;
      color: #1E40AF;
      border: 1px solid #BFDBFE;
      font-size: 0.73rem;
      text-align: center;
      padding: 6px 14px;
      border-radius: 20px;
    }

    .bubble-meta {
      font-size: 0.67rem;
      margin-top: 3px;
      color: #94A3B8;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .chat-bubble-wrap.is-agent .bubble-meta {
      justify-content: flex-end;
    }

    /* Chat Footer & Input */
    .active-chat-footer {
      padding: 12px 18px;
      border-top: 1px solid var(--brand-border);
      background: #FFFFFF;
      flex-shrink: 0;
    }

    .quick-templates-bar {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 8px;
      overflow-x: auto;
      padding-bottom: 4px;
    }
    .template-chip {
      font-size: 0.7rem;
      padding: 3px 8px;
      border-radius: 4px;
      border: 1px solid var(--brand-border);
      background: #F8FAFC;
      color: #475569;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.15s ease;
    }
    .template-chip:hover {
      background: #0F172A;
      color: #FFFFFF;
      border-color: #0F172A;
    }

    .chat-input-row {
      display: flex;
      gap: 8px;
      align-items: flex-end;
    }
    .chat-input-textarea {
      flex: 1;
      resize: none;
      border-radius: 6px;
      border: 1px solid var(--brand-border);
      padding: 8px 12px;
      font-size: 0.84rem;
      max-height: 100px;
      outline: none;
      font-family: inherit;
    }
    .chat-input-textarea:focus {
      border-color: #0F172A;
    }

    /* Column 3: Buyer Dossier (Right Pane) */
    .chat-dossier-pane {
      width: 270px;
      border-left: 1px solid var(--brand-border);
      background: #FAFCFE;
      padding: 16px;
      overflow-y: auto;
      flex-shrink: 0;
    }
    .dossier-section-title {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #64748B;
      margin-bottom: 8px;
      padding-bottom: 4px;
      border-bottom: 1px solid var(--brand-border);
    }
    .dossier-row {
      margin-bottom: 10px;
    }
    .dossier-label {
      font-size: 0.67rem;
      color: #94A3B8;
      text-transform: uppercase;
      font-weight: 600;
      margin-bottom: 1px;
    }
    .dossier-val {
      font-size: 0.8rem;
      font-weight: 600;
      color: #0F172A;
      word-break: break-all;
    }

    /* Empty State */
    .empty-chat-screen {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100%;
      color: #94A3B8;
      text-align: center;
      padding: 30px;
    }

    /* Mobile Back Button */
    .btn-mobile-back {
      display: none;
      align-items: center;
      gap: 4px;
      font-size: 0.8rem;
      font-weight: 600;
      border: none;
      background: transparent;
      color: #0F172A;
      padding: 0;
      margin-right: 12px;
    }

    @media (max-width: 991px) {
      .app-sidebar { display: none; }
      .app-main { margin-left: 0; }
      .chat-dossier-pane { display: none; }
      .btn-mobile-back { display: inline-flex; }
      
      .chat-conversations-pane {
        width: 100%;
      }
      .chat-active-pane {
        display: none;
        width: 100%;
      }
      body.mobile-view-chat .chat-conversations-pane {
        display: none;
      }
      body.mobile-view-chat .chat-active-pane {
        display: flex;
      }
    }
  </style>
</head>
<body>

  <div class="app-wrapper">

    <!-- 1. LEFT EXECUTIVE SIDEBAR -->
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
        <div class="sidebar-section-title">Lead &amp; Deal Engine</div>
        <a href="dashboard.php" class="sidebar-link">
          <div><i class="bi bi-inbox-fill"></i> Inbound RFQs</div>
        </a>
        <a href="live-chat.php" class="sidebar-link active">
          <div><i class="bi bi-chat-dots-fill"></i> Live Chat Desk</div>
          <span class="badge bg-danger font-monospace" id="sidebarWaitingBadge" style="<?= $waitingCount > 0 ? '' : 'display:none;' ?>"><?= $waitingCount ?></span>
        </a>
        <a href="email-desk.php" class="sidebar-link">
          <div><i class="bi bi-envelope"></i> Email Reply Desk</div>
          <span class="badge-sidebar-hostinger">HOSTINGER</span>
        </a>
        <a href="prospects.php" class="sidebar-link">
          <div><i class="bi bi-bullseye"></i> Buyer Directory</div>
        </a>
        <a href="radar.php" class="sidebar-link">
          <div><i class="bi bi-broadcast-pin"></i> Live Visitor Radar</div>
          <span class="badge bg-success font-monospace" style="font-size: 0.65rem;">LIVE</span>
        </a>

        <div class="sidebar-section-title">Logistics &amp; Trade Operations</div>
        <a href="deals.php" class="sidebar-link">
          <div><i class="bi bi-shield-check"></i> Deals &amp; Evidence Vault</div>
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
          <div>
            <div class="fw-bold small" style="color: var(--brand-navy);"><?= htmlspecialchars($agentName) ?></div>
            <div class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($currentTier) ?></div>
          </div>
          <a href="logout.php" class="btn btn-sm btn-outline-secondary p-1 px-2" title="Sign Out">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </div>
      </div>
    </aside>

    <!-- 2. MAIN WORKSPACE -->
    <main class="app-main">

      <!-- Top Bar -->
      <header class="top-bar">
        <div class="d-flex align-items-center gap-3">
          <button type="button" id="sidebarToggle" class="btn btn-sm btn-outline-secondary d-lg-none">
            <i class="bi bi-list fs-5"></i>
          </button>
          <div class="brand-font fw-bold" style="font-size: 1.05rem; color: #0F172A;">
            Live Chat Desk
          </div>
          <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 font-monospace" style="font-size: 0.72rem;">
            <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i>TRADE DESK ONLINE (<?= $onlineCount ?>)
          </span>
        </div>

        <div class="d-flex align-items-center gap-2">
          <!-- Audio Alert Toggle -->
          <button type="button" id="soundToggleBtn" class="btn btn-sm btn-light border px-2.5 py-1 text-muted" title="Toggle audio chime on new buyer message">
            <i class="bi bi-volume-up-fill text-dark me-1" id="soundIcon"></i>
            <span class="small" id="soundLabel">Sound On</span>
          </button>
          <!-- Live Indicator Pill -->
          <div class="badge bg-light text-secondary border px-2 py-1 font-monospace" style="font-size: 0.72rem;">
            Auto-Sync 2.5s
          </div>
        </div>
      </header>

      <!-- 3. TRIPLE-PANE CHAT TERMINAL -->
      <div class="chat-terminal">

        <!-- Column 1: Conversations List -->
        <div class="chat-conversations-pane">
          <div class="conversations-search">
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
              <input type="text" id="convSearchInput" class="form-control border-start-0 ps-0" placeholder="Filter buyer, country or mineral..." autocomplete="off">
            </div>
          </div>

          <!-- Tabs -->
          <div class="chat-filter-tabs">
            <button type="button" class="chat-filter-btn active" data-filter="active">All Active (<span id="countActive"><?= $activeCount ?></span>)</button>
            <button type="button" class="chat-filter-btn text-danger fw-bold" data-filter="waiting">Queue (<span id="countWaiting"><?= $waitingCount ?></span>)</button>
            <button type="button" class="chat-filter-btn" data-filter="my_chats">My Chats (<span id="countMy"><?= $myCount ?></span>)</button>
            <button type="button" class="chat-filter-btn" data-filter="closed">Closed</button>
          </div>

          <!-- Conversations Scroll List -->
          <div class="conversations-list" id="convListContainer">
            <div class="text-center py-4 text-muted small">Loading conversations...</div>
          </div>
        </div>

        <!-- Column 2: Active Chat View -->
        <div class="chat-active-pane" id="chatActivePane">
          
          <div class="empty-chat-screen" id="emptyChatScreen">
            <i class="bi bi-chat-square-dots fs-1 mb-2 text-secondary opacity-50"></i>
            <div class="fw-bold text-dark">No Conversation Selected</div>
            <div class="small text-muted mt-1" style="max-width: 320px;">Select an active buyer session from the queue to start live trade correspondence.</div>
          </div>

          <div id="activeChatContent" style="display: none; height: 100%; flex-direction: column;">
            
            <!-- Chat Header -->
            <div class="active-chat-header">
              <div class="d-flex align-items-center">
                <button type="button" class="btn-mobile-back" id="btnMobileBack">
                  <i class="bi bi-arrow-left"></i> List
                </button>
                <div>
                  <div class="fw-bold text-dark d-flex align-items-center gap-2" id="chatHeaderTitle">
                    <span>Guest Buyer</span>
                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.68rem;" id="chatHeaderCountry">China</span>
                  </div>
                  <div class="small text-muted" style="font-size: 0.72rem;" id="chatHeaderSub">
                    Viewing: /minerals/chromite-ore
                  </div>
                </div>
              </div>

              <!-- Header Action Buttons -->
              <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-dark" id="btnClaimChat" style="display: none;">
                  <i class="bi bi-hand-index-thumb me-1"></i>Claim Chat
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnConvertToRfq" title="Transfer lead and transcript into Inbound RFQs database">
                  <i class="bi bi-file-earmark-plus me-1"></i>Convert to RFQ
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCloseChat" title="End live chat and archive">
                  <i class="bi bi-check2-circle me-1"></i>Close
                </button>
              </div>
            </div>

            <!-- Messages Stream -->
            <div class="active-chat-body" id="activeChatBody">
              <!-- Messages dynamically populated -->
            </div>

            <!-- Footer / Input Console -->
            <div class="active-chat-footer">
              <!-- Quick Response Templates -->
              <div class="quick-templates-bar">
                <span class="small text-muted fw-bold me-1" style="font-size: 0.68rem;">Templates:</span>
                <span class="template-chip" data-text="Hello! How can our export trade desk assist with your mineral procurement today?">Greeting</span>
                <span class="template-chip" data-text="We maintain active ready stockpiles at Karachi Port (KPT) and Port Qasim with independent SGS pre-shipment assay certificates.">Stock &amp; SGS</span>
                <span class="template-chip" data-text="Our standard payment terms are 100% Irrevocable Confirmed Letter of Credit (L/C at sight) or 30% T/T deposit with balance against shipping documents.">Payment (L/C)</span>
                <span class="template-chip" data-text="Please specify your required tonnage and destination port so we can issue an official Proforma Invoice (PI) / FCO.">Request Port &amp; MT</span>
                <span class="template-chip" data-text="You can also connect directly with our Operations Director on WhatsApp at +92 334 8888104.">WhatsApp Direct</span>
              </div>

              <div class="chat-input-row">
                <textarea id="chatInputText" class="chat-input-textarea" rows="2" placeholder="Type official trade desk response... (Press Enter to send, Shift+Enter for new line)"></textarea>
                <button type="button" id="btnSendChat" class="btn btn-dark px-3" style="height: 48px;">
                  <i class="bi bi-send-fill"></i>
                </button>
              </div>
            </div>

          </div>

        </div>

        <!-- Column 3: Buyer Intelligence Dossier -->
        <div class="chat-dossier-pane" id="chatDossierPane">
          <div class="dossier-section-title">Buyer Intelligence</div>
          
          <div class="dossier-row">
            <div class="dossier-label">Buyer / Contact</div>
            <div class="dossier-val" id="dosName">Guest Buyer</div>
          </div>

          <div class="dossier-row">
            <div class="dossier-label">Email Address</div>
            <div class="dossier-val" id="dosEmail">—</div>
          </div>

          <div class="dossier-row">
            <div class="dossier-label">Phone / WhatsApp</div>
            <div class="dossier-val" id="dosPhone">—</div>
          </div>

          <div class="dossier-row">
            <div class="dossier-label">Location / IP Country</div>
            <div class="dossier-val" id="dosCountry">International</div>
          </div>

          <div class="dossier-row">
            <div class="dossier-label">Current Page</div>
            <div class="dossier-val" id="dosPage" style="font-size: 0.72rem;">—</div>
          </div>

          <div class="dossier-row">
            <div class="dossier-label">Mineral Interest</div>
            <div class="dossier-val text-primary" id="dosMineral">Export Minerals</div>
          </div>

          <div class="dossier-row">
            <div class="dossier-label">Assigned Officer</div>
            <div class="dossier-val" id="dosAgent">Unassigned</div>
          </div>

          <div class="mt-4 pt-3 border-top">
            <a href="generate-quote.php" target="_blank" class="btn btn-sm btn-outline-dark w-100 mb-2">
              <i class="bi bi-file-earmark-text me-1"></i>Open Quote Generator
            </a>
            <a href="stockpiles.php" target="_blank" class="btn btn-sm btn-outline-secondary w-100">
              <i class="bi bi-boxes me-1"></i>Check Ready Yards
            </a>
          </div>
        </div>

      </div>

    </main>
  </div>

  <script src="admin-layout.js"></script>
  <script>
    const currentAdminId = <?= (int)$currentUid ?>;
    const currentAdminName = <?= json_encode($agentName) ?>;
    let activeFilter = 'active';
    let currentSessionId = 0;
    let lastMessageId = 0;
    let pollInterval = null;
    let soundEnabled = true;

    // Web Audio Synthesized Chime (Zero external audio file needed)
    function playAudioChime() {
      if (!soundEnabled) return;
      try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc1 = audioCtx.createOscillator();
        const osc2 = audioCtx.createOscillator();
        const gain = audioCtx.createGain();

        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(880.00, audioCtx.currentTime + 0.1); // A5

        gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.35);

        osc1.connect(gain);
        osc2.connect(gain);
        gain.connect(audioCtx.destination);

        osc1.start();
        osc1.stop(audioCtx.currentTime + 0.12);
        osc2.start(audioCtx.currentTime + 0.1);
        osc2.stop(audioCtx.currentTime + 0.35);
      } catch (e) {}
    }

    // Toggle Sound
    document.getElementById('soundToggleBtn').addEventListener('click', () => {
      soundEnabled = !soundEnabled;
      const icon = document.getElementById('soundIcon');
      const label = document.getElementById('soundLabel');
      if (soundEnabled) {
        icon.className = 'bi bi-volume-up-fill text-dark me-1';
        label.textContent = 'Sound On';
        playAudioChime();
      } else {
        icon.className = 'bi bi-volume-mute-fill text-muted me-1';
        label.textContent = 'Muted';
      }
    });

    // Mobile Back Button
    document.getElementById('btnMobileBack').addEventListener('click', () => {
      document.body.classList.remove('mobile-view-chat');
    });

    // Filter Buttons
    document.querySelectorAll('.chat-filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.chat-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeFilter = btn.dataset.filter;
        fetchSessions();
      });
    });

    // Fetch Sessions Poll
    async function fetchSessions() {
      try {
        const res = await fetch(`api-chat-admin.php?action=poll_sessions&filter=${encodeURIComponent(activeFilter)}`);
        const data = await res.json();
        if (data.success) {
          document.getElementById('countActive').textContent = data.active_count || 0;
          document.getElementById('countWaiting').textContent = data.waiting_count || 0;
          document.getElementById('countMy').textContent = data.my_count || 0;
          
          const sideBadge = document.getElementById('sidebarWaitingBadge');
          if (sideBadge) {
            sideBadge.textContent = data.waiting_count || 0;
            sideBadge.style.display = data.waiting_count > 0 ? 'inline-block' : 'none';
          }

          renderConversationsList(data.sessions || []);
        }
      } catch (err) {}
    }

    function renderConversationsList(sessions) {
      const container = document.getElementById('convListContainer');
      const search = document.getElementById('convSearchInput').value.trim().toLowerCase();

      const filtered = sessions.filter(s => {
        if (!search) return true;
        return (s.name && s.name.toLowerCase().includes(search)) ||
               (s.country && s.country.toLowerCase().includes(search)) ||
               (s.mineral && s.mineral.toLowerCase().includes(search)) ||
               (s.last_message && s.last_message.toLowerCase().includes(search));
      });

      if (filtered.length === 0) {
        container.innerHTML = '<div class="text-center py-4 text-muted small">No conversations found.</div>';
        return;
      }

      container.innerHTML = filtered.map(s => {
        const isWaiting = s.status === 'waiting_human';
        const isActive = s.id === currentSessionId;
        const statusBadge = isWaiting
          ? '<span class="badge bg-danger font-monospace" style="font-size: 0.65rem;">WAITING</span>'
          : (s.status === 'agent_active' 
              ? '<span class="badge bg-success font-monospace" style="font-size: 0.65rem;">LIVE AGENT</span>'
              : '<span class="badge bg-secondary font-monospace" style="font-size: 0.65rem;">AI BOT</span>');

        const unread = s.unread_admin > 0 ? `<span class="badge rounded-pill bg-danger">${s.unread_admin}</span>` : '';

        return `
          <div class="conv-item ${isWaiting ? 'is-waiting' : ''} ${isActive ? 'active' : ''}" onclick="selectSession(${s.id})">
            <div class="conv-header">
              <div class="conv-buyer-name">
                <i class="bi bi-person-circle text-secondary"></i>
                <span class="text-truncate" style="max-width: 140px;">${escapeHtml(s.name || 'Guest Buyer')}</span>
              </div>
              <span class="conv-time font-monospace">${s.last_time || ''}</span>
            </div>
            <div class="conv-page">
              <i class="bi bi-globe2 me-1"></i>${escapeHtml(s.country || 'International')} &bull; ${escapeHtml(s.page || '/') }
            </div>
            <div class="conv-snippet">
              ${s.last_sender === 'visitor' ? '<strong>Buyer:</strong> ' : ''}${escapeHtml(s.last_message || 'Session initiated')}
            </div>
            <div class="conv-badge-row">
              <div>${statusBadge}</div>
              <div>${unread}</div>
            </div>
          </div>
        `;
      }).join('');
    }

    document.getElementById('convSearchInput').addEventListener('input', () => {
      fetchSessions();
    });

    // Select a Session
    async function selectSession(sessionId) {
      currentSessionId = sessionId;
      lastMessageId = 0;
      document.body.classList.add('mobile-view-chat');
      document.getElementById('emptyChatScreen').style.display = 'none';
      document.getElementById('activeChatContent').style.display = 'flex';

      document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
      const activeEl = document.querySelector(`.conv-item[onclick="selectSession(${sessionId})"]`);
      if (activeEl) activeEl.classList.add('active');

      await fetchMessages(true);
    }

    // Fetch Messages
    async function fetchMessages(scrollToBottom = false) {
      if (!currentSessionId) return;
      try {
        const res = await fetch(`api-chat-admin.php?action=fetch_messages&session_id=${currentSessionId}&last_id=${lastMessageId}`);
        const data = await res.json();
        if (data.success) {
          const s = data.session;
          // Update Dossier & Header
          document.getElementById('chatHeaderTitle').firstElementChild.textContent = s.visitor_name || 'Guest Buyer';
          document.getElementById('chatHeaderCountry').textContent = s.country || 'International';
          document.getElementById('chatHeaderSub').textContent = `Viewing: ${s.current_page || '/'}`;

          document.getElementById('dosName').textContent = s.visitor_name || 'Guest Buyer';
          document.getElementById('dosEmail').textContent = s.visitor_email || '—';
          document.getElementById('dosPhone').textContent = s.visitor_phone || '—';
          document.getElementById('dosCountry').textContent = `${s.country || 'International'} (IP: ${s.visitor_ip || '—'})`;
          document.getElementById('dosPage').textContent = s.current_page || '—';
          document.getElementById('dosMineral').textContent = s.mineral_interest || 'Export Minerals';
          document.getElementById('dosAgent').textContent = s.assigned_admin_name || 'Unassigned';

          // Claim button visibility
          const claimBtn = document.getElementById('btnClaimChat');
          if (s.status === 'waiting_human' || !s.assigned_admin_id) {
            claimBtn.style.display = 'inline-flex';
          } else {
            claimBtn.style.display = 'none';
          }

          if (data.messages && data.messages.length > 0) {
            let hasNewVisitorMsg = false;
            const body = document.getElementById('activeChatBody');
            
            data.messages.forEach(m => {
              if (m.id > lastMessageId) {
                lastMessageId = m.id;
                if (m.sender_type === 'visitor') hasNewVisitorMsg = true;

                const bubbleWrap = document.createElement('div');
                let wrapClass = 'chat-bubble-wrap is-visitor';
                let bubbleClass = 'chat-bubble bubble-visitor';

                if (m.sender_type === 'agent') {
                  wrapClass = 'chat-bubble-wrap is-agent';
                  bubbleClass = 'chat-bubble bubble-agent';
                } else if (m.sender_type === 'bot') {
                  wrapClass = 'chat-bubble-wrap is-bot';
                  bubbleClass = 'chat-bubble bubble-bot';
                } else if (m.sender_type === 'system') {
                  wrapClass = 'chat-bubble-wrap is-system';
                  bubbleClass = 'chat-bubble bubble-system';
                }

                bubbleWrap.className = wrapClass;
                bubbleWrap.innerHTML = `
                  <div class="${bubbleClass}">
                    ${escapeHtml(m.text).replace(/\n/g, '<br>')}
                  </div>
                  <div class="bubble-meta">
                    <span>${escapeHtml(m.sender_name)}</span> &bull; <span>${m.time}</span>
                  </div>
                `;
                body.appendChild(bubbleWrap);
              }
            });

            if (hasNewVisitorMsg) {
              playAudioChime();
            }

            if (scrollToBottom || hasNewVisitorMsg) {
              body.scrollTop = body.scrollHeight;
            }
          }
        }
      } catch (err) {}
    }

    // Send Message
    async function sendMessage() {
      const input = document.getElementById('chatInputText');
      const text = input.value.trim();
      if (!text || !currentSessionId) return;

      input.value = '';
      try {
        const res = await fetch('api-chat-admin.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'send_agent_message',
            session_id: currentSessionId,
            message: text
          })
        });
        const data = await res.json();
        if (data.success) {
          await fetchMessages(true);
          fetchSessions();
        }
      } catch (err) {}
    }

    document.getElementById('btnSendChat').addEventListener('click', sendMessage);
    document.getElementById('chatInputText').addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    // Quick Templates
    document.querySelectorAll('.template-chip').forEach(chip => {
      chip.addEventListener('click', () => {
        const input = document.getElementById('chatInputText');
        input.value = chip.dataset.text;
        input.focus();
      });
    });

    // Claim Chat
    document.getElementById('btnClaimChat').addEventListener('click', async () => {
      if (!currentSessionId) return;
      try {
        const res = await fetch('api-chat-admin.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'claim_session', session_id: currentSessionId })
        });
        const data = await res.json();
        if (data.success) {
          await fetchMessages(true);
          fetchSessions();
        }
      } catch (err) {}
    });

    // Close Chat
    document.getElementById('btnCloseChat').addEventListener('click', async () => {
      if (!currentSessionId || !confirm('Close and archive this live conversation?')) return;
      try {
        const res = await fetch('api-chat-admin.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'close_session', session_id: currentSessionId })
        });
        const data = await res.json();
        if (data.success) {
          await fetchMessages(true);
          fetchSessions();
        }
      } catch (err) {}
    });

    // Convert to RFQ
    document.getElementById('btnConvertToRfq').addEventListener('click', async () => {
      if (!currentSessionId || !confirm('Convert this conversation into an official Inbound RFQ / Lead?')) return;
      try {
        const res = await fetch('api-chat-admin.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'convert_to_rfq', session_id: currentSessionId })
        });
        const data = await res.json();
        if (data.success) {
          alert('Successfully converted to Inquiry #' + data.inquiry_id + '! You can view it in Inbound RFQs.');
          await fetchMessages(true);
          fetchSessions();
        }
      } catch (err) {}
    });

    function escapeHtml(str) {
      return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    // Auto-poll loop every 2.5 seconds
    fetchSessions();
    pollInterval = setInterval(() => {
      fetchSessions();
      if (currentSessionId) {
        fetchMessages(false);
      }
    }, 2500);
  </script>
</body>
</html>
