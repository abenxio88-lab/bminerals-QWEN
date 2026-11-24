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
$rawAgentName = trim((string)($user['full_name'] ?: ($user['username'] ?? 'Balochistan Minerals Executive')));
if ($rawAgentName === '' || strcasecmp($rawAgentName, 'Balochistan Minerals Admin') === 0 || strcasecmp($rawAgentName, 'Admin') === 0) {
    $agentName = 'Balochistan Minerals Executive';
} else {
    $agentName = $rawAgentName;
}

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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
    /* Executive Density Desktop Calibration (92% zoom scale) */
    @media (min-width: 992px) {
      body {
        zoom: 0.92;
      }
      .app-wrapper {
        min-height: calc(100vh / 0.92) !important;
        height: calc(100vh / 0.92) !important;
      }
      .app-main {
        min-height: calc(100vh / 0.92) !important;
        height: calc(100vh / 0.92) !important;
      }
      .chat-terminal {
        height: calc((100vh / 0.92) - 52px) !important;
      }
    }
    h1, h2, h3, h4, h5, h6, .brand-font { font-family: 'Montserrat', sans-serif; letter-spacing: -0.015em; }

    .app-wrapper {
      display: flex;
      min-height: 100vh;
      min-height: calc(100vh / 0.92);
      height: 100vh;
      height: calc(100vh / 0.92);
      overflow: hidden;
    }
    .app-sidebar {
      width: var(--sidebar-width);
      background: var(--brand-surface);
      border-right: 1px solid var(--brand-border);
      position: fixed;
      top: 0; bottom: 0; left: 0;
      z-index: 1050;
      display: flex;
      flex-direction: column;
      transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    body.sidebar-collapsed .app-sidebar {
      transform: translateX(-100%);
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
      background: var(--brand-orange);
      border-radius: 0 3px 3px 0;
    }
    .sidebar-link.active i { color: var(--brand-orange); }
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
      transition: margin-left 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    body.sidebar-collapsed .app-main {
      margin-left: 0 !important;
    }

    /* Sidebar Collapse & Reopen Controls */
    .btn-sidebar-toggle {
      width: 34px;
      height: 34px;
      border-radius: 6px;
      border: 1px solid #CBD5E1;
      background: #FFFFFF;
      color: #0F172A;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.15s ease;
      box-shadow: none !important;
      padding: 0;
      flex-shrink: 0;
      text-decoration: none;
    }
    .btn-sidebar-toggle:hover {
      background: #F1F5F9;
      border-color: #94A3B8;
      color: #0F172A;
    }
    .btn-sidebar-toggle i {
      font-size: 1.1rem;
    }

    @media (min-width: 1100px) {
      body:not(.sidebar-collapsed) .btn-sidebar-toggle {
        display: none !important;
      }
      body.sidebar-collapsed .btn-sidebar-toggle {
        display: inline-flex !important;
      }
    }
    @media (max-width: 1099.98px) {
      .btn-sidebar-toggle {
        display: inline-flex !important;
      }
    }

    .top-bar {
      background: var(--brand-surface);
      border-bottom: 1px solid var(--brand-border);
      padding: 8px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
      z-index: 1030;
      min-height: 52px;
      gap: 10px;
    }

    /* Live Chat Triple-Pane Terminal */
    .chat-terminal {
      display: flex;
      flex: 1;
      height: calc(100vh - 52px);
      overflow: hidden;
      background: #FFFFFF;
      width: 100%;
    }

    /* Column 1: Conversations List */
    .chat-conversations-pane {
      width: 360px;
      border-right: 1px solid var(--brand-border);
      display: flex;
      flex-direction: column;
      background: #FAFCFE;
      flex-shrink: 0;
    }

    .conversations-search {
      padding: 9px 12px;
      border-bottom: 1px solid var(--brand-border);
      background: #FFFFFF;
    }

    .chat-filter-tabs {
      display: flex;
      align-items: center;
      padding: 6px 10px;
      gap: 5px;
      border-bottom: 1px solid var(--brand-border);
      background: #F8FAFC;
      overflow-x: auto;
      scrollbar-width: none;
      -webkit-overflow-scrolling: touch;
    }
    .chat-filter-tabs::-webkit-scrollbar {
      display: none;
    }
    .chat-filter-btn {
      font-size: 0.69rem;
      font-weight: 600;
      padding: 3px 8px;
      border-radius: 9999px;
      border: 1px solid var(--brand-border);
      background: #FFFFFF;
      color: #64748B;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.15s ease;
      line-height: 1.3;
    }
    .chat-filter-btn.active {
      background: #0F172A;
      color: #FFFFFF;
      border-color: #0F172A;
    }

    .conversations-list {
      flex: 1;
      overflow-y: auto;
      padding: 8px;
    }

    /* Top Bar Controls */
    .desk-online-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #FFFFFF;
      border: 1px solid #CBD5E1;
      border-radius: 20px;
      padding: 3.5px 10px;
      font-size: 0.70rem;
      font-weight: 700;
      color: #0F172A;
      letter-spacing: 0.03em;
      white-space: nowrap;
    }
    .desk-online-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #059669;
      flex-shrink: 0;
    }
    .desk-online-count {
      color: #64748B;
      font-weight: 600;
    }

    .btn-desk-control {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #FFFFFF !important;
      border: 1px solid #CBD5E1 !important;
      border-radius: 6px !important;
      padding: 5px 11px !important;
      font-size: 0.74rem !important;
      font-weight: 600 !important;
      color: #0F172A !important;
      cursor: pointer !important;
      height: 31px !important;
      transition: all 0.15s ease !important;
      box-shadow: none !important;
    }
    .btn-desk-control:hover {
      background: #F8FAFC !important;
      border-color: #94A3B8 !important;
      color: #0F172A !important;
    }
    .desk-sync-control {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #FFFFFF !important;
      border: 1px solid #CBD5E1 !important;
      border-radius: 6px !important;
      padding: 5px 11px !important;
      font-size: 0.73rem !important;
      font-weight: 600 !important;
      color: #475569 !important;
      height: 31px !important;
      box-shadow: none !important;
      white-space: nowrap !important;
    }
    .desk-sync-control i {
      color: #059669;
      font-size: 0.85rem;
    }

    /* Status Badges - Deep Executive Palette (Zero Neon / Zero Cartoon) */
    /* Status Badges - Bespoke Swiss Executive Design (Zero Monospace / Zero Chunky Borders) */
    .badge-status-waiting {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 8px;
      border-radius: 9999px;
      font-size: 0.58rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      background: #FFFFFF !important;
      color: #F97316 !important;
      border: 1px solid #FED7AA !important;
      box-shadow: none !important;
      text-shadow: none !important;
      white-space: nowrap;
      line-height: 1.2;
      font-family: inherit;
    }
    .badge-status-waiting .status-dot {
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: #F97316;
      box-shadow: none !important;
      animation: pulseOrangeDot 2s infinite ease-in-out;
      flex-shrink: 0;
    }
    @keyframes pulseOrangeDot {
      0%, 100% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.35); opacity: 0.55; }
    }

    .badge-status-live {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 9px;
      border-radius: 9999px;
      font-size: 0.58rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      background: #F0FDF4;
      color: #047857;
      border: 1px solid #A7F3D0;
      box-shadow: 0 1px 2px rgba(4, 120, 87, 0.05);
      white-space: nowrap;
      line-height: 1.2;
    }
    .badge-status-live .status-dot {
      width: 5.5px;
      height: 5.5px;
      border-radius: 50%;
      background: #10B981;
      box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.22);
      animation: pulseGreenDot 2s infinite ease-in-out;
      flex-shrink: 0;
    }
    @keyframes pulseGreenDot {
      0%, 100% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.35); opacity: 0.55; }
    }

    .badge-status-bot {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 9px;
      border-radius: 9999px;
      font-size: 0.58rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      background: #F8FAFC;
      color: #475569;
      border: 1px solid #E2E8F0;
      white-space: nowrap;
      line-height: 1.2;
    }
    .badge-status-bot .status-dot {
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: #94A3B8;
      flex-shrink: 0;
    }

    .badge-status-unread {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 18px;
      height: 18px;
      padding: 0 5px;
      border-radius: 9999px;
      font-size: 0.62rem;
      font-weight: 700;
      background: #0F172A;
      color: #FFFFFF;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.25);
      line-height: 1;
      border: 1.5px solid #FFFFFF;
      margin-top: 1px;
    }

    /* Multi-Channel Indicators - Executive Precision (Zero cartoon / Zero all-caps neon) */
    .badge-channel-item {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 8px;
      border-radius: 6px;
      font-size: 0.70rem;
      font-weight: 600;
      letter-spacing: 0.01em;
      line-height: 1.25;
      flex-shrink: 0;
      text-transform: none;
      vertical-align: middle;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }
    .badge-channel-whatsapp {
      background: #F8FAFC;
      color: #0F172A;
      border: 1px solid #E2E8F0;
    }
    .badge-channel-instagram {
      background: #F8FAFC;
      color: #0F172A;
      border: 1px solid #E2E8F0;
    }
    .badge-channel-facebook {
      background: #F8FAFC;
      color: #0F172A;
      border: 1px solid #E2E8F0;
    }
    .badge-channel-web {
      background: #F8FAFC;
      color: #0F172A;
      border: 1px solid #E2E8F0;
    }

    /* Executive Dossier WhatsApp Action Button */
    .btn-dossier-wa-action {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 8px;
      background: #FFFFFF;
      border: 1px solid #CBD5E1;
      border-radius: 5px;
      color: #0F172A;
      font-size: 0.70rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.15s ease;
      white-space: nowrap;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }
    .btn-dossier-wa-action:hover {
      background: #F8FAFC;
      border-color: #94A3B8;
      color: #0284C7;
      transform: translateY(-0.5px);
    }

    /* Borderless Executive Manage Link for Dossier Channel Source */
    .btn-dos-manage-link {
      border: none !important;
      background: transparent !important;
      padding: 0 2px !important;
      color: #0284C7 !important;
      font-size: 0.72rem !important;
      font-weight: 600 !important;
      line-height: 1.2 !important;
      cursor: pointer;
      text-decoration: none !important;
      outline: none !important;
      box-shadow: none !important;
      transition: color 0.15s ease;
    }
    .btn-dos-manage-link:hover {
      color: #0369A1 !important;
      text-decoration: underline !important;
    }

    /* Channel Filter Pills Slider with Dual Arrow Navigation on the Right */
    .chat-channel-slider-wrap {
      display: flex;
      align-items: center;
      background: #FFFFFF;
      border-bottom: 1px solid var(--brand-border);
      position: relative;
      padding: 2px 6px 2px 8px;
      gap: 6px;
    }
    .chat-channel-pills {
      display: flex;
      align-items: center;
      gap: 5px;
      padding: 4px 2px;
      background: transparent;
      border-bottom: none;
      overflow-x: auto;
      scrollbar-width: none;
      scroll-behavior: smooth;
      flex: 1;
      min-width: 0;
    }
    .chat-channel-pills::-webkit-scrollbar {
      display: none;
    }
    .chat-channel-arrows-wrap {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      flex-shrink: 0;
      padding-left: 6px;
      border-left: 1px solid #E2E8F0;
      background: #FFFFFF;
      z-index: 2;
    }
    .btn-channel-slide {
      width: 22px;
      height: 24px;
      border: 1px solid #E2E8F0;
      background: #F8FAFC;
      color: #64748B;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      border-radius: 4px;
      font-size: 0.72rem;
      flex-shrink: 0;
      transition: all 0.15s ease;
      padding: 0;
    }
    .btn-channel-slide:hover {
      background: #0F172A;
      color: #FFFFFF;
      border-color: #0F172A;
    }
    .btn-channel-slide:active {
      transform: scale(0.92);
    }
    .channel-pill {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      height: 24px;
      padding: 0 9px;
      border-radius: 12px;
      font-size: 0.67rem;
      font-weight: 600;
      border: 1px solid var(--brand-border);
      background: #F8FAFC;
      color: #475569;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.15s ease;
      flex-shrink: 0;
    }
    .channel-pill:hover {
      background: #F1F5F9;
      color: #0F172A;
      border-color: #CBD5E1;
    }
    .channel-pill.active {
      background: #0F172A;
      color: #FFFFFF;
      border-color: #0F172A;
    }
    .channel-pill.active i {
      color: #FFFFFF !important;
    }

    /* WhatsApp / Messenger style conversation list items */
    .conv-item {
      display: flex;
      align-items: center;
      gap: 11px;
      padding: 10px 12px;
      border-radius: 8px;
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      margin-bottom: 6px;
      cursor: pointer;
      transition: all 0.15s ease;
      position: relative;
    }
    .conv-item:hover {
      border-color: #94A3B8;
      box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .conv-item.active {
      border-color: #0F172A;
      background: #F8FAFC;
      border-left: 3.5px solid #0F172A;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    }
    .conv-item.is-waiting {
      border-left: 3.5px solid #D97706;
    }
    .conv-avatar {
      position: relative;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #F8FAFC;
      color: #0F172A;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.88rem;
      flex-shrink: 0;
      border: 1px solid #CBD5E1;
    }

    /* High-Quality Official Brand SVG Icon Overlay on Avatar Bottom-Right (Zero White Circle) */
    .conv-channel-badge {
      position: absolute;
      bottom: -3px;
      right: -3px;
      width: 18px;
      height: 18px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
      filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.3));
      z-index: 3;
      flex-shrink: 0;
      pointer-events: none;
    }
    .conv-channel-badge svg {
      width: 18px;
      height: 18px;
      display: block;
    }
    .conv-content {
      flex: 1;
      min-width: 0;
    }
    .conv-top-line {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 6px;
      margin-bottom: 2px;
    }
    .conv-name {
      font-weight: 600;
      font-size: 0.82rem;
      color: #0F172A;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .conv-time {
      font-size: 0.68rem;
      color: #94A3B8;
      flex-shrink: 0;
    }
    .conv-subline {
      font-size: 0.7rem;
      color: #64748B;
      display: flex;
      align-items: center;
      gap: 5px;
      margin-bottom: 2px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .conv-snippet {
      font-size: 0.74rem;
      color: #475569;
      line-height: 1.35;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .conv-badge-col {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 3px;
      flex-shrink: 0;
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
      padding: 9px 16px;
      border-bottom: 1px solid var(--brand-border);
      background: #FFFFFF;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
      gap: 10px;
      min-height: 52px;
    }

    .active-chat-buyer-wrap {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 0;
      flex: 1;
    }

    .active-chat-avatar {
      position: relative;
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: #F8FAFC;
      color: #0F172A;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.82rem;
      flex-shrink: 0;
      border: 1px solid #CBD5E1;
    }
    .active-chat-avatar .conv-channel-badge {
      width: 16px;
      height: 16px;
      bottom: -2px;
      right: -2px;
    }
    .active-chat-avatar .conv-channel-badge svg {
      width: 16px;
      height: 16px;
    }

    .active-chat-info {
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 1px;
      overflow: hidden;
    }

    .active-chat-name {
      font-size: 0.88rem;
      font-weight: 700;
      color: #0F172A;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      line-height: 1.25;
    }

    .active-chat-sub {
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 0.68rem;
      color: #64748B;
      line-height: 1.2;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .active-chat-country {
      color: #475569;
      font-weight: 600;
      flex-shrink: 0;
      letter-spacing: 0.02em;
    }

    .active-chat-sep {
      color: #CBD5E1;
      font-size: 0.65rem;
      flex-shrink: 0;
    }

    .active-chat-sub-label {
      color: #94A3B8;
      font-weight: 500;
      flex-shrink: 0;
    }

    .active-chat-path {
      font-family: var(--font-mono, monospace);
      font-size: 0.68rem;
      color: #0F172A;
      background: #F1F5F9;
      padding: 0 5px;
      border-radius: 3px;
      border: 1px solid #E2E8F0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 140px;
    }

    /* Action Buttons in Chat Header with Clear Hierarchy */
    .active-chat-actions {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-shrink: 0;
    }

    .btn-chat-header {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      height: 28px;
      padding: 0 9px;
      font-size: 0.72rem;
      font-weight: 600;
      border-radius: 5px;
      border: 1px solid #CBD5E1;
      cursor: pointer;
      transition: all 0.15s ease;
      white-space: nowrap;
      text-decoration: none;
      box-shadow: none !important;
    }

    .btn-chat-header i {
      font-size: 0.82rem;
      flex-shrink: 0;
    }

    .btn-chat-header--claim {
      background: #0F172A !important;
      color: #FFFFFF !important;
      border-color: #0F172A !important;
    }
    .btn-chat-header--claim:hover {
      background: #1E293B !important;
      border-color: #1E293B !important;
      color: #FFFFFF !important;
    }

    .btn-chat-header--rfq {
      background: #FFFFFF !important;
      color: #0F172A !important;
      border-color: #CBD5E1 !important;
    }
    .btn-chat-header--rfq:hover {
      background: #F8FAFC !important;
      border-color: #94A3B8 !important;
      color: #0F172A !important;
    }

    .btn-chat-header--close {
      background: #FFFFFF !important;
      color: #475569 !important;
      border-color: #CBD5E1 !important;
    }
    .btn-chat-header--close:hover {
      background: #FEF2F2 !important;
      border-color: #FECACA !important;
      color: #991B1B !important;
    }

    .active-chat-body {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      padding: 14px;
      background: #F8FAFC;
      display: flex;
      flex-direction: column;
      gap: 10px;
      touch-action: pan-y;
      -webkit-overflow-scrolling: touch;
    }

    /* Message Rows & Side Avatars */
    .chat-msg-row {
      display: flex;
      gap: 8px;
      align-items: flex-end;
      width: 100%;
      margin-bottom: 6px;
    }
    .chat-msg-row.is-visitor {
      justify-content: flex-start;
    }
    .chat-msg-row.is-agent {
      justify-content: flex-end;
    }
    .chat-msg-row.is-system {
      justify-content: center;
      margin: 4px 0;
    }
    .chat-msg-row.is-bot {
      justify-content: flex-start;
    }

    .chat-msg-avatar {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      border: 1px solid #CBD5E1;
      margin-bottom: 12px;
    }
    .chat-msg-avatar--visitor {
      background: #F1F5F9;
      color: #475569;
      font-size: 0.8rem;
    }
    .chat-msg-avatar--agent {
      background: #FFFFFF;
      border-color: #CBD5E1;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      overflow: hidden;
      padding: 3px;
    }
    .chat-avatar-logo {
      width: 100%;
      height: 100%;
      object-fit: contain;
      border-radius: 50%;
    }
    .chat-msg-avatar--bot {
      background: #EFF6FF;
      color: #2563EB;
      font-size: 0.8rem;
    }

    /* Message Bubbles */
    .chat-bubble-wrap {
      display: flex;
      flex-direction: column;
      max-width: 78%;
    }
    .chat-bubble-wrap.is-visitor {
      align-items: flex-start;
    }
    .chat-bubble-wrap.is-agent {
      align-items: flex-end;
    }
    .chat-bubble-wrap.is-bot {
      max-width: 82%;
    }
    .chat-bubble-wrap.is-system {
      max-width: 92%;
      align-items: center;
    }

    .chat-bubble {
      padding: 8px 13px;
      border-radius: 12px;
      font-size: 0.82rem;
      line-height: 1.45;
      position: relative;
      word-break: break-word;
      overflow-wrap: break-word;
    }
    .chat-bubble.bubble-visitor {
      background: #FFFFFF;
      color: #0F172A;
      border: 1px solid var(--brand-border);
      border-radius: 12px 12px 12px 3px;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    }
    .chat-bubble.bubble-agent {
      background: #F1F5F9; /* Clean executive slate neutral - ZERO ORANGE & ZERO BLACK */
      color: #0F172A;      /* Deep midnight slate text, sharp and readable */
      border: 1px solid #CBD5E1;
      border-radius: 12px 12px 3px 12px;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    }
    .chat-bubble.bubble-bot {
      background: #F1F5F9;
      color: #334155;
      border: 1px dashed #CBD5E1;
      border-radius: 12px 12px 12px 3px;
      font-size: 0.78rem;
    }
    .chat-bubble.bubble-system {
      background: #FFFFFF;
      color: #C2410C;
      border: 1px solid #FED7AA;
      font-size: 0.72rem;
      font-weight: 500;
      text-align: center;
      padding: 5px 14px;
      border-radius: 9999px;
      line-height: 1.4;
      word-break: break-word;
      box-shadow: 0 1px 3px rgba(230, 92, 26, 0.05);
    }

    .bubble-meta {
      font-size: 0.65rem;
      margin-top: 2px;
      color: #94A3B8;
      display: flex;
      align-items: center;
      gap: 4px;
      padding: 0 2px;
    }
    .chat-bubble-wrap.is-agent .bubble-meta {
      justify-content: flex-end;
    }

    /* Chat Footer & Input */
    .active-chat-footer {
      padding: 8px 12px calc(8px + env(safe-area-inset-bottom, 0px)) 12px;
      border-top: 1px solid var(--brand-border);
      background: #FFFFFF;
      flex-shrink: 0;
      z-index: 10;
    }

    .quick-templates-bar {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 6px;
      overflow-x: auto;
      scrollbar-width: none;
      -webkit-overflow-scrolling: touch;
      padding-bottom: 2px;
    }
    .quick-templates-bar::-webkit-scrollbar {
      display: none;
    }
    .template-chip {
      font-size: 0.68rem;
      font-weight: 500;
      padding: 3px 9px;
      border-radius: 9999px;
      border: 1px solid var(--brand-border);
      background: #F8FAFC;
      color: #475569;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.15s ease;
      flex-shrink: 0;
    }
    .template-chip:hover, .template-chip:active {
      background: #0F172A;
      color: #FFFFFF;
      border-color: #0F172A;
    }

    .chat-input-row {
      display: flex;
      gap: 8px;
      align-items: center;
      width: 100%;
    }
    .chat-input-textarea {
      flex: 1;
      resize: none;
      border-radius: 20px;
      border: 1px solid var(--brand-border);
      padding: 8px 14px;
      font-size: 0.8125rem;
      line-height: 1.35;
      min-height: 38px;
      max-height: 85px;
      outline: none;
      font-family: inherit;
      background: #F8FAFC;
      box-sizing: border-box;
      transition: border-color 0.2s, background-color 0.2s;
    }
    .chat-input-textarea::placeholder {
      font-size: 0.75rem;
      color: #94A3B8;
      font-weight: 400;
      letter-spacing: 0.01em;
    }
    .chat-input-textarea:focus {
      border-color: #0F172A;
      background: #FFFFFF;
    }

    .btn-send-message {
      width: 38px;
      height: 38px;
      border-radius: 8px;
      background: #0F172A;
      color: #FFFFFF;
      border: 1px solid #0F172A;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.95rem;
      flex-shrink: 0;
      cursor: pointer;
      transition: all 0.15s ease;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.15);
    }
    .btn-send-message:hover {
      background: #1E293B;
      border-color: #1E293B;
    }
    .btn-send-message:active {
      transform: scale(0.95);
    }

    /* Column 3: Buyer Dossier (Right Pane) */
    .chat-dossier-pane {
      width: 290px;
      border-left: 1px solid var(--brand-border);
      background: #FAFCFE;
      padding: 14px 16px;
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
      font-size: 0.65rem;
      color: #94A3B8;
      text-transform: uppercase;
      font-weight: 700;
      letter-spacing: 0.04em;
      margin-bottom: 2px;
    }
    .dossier-val {
      font-size: 0.8rem;
      font-weight: 600;
      color: #0F172A;
      word-break: break-word;
      line-height: 1.35;
    }
    .dossier-time-card {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 6px;
      padding: 7px 10px;
    }
    .dossier-link {
      color: #0F172A;
      text-decoration: none;
      transition: color 0.15s ease;
    }
    .dossier-link:hover {
      color: #E65C1A;
      text-decoration: underline;
    }
    .conv-avatar-flag {
      display: none !important;
    }
    .active-chat-time {
      font-size: 0.68rem;
      font-weight: 600;
      color: #0F172A;
      background: #F1F5F9;
      padding: 1px 5px;
      border-radius: 3px;
      border: 1px solid #E2E8F0;
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

    @media (max-width: 991.98px) {
      .app-sidebar {
        display: flex !important;
        transform: translateX(-100%);
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
        z-index: 1050 !important;
      }
      body.sidebar-open .app-sidebar {
        transform: translateX(0) !important;
      }
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
      body.mobile-view-chat .top-bar {
        display: none !important;
      }
      body.mobile-view-chat .chat-conversations-pane {
        display: none !important;
      }
      body.mobile-view-chat .chat-terminal {
        height: 100vh !important;
        height: 100dvh !important;
      }
      body.mobile-view-chat .chat-active-pane {
        display: flex !important;
        height: 100vh !important;
        height: 100dvh !important;
      }
    }

    @media (max-width: 575.98px) {
      .active-chat-header {
        padding: 6px 10px;
        min-height: 46px;
        gap: 6px;
      }
      .active-chat-buyer-wrap {
        gap: 8px;
        min-width: 0;
      }
      .btn-mobile-back {
        margin-right: 0 !important;
        width: 24px;
        height: 26px;
        font-size: 1.1rem;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
      }
      .active-chat-avatar {
        width: 28px;
        height: 28px;
        font-size: 0.72rem;
      }
      .active-chat-info {
        gap: 0;
        min-width: 0;
      }
      .active-chat-name {
        font-size: 0.82rem;
        max-width: 130px;
        line-height: 1.2;
      }
      .active-chat-sub {
        gap: 4px;
        font-size: 0.64rem;
        line-height: 1.15;
      }
      .active-chat-country {
        font-size: 0.64rem;
      }
      .active-chat-path {
        font-size: 0.62rem;
        padding: 0 4px;
        max-width: 80px;
      }
      .active-chat-actions {
        gap: 4px;
      }
      .btn-chat-header {
        height: 26px;
        padding: 0 7px;
        font-size: 0.68rem;
        border-radius: 4px;
      }
      .btn-chat-header i {
        font-size: 0.78rem;
      }
      .chat-input-textarea {
        font-size: 0.8125rem;
        padding: 6px 12px;
        min-height: 36px;
      }
      .chat-input-textarea::placeholder {
        font-size: 0.72rem;
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
        <div class="sidebar-footer-row-user d-flex align-items-center justify-content-between gap-2 mb-1">
          <div class="fw-bold small text-truncate" style="color: var(--brand-navy); font-size: 0.8rem;" title="<?= htmlspecialchars($agentName) ?>"><?= htmlspecialchars($agentName) ?></div>
          <a href="logout.php" class="btn btn-sm btn-outline-secondary p-1 px-2 flex-shrink-0" title="Sign Out">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </div>
        <div class="sidebar-footer-row-meta d-flex align-items-center justify-content-between gap-2">
          <div class="text-muted text-truncate" style="font-size: 0.7rem;">Commercial Export Desk</div>
          <span class="bm-role-tier-pill tier-owner px-2 py-0.5 font-monospace flex-shrink-0" style="font-size:0.68rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;background:#FFFFFF;color:#E65C1A;border:none;border-radius:6px;"><?= ($user['role'] ?? '') === 'Owner' ? 'CEO' : htmlspecialchars($user['role'] ?? 'CEO') ?></span>
        </div>
      </div>
    </aside>

    <!-- Mobile / Tablet Backdrop Overlay -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- 2. MAIN WORKSPACE -->
    <main class="app-main">

      <!-- Top Bar -->
      <header class="top-bar">
        <div class="topbar-left d-flex align-items-center gap-2 min-w-0">
          <button type="button" id="sidebarToggle" class="btn-sidebar-toggle" title="Toggle Sidebar (Ctrl + B)" aria-label="Toggle Sidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
          </button>
          <div class="brand-font fw-bold" style="font-size: 0.95rem; color: #0F172A; white-space: nowrap;">
            <span class="d-none d-sm-inline">Live </span>Chat Desk
          </div>
          <div class="desk-online-badge" title="Active Trade Desk Officers in Portal">
            <span class="desk-online-dot"></span>
            <span>ONLINE <span class="desk-online-count">(<?= $onlineCount ?>)</span></span>
          </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-shrink-0">
          <!-- WhatsApp QR Login & Meta Hub Button -->
          <button type="button" id="btnMetaHub" class="btn-desk-control d-inline-flex align-items-center gap-1.5" title="Scan QR Code to Link WhatsApp on your phone, or manage Instagram & Facebook" style="background: #F0FDF4; border: 1px solid #86EFAC; color: #166534;">
            <i class="bi bi-qr-code" style="color: #16A34A; font-size: 0.95rem;"></i>
            <span class="fw-bold" style="font-size: 0.76rem;">WhatsApp QR Login</span>
            <span class="badge bg-success" id="metaHubBadge" style="font-size: 0.58rem; padding: 2px 5px;">LIVE</span>
          </button>
          <!-- Audio Alert Toggle -->
          <button type="button" id="soundToggleBtn" class="btn-desk-control" title="Toggle audio chime on new buyer message">
            <i class="bi bi-volume-up-fill" id="soundIcon"></i>
            <span class="d-none d-sm-inline" id="soundLabel">Sound On</span>
          </button>
          <!-- Live Indicator Pill -->
          <div class="desk-sync-control" title="Real-time live sync active (2.5s)">
            <i class="bi bi-arrow-repeat"></i>
            <span><span class="d-none d-sm-inline">Auto-Sync </span><strong>2.5s</strong></span>
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

          <!-- Channel Filter Pills with Arrow Slider on Right -->
          <div class="chat-channel-slider-wrap">
            <div class="chat-channel-pills" id="channelFilterPills">
              <button type="button" class="channel-pill active" data-channel="all"><i class="bi bi-grid-fill"></i> All (<span id="countAllChannels">0</span>)</button>
              <button type="button" class="channel-pill" data-channel="whatsapp"><i class="bi bi-whatsapp" style="color:#25D366;"></i> WhatsApp (<span id="countWhatsApp">0</span>)</button>
              <button type="button" class="channel-pill" data-channel="instagram"><i class="bi bi-instagram" style="color:#E1306C;"></i> Instagram (<span id="countInstagram">0</span>)</button>
              <button type="button" class="channel-pill" data-channel="facebook"><i class="bi bi-messenger" style="color:#1877F2;"></i> Facebook (<span id="countFacebook">0</span>)</button>
              <button type="button" class="channel-pill" data-channel="web" title="Website AI Chatbot Buddy"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M6 3h12l4 6-10 12L2 9z"></path><path d="M11 3v6l-4 3"></path><path d="M13 3v6l4 3"></path></svg> Chatbot (<span id="countWeb">0</span>)</button>
            </div>
            <div class="chat-channel-arrows-wrap">
              <button type="button" class="btn-channel-slide" id="btnChannelSlidePrev" title="Slide channels left" aria-label="Previous channels">
                <i class="bi bi-chevron-left"></i>
              </button>
              <button type="button" class="btn-channel-slide" id="btnChannelSlideNext" title="Slide channels right" aria-label="Next channels">
                <i class="bi bi-chevron-right"></i>
              </button>
            </div>
          </div>

          <!-- Tabs -->
          <div class="chat-filter-tabs">
            <button type="button" class="chat-filter-btn active" data-filter="active">Active (<span id="countActive"><?= $activeCount ?></span>)</button>
            <button type="button" class="chat-filter-btn" data-filter="waiting">Queue (<span id="countWaiting"><?= $waitingCount ?></span>)</button>
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
              <div class="active-chat-buyer-wrap">
                <button type="button" class="btn-mobile-back d-lg-none" id="btnMobileBack" aria-label="Back to conversations list">
                  <i class="bi bi-chevron-left"></i>
                </button>
                <div class="active-chat-avatar" id="chatHeaderAvatar">
                  <span id="chatHeaderInitial">G</span>
                </div>
                <div class="active-chat-info">
                  <div class="d-flex align-items-center gap-1.5 flex-wrap">
                    <div class="active-chat-name" id="chatHeaderTitleText">Guest Buyer</div>
                    <span id="chatHeaderChannelBadge"></span>
                  </div>
                  <div class="active-chat-sub" id="chatHeaderSub">
                    <span class="active-chat-country" id="chatHeaderCountry">International</span>
                    <span class="active-chat-sep">&bull;</span>
                    <span class="active-chat-sub-label d-none d-md-inline">Local:</span>
                    <span class="active-chat-time font-monospace d-none d-sm-inline" id="chatHeaderLocalTime">—</span>
                    <span class="active-chat-sep d-none d-sm-inline">&bull;</span>
                    <span class="active-chat-sub-label d-none d-sm-inline" id="chatHeaderOriginLabel">Viewing:</span>
                    <span class="active-chat-path" id="chatHeaderSubPath">/</span>
                  </div>
                </div>
              </div>

              <!-- Header Action Buttons with Clear Executive Hierarchy -->
              <div class="active-chat-actions">
                <button type="button" class="btn-chat-header btn-chat-header--claim" id="btnClaimChat" style="display: none;" title="Claim this session to take over live correspondence">
                  <i class="bi bi-shield-check"></i>
                  <span class="d-none d-sm-inline">Claim</span>
                </button>
                <button type="button" class="btn-chat-header btn-chat-header--close" id="btnCloseChat" title="End live chat and archive session">
                  <i class="bi bi-x-circle"></i>
                  <span class="d-none d-sm-inline">Close</span>
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
                <textarea id="chatInputText" class="chat-input-textarea" rows="1" placeholder="Type official reply... (Enter to send)"></textarea>
                <button type="button" id="btnSendChat" class="btn btn-dark px-3 btn-send-message" aria-label="Send Message" style="height: 40px; min-width: 44px; border-radius: 8px;">
                  <i class="bi bi-send-fill"></i>
                </button>
              </div>
            </div>

          </div>

        </div>

        <!-- Column 3: Buyer Intelligence Dossier -->
        <div class="chat-dossier-pane" id="chatDossierPane">
          <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
            <div class="dossier-section-title mb-0 border-0 p-0">Buyer Intelligence</div>
            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" id="btnToggleDossierEdit" title="Edit buyer contact details" style="font-size: 0.68rem; height: 23px; border-radius: 4px;">
              <i class="bi bi-pencil-square me-1"></i><span id="btnEditLabel">Edit</span>
            </button>
          </div>

          <!-- Buyer Local Time Card -->
          <div class="dossier-time-card mb-3 p-2 rounded" style="background: #F8FAFC; border: 1px solid #E2E8F0;">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <span class="text-muted" style="font-size: 0.64rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Buyer Local Time</span>
              <span class="badge font-monospace" style="background:#E2E8F0; color:#334155; font-size:0.62rem;" id="dosTimezone">UTC</span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-clock-history text-secondary" style="font-size: 0.92rem;"></i>
              <span class="fw-bold font-monospace text-dark" style="font-size: 0.88rem;" id="dosLocalTime">—</span>
            </div>
          </div>

          <!-- Buyer Name -->
          <div class="dossier-row">
            <div class="dossier-label">Buyer / Contact</div>
            <div class="dossier-val" id="dosNameView">Buyer #—</div>
            <div id="dosNameEditWrap" style="display: none;" class="mt-1">
              <input type="text" id="inputDosName" class="form-control form-control-sm" placeholder="e.g. Li Wei (Orient Trading)" style="font-size: 0.75rem;">
            </div>
          </div>

          <!-- Location & Origin -->
          <div class="dossier-row">
            <div class="dossier-label">Location / Origin</div>
            <div class="dossier-val" id="dosCountryView"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="d-inline-block me-1" style="vertical-align: -2px; color: #475569;"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>International</div>
            <div class="text-muted font-monospace mt-0.5" style="font-size: 0.67rem;" id="dosIpView">IP: —</div>
          </div>

          <!-- Channel / Platform Origin -->
          <div class="dossier-row">
            <div class="dossier-label">Platform Channel</div>
            <div class="dossier-val d-flex align-items-center gap-1.5" id="dosChannelView">
              <span id="dosChannelBadge" class="badge-channel-item badge-channel-web"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1.5px;flex-shrink:0;"><path d="M6 3h12l4 6-10 12L2 9z"></path><path d="M11 3v6l-4 3"></path><path d="M13 3v6l4 3"></path></svg> <span>Web Desk</span></span>
              <span id="dosChannelIdText" class="font-monospace text-muted" style="font-size:0.67rem;"></span>
            </div>
          </div>

          <!-- Email Address -->
          <div class="dossier-row">
            <div class="dossier-label">Email Address</div>
            <div class="dossier-val" id="dosEmailView">—</div>
            <div id="dosEmailEditWrap" style="display: none;" class="mt-1">
              <input type="email" id="inputDosEmail" class="form-control form-control-sm" placeholder="buyer@company.com" style="font-size: 0.75rem;">
            </div>
          </div>

          <!-- Phone / WhatsApp -->
          <div class="dossier-row">
            <div class="dossier-label">Phone / WhatsApp</div>
            <div class="dossier-val" id="dosPhoneView">—</div>
            <div id="dosPhoneEditWrap" style="display: none;" class="mt-1">
              <input type="text" id="inputDosPhone" class="form-control form-control-sm" placeholder="+86 138 0000 0000" style="font-size: 0.75rem;">
            </div>
          </div>

          <!-- Current Page / Channel Navigation -->
          <div class="dossier-row">
            <div class="dossier-label" id="dosPageRowLabel">Current Page</div>
            <div class="dossier-val" id="dosPageView">
              <a href="/" target="_blank" id="dosPageLink" class="text-dark text-decoration-none font-monospace d-inline-flex align-items-center gap-1 p-1 px-1.5 rounded" style="background:#F1F5F9; border:1px solid #CBD5E1; font-size:0.7rem; max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                <i class="bi bi-box-arrow-up-right text-primary" style="font-size:0.65rem;"></i>
                <span id="dosPageText">/</span>
              </a>
            </div>
          </div>

          <!-- Mineral Interest -->
          <div class="dossier-row">
            <div class="dossier-label">Mineral Interest</div>
            <div class="dossier-val" id="dosMineralView">
              <span class="badge" style="background: #F1F5F9; color: #0F172A; border: 1px solid #CBD5E1; font-size: 0.72rem; font-weight: 600; padding: 4px 10px; border-radius: 6px;" id="dosMineralBadge">Export Minerals</span>
            </div>
            <div id="dosMineralEditWrap" style="display: none;" class="mt-1">
              <select id="selectDosMineral" class="form-select form-select-sm" style="font-size: 0.75rem;">
                <option value="Export Minerals">Export Minerals (General)</option>
                <option value="Barite 4.2+ SG">Barite 4.2+ SG (API 13A)</option>
                <option value="Chromite 42-52%">Chromite 42-52% (Lumpy)</option>
                <option value="Chagai Copper DSO">Chagai Copper DSO</option>
                <option value="Iron Ore (62%+ Fe)">Iron Ore (62%+ Fe)</option>
                <option value="Fluorspar (CaF2 85%+)">Fluorspar (CaF2 85%+)</option>
                <option value="Balochistan Marble &amp; Onyx">Balochistan Marble &amp; Onyx</option>
                <option value="Manganese Ore">Manganese Ore</option>
                <option value="Antimony Ore">Antimony Ore</option>
              </select>
            </div>
          </div>

          <!-- Assigned Officer -->
          <div class="dossier-row">
            <div class="dossier-label">Assigned Officer</div>
            <div class="dossier-val" id="dosAgent">Unassigned</div>
          </div>

          <!-- Internal Trade Desk Notes -->
          <div class="dossier-row mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <div class="dossier-label mb-0">Trade Desk Notes</div>
              <span id="notesSavedIndicator" class="text-success small" style="display:none; font-size:0.64rem;"><i class="bi bi-check-circle-fill"></i> Saved</span>
            </div>
            <textarea id="dosInternalNotes" class="form-control form-control-sm" rows="2" placeholder="Record buyer target price, port, terms..." style="font-size: 0.73rem;"></textarea>
            <button type="button" id="btnSaveNotes" class="btn btn-xs btn-outline-dark mt-1 w-100 py-1" style="font-size: 0.68rem; font-weight: 600;">
              <i class="bi bi-save me-1"></i>Save Notes
            </button>
          </div>

          <!-- Save / Cancel Buttons in Edit Mode -->
          <div id="dossierEditActions" style="display: none;" class="mb-3">
            <button type="button" class="btn btn-sm btn-dark w-100 mb-1" id="btnSaveDossier" style="font-size: 0.74rem;">
              <i class="bi bi-check-lg me-1"></i>Save Changes
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="btnCancelDossierEdit" style="font-size: 0.72rem;">
              Cancel
            </button>
          </div>

          <!-- Quick Action Conversion Buttons -->
          <div class="pt-2 border-top">
            <button type="button" class="btn btn-sm btn-outline-primary w-100 mb-2" id="dosBtnRfq" style="font-size: 0.75rem; font-weight: 600;">
              <i class="bi bi-file-earmark-plus me-1"></i>Convert to RFQ
            </button>
            <a href="stockpiles.php" target="_blank" class="btn btn-sm btn-outline-secondary w-100" style="font-size: 0.75rem;">
              <i class="bi bi-boxes me-1"></i>Check Ready Yards
            </a>
          </div>
        </div>

      </div>

    </main>
  </div>

  <!-- Meta Accounts & Channels Hub Modal -->
  <div class="modal fade" id="metaHubModal" tabindex="-1" aria-labelledby="metaHubModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content" style="border: 1px solid var(--brand-border); box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25); border-radius: 14px; overflow: hidden;">
        
        <!-- Modal Header (Clean Executive Light Theme - Zero Jarring Black Bar) -->
        <div class="modal-header" style="background: #FFFFFF; color: #0F172A; padding: 18px 24px; border-bottom: 1px solid #E2E8F0;">
          <div class="d-flex align-items-center gap-2.5">
            <div style="width: 38px; height: 38px; border-radius: 9px; background: #F0F9FF; border: 1px solid #BAE6FD; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
              <svg width="22" height="15" viewBox="0 0 28 18" fill="none">
                <path d="M14.07 10.97c-.96-1.72-2.18-3.48-3.56-4.9-1.92-1.98-4.04-3.07-6.3-3.07C1.86 3 0 5.12 0 8.52c0 3.78 2.37 6.48 5.76 6.48 2.5 0 4.67-1.47 6.38-3.76l1.93-.27zm-8.31 1.77c-2.11 0-3.48-1.74-3.48-4.22 0-2.22 1.25-3.8 3.14-3.8 1.63 0 3.25.92 4.7 2.62 1.15 1.35 2.12 2.8 2.92 4.16-1.9 1.94-3.86 2.96-5.8 2.96l-.48.28zm16.48-9.74c-2.26 0-4.38 1.09-6.3 3.07-1.38 1.42-2.6 3.18-3.56 4.9l1.93.27c1.71 2.29 3.88 3.76 6.38 3.76 3.39 0 5.76-2.7 5.76-6.48 0-3.4-1.86-5.52-4.21-5.52zm-1.48 10.02c-1.94 0-3.9-1.02-5.8-2.96.8-1.36 1.77-2.81 2.92-4.16 1.45-1.7 3.07-2.62 4.7-2.62 1.89 0 3.14 1.58 3.14 3.8 0 2.48-1.37 4.22-3.48 4.22l-.48-.28z" fill="#0284C7"/>
              </svg>
            </div>
            <div>
              <h6 class="modal-title fw-bold mb-0" id="metaHubModalLabel" style="font-size: 1.02rem; color: #0F172A; letter-spacing: -0.015em;">Meta Accounts &amp; Channels Hub</h6>
              <div class="text-muted" style="font-size: 0.74rem;">Unified WhatsApp Web Companion, Instagram Direct &amp; Facebook Messenger</div>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- Navigation Tabs -->
        <div class="modal-body p-0">
          <ul class="nav nav-tabs nav-fill px-4 pt-2.5" id="metaHubTabs" role="tablist" style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0;">
            <li class="nav-item" role="presentation">
              <button class="nav-link active fw-semibold d-flex align-items-center justify-content-center gap-2 py-2.5" id="tabWhatsAppBtn" data-bs-toggle="tab" data-bs-target="#tabWhatsApp" type="button" role="tab" style="font-size: 0.82rem; border-radius: 8px 8px 0 0;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path fill="#25D366" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2z"/><path fill="#FFFFFF" d="M17.5 14.33c-.28-.14-1.65-.81-1.9-.91-.26-.09-.45-.14-.64.14-.19.28-.73.91-.9 1.1-.16.19-.33.21-.61.07-.28-.14-1.18-.44-2.25-1.39-.83-.74-1.4-1.66-1.56-1.94-.16-.28-.02-.43.12-.57.13-.13.28-.33.42-.5.14-.16.19-.28.28-.47.09-.19.05-.35-.02-.5-.07-.14-.64-1.54-.87-2.11-.23-.56-.47-.48-.64-.49-.17-.01-.36-.01-.56-.01-.19 0-.5.07-.76.35-.26.28-1 1-1 2.43s1.03 2.82 1.17 3.01c.14.19 2.02 3.1 4.9 4.35 2.87 1.25 2.87.83 3.39.79.52-.05 1.65-.67 1.88-1.33.23-.65.23-1.22.16-1.33-.07-.12-.26-.19-.54-.33z"/></svg>
                <span>WhatsApp Companion</span>
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link fw-semibold d-flex align-items-center justify-content-center gap-2 py-2.5" id="tabMetaSocialBtn" data-bs-toggle="tab" data-bs-target="#tabMetaSocial" type="button" role="tab" style="font-size: 0.82rem; border-radius: 8px 8px 0 0;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path fill="#0084FF" d="M12 2C6.48 2 2 6.15 2 11.26c0 2.91 1.45 5.52 3.73 7.18v3.56l3.42-1.88c.9.25 1.86.39 2.85.39 5.52 0 10-4.15 10-9.25C22 6.15 17.52 2 12 2z"/><path fill="#FFFFFF" d="m6.8 13.7 3.3-3.5 2.5 2.5 4.6-4.5-3.3 3.5-2.5-2.5z"/></svg>
                <span>Instagram &amp; Facebook</span>
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link fw-semibold d-flex align-items-center justify-content-center gap-2 py-2.5" id="tabOverviewBtn" data-bs-toggle="tab" data-bs-target="#tabOverview" type="button" role="tab" style="font-size: 0.82rem; border-radius: 8px 8px 0 0;">
                <i class="bi bi-diagram-3 text-secondary"></i>
                <span>Channel Matrix</span>
              </button>
            </li>
          </ul>

          <div class="tab-content p-4" id="metaHubTabContent">
            
            <!-- TAB 1: WHATSAPP COMPANION (OPTION 2: QR LINK) -->
            <div class="tab-pane fade show active" id="tabWhatsApp" role="tabpanel">
              
              <!-- Multi-Device Explanation Banner -->
              <div class="p-3 mb-3.5 rounded d-flex align-items-start gap-2.5" style="background: #F0FDF4; border: 1px solid #BBF7D0; border-left: 4px solid #10B981;">
                <div style="width: 26px; height: 26px; border-radius: 50%; background: #DCFCE7; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #16A34A; margin-top: 1px;">
                  <i class="bi bi-shield-check" style="font-size: 0.95rem;"></i>
                </div>
                <div style="font-size: 0.77rem; color: #166534; line-height: 1.5;">
                  <strong style="color: #14532D;">Multi-Device Companion Link Active:</strong> Your WhatsApp account stays 100% active on your physical cell phone. No number deactivation or SIM swap required. Staff can reply directly from this Trade Desk, and all messages appear on your phone simultaneously.
                </div>
              </div>

              <div class="row g-3.5 align-items-center">
                <!-- Left: QR Code / Device Display -->
                <div class="col-md-5 text-center">
                  <div class="p-3 bg-white rounded border d-inline-block position-relative w-100" style="box-shadow: 0 4px 16px rgba(15,23,42,0.06); border-color: #E2E8F0 !important;" id="waQrCodeWrapper">
                    <div id="waQrContainer" style="min-height: 220px; display: flex; align-items: center; justify-content: center;">
                      <div class="spinner-border text-success" role="status"></div>
                    </div>
                  </div>
                  <div class="mt-2 small text-muted" style="font-size: 0.7rem;">
                    <i class="bi bi-clock-history me-1"></i>QR refreshes dynamically
                  </div>
                </div>

                <!-- Right: Status & Steps -->
                <div class="col-md-7">
                  <div class="d-flex align-items-center justify-content-between mb-3 pb-2.5 border-bottom">
                    <div>
                      <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.04em;">Companion Status</span>
                      <div class="fw-bold d-flex align-items-center gap-1.5 mt-0.5" id="waStatusBadge" style="font-size: 0.88rem; color: #0F172A;">
                        <span class="badge bg-secondary">Checking status...</span>
                      </div>
                    </div>
                    <div class="text-end">
                      <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.04em;">Linked Phone</span>
                      <div class="fw-bold font-monospace mt-0.5" id="waConnectedPhone" style="font-size: 0.82rem; color: #0F172A;">+92 334 8888104</div>
                    </div>
                  </div>

                  <h6 class="fw-bold mb-2.5 text-dark" style="font-size: 0.82rem; letter-spacing: -0.01em;">How to Link WhatsApp on Mobile:</h6>
                  <div class="d-flex flex-column gap-2 mb-3">
                    <div class="d-flex align-items-start gap-2 text-dark" style="font-size: 0.76rem;">
                      <span style="width: 20px; height: 20px; border-radius: 50%; background: #0F172A; color: #FFF; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; flex-shrink: 0; margin-top: 1px;">1</span>
                      <span>Open <strong>WhatsApp</strong> on your mobile phone.</span>
                    </div>
                    <div class="d-flex align-items-start gap-2 text-dark" style="font-size: 0.76rem;">
                      <span style="width: 20px; height: 20px; border-radius: 50%; background: #0F172A; color: #FFF; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; flex-shrink: 0; margin-top: 1px;">2</span>
                      <span>Tap <strong>Settings</strong> (iPhone) or <strong>⋮ (3 Dots)</strong> (Android).</span>
                    </div>
                    <div class="d-flex align-items-start gap-2 text-dark" style="font-size: 0.76rem;">
                      <span style="width: 20px; height: 20px; border-radius: 50%; background: #0F172A; color: #FFF; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; flex-shrink: 0; margin-top: 1px;">3</span>
                      <span>Select <strong>Linked Devices</strong> &rarr; tap <strong>Link a Device</strong>.</span>
                    </div>
                    <div class="d-flex align-items-start gap-2 text-dark" style="font-size: 0.76rem;">
                      <span style="width: 20px; height: 20px; border-radius: 50%; background: #0F172A; color: #FFF; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; flex-shrink: 0; margin-top: 1px;">4</span>
                      <span>Point your phone camera at the QR code on the left.</span>
                    </div>
                  </div>

                  <div class="d-flex flex-wrap gap-2 pt-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1.5 py-1.5 px-3" id="btnRefreshWaQr" style="font-size: 0.76rem; font-weight: 600; border-radius: 6px;">
                      <i class="bi bi-arrow-clockwise"></i> Refresh QR
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1.5 py-1.5 px-3" id="btnDisconnectWa" style="font-size: 0.76rem; font-weight: 600; border-radius: 6px;">
                      <i class="bi bi-power"></i> Disconnect
                    </button>
                    <button type="button" class="btn btn-sm btn-success text-white d-inline-flex align-items-center gap-1.5 py-1.5 px-3" id="btnQuickConfirmPair" style="font-size: 0.76rem; font-weight: 600; border-radius: 6px; background: #16A34A; border-color: #16A34A;">
                      <i class="bi bi-check2-circle"></i> Mark Linked
                    </button>
                  </div>
                </div>
              </div>

              <!-- Instant Simulation Accordion -->
              <div class="mt-4 pt-3 border-top">
                <button class="btn btn-sm btn-outline-secondary w-100 text-start d-flex align-items-center justify-content-between p-2" type="button" data-bs-toggle="collapse" data-bs-target="#waSimulationBox">
                  <span class="fw-bold" style="font-size: 0.75rem;"><i class="bi bi-broadcast text-success me-1.5"></i> Test Simulator: Simulate Inbound WhatsApp Inquiry</span>
                  <i class="bi bi-chevron-down small"></i>
                </button>
                <div class="collapse mt-2" id="waSimulationBox">
                  <div class="p-3 bg-light rounded border">
                    <div class="row g-2 mb-2">
                      <div class="col-md-6">
                        <label class="form-label small mb-1 fw-bold" style="font-size: 0.7rem;">Buyer Phone (+Country Code)</label>
                        <input type="text" class="form-control form-control-sm" id="simWaPhone" value="+86 138 0013 8000" placeholder="+86 138 0000 0000" style="font-size: 0.75rem;">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label small mb-1 fw-bold" style="font-size: 0.7rem;">Buyer / Company Name</label>
                        <input type="text" class="form-control form-control-sm" id="simWaName" value="Zhang Wei (Sinochem Raw Materials)" placeholder="e.g. Li Wei" style="font-size: 0.75rem;">
                      </div>
                    </div>
                    <div class="mb-2">
                      <label class="form-label small mb-1 fw-bold" style="font-size: 0.7rem;">Inquiry Message Text</label>
                      <input type="text" class="form-control form-control-sm" id="simWaMsg" value="We require 12,000 MT Barite 4.2+ SG (API 13A grade) FOB Karachi for Tianjin Port. Can you share assay and quote?" style="font-size: 0.75rem;">
                    </div>
                    <button type="button" class="btn btn-sm btn-dark w-100 py-1.5 d-inline-flex align-items-center justify-content-center gap-1" id="btnRunWaSimulation" style="font-size: 0.75rem; font-weight: 600;">
                      <i class="bi bi-send-fill text-success"></i> Send Simulated WhatsApp Inquiry into Desk
                    </button>
                    <div id="simWaResult" class="small mt-2" style="display: none;"></div>
                  </div>
                </div>
              </div>

            </div>

            <!-- TAB 2: INSTAGRAM & FACEBOOK SETTINGS -->
            <div class="tab-pane fade" id="tabMetaSocial" role="tabpanel">
              
              <!-- Webhook Card -->
              <div class="p-3 mb-3 rounded" style="background: #F8FAFC; border: 1px solid #E2E8F0;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="fw-bold text-dark" style="font-size: 0.78rem;"><i class="bi bi-link-45deg text-primary me-1"></i> Meta Webhook Endpoint</span>
                  <span class="badge bg-primary" style="font-size: 0.62rem;">Universal Handshake</span>
                </div>
                <div class="row g-2">
                  <div class="col-md-7">
                    <label class="form-label small text-muted mb-1" style="font-size: 0.68rem;">Callback URL (Paste into Meta App Dashboard)</label>
                    <div class="input-group input-group-sm">
                      <input type="text" class="form-control font-monospace" id="metaWebhookUrlDisplay" readonly style="font-size: 0.72rem; background: #FFFFFF;">
                      <button class="btn btn-outline-secondary" type="button" id="btnCopyWebhookUrl" title="Copy to clipboard"><i class="bi bi-clipboard"></i> Copy</button>
                    </div>
                  </div>
                  <div class="col-md-5">
                    <label class="form-label small text-muted mb-1" style="font-size: 0.68rem;">Verify Token (hub.verify_token)</label>
                    <div class="input-group input-group-sm">
                      <input type="text" class="form-control font-monospace" id="metaVerifyTokenDisplay" readonly style="font-size: 0.72rem; background: #FFFFFF;">
                      <button class="btn btn-outline-secondary" type="button" id="btnCopyVerifyToken" title="Copy to clipboard"><i class="bi bi-clipboard"></i> Copy</button>
                    </div>
                  </div>
                </div>
                <div class="mt-2 text-muted" style="font-size: 0.68rem;">
                  <i class="bi bi-info-circle me-1"></i>Subscribe to webhook fields: <code class="text-primary">messages</code>, <code class="text-primary">messaging_postbacks</code>
                </div>
              </div>

              <!-- Credentials Form -->
              <div class="row g-2.5">
                <div class="col-md-6">
                  <label class="form-label small mb-1 fw-bold" style="font-size: 0.72rem;">Facebook Page ID</label>
                  <input type="text" class="form-control form-control-sm font-monospace" id="inputFbPageId" placeholder="e.g. 102948192847291" style="font-size: 0.75rem;">
                </div>
                <div class="col-md-6">
                  <label class="form-label small mb-1 fw-bold" style="font-size: 0.72rem;">Instagram Business Account ID</label>
                  <input type="text" class="form-control form-control-sm font-monospace" id="inputIgAccountId" placeholder="e.g. 178414002938472" style="font-size: 0.75rem;">
                </div>
                <div class="col-md-12">
                  <label class="form-label small mb-1 fw-bold" style="font-size: 0.72rem;">Facebook Page Access Token (Never expires recommended)</label>
                  <input type="password" class="form-control form-control-sm font-monospace" id="inputFbPageToken" placeholder="EAA..." style="font-size: 0.75rem;">
                </div>
                <div class="col-md-6">
                  <label class="form-label small mb-1 fw-bold" style="font-size: 0.72rem;">Meta App ID (Optional)</label>
                  <input type="text" class="form-control form-control-sm font-monospace" id="inputMetaAppId" placeholder="e.g. 84920193847" style="font-size: 0.75rem;">
                </div>
                <div class="col-md-6">
                  <label class="form-label small mb-1 fw-bold" style="font-size: 0.72rem;">Meta App Secret (Optional)</label>
                  <input type="password" class="form-control form-control-sm font-monospace" id="inputMetaAppSecret" placeholder="••••••••" style="font-size: 0.75rem;">
                </div>
              </div>

              <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top">
                <span id="metaSettingsSaveNotice" class="text-success small fw-bold" style="display: none; font-size: 0.72rem;">
                  <i class="bi bi-check-circle-fill me-1"></i>Credentials saved successfully
                </span>
                <button type="button" class="btn btn-sm btn-primary ms-auto d-inline-flex align-items-center gap-1.5" id="btnSaveMetaSettings" style="font-size: 0.75rem; font-weight: 600;">
                  <i class="bi bi-shield-lock-fill"></i> Save Meta Credentials
                </button>
              </div>

              <!-- Instant Simulation for Social -->
              <div class="mt-4 pt-3 border-top">
                <button class="btn btn-sm btn-outline-secondary w-100 text-start d-flex align-items-center justify-content-between p-2" type="button" data-bs-toggle="collapse" data-bs-target="#socialSimulationBox">
                  <span class="fw-bold" style="font-size: 0.75rem;"><i class="bi bi-broadcast text-primary me-1.5"></i> Test Simulator: Simulate Inbound Instagram DM or Facebook Message</span>
                  <i class="bi bi-chevron-down small"></i>
                </button>
                <div class="collapse mt-2" id="socialSimulationBox">
                  <div class="p-3 bg-light rounded border">
                    <div class="row g-2 mb-2">
                      <div class="col-md-4">
                        <label class="form-label small mb-1 fw-bold" style="font-size: 0.7rem;">Target Channel</label>
                        <select class="form-select form-select-sm" id="simSocialChannel" style="font-size: 0.75rem;">
                          <option value="instagram">Instagram Direct (🟣)</option>
                          <option value="facebook">Facebook Messenger (🔵)</option>
                        </select>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label small mb-1 fw-bold" style="font-size: 0.7rem;">Buyer Handle / Name</label>
                        <input type="text" class="form-control form-control-sm" id="simSocialName" value="Global Mineral Trading Ltd" style="font-size: 0.75rem;">
                      </div>
                      <div class="col-md-4">
                        <label class="form-label small mb-1 fw-bold" style="font-size: 0.7rem;">Country</label>
                        <input type="text" class="form-control form-control-sm" id="simSocialCountry" value="United Arab Emirates" style="font-size: 0.75rem;">
                      </div>
                    </div>
                    <div class="mb-2">
                      <label class="form-label small mb-1 fw-bold" style="font-size: 0.7rem;">Message Text</label>
                      <input type="text" class="form-control form-control-sm" id="simSocialMsg" value="We require 5,000 MT Chromite 44% CIF Jebel Ali. Please send specification and FOB quote." style="font-size: 0.75rem;">
                    </div>
                    <button type="button" class="btn btn-sm btn-dark w-100 py-1.5 d-inline-flex align-items-center justify-content-center gap-1" id="btnRunSocialSimulation" style="font-size: 0.75rem; font-weight: 600;">
                      <i class="bi bi-send-fill text-primary"></i> Send Simulated Social Message into Desk
                    </button>
                    <div id="simSocialResult" class="small mt-2" style="display: none;"></div>
                  </div>
                </div>
              </div>

            </div>

            <!-- TAB 3: CHANNEL MATRIX & HEALTH -->
            <div class="tab-pane fade" id="tabOverview" role="tabpanel">
              <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 0.76rem;">
                  <thead class="table-light">
                    <tr>
                      <th>Channel</th>
                      <th>Integration Method</th>
                      <th>Status</th>
                      <th>Inbound Route</th>
                      <th>Outbound Route</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td class="fw-bold"><i class="bi bi-whatsapp text-success me-1"></i> WhatsApp</td>
                      <td>Multi-Device Web Companion (QR)</td>
                      <td><span class="badge bg-success" id="matrixWaStatus">Connected</span></td>
                      <td>Real-time Companion Socket</td>
                      <td>Direct via Authorized Companion</td>
                    </tr>
                    <tr>
                      <td class="fw-bold"><i class="bi bi-instagram text-danger me-1"></i> Instagram</td>
                      <td>Meta Graph API &amp; Webhook</td>
                      <td><span class="badge bg-secondary" id="matrixIgStatus">Configured</span></td>
                      <td><code>/api/meta-webhook.php</code></td>
                      <td>Meta Graph API <code>/me/messages</code></td>
                    </tr>
                    <tr>
                      <td class="fw-bold"><i class="bi bi-messenger text-primary me-1"></i> Facebook</td>
                      <td>Meta Messenger Platform</td>
                      <td><span class="badge bg-secondary" id="matrixFbStatus">Configured</span></td>
                      <td><code>/api/meta-webhook.php</code></td>
                      <td>Meta Graph API <code>/me/messages</code></td>
                    </tr>
                    <tr>
                      <td class="fw-bold"><i class="bi bi-globe text-secondary me-1"></i> Web Live Chat</td>
                      <td>Native Balochistan Portal</td>
                      <td><span class="badge bg-success">Always Online</span></td>
                      <td><code>/api/live-chat.php</code></td>
                      <td>Trade Desk SSE / Polling</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="p-3 mt-3 rounded bg-light border text-muted" style="font-size: 0.72rem; line-height: 1.5;">
                <div class="fw-bold text-dark mb-1"><i class="bi bi-layers-fill text-primary me-1"></i> Single Roof Trade Desk Dispatch:</div>
                When an officer types a response in this console, the system checks whether the inquiry originated from WhatsApp, Instagram, Facebook, or the Website. Outbound messages are automatically dispatched to the buyer's original platform and saved to the audit ledger.
              </div>
            </div>

          </div>
        </div>

        <div class="modal-footer py-2.5 px-4" style="background: #FFFFFF; border-top: 1px solid #E2E8F0;">
          <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-1.5" data-bs-dismiss="modal" style="font-size: 0.76rem; font-weight: 600; border-radius: 6px;">Close Window</button>
        </div>

      </div>
    </div>
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
    let isDossierEditing = false;
    let activeSessionCache = null;

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

    let activeChannelFilter = 'all';

    // Channel Filter Pills Listener
    document.querySelectorAll('.channel-pill').forEach(pill => {
      pill.addEventListener('click', () => {
        document.querySelectorAll('.channel-pill').forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        activeChannelFilter = pill.dataset.channel || 'all';
        fetchSessions();
      });
    });

    // Channel Filter Slider Arrows
    const channelSlider = document.getElementById('channelFilterPills');
    const btnSlidePrev = document.getElementById('btnChannelSlidePrev');
    const btnSlideNext = document.getElementById('btnChannelSlideNext');
    if (btnSlidePrev && channelSlider) {
      btnSlidePrev.addEventListener('click', () => {
        channelSlider.scrollBy({ left: -90, behavior: 'smooth' });
      });
    }
    if (btnSlideNext && channelSlider) {
      btnSlideNext.addEventListener('click', () => {
        channelSlider.scrollBy({ left: 90, behavior: 'smooth' });
      });
    }

    // High-Resolution Vector Flag Resolver (Fixes Windows Desktop plain-text emoji bug)
    function inferCountryCode(country, code) {
      if (code && code !== 'XX' && code !== 'T1') return code.toLowerCase();
      const c = (country || '').toLowerCase();
      if (c.includes('china')) return 'cn';
      if (c.includes('emirates') || c.includes('uae')) return 'ae';
      if (c.includes('pakistan')) return 'pk';
      if (c.includes('united states') || c.includes('usa')) return 'us';
      if (c.includes('saudi')) return 'sa';
      if (c.includes('united kingdom') || c.includes('uk')) return 'gb';
      if (c.includes('india')) return 'in';
      if (c.includes('turkey') || c.includes('turkiye')) return 'tr';
      if (c.includes('germany')) return 'de';
      if (c.includes('japan')) return 'jp';
      if (c.includes('korea')) return 'kr';
      if (c.includes('singapore')) return 'sg';
      if (c.includes('malaysia')) return 'my';
      if (c.includes('oman')) return 'om';
      if (c.includes('qatar')) return 'qa';
      if (c.includes('kuwait')) return 'kw';
      if (c.includes('bahrain')) return 'bh';
      if (c.includes('vietnam')) return 'vn';
      if (c.includes('thailand')) return 'th';
      if (c.includes('indonesia')) return 'id';
      return '';
    }

    function getCountryFlagHtml(country, code, fallbackEmoji = '') {
      const c = inferCountryCode(country, code);
      if (!c) {
        return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="d-inline-block me-1.5" style="vertical-align: -1.5px; color: #64748B;"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>`;
      }
      return `<img src="https://flagcdn.com/${c}.svg" width="16" height="12" alt="${c.toUpperCase()}" class="country-flag-icon me-1.5" style="vertical-align: -1.5px; display: inline-block; object-fit: cover; border-radius: 2px; box-shadow: 0 1px 2px rgba(0,0,0,0.18); border: 1px solid rgba(0,0,0,0.08);" onerror="this.outerHTML='<span class=\\'me-1.5 fw-bold font-monospace\\' style=\\'font-size:0.75rem;\\'>${fallbackEmoji || c.toUpperCase()}</span>'">`;
    }

    // High-Quality Official Brand Vector SVG Badge for Channel Overlay on Avatar (Zero White Circle)
    function getChannelAvatarBadge(channel) {
      const ch = (channel || 'web').toLowerCase();
      if (ch === 'whatsapp') {
        return `<span class="conv-channel-badge conv-channel-badge--whatsapp" title="Channel: WhatsApp">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path fill="#25D366" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2z"/>
            <path fill="#FFFFFF" d="M17.5 14.33c-.28-.14-1.65-.81-1.9-.91-.26-.09-.45-.14-.64.14-.19.28-.73.91-.9 1.1-.16.19-.33.21-.61.07-.28-.14-1.18-.44-2.25-1.39-.83-.74-1.4-1.66-1.56-1.94-.16-.28-.02-.43.12-.57.13-.13.28-.33.42-.5.14-.16.19-.28.28-.47.09-.19.05-.35-.02-.5-.07-.14-.64-1.54-.87-2.11-.23-.56-.47-.48-.64-.49-.17-.01-.36-.01-.56-.01-.19 0-.5.07-.76.35-.26.28-1 1-1 2.43s1.03 2.82 1.17 3.01c.14.19 2.02 3.1 4.9 4.35 2.87 1.25 2.87.83 3.39.79.52-.05 1.65-.67 1.88-1.33.23-.65.23-1.22.16-1.33-.07-.12-.26-.19-.54-.33z"/>
          </svg>
        </span>`;
      }
      if (ch === 'facebook') {
        return `<span class="conv-channel-badge conv-channel-badge--facebook" title="Channel: Facebook Messenger">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path fill="#0084FF" d="M12 2C6.48 2 2 6.15 2 11.26c0 2.91 1.45 5.52 3.73 7.18v3.56l3.42-1.88c.9.25 1.86.39 2.85.39 5.52 0 10-4.15 10-9.25C22 6.15 17.52 2 12 2z"/>
            <path fill="#FFFFFF" d="m6.8 13.7 3.3-3.5 2.5 2.5 4.6-4.5-3.3 3.5-2.5-2.5z"/>
          </svg>
        </span>`;
      }
      if (ch === 'instagram') {
        return `<span class="conv-channel-badge conv-channel-badge--instagram" title="Channel: Instagram Direct">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <defs>
              <linearGradient id="igAvatarGrad" x1="0%" y1="100%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="#FFD600"/>
                <stop offset="25%" stop-color="#FF0069"/>
                <stop offset="50%" stop-color="#D300C5"/>
                <stop offset="75%" stop-color="#7638FA"/>
                <stop offset="100%" stop-color="#00B2FE"/>
              </linearGradient>
            </defs>
            <rect width="24" height="24" rx="6.5" fill="url(#igAvatarGrad)"/>
            <rect x="5.5" y="5.5" width="13" height="13" rx="3.5" stroke="#FFFFFF" stroke-width="1.8"/>
            <circle cx="12" cy="12" r="3.2" stroke="#FFFFFF" stroke-width="1.8"/>
            <circle cx="15.8" cy="8.2" r="0.9" fill="#FFFFFF"/>
          </svg>
        </span>`;
      }
      // Default: Web (Balochistan Minerals Chatbot Buddy Diamond)
      return `<span class="conv-channel-badge conv-channel-badge--web" title="Channel: Website Chatbot Buddy">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <rect width="24" height="24" rx="6" fill="#0F172A"/>
          <path d="M6 5h12l4 5-10 10L2 10z" stroke="#F59E0B" stroke-width="1.8" stroke-linejoin="round" fill="none"/>
          <path d="M10.5 5v5l-3.5 2.5M13.5 5v5l3.5 2.5" stroke="#F59E0B" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </span>`;
    }

    // Fetch Sessions Poll
    async function fetchSessions() {
      try {
        const res = await fetch(`api-chat-admin.php?action=poll_sessions&filter=${encodeURIComponent(activeFilter)}&channel=${encodeURIComponent(activeChannelFilter)}`);
        const data = await res.json();
        if (data.success) {
          document.getElementById('countActive').textContent = data.active_count || 0;
          document.getElementById('countWaiting').textContent = data.waiting_count || 0;
          document.getElementById('countMy').textContent = data.my_count || 0;
          
          // Channel Counts
          const totalChan = (data.web_count || 0) + (data.whatsapp_count || 0) + (data.instagram_count || 0) + (data.facebook_count || 0);
          const cAll = document.getElementById('countAllChannels');
          if (cAll) cAll.textContent = totalChan;
          const cWeb = document.getElementById('countWeb');
          if (cWeb) cWeb.textContent = data.web_count || 0;
          const cWa = document.getElementById('countWhatsApp');
          if (cWa) cWa.textContent = data.whatsapp_count || 0;
          const cIg = document.getElementById('countInstagram');
          if (cIg) cIg.textContent = data.instagram_count || 0;
          const cFb = document.getElementById('countFacebook');
          if (cFb) cFb.textContent = data.facebook_count || 0;

          const sideBadge = document.getElementById('sidebarWaitingBadge');
          if (sideBadge) {
            sideBadge.textContent = data.waiting_count || 0;
            sideBadge.style.display = data.waiting_count > 0 ? 'inline-block' : 'none';
          }

          renderConversationsList(data.sessions || []);

          // Auto-select active or first session so chat console is immediately loaded and visible
          if (data.sessions && data.sessions.length > 0) {
            const savedSessionId = parseInt(localStorage.getItem('bm_active_chat_session') || '0', 10);
            if (!currentSessionId) {
              const target = data.sessions.find(s => s.id === savedSessionId) || data.sessions[0];
              if (target) {
                selectSession(target.id);
              }
            }
          }
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
               (s.city && s.city.toLowerCase().includes(search)) ||
               (s.country_with_city && s.country_with_city.toLowerCase().includes(search)) ||
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
          ? '<span class="badge-status-waiting"><span class="status-dot"></span>WAITING</span>'
          : (s.status === 'agent_active' 
              ? '<span class="badge-status-live"><span class="status-dot"></span>LIVE</span>'
              : '<span class="badge-status-bot"><span class="status-dot"></span>BOT</span>');

        const unread = s.unread_admin > 0 ? `<span class="badge-status-unread">${s.unread_admin}</span>` : '';
        const nameStr = s.display_name || s.name || `Buyer #${1000 + s.id}`;
        const initial = (nameStr.replace(/[^a-zA-Z]/g, '') || 'B').charAt(0).toUpperCase();
        const flagHtml = getCountryFlagHtml(s.country, s.country_code, s.flag);
        let locSnippet = '';
        if (s.country && s.country !== 'International') {
          locSnippet = `${flagHtml}<span>${escapeHtml(s.country)}${s.city ? ` (${escapeHtml(s.city)})` : ''}</span>`;
        } else {
          locSnippet = `${flagHtml}<span>International</span>`;
        }

        const channelBadge = getChannelAvatarBadge(s.channel);

        return `
          <div class="conv-item ${isWaiting ? 'is-waiting' : ''} ${isActive ? 'active' : ''}" onclick="selectSession(${s.id})">
            <div class="conv-avatar">
              <span>${initial}</span>
              ${channelBadge}
            </div>
            <div class="conv-content">
              <div class="conv-top-line">
                <span class="conv-name text-truncate" title="${escapeHtml(nameStr)}">${escapeHtml(nameStr)}</span>
                <span class="conv-time flex-shrink-0">${s.last_time || ''}</span>
              </div>
              <div class="conv-subline">
                <span>${locSnippet}</span>
                ${s.mineral ? `&bull; <span class="badge border" style="background:#F1F5F9; color:#0F172A; font-size:0.65rem; font-weight:600;">${escapeHtml(s.mineral)}</span>` : ''}
              </div>
              <div class="conv-snippet">
                ${s.last_sender === 'visitor' ? '<strong>Buyer: </strong>' : ''}${escapeHtml(s.last_message || 'Session initiated')}
              </div>
            </div>
            <div class="conv-badge-col">
              ${statusBadge}
              ${unread}
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
      try { localStorage.setItem('bm_active_chat_session', sessionId); } catch (e) {}
      lastMessageId = 0;
      setDossierEditing(false);
      document.body.classList.add('mobile-view-chat');
      document.getElementById('emptyChatScreen').style.display = 'none';
      document.getElementById('activeChatContent').style.display = 'flex';
      document.getElementById('activeChatBody').innerHTML = ''; // Fresh stream on session switch

      document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
      const activeEl = document.querySelector(`.conv-item[onclick="selectSession(${sessionId})"]`);
      if (activeEl) activeEl.classList.add('active');

      await fetchMessages(true, false);
    }

    // Toggle Dossier Edit Mode
    function setDossierEditing(edit) {
      isDossierEditing = edit;
      const btnLabel = document.getElementById('btnEditLabel');
      if (btnLabel) btnLabel.textContent = edit ? 'Editing' : 'Edit';

      document.getElementById('dosNameView').style.display = edit ? 'none' : 'block';
      document.getElementById('dosNameEditWrap').style.display = edit ? 'block' : 'none';

      document.getElementById('dosEmailView').style.display = edit ? 'none' : 'block';
      document.getElementById('dosEmailEditWrap').style.display = edit ? 'block' : 'none';

      document.getElementById('dosPhoneView').style.display = edit ? 'none' : 'block';
      document.getElementById('dosPhoneEditWrap').style.display = edit ? 'block' : 'none';

      document.getElementById('dosMineralView').style.display = edit ? 'none' : 'block';
      document.getElementById('dosMineralEditWrap').style.display = edit ? 'block' : 'none';

      document.getElementById('dossierEditActions').style.display = edit ? 'block' : 'none';
    }

    document.getElementById('btnToggleDossierEdit').addEventListener('click', () => {
      setDossierEditing(!isDossierEditing);
    });

    document.getElementById('btnCancelDossierEdit').addEventListener('click', () => {
      setDossierEditing(false);
      if (activeSessionCache) {
        populateDossierFields(activeSessionCache);
      }
    });

    // Save Dossier Changes
    document.getElementById('btnSaveDossier').addEventListener('click', async () => {
      if (!currentSessionId) return;
      const newName = document.getElementById('inputDosName').value.trim();
      const newEmail = document.getElementById('inputDosEmail').value.trim();
      const newPhone = document.getElementById('inputDosPhone').value.trim();
      const newMineral = document.getElementById('selectDosMineral').value;
      const notes = document.getElementById('dosInternalNotes').value.trim();

      try {
        const res = await fetch('api-chat-admin.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'update_buyer_dossier',
            session_id: currentSessionId,
            visitor_name: newName,
            visitor_email: newEmail,
            visitor_phone: newPhone,
            mineral_interest: newMineral,
            internal_notes: notes
          })
        });
        const data = await res.json();
        if (data.success) {
          setDossierEditing(false);
          await fetchMessages(false, false);
          fetchSessions();
        }
      } catch (e) {}
    });

    // Save Notes Only
    document.getElementById('btnSaveNotes').addEventListener('click', async () => {
      if (!currentSessionId) return;
      const notes = document.getElementById('dosInternalNotes').value.trim();
      try {
        const res = await fetch('api-chat-admin.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'update_buyer_dossier',
            session_id: currentSessionId,
            internal_notes: notes
          })
        });
        const data = await res.json();
        if (data.success) {
          const ind = document.getElementById('notesSavedIndicator');
          if (ind) {
            ind.style.display = 'inline';
            setTimeout(() => { ind.style.display = 'none'; }, 2200);
          }
        }
      } catch (e) {}
    });

    function populateDossierFields(s) {
      const vName = s.display_name || s.name || `Buyer #${1000 + s.id}`;
      const locationText = s.country_with_city || s.country || 'International';

      // Header Updates
      const titleTextEl = document.getElementById('chatHeaderTitleText');
      if (titleTextEl) titleTextEl.textContent = vName;

      const headerAvatar = document.getElementById('chatHeaderAvatar');
      if (headerAvatar) {
        const initial = (vName.replace(/[^a-zA-Z]/g, '') || 'B').charAt(0).toUpperCase();
        headerAvatar.innerHTML = `<span>${initial}</span>${getChannelAvatarBadge(s.channel)}`;
      }

      const globeSvgSmall = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="d-inline-block me-1" style="vertical-align: -1.5px; color: #475569;"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>`;
      const globeSvgMedium = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="d-inline-block me-1" style="vertical-align: -2px; color: #475569;"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>`;

      const countryEl = document.getElementById('chatHeaderCountry');
      if (countryEl) {
        const flagHtml = getCountryFlagHtml(s.country, s.country_code, s.flag);
        if (s.country && s.country !== 'International') {
          countryEl.innerHTML = `${flagHtml}<span>${escapeHtml(s.country)}${s.city ? ` (${escapeHtml(s.city)})` : ''}</span>`;
        } else {
          countryEl.innerHTML = `${flagHtml}<span>International</span>`;
        }
      }

      const localTimeEl = document.getElementById('chatHeaderLocalTime');
      if (localTimeEl) localTimeEl.textContent = s.local_time_str || '—';

      const pathEl = document.getElementById('chatHeaderSubPath');
      if (pathEl) {
        if (s.channel === 'whatsapp') {
          pathEl.textContent = 'WhatsApp Direct';
        } else if (s.channel === 'instagram') {
          pathEl.textContent = 'Instagram Direct';
        } else if (s.channel === 'facebook') {
          pathEl.textContent = 'Facebook Messenger';
        } else {
          pathEl.textContent = s.page || s.current_page || '/';
        }
      }

      // Channel Header & Dossier Updates
      const headerChannelBadge = document.getElementById('chatHeaderChannelBadge');
      if (headerChannelBadge) {
        headerChannelBadge.innerHTML = ''; // Keep header title clean and uncluttered as requested
      }
      const originLabel = document.getElementById('chatHeaderOriginLabel');
      if (originLabel) {
        originLabel.textContent = (s.channel && s.channel !== 'web') ? 'Origin:' : 'Viewing:';
      }
      const dosChannelBadge = document.getElementById('dosChannelBadge');
      if (dosChannelBadge) {
        dosChannelBadge.outerHTML = s.channel_badge_html ? s.channel_badge_html.replace('badge-channel-item', 'badge-channel-item me-1') : `<span id="dosChannelBadge" class="badge-channel-item badge-channel-web"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1.5px;flex-shrink:0;"><path d="M6 3h12l4 6-10 12L2 9z"></path><path d="M11 3v6l-4 3"></path><path d="M13 3v6l4 3"></path></svg> <span>Chatbot Buddy</span></span>`;
      }
      const dosChannelIdText = document.getElementById('dosChannelIdText');
      if (dosChannelIdText) {
        if (s.channel_id && s.channel !== 'whatsapp') {
          dosChannelIdText.textContent = `· @${s.channel_id.replace(/^@/, '')}`;
          dosChannelIdText.className = 'text-muted fw-semibold small';
        } else {
          dosChannelIdText.textContent = '';
        }
      }

      // Dossier Updates
      const dosTimezone = document.getElementById('dosTimezone');
      if (dosTimezone) dosTimezone.textContent = s.timezone || 'UTC';

      const dosLocalTime = document.getElementById('dosLocalTime');
      if (dosLocalTime) dosLocalTime.textContent = s.local_time_str || '—';

      const dosNameView = document.getElementById('dosNameView');
      if (dosNameView) dosNameView.textContent = vName;
      if (!isDossierEditing) document.getElementById('inputDosName').value = vName;

      const dosCountryView = document.getElementById('dosCountryView');
      if (dosCountryView) {
        const flagHtml = getCountryFlagHtml(s.country, s.country_code, s.flag);
        if (s.country && s.country !== 'International') {
          dosCountryView.innerHTML = `${flagHtml}<span>${escapeHtml(s.country)}${s.city ? ` (${escapeHtml(s.city)})` : ''}</span>`;
        } else {
          dosCountryView.innerHTML = `${flagHtml}<span>International</span>`;
        }
      }

      const dosIpView = document.getElementById('dosIpView');
      if (dosIpView) dosIpView.textContent = s.ip ? `IP: ${s.ip}` : 'IP: —';

      // Email with mailto link
      const dosEmailView = document.getElementById('dosEmailView');
      if (dosEmailView) {
        if (s.email) {
          dosEmailView.innerHTML = `
            <a href="mailto:${escapeHtml(s.email)}" class="dossier-link d-inline-flex align-items-center gap-1">
              <i class="bi bi-envelope text-primary"></i>
              <span>${escapeHtml(s.email)}</span>
            </a>
          `;
        } else {
          dosEmailView.innerHTML = '<span class="text-muted fst-italic" style="font-size:0.75rem;">Not captured yet</span>';
        }
      }
      if (!isDossierEditing) document.getElementById('inputDosEmail').value = s.email || '';

      // Phone with direct WhatsApp click-to-chat
      const dosPhoneView = document.getElementById('dosPhoneView');
      if (dosPhoneView) {
        if (s.phone) {
          const cleanDigits = s.phone.replace(/[^\d]/g, '');
          const waUrl = `https://wa.me/${cleanDigits}`;
          const hqWaVector = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" style="flex-shrink:0;"><path fill="#25D366" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2z"/><path fill="#FFFFFF" d="M17.5 14.33c-.28-.14-1.65-.81-1.9-.91-.26-.09-.45-.14-.64.14-.19.28-.73.91-.9 1.1-.16.19-.33.21-.61.07-.28-.14-1.18-.44-2.25-1.39-.83-.74-1.4-1.66-1.56-1.94-.16-.28-.02-.43.12-.57.13-.13.28-.33.42-.5.14-.16.19-.28.28-.47.09-.19.05-.35-.02-.5-.07-.14-.64-1.54-.87-2.11-.23-.56-.47-.48-.64-.49-.17-.01-.36-.01-.56-.01-.19 0-.5.07-.76.35-.26.28-1 1-1 2.43s1.03 2.82 1.17 3.01c.14.19 2.02 3.1 4.9 4.35 2.87 1.25 2.87.83 3.39.79.52-.05 1.65-.67 1.88-1.33.23-.65.23-1.22.16-1.33-.07-.12-.26-.19-.54-.33z"/></svg>`;
          dosPhoneView.innerHTML = `
            <div class="d-flex align-items-center justify-content-between gap-2">
              <span class="fw-semibold text-dark font-monospace" style="font-size:0.80rem;">${escapeHtml(s.phone)}</span>
              <a href="${waUrl}" target="_blank" rel="noopener noreferrer" class="btn-dossier-wa-action" title="Open direct WhatsApp conversation with buyer">
                ${hqWaVector}
                <span>Direct Chat</span>
              </a>
            </div>
          `;
        } else {
          dosPhoneView.innerHTML = '<span class="text-muted fst-italic" style="font-size:0.75rem;">Not captured yet</span>';
        }
      }
      if (!isDossierEditing) document.getElementById('inputDosPhone').value = s.phone || '';

      // Current page / channel origin link
      const curPage = s.page || s.current_page || '/';
      const pageView = document.getElementById('dosPageView');
      const pageRowLabel = document.getElementById('dosPageRowLabel');
      
      if (s.channel === 'whatsapp') {
        if (pageRowLabel) pageRowLabel.textContent = 'Channel Source';
        if (pageView) {
          pageView.innerHTML = `
            <div class="d-flex align-items-center justify-content-between p-1.5 px-2 rounded" style="background:#F8FAFC; border:1px solid #E2E8F0;">
              <div class="d-flex align-items-center gap-1.5">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path fill="#25D366" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2z"/><path fill="#FFFFFF" d="M17.5 14.33c-.28-.14-1.65-.81-1.9-.91-.26-.09-.45-.14-.64.14-.19.28-.73.91-.9 1.1-.16.19-.33.21-.61.07-.28-.14-1.18-.44-2.25-1.39-.83-.74-1.4-1.66-1.56-1.94-.16-.28-.02-.43.12-.57.13-.13.28-.33.42-.5.14-.16.19-.28.28-.47.09-.19.05-.35-.02-.5-.07-.14-.64-1.54-.87-2.11-.23-.56-.47-.48-.64-.49-.17-.01-.36-.01-.56-.01-.19 0-.5.07-.76.35-.26.28-1 1-1 2.43s1.03 2.82 1.17 3.01c.14.19 2.02 3.1 4.9 4.35 2.87 1.25 2.87.83 3.39.79.52-.05 1.65-.67 1.88-1.33.23-.65.23-1.22.16-1.33-.07-.12-.26-.19-.54-.33z"/></svg>
                <span class="fw-semibold text-dark" style="font-size:0.73rem;">WhatsApp Mobile Linked</span>
              </div>
              <button type="button" class="btn-dos-manage-link" id="dosWaQuickBtn" title="View QR Login and Meta Hub">
                Manage
              </button>
            </div>
          `;
          const waQuick = document.getElementById('dosWaQuickBtn');
          if (waQuick) {
            waQuick.addEventListener('click', () => {
              const metaBtn = document.getElementById('btnMetaHub');
              if (metaBtn) metaBtn.click();
            });
          }
        }
      } else if (s.channel === 'instagram' || s.channel === 'facebook') {
        if (pageRowLabel) pageRowLabel.textContent = 'Channel Source';
        const isIg = s.channel === 'instagram';
        const igSvg = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><defs><linearGradient id="igSourceGrad" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" stop-color="#FFD600"/><stop offset="25%" stop-color="#FF0069"/><stop offset="50%" stop-color="#D300C5"/><stop offset="75%" stop-color="#7638FA"/><stop offset="100%" stop-color="#00B2FE"/></linearGradient></defs><rect width="24" height="24" rx="6" fill="url(#igSourceGrad)"/><rect x="5.5" y="5.5" width="13" height="13" rx="3.5" stroke="#FFFFFF" stroke-width="1.8"/><circle cx="12" cy="12" r="3.2" stroke="#FFFFFF" stroke-width="1.8"/><circle cx="15.8" cy="8.2" r="0.9" fill="#FFFFFF"/></svg>`;
        const fbSvg = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path fill="#0084FF" d="M12 2C6.48 2 2 6.15 2 11.26c0 2.91 1.45 5.52 3.73 7.18v3.56l3.42-1.88c.9.25 1.86.39 2.85.39 5.52 0 10-4.15 10-9.25C22 6.15 17.52 2 12 2z"/><path fill="#FFFFFF" d="m6.8 13.7 3.3-3.5 2.5 2.5 4.6-4.5-3.3 3.5-2.5-2.5z"/></svg>`;
        if (pageView) {
          pageView.innerHTML = `
            <div class="d-flex align-items-center justify-content-between p-1.5 px-2 rounded" style="background:#F8FAFC; border:1px solid #E2E8F0;">
              <div class="d-flex align-items-center gap-1.5">
                ${isIg ? igSvg : fbSvg}
                <span class="fw-semibold text-dark" style="font-size:0.73rem;">${isIg ? 'Instagram Direct' : 'Facebook Messenger'}</span>
              </div>
              <button type="button" class="btn-dos-manage-link" id="dosMetaQuickBtn" title="Manage Meta Account">
                Settings
              </button>
            </div>
          `;
          const metaQuick = document.getElementById('dosMetaQuickBtn');
          if (metaQuick) {
            metaQuick.addEventListener('click', () => {
              const metaBtn = document.getElementById('btnMetaHub');
              if (metaBtn) metaBtn.click();
            });
          }
        }
      } else {
        if (pageRowLabel) pageRowLabel.textContent = 'Current Page';
        const isExternal = curPage.startsWith('http');
        const cleanHref = isExternal ? curPage : (curPage.startsWith('/') ? `..${curPage}` : `../${curPage}`);
        if (pageView) {
          pageView.innerHTML = `
            <a href="${cleanHref}" target="_blank" id="dosPageLink" class="text-dark text-decoration-none d-inline-flex align-items-center gap-1.5 p-1 px-2 rounded" style="background:#F8FAFC; border:1px solid #CBD5E1; font-size:0.73rem; max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:500;">
              <i class="bi bi-box-arrow-up-right text-primary" style="font-size:0.70rem;"></i>
              <span id="dosPageText">${escapeHtml(curPage)}</span>
            </a>
          `;
        }
      }

      // Mineral interest
      const mineralText = s.mineral || s.mineral_interest || 'Export Minerals';
      const mineralBadge = document.getElementById('dosMineralBadge');
      if (mineralBadge) mineralBadge.textContent = mineralText;
      if (!isDossierEditing) {
        const selectMin = document.getElementById('selectDosMineral');
        if (selectMin) {
          const matchOpt = Array.from(selectMin.options).find(o => o.value.toLowerCase() === mineralText.toLowerCase());
          if (matchOpt) selectMin.value = matchOpt.value;
        }
      }

      // Officer
      const dosAgent = document.getElementById('dosAgent');
      if (dosAgent) dosAgent.textContent = s.assigned_name || 'Unassigned';

      // Notes
      if (document.activeElement !== document.getElementById('dosInternalNotes')) {
        document.getElementById('dosInternalNotes').value = s.internal_notes || '';
      }
    }

    // Fetch Messages
    async function fetchMessages(scrollToBottom = false, isBackgroundPoll = false) {
      if (!currentSessionId) return;
      try {
        const res = await fetch(`api-chat-admin.php?action=fetch_messages&session_id=${currentSessionId}&last_id=${lastMessageId}`);
        const data = await res.json();
        if (data.success) {
          const s = data.session;
          activeSessionCache = s;
          populateDossierFields(s);

          // Claim button visibility
          const claimBtn = document.getElementById('btnClaimChat');
          if (s.status === 'waiting_human' || !s.assigned_id) {
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

                const row = document.createElement('div');
                let rowClass = 'chat-msg-row is-visitor';
                let bubbleClass = 'chat-bubble bubble-visitor';
                let metaHtml = `<span>${m.time}</span>`;
                let avatarHtml = `<div class="chat-msg-avatar chat-msg-avatar--visitor" title="Buyer / Contact"><i class="bi bi-person-fill"></i></div>`;

                if (m.sender_type === 'agent') {
                  rowClass = 'chat-msg-row is-agent';
                  bubbleClass = 'chat-bubble bubble-agent';
                  metaHtml = `<span>${m.time} &bull; Delivered</span>`;
                  avatarHtml = `<div class="chat-msg-avatar chat-msg-avatar--agent" title="Balochistan Minerals"><img src="../images/favicon/apple-touch-icon.png" alt="BM" class="chat-avatar-logo"></div>`;
                } else if (m.sender_type === 'bot') {
                  rowClass = 'chat-msg-row is-bot';
                  bubbleClass = 'chat-bubble bubble-bot';
                  metaHtml = `<span>AI Assistant</span> &bull; <span>${m.time}</span>`;
                  avatarHtml = `<div class="chat-msg-avatar chat-msg-avatar--bot" title="AI Assistant"><i class="bi bi-robot"></i></div>`;
                } else if (m.sender_type === 'system') {
                  rowClass = 'chat-msg-row is-system';
                  bubbleClass = 'chat-bubble bubble-system';
                  metaHtml = `<span>${m.time}</span>`;
                  avatarHtml = '';
                } else {
                  metaHtml = `<span>${escapeHtml(m.sender_name || 'Buyer')}</span> &bull; <span>${m.time}</span>`;
                }

                row.className = rowClass;
                if (m.sender_type === 'agent') {
                  row.innerHTML = `
                    <div class="chat-bubble-wrap is-agent">
                      <div class="${bubbleClass}">
                        ${escapeHtml(m.text).replace(/\n/g, '<br>')}
                      </div>
                      <div class="bubble-meta">
                        ${metaHtml}
                      </div>
                    </div>
                    ${avatarHtml}
                  `;
                } else if (m.sender_type === 'system') {
                  row.innerHTML = `
                    <div class="chat-bubble-wrap is-system">
                      <div class="${bubbleClass}">
                        ${escapeHtml(m.text).replace(/\n/g, '<br>')}
                      </div>
                      <div class="bubble-meta">
                        ${metaHtml}
                      </div>
                    </div>
                  `;
                } else {
                  row.innerHTML = `
                    ${avatarHtml}
                    <div class="chat-bubble-wrap ${m.sender_type === 'bot' ? 'is-bot' : 'is-visitor'}">
                      <div class="${bubbleClass}">
                        ${escapeHtml(m.text).replace(/\n/g, '<br>')}
                      </div>
                      <div class="bubble-meta">
                        ${metaHtml}
                      </div>
                    </div>
                  `;
                }
                body.appendChild(row);
              }
            });

            if (hasNewVisitorMsg && isBackgroundPoll) {
              playAudioChime();
            }

            if (scrollToBottom || hasNewVisitorMsg) {
              body.scrollTop = body.scrollHeight;
            }
          }
        } else if (data && data.error === 'Session not found') {
          currentSessionId = null;
          lastMessageId = 0;
          document.getElementById('emptyChatScreen').style.display = 'flex';
          document.getElementById('activeChatContent').style.display = 'none';
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

    // Convert to RFQ (Header Button & Dossier Button)
    async function convertSessionToRfq() {
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
    }

    const btnConvertToRfq = document.getElementById('btnConvertToRfq');
    if (btnConvertToRfq) btnConvertToRfq.addEventListener('click', convertSessionToRfq);
    const dosBtnRfq = document.getElementById('dosBtnRfq');
    if (dosBtnRfq) dosBtnRfq.addEventListener('click', convertSessionToRfq);

    function escapeHtml(str) {
      return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    // =========================================================================
    // META ACCOUNTS & CHANNELS HUB CONTROLLER
    // =========================================================================
    let metaHubModalInstance = null;

    function getMetaModal() {
      if (!metaHubModalInstance && window.bootstrap && bootstrap.Modal) {
        metaHubModalInstance = new bootstrap.Modal(document.getElementById('metaHubModal'));
      }
      return metaHubModalInstance;
    }

    document.getElementById('btnMetaHub').addEventListener('click', async () => {
      const modal = getMetaModal();
      if (modal) modal.show();
      await refreshWhatsAppCompanionStatus();
      await loadMetaSettings();
    });

    // 1. WhatsApp Companion Handlers
    async function refreshWhatsAppCompanionStatus() {
      const container = document.getElementById('waQrContainer');
      const badge = document.getElementById('waStatusBadge');
      const phoneEl = document.getElementById('waConnectedPhone');
      const matrixWa = document.getElementById('matrixWaStatus');

      try {
        const res = await fetch('../api/whatsapp-companion.php?action=get_status');
        const data = await res.json();
        if (data.success) {
          if (data.is_connected) {
            badge.innerHTML = `<span class="badge d-inline-flex align-items-center gap-1.5 py-1 px-2.5" style="background: #DCFCE7; color: #166534; border: 1px solid #86EFAC; font-size: 0.72rem; border-radius: 6px;"><span class="status-dot" style="background:#16A34A; width:5px; height:5px;"></span> Active &amp; Synced</span>`;
            if (matrixWa) {
              matrixWa.innerHTML = `Connected (${escapeHtml(data.phone)})`;
              matrixWa.className = 'badge bg-success';
            }
            container.innerHTML = `
              <div class="p-3 text-center w-100">
                <div class="mx-auto mb-2.5 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; border-radius: 50%; background: #F0FDF4; border: 1.5px solid #86EFAC; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.12);">
                  <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                    <path fill="#25D366" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2z"/>
                    <path fill="#FFFFFF" d="M17.5 14.33c-.28-.14-1.65-.81-1.9-.91-.26-.09-.45-.14-.64.14-.19.28-.73.91-.9 1.1-.16.19-.33.21-.61.07-.28-.14-1.18-.44-2.25-1.39-.83-.74-1.4-1.66-1.56-1.94-.16-.28-.02-.43.12-.57.13-.13.28-.33.42-.5.14-.16.19-.28.28-.47.09-.19.05-.35-.02-.5-.07-.14-.64-1.54-.87-2.11-.23-.56-.47-.48-.64-.49-.17-.01-.36-.01-.56-.01-.19 0-.5.07-.76.35-.26.28-1 1-1 2.43s1.03 2.82 1.17 3.01c.14.19 2.02 3.1 4.9 4.35 2.87 1.25 2.87.83 3.39.79.52-.05 1.65-.67 1.88-1.33.23-.65.23-1.22.16-1.33-.07-.12-.26-.19-.54-.33z"/>
                  </svg>
                </div>
                <div class="fw-bold text-dark" style="font-size: 0.92rem; letter-spacing: -0.01em;">WhatsApp Companion Linked</div>
                <div class="fw-semibold text-secondary font-monospace mt-1" style="font-size: 0.82rem;">${escapeHtml(data.phone)}</div>
                <div class="d-inline-flex align-items-center gap-1.5 mt-2 py-1 px-2.5 rounded-pill" style="background: #F0FDF4; border: 1px solid #BBF7D0; color: #166534; font-size: 0.70rem; font-weight: 600;">
                  <span class="status-dot" style="background:#16A34A; width:5px; height:5px;"></span>
                  <span>Multi-Device Sync Active</span>
                </div>
              </div>
            `;
          } else {
            badge.innerHTML = `<span class="badge d-inline-flex align-items-center gap-1.5 py-1 px-2.5" style="background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; font-size: 0.72rem; border-radius: 6px;"><i class="bi bi-qr-code-scan"></i> Standby (Scan QR)</span>`;
            if (matrixWa) {
              matrixWa.innerHTML = 'Standby';
              matrixWa.className = 'badge bg-secondary';
            }
            if (data.qr_svg) {
              container.innerHTML = data.qr_svg;
            }
          }
          if (phoneEl) phoneEl.textContent = data.phone || '+92 334 8888104';
        }
      } catch (e) {
        if (badge) badge.innerHTML = `<span class="badge bg-danger">Error fetching status</span>`;
      }
    }

    // Refresh QR Button
    document.getElementById('btnRefreshWaQr').addEventListener('click', async () => {
      const container = document.getElementById('waQrContainer');
      container.innerHTML = '<div class="spinner-border text-success" role="status"></div>';
      try {
        const res = await fetch('../api/whatsapp-companion.php?action=generate_qr');
        const data = await res.json();
        if (data.success && data.qr_svg) {
          container.innerHTML = data.qr_svg;
          const badge = document.getElementById('waStatusBadge');
          if (badge) badge.innerHTML = `<span class="badge bg-warning text-dark d-inline-flex align-items-center gap-1"><i class="bi bi-qr-code-scan"></i> Awaiting Phone Scan</span>`;
        }
      } catch (e) {}
    });

    // Disconnect Button
    document.getElementById('btnDisconnectWa').addEventListener('click', async () => {
      if (!confirm('Disconnect WhatsApp Companion multi-device session?')) return;
      try {
        await fetch('../api/whatsapp-companion.php?action=disconnect');
        await refreshWhatsAppCompanionStatus();
      } catch (e) {}
    });

    // Mark Linked / Quick Confirm
    document.getElementById('btnQuickConfirmPair').addEventListener('click', async () => {
      const p = prompt('Confirm phone number authorized for WhatsApp Web Companion:', '+92 334 8888104');
      if (!p) return;
      try {
        await fetch('../api/whatsapp-companion.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'confirm_pairing', phone: p })
        });
        await refreshWhatsAppCompanionStatus();
      } catch (e) {}
    });

    // Run Simulated WhatsApp Message
    document.getElementById('btnRunWaSimulation').addEventListener('click', async () => {
      const phone = document.getElementById('simWaPhone').value.trim();
      const name = document.getElementById('simWaName').value.trim();
      const msg = document.getElementById('simWaMsg').value.trim();
      const resEl = document.getElementById('simWaResult');

      if (!msg) return;
      resEl.style.display = 'block';
      resEl.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i> Ingesting message...</span>';

      try {
        const res = await fetch('../api/whatsapp-companion.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'simulate_incoming', phone, name, message: msg })
        });
        const data = await res.json();
        if (data.success) {
          resEl.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> Ingested into Desk! ${escapeHtml(data.flag || '')} Session #${data.session_id} created.</span>`;
          fetchSessions();
        } else {
          resEl.innerHTML = `<span class="text-danger">Error: ${escapeHtml(data.error || 'Failed')}</span>`;
        }
      } catch (e) {
        resEl.innerHTML = `<span class="text-danger">Request failed.</span>`;
      }
    });

    // 2. Meta Social Handlers
    async function loadMetaSettings() {
      try {
        const res = await fetch('api-chat-admin.php?action=get_meta_settings');
        const data = await res.json();
        if (data.success) {
          const s = data.settings || {};
          document.getElementById('metaWebhookUrlDisplay').value = data.webhook_url || '';
          document.getElementById('metaVerifyTokenDisplay').value = s.meta_verify_token || '';
          document.getElementById('inputFbPageId').value = s.fb_page_id || '';
          document.getElementById('inputIgAccountId').value = s.ig_account_id || '';
          document.getElementById('inputFbPageToken').value = s.fb_page_access_token || '';
          document.getElementById('inputMetaAppId').value = s.meta_app_id || '';
          document.getElementById('inputMetaAppSecret').value = s.meta_app_secret || '';

          // Update matrix badges
          const matrixIg = document.getElementById('matrixIgStatus');
          if (matrixIg) {
            matrixIg.textContent = s.fb_page_access_token ? 'Active / Configured' : 'Needs Token';
            matrixIg.className = s.fb_page_access_token ? 'badge bg-success' : 'badge bg-secondary';
          }
          const matrixFb = document.getElementById('matrixFbStatus');
          if (matrixFb) {
            matrixFb.textContent = s.fb_page_access_token ? 'Active / Configured' : 'Needs Token';
            matrixFb.className = s.fb_page_access_token ? 'badge bg-success' : 'badge bg-secondary';
          }
        }
      } catch (e) {}
    }

    // Copy buttons
    document.getElementById('btnCopyWebhookUrl').addEventListener('click', () => {
      const input = document.getElementById('metaWebhookUrlDisplay');
      input.select();
      navigator.clipboard.writeText(input.value);
      alert('Webhook URL copied to clipboard: ' + input.value);
    });
    document.getElementById('btnCopyVerifyToken').addEventListener('click', () => {
      const input = document.getElementById('metaVerifyTokenDisplay');
      input.select();
      navigator.clipboard.writeText(input.value);
      alert('Verify Token copied to clipboard: ' + input.value);
    });

    // Save Meta Settings
    document.getElementById('btnSaveMetaSettings').addEventListener('click', async () => {
      const payload = {
        action: 'save_meta_settings',
        fb_page_id: document.getElementById('inputFbPageId').value.trim(),
        ig_account_id: document.getElementById('inputIgAccountId').value.trim(),
        fb_page_access_token: document.getElementById('inputFbPageToken').value.trim(),
        meta_app_id: document.getElementById('inputMetaAppId').value.trim(),
        meta_app_secret: document.getElementById('inputMetaAppSecret').value.trim(),
      };

      try {
        const res = await fetch('api-chat-admin.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
          const notice = document.getElementById('metaSettingsSaveNotice');
          if (notice) {
            notice.style.display = 'inline-block';
            setTimeout(() => { notice.style.display = 'none'; }, 2500);
          }
          await loadMetaSettings();
        }
      } catch (e) {}
    });

    // Run Simulated Social Message
    document.getElementById('btnRunSocialSimulation').addEventListener('click', async () => {
      const channel = document.getElementById('simSocialChannel').value;
      const name = document.getElementById('simSocialName').value.trim();
      const country = document.getElementById('simSocialCountry').value.trim();
      const msg = document.getElementById('simSocialMsg').value.trim();
      const resEl = document.getElementById('simSocialResult');

      if (!msg) return;
      resEl.style.display = 'block';
      resEl.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i> Ingesting message...</span>';

      try {
        const res = await fetch('../api/meta-webhook.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'simulate',
            channel: channel,
            sender_id: 'sim_' + channel + '_' + Date.now(),
            sender_name: name,
            country: country,
            message: msg
          })
        });
        const data = await res.json();
        if (data.success) {
          resEl.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> Ingested into Desk! ${escapeHtml(channel.toUpperCase())} Session #${data.session_id} created.</span>`;
          fetchSessions();
        } else {
          resEl.innerHTML = `<span class="text-danger">Error: ${escapeHtml(data.error || 'Failed')}</span>`;
        }
      } catch (e) {
        resEl.innerHTML = `<span class="text-danger">Request failed.</span>`;
      }
    });

    // Auto-poll loop every 2.5 seconds
    fetchSessions();
    pollInterval = setInterval(() => {
      fetchSessions();
      if (currentSessionId) {
        fetchMessages(false, true);
      }
    }, 2500);
  </script>
</body>
</html>
