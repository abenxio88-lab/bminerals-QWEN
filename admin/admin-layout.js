/**
 * Balochistan Minerals - Executive Trade Desk Unified Layout Scripts
 * Handles collapsible sidebar, responsive drawers, keyboard shortcuts, and state persistence
 */

(function () {
  'use strict';

  const STORAGE_KEY = 'bm_sidebar_collapsed';

  function initSidebar() {
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    const backdrop = document.getElementById('sidebarBackdrop');

    // Restore desktop preference on load
    const isMobile = window.innerWidth < 1100;
    const isCollapsedSaved = localStorage.getItem(STORAGE_KEY) === '1';

    if (!isMobile && isCollapsedSaved) {
      document.body.classList.add('sidebar-collapsed');
    }

    function toggleSidebar() {
      if (window.innerWidth < 1100) {
        // Mobile / Tablet Drawer toggle
        document.body.classList.toggle('sidebar-open');
      } else {
        // Desktop collapse toggle
        const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
        try {
          localStorage.setItem(STORAGE_KEY, isCollapsed ? '1' : '0');
        } catch (e) {}
      }
    }

    function closeMobileSidebar() {
      document.body.classList.remove('sidebar-open');
    }

    // Bind all sidebar toggle / collapse buttons across topbar and sidebar panel
    document.querySelectorAll('#sidebarToggle, #sidebarCloseBtn, .btn-sidebar-toggle, .btn-sidebar-panel-toggle').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        toggleSidebar();
      });
    });

    if (backdrop) {
      backdrop.addEventListener('click', closeMobileSidebar);
    }

    // Keyboard shortcut: Ctrl + B or Cmd + B to toggle sidebar
    window.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
        const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
        if (activeTag !== 'input' && activeTag !== 'textarea') {
          e.preventDefault();
          toggleSidebar();
        }
      }
    });

    // Window resize handler: clean up state transitions
    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        if (window.innerWidth >= 1100) {
          document.body.classList.remove('sidebar-open');
          const isCollapsed = localStorage.getItem(STORAGE_KEY) === '1';
          if (isCollapsed) {
            document.body.classList.add('sidebar-collapsed');
          } else {
            document.body.classList.remove('sidebar-collapsed');
          }
        }
      }, 100);
    });
  }

  // Live PKT Clock
  function initLiveClock() {
    const clockEl = document.getElementById('livePktClock');
    if (!clockEl) return;

    function update() {
      try {
        const now = new Date();
        const options = {
          timeZone: 'Asia/Karachi',
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hour12: true
        };
        clockEl.textContent = new Intl.DateTimeFormat('en-US', options).format(now);
      } catch (e) {
        const now = new Date();
        clockEl.textContent = now.toLocaleTimeString('en-US', { hour12: true });
      }
    }

    update();
    setInterval(update, 1000);
  }

  // ==========================================================================
  // STRETCHABLE & RESIZABLE WINDOW MODALS (Real Windows Feel)
  // ==========================================================================
  function initResizableModals() {
    function setupModal(dialog) {
      if (dialog._resizableInitialized) return;
      dialog._resizableInitialized = true;

      const content = dialog.querySelector('.modal-content');
      if (!content) return;

      // 1. Add Corner Resize Handle if missing
      let handle = content.querySelector('.modal-resize-handle');
      if (!handle) {
        handle = document.createElement('div');
        handle.className = 'modal-resize-handle';
        handle.title = 'Drag corner to resize window';
        content.appendChild(handle);
      }

      // 2. Add Maximize / Restore Button to modal header
      const header = content.querySelector('.modal-header');
      if (header && !header.querySelector('.btn-modal-maximize')) {
        const closeBtn = header.querySelector('.btn-close');
        const maxBtn = document.createElement('button');
        maxBtn.type = 'button';
        maxBtn.className = 'btn-window-control btn-modal-maximize me-1.5';
        maxBtn.title = 'Maximize / Restore window';
        maxBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
        maxBtn.setAttribute('aria-label', 'Maximize or Restore');

        maxBtn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const isMax = dialog.classList.toggle('modal-maximized');
          maxBtn.innerHTML = isMax ? '<i class="bi bi-fullscreen-exit"></i>' : '<i class="bi bi-arrows-fullscreen"></i>';
          maxBtn.title = isMax ? 'Restore window size' : 'Maximize window';
          if (!isMax) {
            dialog.style.maxWidth = '';
            dialog.style.width = '';
            content.style.height = '';
          }
        });

        // Double-click modal header bar to toggle maximize (standard OS behavior)
        header.addEventListener('dblclick', (e) => {
          if (e.target.closest('button, input, select, textarea, a, .btn-close')) return;
          maxBtn.click();
        });

        if (closeBtn) {
          header.insertBefore(maxBtn, closeBtn);
        } else {
          header.appendChild(maxBtn);
        }
      }

      // 3. Smooth Corner Drag Resizing Handle (X & Y)
      let isDragging = false;
      let startX, startY, startWidth, startHeight;

      handle.addEventListener('mousedown', (e) => {
        if (dialog.classList.contains('modal-maximized')) return;
        e.preventDefault();
        e.stopPropagation();
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        const rect = content.getBoundingClientRect();
        startWidth = rect.width;
        startHeight = rect.height;

        document.body.style.userSelect = 'none';
        document.body.style.cursor = 'nwse-resize';

        function onMouseMove(moveEvent) {
          if (!isDragging) return;
          const dx = moveEvent.clientX - startX;
          const dy = moveEvent.clientY - startY;

          const minW = Math.min(560, window.innerWidth - 30);
          const maxW = window.innerWidth - 30;
          const minH = 420;
          const maxH = window.innerHeight - 40;

          const newW = Math.max(minW, Math.min(maxW, startWidth + dx));
          const newH = Math.max(minH, Math.min(maxH, startHeight + dy));

          dialog.style.maxWidth = newW + 'px';
          dialog.style.width = newW + 'px';
          content.style.height = newH + 'px';

          const modalBody = content.querySelector('.modal-body');
          if (modalBody && !(dialog.classList.contains('modal-dialog-reply-wide') && window.innerWidth >= 992)) {
            modalBody.style.flex = '1';
            modalBody.style.overflowY = 'auto';
          }
        }

        function onMouseUp() {
          isDragging = false;
          document.body.style.userSelect = '';
          document.body.style.cursor = '';
          window.removeEventListener('mousemove', onMouseMove);
          window.removeEventListener('mouseup', onMouseUp);
        }

        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp);
      });
    }

    document.querySelectorAll('.modal-dialog-resizable').forEach(setupModal);

    // Also watch for newly opened modals
    document.addEventListener('show.bs.modal', (e) => {
      const modal = e.target;
      const resizableDialog = modal ? modal.querySelector('.modal-dialog-resizable') : null;
      if (resizableDialog) {
        setupModal(resizableDialog);
      }
    });
  }

  // Initialize Bootstrap Tooltips
  function initTooltips() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
        try {
          if (!bootstrap.Tooltip.getInstance(el)) {
            new bootstrap.Tooltip(el, { container: 'body' });
          }
        } catch (e) {}
      });
    }
  }

  // ==========================================================================
  // UNIFIED INDUSTRY-STANDARD NOTIFICATION CENTER & TOAST STACK
  // ==========================================================================
  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function createToastContainer() {
    let container = document.getElementById('bmUnifiedToastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'bmUnifiedToastContainer';
      container.className = 'live-toast-container';
      document.body.appendChild(container);
    }
    return container;
  }

  function showToast(opts) {
    const cfg = (typeof opts === 'string')
      ? { title: opts, message: arguments[1] || '', type: 'info' }
      : (opts || {});
    const type = ['success', 'info', 'warning', 'error'].includes(cfg.type) ? cfg.type : 'info';
    const title = cfg.title || 'Notification';
    const message = cfg.message || '';
    const inquiryId = cfg.inquiryId || null;
    const duration = cfg.duration || ((type === 'warning' || type === 'error') ? 10000 : 5500);

    const container = createToastContainer();
    // Enforce clean max-3 visible stack so toasts never pile across the viewport
    const existing = container.querySelectorAll('.live-toast:not(.hiding)');
    if (existing.length >= 3) {
      const oldest = existing[0];
      oldest.remove();
    }

    const iconMap = {
      success: 'bi-check-circle-fill',
      warning: 'bi-exclamation-triangle-fill',
      error: 'bi-x-octagon-fill',
      info: 'bi-bell-fill'
    };

    const toast = document.createElement('div');
    toast.className = `live-toast toast-${type}`;

    let ctaHtml = '';
    if (inquiryId && typeof window.openBuyerDossier === 'function') {
      ctaHtml = `<button type="button" class="live-toast-btn" data-dossier-id="${Number(inquiryId)}"><i class="bi bi-folder2-open me-1"></i> Open Dossier</button>`;
    } else if (cfg.openBell) {
      ctaHtml = `<button type="button" class="live-toast-btn" data-open-bell="1"><i class="bi bi-bell me-1"></i> View in Notifications</button>`;
    }

    toast.innerHTML = `
      <div class="live-toast-icon"><i class="bi ${iconMap[type]}"></i></div>
      <div class="live-toast-body">
        <div class="live-toast-title">${escapeHtml(title)}</div>
        <div class="live-toast-text">${escapeHtml(message)}</div>
        ${ctaHtml}
      </div>
      <button type="button" class="live-toast-close" aria-label="Dismiss">&times;</button>
    `;

    const dismiss = () => {
      if (toast.classList.contains('hiding')) return;
      toast.classList.add('hiding');
      setTimeout(() => toast.remove(), 260);
    };

    toast.querySelector('.live-toast-close')?.addEventListener('click', dismiss);
    toast.querySelector('[data-dossier-id]')?.addEventListener('click', () => {
      if (typeof window.openBuyerDossier === 'function') {
        window.openBuyerDossier(Number(inquiryId));
      }
      dismiss();
    });
    toast.querySelector('[data-open-bell]')?.addEventListener('click', () => {
      if (window.BMNotify && typeof window.BMNotify.open === 'function') {
        window.BMNotify.open();
      }
      dismiss();
    });

    // Hover pauses auto-dismiss so long alerts or errors can be read comfortably
    let timer = setTimeout(dismiss, duration);
    toast.addEventListener('mouseenter', () => { clearTimeout(timer); });
    toast.addEventListener('mouseleave', () => { timer = setTimeout(dismiss, 3500); });

    container.appendChild(toast);
  }

  function initNotificationCenter() {
    const topbarRight = document.querySelector('.top-bar .topbar-right');
    if (!topbarRight || document.getElementById('bmNotifWrap')) return;

    let notifCsrf = '';
    const wrap = document.createElement('div');
    wrap.id = 'bmNotifWrap';
    wrap.className = 'bm-notif-wrap';
    wrap.innerHTML = `
      <button type="button" id="bmNotifBtn" class="bm-notif-btn" title="Notifications & Sync Alerts" aria-expanded="false">
        <i class="bi bi-bell"></i>
        <span id="bmNotifBadge" class="bm-notif-badge">0</span>
      </button>
      <div id="bmNotifPanel" class="bm-notif-panel" role="region" aria-label="Notification Center">
        <div class="bm-notif-header">
          <span class="bm-notif-header-title"><i class="bi bi-bell-fill text-warning me-1"></i> Notifications</span>
          <div class="bm-notif-actions">
            <button type="button" id="bmNotifMarkAll" class="bm-notif-action-link">Mark all read</button>
            <button type="button" id="bmNotifClearAll" class="bm-notif-action-link">Clear</button>
          </div>
        </div>
        <div id="bmNotifList" class="bm-notif-list">
          <div class="bm-notif-empty">Loading notifications...</div>
        </div>
      </div>
    `;

    // Insert cleanly as first button inside topbar-right
    topbarRight.insertBefore(wrap, topbarRight.firstChild);

    const btn = document.getElementById('bmNotifBtn');
    const panel = document.getElementById('bmNotifPanel');
    const badge = document.getElementById('bmNotifBadge');
    const list = document.getElementById('bmNotifList');

    async function refreshNotifications() {
      try {
        const res = await fetch('api-notifications.php');
        if (!res.ok) return;
        const data = await res.json();
        if (!data || !data.success) return;

        notifCsrf = data.csrf_token || notifCsrf;
        const unread = Number(data.unread_count || 0);
        if (badge) {
          badge.textContent = unread > 99 ? '99+' : String(unread);
          badge.classList.toggle('is-visible', unread > 0);
        }

        const items = Array.isArray(data.notifications) ? data.notifications : [];
        if (!items.length) {
          list.innerHTML = '<div class="bm-notif-empty"><i class="bi bi-check2-circle fs-4 d-block mb-1 text-success"></i>All caught up — no notifications.</div>';
          return;
        }

        list.innerHTML = items.map((n) => {
          const unreadCls = n.is_read ? '' : 'is-unread';
          const canDossier = n.inquiry_id && typeof window.openBuyerDossier === 'function';
          const ctaLabel = canDossier ? 'Open Dossier' : (n.link_url ? 'Open Page' : '');
          const ctaAttr = canDossier
            ? `data-open-dossier="${Number(n.inquiry_id)}"`
            : (n.link_url ? `href="${escapeHtml(n.link_url)}"` : '');
          const ctaEl = ctaLabel
            ? (canDossier
                ? `<button type="button" class="bm-notif-action-link text-primary p-0" ${ctaAttr}>${ctaLabel} →</button>`
                : `<a class="bm-notif-item-cta" ${ctaAttr}>${ctaLabel} →</a>`)
            : '';
          return `
            <div class="bm-notif-item ${unreadCls}" data-notif-id="${Number(n.id)}">
              <span class="bm-notif-dot type-${escapeHtml(n.type || 'info')}"></span>
              <div class="bm-notif-item-body">
                <div class="bm-notif-item-title">${escapeHtml(n.title)}</div>
                <div class="bm-notif-item-msg">${escapeHtml(n.message)}</div>
                <div class="bm-notif-item-meta">
                  <span>${escapeHtml(n.created_human || n.created_at || '')}</span>
                  ${ctaEl}
                </div>
              </div>
            </div>
          `;
        }).join('');
      } catch (e) {}
    }

    async function postNotifAction(action, extra = {}) {
      if (!notifCsrf) await refreshNotifications();
      const body = new URLSearchParams({ action, csrf_token: notifCsrf, ...extra });
      await fetch('api-notifications.php', { method: 'POST', body });
      await refreshNotifications();
    }

    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const open = panel.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) refreshNotifications();
    });

    document.addEventListener('click', (e) => {
      if (!wrap.contains(e.target)) {
        panel.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
      }
    });

    list.addEventListener('click', async (e) => {
      const dossierBtn = e.target.closest('[data-open-dossier]');
      const row = e.target.closest('[data-notif-id]');
      const id = row ? row.getAttribute('data-notif-id') : null;
      if (id && row.classList.contains('is-unread')) {
        await postNotifAction('mark_read', { id });
      }
      if (dossierBtn && typeof window.openBuyerDossier === 'function') {
        e.preventDefault();
        const inqId = Number(dossierBtn.getAttribute('data-open-dossier'));
        panel.classList.remove('is-open');
        window.openBuyerDossier(inqId);
      }
    });

    document.getElementById('bmNotifMarkAll')?.addEventListener('click', (e) => {
      e.stopPropagation();
      postNotifAction('mark_all_read');
    });
    document.getElementById('bmNotifClearAll')?.addEventListener('click', (e) => {
      e.stopPropagation();
      postNotifAction('clear_all');
    });

    window.BMNotify = {
      toast: showToast,
      refresh: refreshNotifications,
      open: () => {
        panel.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        refreshNotifications();
      }
    };

    // Adopt any server-rendered flash toast into unified non-overlapping stack
    const flashWrap = document.getElementById('flashToastContainer');
    if (flashWrap) {
      const ft = flashWrap.querySelector('.live-toast');
      if (ft) {
        const title = ft.querySelector('.live-toast-title')?.textContent?.trim() || 'Notice';
        const msg = ft.querySelector('.live-toast-text')?.textContent?.trim() || '';
        const iconStyle = ft.querySelector('.live-toast-icon')?.getAttribute('style') || '';
        const t = iconStyle.includes('EF4444') ? 'error' : (iconStyle.includes('F59E0B') ? 'warning' : 'success');
        flashWrap.remove();
        showToast({ type: t, title, message: msg });
      }
    }

    refreshNotifications();
    setInterval(() => {
      if (document.visibilityState === 'visible') refreshNotifications();
    }, 30000);
  }

  if (!window.BMNotify) {
    window.BMNotify = { toast: showToast, refresh: () => {}, open: () => {} };
  }

  function hideBrokenRichEmailImg(img) {
    if (!img || img.dataset.bmBrokenImg === '1') return;
    img.style.display = 'none';
    img.dataset.bmBrokenImg = '1';
    const parentLink = img.closest('a');
    if (parentLink && parentLink.textContent.trim() === '') {
      const remainingImgs = parentLink.querySelectorAll('img:not([data-bm-broken-img="1"])');
      if (remainingImgs.length === 0) {
        parentLink.style.display = 'none';
      }
    }
  }

  function initEmailImageFallback() {
    document.addEventListener('error', (e) => {
      const el = e.target;
      if (!el || el.tagName !== 'IMG' || !el.closest('.thread-rich-body')) return;
      hideBrokenRichEmailImg(el);
    }, true);

    window.addEventListener('load', () => {
      document.querySelectorAll('.thread-rich-body img').forEach((img) => {
        if (img.complete && img.naturalWidth === 0) {
          hideBrokenRichEmailImg(img);
        }
      });
    });
  }

  // Initialize once DOM is ready
  initEmailImageFallback();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      initSidebar();
      initLiveClock();
      initResizableModals();
      initTooltips();
      initNotificationCenter();
    });
  } else {
    initSidebar();
    initLiveClock();
    initResizableModals();
    initTooltips();
    initNotificationCenter();
  }
})();
