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
    .badge-status-waiting {
      display: inline-flex;
      align-items: center;
      padding: 2.5px 7px;
      border-radius: 4px;
      font-size: 0.62rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      background: #FEF3C7;
      color: #92400E;
      border: 1px solid #FCD34D;
      font-family: var(--font-mono, monospace);
    }
    .badge-status-live {
      display: inline-flex;
      align-items: center;
      padding: 2.5px 7px;
      border-radius: 4px;
      font-size: 0.62rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      background: #ECFDF5;
      color: #065F46;
      border: 1px solid #A7F3D0;
      font-family: var(--font-mono, monospace);
    }
    .badge-status-bot {
      display: inline-flex;
      align-items: center;
      padding: 2.5px 7px;
      border-radius: 4px;
      font-size: 0.62rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      background: #F1F5F9;
      color: #475569;
      border: 1px solid #CBD5E1;
      font-family: var(--font-mono, monospace);
    }
    .badge-status-unread {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 17px;
      height: 17px;
      padding: 0 4px;
      border-radius: 9px;
      font-size: 0.62rem;
      font-weight: 700;
      background: #991B1B;
      color: #FFFFFF;
      line-height: 1;
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
      border-left: 3.5px solid #475569;
    }
    .conv-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: #F1F5F9;
      color: #0F172A;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.8rem;
      flex-shrink: 0;
      border: 1px solid #CBD5E1;
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
      position: absolute;
      bottom: -3px;
      right: -3px;
      font-size: 0.74rem;
      line-height: 1;
      filter: drop-shadow(0 1px 1px rgba(0,0,0,0.25));
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
          <div class="fw-bold small text-truncate" style="color: var(--brand-navy); font-size: 0.8rem;" title="<?= htmlspecialchars($user['full_name']) ?>"><?= htmlspecialchars($user['full_name']) ?></div>
          <a href="logout.php" class="btn btn-sm btn-outline-secondary p-1 px-2 flex-shrink-0" title="Sign Out">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </div>
        <div class="sidebar-footer-row-meta d-flex align-items-center justify-content-between gap-2">
          <div class="text-muted text-truncate" style="font-size: 0.7rem;"><?= htmlspecialchars($currentTier) ?></div>
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
                <div class="active-chat-avatar" id="chatHeaderAvatar" style="position: relative;">
                  <span id="chatHeaderInitial">G</span>
                  <span id="chatHeaderFlag" class="conv-avatar-flag" style="display: none;"></span>
                </div>
                <div class="active-chat-info">
                  <div class="active-chat-name" id="chatHeaderTitleText">Guest Buyer</div>
                  <div class="active-chat-sub" id="chatHeaderSub">
                    <span class="active-chat-country" id="chatHeaderCountry">International</span>
                    <span class="active-chat-sep">&bull;</span>
                    <span class="active-chat-sub-label d-none d-md-inline">Local:</span>
                    <span class="active-chat-time font-monospace d-none d-sm-inline" id="chatHeaderLocalTime">—</span>
                    <span class="active-chat-sep d-none d-sm-inline">&bull;</span>
                    <span class="active-chat-sub-label d-none d-sm-inline">Viewing:</span>
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
                <button type="button" class="btn-chat-header btn-chat-header--rfq" id="btnConvertToRfq" title="Transfer lead and transcript into Inbound RFQs database">
                  <i class="bi bi-file-earmark-plus"></i>
                  <span class="d-none d-sm-inline">RFQ</span>
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

          <!-- Current Page (Live Navigation) -->
          <div class="dossier-row">
            <div class="dossier-label">Current Page</div>
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
            <a href="generate-quote.php" target="_blank" class="btn btn-sm btn-outline-dark w-100 mb-2" style="font-size: 0.75rem;">
              <i class="bi bi-file-earmark-text me-1"></i>Open Quote Generator
            </a>
            <a href="stockpiles.php" target="_blank" class="btn btn-sm btn-outline-secondary w-100" style="font-size: 0.75rem;">
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
          ? '<span class="badge-status-waiting">WAITING</span>'
          : (s.status === 'agent_active' 
              ? '<span class="badge-status-live">LIVE</span>'
              : '<span class="badge-status-bot">AI BOT</span>');

        const unread = s.unread_admin > 0 ? `<span class="badge-status-unread">${s.unread_admin}</span>` : '';
        const nameStr = s.display_name || s.name || `Buyer #${1000 + s.id}`;
        const initial = (nameStr.replace(/[^a-zA-Z]/g, '') || 'B').charAt(0).toUpperCase();
        const flag = (s.flag && s.flag !== '🌐') ? s.flag : '';
        let locSnippet = '';
        if (flag) {
          locSnippet = `<span class="me-1">${flag}</span> ${escapeHtml(s.country || 'International')}${s.city ? ` (${escapeHtml(s.city)})` : ''}`;
        } else {
          locSnippet = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="d-inline-block me-1" style="vertical-align: -1px; color: #64748B;"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>International`;
        }

        return `
          <div class="conv-item ${isWaiting ? 'is-waiting' : ''} ${isActive ? 'active' : ''}" onclick="selectSession(${s.id})">
            <div class="conv-avatar">
              <span>${initial}</span>
              ${flag ? `<span class="conv-avatar-flag">${flag}</span>` : ''}
            </div>
            <div class="conv-content">
              <div class="conv-top-line">
                <span class="conv-name">${escapeHtml(nameStr)}</span>
                <span class="conv-time">${s.last_time || ''}</span>
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

      const headerInitial = document.getElementById('chatHeaderInitial');
      if (headerInitial) {
        headerInitial.textContent = (vName.replace(/[^a-zA-Z]/g, '') || 'B').charAt(0).toUpperCase();
      }
      const headerFlag = document.getElementById('chatHeaderFlag');
      if (headerFlag) {
        if (s.flag && s.flag !== '🌐') {
          headerFlag.textContent = s.flag;
          headerFlag.style.display = 'inline';
        } else {
          headerFlag.style.display = 'none';
        }
      }

      const globeSvgSmall = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="d-inline-block me-1" style="vertical-align: -1.5px; color: #475569;"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>`;
      const globeSvgMedium = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="d-inline-block me-1" style="vertical-align: -2px; color: #475569;"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>`;

      const countryEl = document.getElementById('chatHeaderCountry');
      if (countryEl) {
        if (s.flag && s.flag !== '🌐') {
          countryEl.innerHTML = `<span class="me-1">${s.flag}</span> <span>${escapeHtml(s.country || 'International')}${s.city ? ` (${escapeHtml(s.city)})` : ''}</span>`;
        } else {
          countryEl.innerHTML = `${globeSvgSmall}<span>International</span>`;
        }
      }

      const localTimeEl = document.getElementById('chatHeaderLocalTime');
      if (localTimeEl) localTimeEl.textContent = s.local_time_str || '—';

      const pathEl = document.getElementById('chatHeaderSubPath');
      if (pathEl) pathEl.textContent = s.page || s.current_page || '/';

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
        if (s.flag && s.flag !== '🌐') {
          dosCountryView.innerHTML = `<span class="me-1">${s.flag}</span> <span>${escapeHtml(s.country || 'International')}${s.city ? ` (${escapeHtml(s.city)})` : ''}</span>`;
        } else {
          dosCountryView.innerHTML = `${globeSvgMedium}<span>International</span>`;
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
          dosPhoneView.innerHTML = `
            <div class="d-flex align-items-center justify-content-between gap-1">
              <span class="font-monospace">${escapeHtml(s.phone)}</span>
              <a href="${waUrl}" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-success text-white py-0 px-1.5 d-inline-flex align-items-center gap-1" style="font-size:0.67rem; border-radius:4px;" title="Chat directly with buyer on WhatsApp">
                <i class="bi bi-whatsapp"></i> Chat
              </a>
            </div>
          `;
        } else {
          dosPhoneView.innerHTML = '<span class="text-muted fst-italic" style="font-size:0.75rem;">Not captured yet</span>';
        }
      }
      if (!isDossierEditing) document.getElementById('inputDosPhone').value = s.phone || '';

      // Current page link
      const curPage = s.page || s.current_page || '/';
      const pageText = document.getElementById('dosPageText');
      if (pageText) pageText.textContent = curPage;
      const pageLink = document.getElementById('dosPageLink');
      if (pageLink) pageLink.href = curPage;

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

    document.getElementById('btnConvertToRfq').addEventListener('click', convertSessionToRfq);
    document.getElementById('dosBtnRfq').addEventListener('click', convertSessionToRfq);

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
        fetchMessages(false, true);
      }
    }, 2500);

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
        fetchMessages(false, true);
      }
    }, 2500);
  </script>
</body>
</html>
