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

  // ==========================================================================
  // EXECUTIVE REPLY MODAL CONTROLLER (RICH HTML VIEWER, COMPOSER, PREVIEW, FILES, DRAFTS)
  // ==========================================================================
  function htmlFragmentToPlainText(container) {
    if (!container) return '';
    const clone = container.cloneNode(true);
    clone.querySelectorAll('li').forEach((li) => {
      li.textContent = '• ' + li.textContent.trim();
    });
    clone.querySelectorAll('tr').forEach((tr) => {
      const cells = Array.from(tr.children).map((c) => c.textContent.trim()).filter(Boolean);
      if (cells.length) {
        tr.innerHTML = '';
        tr.textContent = cells.join(': ') + '\n';
      }
    });
    const raw = (clone.innerText || clone.textContent || '')
      .replace(/\r\n/g, '\n')
      .replace(/\n{3,}/g, '\n\n')
      .trim();
    return raw;
  }

  function formatReplyTemplateHtml(val, meta) {
    const buyer = escapeHtml(meta.buyerName || 'Buyer');
    const mineral = escapeHtml(meta.mineral || 'Minerals');
    const grade = meta.grade ? ` (${escapeHtml(meta.grade)})` : '';
    const qty = escapeHtml(meta.quantity || 'your required tonnage');
    const port = escapeHtml(meta.port || 'FOB Karachi');
    const price = escapeHtml(meta.price || '185.00');

    if (val === 'assay') {
      return `<p>Dear ${buyer},</p>`
        + `<p>In reference to your technical inquiry for <strong>${mineral}${grade}</strong>, we have verified that our ready Balochistan stockpiles conform to international export purity tolerances.</p>`
        + `<ul>`
        + `<li><strong>Chemical Assay:</strong> Sampled &amp; verified by SGS Pakistan / Alfred H. Knight at Karachi / Gwadar corridors</li>`
        + `<li><strong>Certificate of Analysis (COA):</strong> Issued upon lot demarcation prior to loading</li>`
        + `<li><strong>Moisture &amp; Sizing:</strong> Guaranteed export grade per commercial contract specification</li>`
        + `</ul>`
        + `<p>Please let us know if your metallurgical team requires a courier trial sample or scanned batch assay certificate.</p>`
        + `<p>Sincerely,<br><strong>Technical &amp; Quality Control Desk</strong><br>Balochistan Minerals (Pvt) Ltd &bull; Karachi / Quetta, Pakistan</p>`;
    }
    if (val === 'lc') {
      return `<p>Dear ${buyer},</p>`
        + `<p>Regarding commercial settlement &amp; banking structure for <strong>${mineral}</strong> export from Pakistan:</p>`
        + `<ul>`
        + `<li><strong>Payment Instrument:</strong> 100% Irrevocable Letter of Credit (L/C at Sight) or SBP-compliant Advance TT terms</li>`
        + `<li><strong>Advising Bank:</strong> Habib Bank Limited (HBL) / Meezan Bank, Karachi Corporate Branch</li>`
        + `<li><strong>Inspection:</strong> Final weight &amp; quality certificate issued by SGS at port of loading</li>`
        + `<li><strong>Shipping Terms:</strong> ${port} or CIF designated discharge port (Incoterms 2020)</li>`
        + `</ul>`
        + `<p>Please share your corporate billing entity and issuing bank details so we may issue our formal draft contract.</p>`
        + `<p>Sincerely,<br><strong>Commercial Finance Desk</strong><br>Balochistan Minerals (Pvt) Ltd &bull; Web: https://balochistanminerals.com</p>`;
    }
    if (val === 'sample') {
      return `<p>Dear ${buyer},</p>`
        + `<p>We would be pleased to dispatch a sealed laboratory test sample of our <strong>${mineral}${grade}</strong> directly from our concession stockpiles to your assay lab.</p>`
        + `<ul>`
        + `<li><strong>Sample Lot:</strong> 5 kg – 25 kg sealed representative lump/crushed sample</li>`
        + `<li><strong>Logistics:</strong> Dispatched via DHL Express / FedEx Priority with origin packing list</li>`
        + `<li><strong>Indicative Basis:</strong> $${price} USD / MT (${port}) upon qualification</li>`
        + `</ul>`
        + `<p>Please confirm your recipient name, complete lab delivery address, and phone contact to schedule immediate dispatch.</p>`
        + `<p>Sincerely,<br><strong>Export Commercial Desk</strong><br>Balochistan Minerals (Pvt) Ltd</p>`;
    }
    if (val === 'custom') {
      return `<p>Dear ${buyer},</p><p>Thank you for reaching out to Balochistan Minerals (Pvt) Ltd regarding <strong>${mineral}</strong>.</p><p>[Enter your tailored message here]</p><p>Best regards,<br><strong>Export Commercial Desk</strong><br>Balochistan Minerals (Pvt) Ltd</p>`;
    }
    return `<p>Dear ${buyer},</p>`
      + `<p>Thank you for contacting <strong>Balochistan Minerals (Pvt) Ltd</strong> regarding your inquiry for <strong>${mineral}</strong>.</p>`
      + `<p>We are pleased to provide our current commercial indicative terms for ${qty}:</p>`
      + `<ul>`
      + `<li><strong>Commodity:</strong> ${mineral}${grade}</li>`
      + `<li><strong>Delivery Terms:</strong> ${port}</li>`
      + `<li><strong>Indicative Spot Rate:</strong> $${price} USD / MT</li>`
      + `<li><strong>Packing:</strong> Bulk in vessel hold or 1.5 MT heavy-duty jumbo bags in 20ft containers</li>`
      + `<li><strong>Quality Control:</strong> SGS / Alfred H. Knight sampling &amp; assay at load port prior to loading</li>`
      + `</ul>`
      + `<p>Please advise your target delivery schedule and discharge port so we may issue our formal Corporate Proforma Invoice.</p>`
      + `<p>Sincerely,<br><strong>Export Commercial Desk</strong><br>Balochistan Minerals (Pvt) Ltd &bull; Karachi / Quetta, Pakistan</p>`;
  }

  function renderCorporateEmailPreview(subject, fromEmail, toEmail, ccEmail, bodyHtml) {
    const ccBadge = ccEmail ? `<span class="badge bg-light text-secondary border font-monospace">CC: ${escapeHtml(ccEmail)}</span>` : '';
    return `
      <div class="d-flex align-items-center flex-wrap gap-1.5 mb-2.5 pb-2 border-bottom small" style="font-size: 0.72rem;">
        <span class="badge bg-white text-dark border font-monospace">From: ${escapeHtml(fromEmail)}</span>
        <span class="badge bg-white text-dark border font-monospace">To: ${escapeHtml(toEmail)}</span>
        ${ccBadge}
      </div>
      <div style="max-width: 650px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(15,23,42,0.05);">
        <div style="padding: 18px 22px; background: #0F172A; border-bottom: 3px solid #E65C1A; display: flex; justify-content: space-between; align-items: center; gap: 12px;">
          <div>
            <div style="font-size: 14px; font-weight: 800; color: #FFFFFF; letter-spacing: 0.4px;">BALOCHISTAN MINERALS (PVT) LTD</div>
            <div style="font-size: 10px; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 2px;">Commercial Export Desk &bull; Gwadar, KPT &amp; Port Qasim</div>
          </div>
          <span style="padding: 3px 8px; background: rgba(230,92,26,0.18); border: 1px solid #E65C1A; color: #E65C1A; font-size: 10px; font-weight: 700; border-radius: 4px; white-space: nowrap;">OFFICIAL B2B OFFER</span>
        </div>
        <div style="padding: 18px 22px; font-size: 13px; line-height: 1.6; color: #1E293B;" class="thread-rich-body">
          <div style="font-weight: 700; font-size: 14px; color: #0F172A; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #F1F5F9;">${escapeHtml(subject)}</div>
          ${bodyHtml}
        </div>
        <div style="padding: 16px 22px; background: #F8FAFC; border-top: 1px solid #E2E8F0;">
          <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
              <div style="font-weight: 800; font-size: 11px; color: #0F172A; text-transform: uppercase;">Commercial Export Operations Desk</div>
              <div style="font-size: 11px; font-weight: 700; color: #E65C1A;">Balochistan Minerals (Pvt) Ltd</div>
              <div style="font-size: 10px; color: #64748B; margin-top: 4px; line-height: 1.45;">
                SECP Registered &bull; Concessions: Muslim Bagh, Khuzdar &amp; Chagai<br>
                Direct Desk: +92 334 8888104 &bull; Email: ${escapeHtml(fromEmail)}
              </div>
            </div>
            <div style="padding: 6px 10px; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 10px; color: #475569; line-height: 1.35;">
              <strong style="color: #0F172A;">QUALITY ASSURANCE:</strong><br>
              Verified by SGS / PCSIR / Alfred H Knight.
            </div>
          </div>
        </div>
      </div>
    `;
  }

  function initReplyModals() {
    const MAX_FILES = 10;
    const MAX_FILE_BYTES = 10 * 1024 * 1024;
    const MAX_TOTAL_BYTES = 20 * 1024 * 1024;

    function fmtSize(bytes) {
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
      return (bytes / 1048576).toFixed(1) + ' MB';
    }

    async function hydrateLeftContext(modal, inquiryId) {
      if (modal.dataset.contextLoaded === '1' || !inquiryId) return;
      modal.dataset.contextLoaded = '1';
      try {
        const res = await fetch('api-dossier.php?inquiry_id=' + inquiryId);
        const data = await res.json();
        if (!data || !data.success) return;

        const msgs = Array.isArray(data.messages) ? data.messages : [];
        const cntEl = modal.querySelector('[data-thread-count]');
        if (cntEl) cntEl.textContent = String(msgs.length || 1);

        const richPane = modal.querySelector('[data-pane="rich"]');
        const textPane = modal.querySelector('[data-pane="text"]');
        const threadPane = modal.querySelector('[data-pane="thread"]');

        if (msgs.length > 0) {
          const latestInbound = [...msgs].reverse().find(m => m.direction === 'inbound') || msgs[msgs.length - 1];
          if (richPane && latestInbound.body_html) {
            richPane.innerHTML = latestInbound.body_html;
          }
          if (textPane && latestInbound.body_text) {
            textPane.innerHTML = `<div style="white-space: pre-wrap; word-break: break-word;">${escapeHtml(latestInbound.body_text)}</div>`;
          }
          if (threadPane) {
            threadPane.innerHTML = msgs.map(m => {
              const isIn = m.direction === 'inbound';
              const badge = isIn
                ? `<span class="thread-dir-pill thread-dir-inbound"><i class="bi bi-arrow-down-left"></i> Inbound</span>`
                : `<span class="thread-dir-pill thread-dir-outbound"><i class="bi bi-arrow-up-right"></i> Desk Reply</span>`;
              const body = m.body_html
                ? `<div class="thread-rich-body">${m.body_html}</div>`
                : `<div class="thread-body">${escapeHtml(m.body_text || '')}</div>`;
              return `
                <div class="thread-bubble ${isIn ? 'thread-bubble-inbound' : 'thread-bubble-outbound'} p-2.5 mb-2">
                  <div class="thread-card-header mb-1.5 pb-1.5">
                    <div class="d-flex align-items-center gap-1.5 flex-wrap thread-meta">${badge} <span class="text-secondary">${escapeHtml(m.from_email || '')}</span></div>
                    <span class="text-muted font-monospace" style="font-size:0.66rem;">${escapeHtml(m.sent_at || '')}</span>
                  </div>
                  <div class="fw-bold text-dark mb-1.5" style="font-size: 0.78rem;">${escapeHtml(m.subject || '')}</div>
                  ${body}
                </div>
              `;
            }).join('');
          }
        }
      } catch (err) {
        // Keep server-rendered fallback silently on transient fetch errors
      }
    }

    function setupReplyModal(modal) {
      if (!modal || modal._replyEnhInitialized) return;
      modal._replyEnhInitialized = true;

      const inquiryId = modal.dataset.inquiryId;
      const meta = {
        buyerName: modal.dataset.buyerName || 'Buyer',
        buyerEmail: modal.dataset.buyerEmail || '',
        mineral: modal.dataset.mineral || '',
        grade: modal.dataset.grade || '',
        quantity: modal.dataset.quantity || '',
        port: modal.dataset.port || 'FOB Karachi',
        price: modal.dataset.price || '185.00',
      };

      const form = modal.querySelector('[data-reply-form]');
      const editor = modal.querySelector('[data-rich-editor]');
      const previewEl = modal.querySelector('[data-email-preview]');
      const hiddenHtml = modal.querySelector('[data-reply-html-input]');
      const hiddenText = modal.querySelector('[data-reply-text-input]');
      const subjectInput = modal.querySelector('[data-subject-input]');
      const fromSelect = modal.querySelector('[data-from-select]');
      const toInput = modal.querySelector('input[name="to_email"]');
      const smtpBadge = modal.querySelector('[data-smtp-badge]');
      const draftBadge = modal.querySelector('[data-draft-badge]');
      const tplSelect = modal.querySelector('[data-template-select]');
      const ccInput = modal.querySelector('[data-cc-input]');
      const bccInput = modal.querySelector('[data-bcc-input]');
      const draftKey = 'bm_reply_draft_' + inquiryId;

      function updateSmtpPill() {
        if (!fromSelect || !smtpBadge) return;
        const opt = fromSelect.options[fromSelect.selectedIndex];
        const ready = opt && opt.dataset.smtpReady === '1';
        smtpBadge.className = 'reply-smtp-pill ' + (ready ? 'is-ready' : 'is-sim');
        smtpBadge.innerHTML = ready
          ? '<i class="bi bi-shield-check"></i> SMTP Ready'
          : '<i class="bi bi-journal-check"></i> Dossier Log Only (Set Mail Config for Live SMTP)';
      }
      if (fromSelect) {
        fromSelect.addEventListener('change', updateSmtpPill);
        updateSmtpPill();
      }

      // Left Panel View Switcher (Rich Email / Plain Text / Thread)
      modal.querySelectorAll('[data-view-mode]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const mode = btn.dataset.viewMode;
          modal.querySelectorAll('[data-view-mode]').forEach(b => b.classList.toggle('active', b === btn));
          modal.querySelectorAll('[data-pane]').forEach(p => p.classList.toggle('d-none', p.dataset.pane !== mode));
        });
      });

      // + CC / + BCC toggles
      const ccBccRow = modal.querySelector('[data-cc-bcc-row]');
      const ccCol = modal.querySelector('[data-cc-col]');
      const bccCol = modal.querySelector('[data-bcc-col]');
      modal.querySelector('[data-toggle-cc]')?.addEventListener('click', (e) => {
        const opening = ccCol.classList.toggle('d-none') === false;
        e.currentTarget.classList.toggle('is-open', opening);
        ccBccRow.classList.toggle('d-none', ccCol.classList.contains('d-none') && bccCol.classList.contains('d-none'));
        if (opening) ccInput?.focus();
      });
      modal.querySelector('[data-toggle-bcc]')?.addEventListener('click', (e) => {
        const opening = bccCol.classList.toggle('d-none') === false;
        e.currentTarget.classList.toggle('is-open', opening);
        ccBccRow.classList.toggle('d-none', ccCol.classList.contains('d-none') && bccCol.classList.contains('d-none'));
        if (opening) bccInput?.focus();
      });

      // Sync editor -> hidden inputs & localStorage draft
      function syncFormPayload() {
        if (editor && hiddenHtml) hiddenHtml.value = editor.innerHTML.trim();
        if (editor && hiddenText) hiddenText.value = htmlFragmentToPlainText(editor);
      }

      function saveDraft() {
        try {
          const payload = {
            subject: subjectInput?.value || '',
            cc: ccInput?.value || '',
            bcc: bccInput?.value || '',
            html: editor?.innerHTML || '',
            ts: Date.now()
          };
          localStorage.setItem(draftKey, JSON.stringify(payload));
        } catch {}
      }

      // Check existing unsent draft
      try {
        const savedRaw = localStorage.getItem(draftKey);
        if (savedRaw) {
          const saved = JSON.parse(savedRaw);
          if (saved && saved.html && editor && saved.html !== editor.dataset.defaultHtml) {
            editor.innerHTML = saved.html;
            editor.dataset.dirty = '1';
            if (saved.subject && subjectInput) subjectInput.value = saved.subject;
            if (saved.cc && ccInput) {
              ccInput.value = saved.cc;
              ccCol?.classList.remove('d-none');
              ccBccRow?.classList.remove('d-none');
              modal.querySelector('[data-toggle-cc]')?.classList.add('is-open');
            }
            if (saved.bcc && bccInput) {
              bccInput.value = saved.bcc;
              bccCol?.classList.remove('d-none');
              ccBccRow?.classList.remove('d-none');
              modal.querySelector('[data-toggle-bcc]')?.classList.add('is-open');
            }
            draftBadge?.classList.remove('d-none');
            syncFormPayload();
          }
        }
      } catch {}

      modal.querySelector('[data-reset-draft]')?.addEventListener('click', () => {
        try { localStorage.removeItem(draftKey); } catch {}
        if (editor) {
          editor.innerHTML = editor.dataset.defaultHtml || formatReplyTemplateHtml('quote', meta);
          delete editor.dataset.dirty;
        }
        if (tplSelect) tplSelect.value = 'quote';
        draftBadge?.classList.add('d-none');
        syncFormPayload();
      });

      if (editor) {
        editor.addEventListener('input', () => {
          editor.dataset.dirty = '1';
          syncFormPayload();
          saveDraft();
        });
      }
      subjectInput?.addEventListener('input', saveDraft);
      ccInput?.addEventListener('input', saveDraft);
      bccInput?.addEventListener('input', saveDraft);

      // Rich Text Toolbar commands
      modal.querySelectorAll('[data-cmd]').forEach((btn) => {
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', () => {
          editor?.focus();
          document.execCommand(btn.dataset.cmd, false, null);
          editor.dataset.dirty = '1';
          syncFormPayload();
          saveDraft();
        });
      });

      modal.querySelector('[data-insert-spec-table]')?.addEventListener('mousedown', (e) => e.preventDefault());
      modal.querySelector('[data-insert-spec-table]')?.addEventListener('click', () => {
        if (!editor) return;
        editor.focus();
        const tblHtml = `<table style="width:100%; border-collapse:collapse; margin:10px 0;">`
          + `<thead><tr style="background:#F8FAFC;"><th style="border:1px solid #CBD5E1; padding:6px 8px; text-align:left;">Specification</th><th style="border:1px solid #CBD5E1; padding:6px 8px; text-align:left;">Offer Details</th></tr></thead>`
          + `<tbody>`
          + `<tr><td style="border:1px solid #CBD5E1; padding:5px 8px;"><strong>Commodity</strong></td><td style="border:1px solid #CBD5E1; padding:5px 8px;">${escapeHtml(meta.mineral)}${meta.grade ? ' (' + escapeHtml(meta.grade) + ')' : ''}</td></tr>`
          + `<tr><td style="border:1px solid #CBD5E1; padding:5px 8px;"><strong>Quantity / Lot</strong></td><td style="border:1px solid #CBD5E1; padding:5px 8px;">${escapeHtml(meta.quantity)}</td></tr>`
          + `<tr><td style="border:1px solid #CBD5E1; padding:5px 8px;"><strong>Delivery Terms</strong></td><td style="border:1px solid #CBD5E1; padding:5px 8px;">${escapeHtml(meta.port)} (Incoterms 2020)</td></tr>`
          + `<tr><td style="border:1px solid #CBD5E1; padding:5px 8px;"><strong>Indicative Rate</strong></td><td style="border:1px solid #CBD5E1; padding:5px 8px;"><strong>$${escapeHtml(meta.price)} USD / MT</strong></td></tr>`
          + `<tr><td style="border:1px solid #CBD5E1; padding:5px 8px;"><strong>Inspection</strong></td><td style="border:1px solid #CBD5E1; padding:5px 8px;">SGS / Alfred H. Knight at Load Port</td></tr>`
          + `</tbody></table><p><br></p>`;
        document.execCommand('insertHTML', false, tblHtml);
        editor.dataset.dirty = '1';
        syncFormPayload();
        saveDraft();
      });

      modal.querySelector('[data-insert-quote]')?.addEventListener('mousedown', (e) => e.preventDefault());
      modal.querySelector('[data-insert-quote]')?.addEventListener('click', () => {
        if (!editor) return;
        const textPane = modal.querySelector('[data-pane="text"]');
        let snippet = (textPane?.innerText || '').trim().slice(0, 600);
        if (snippet.length >= 600) snippet += '…';
        if (!snippet) return;
        editor.focus();
        const qHtml = `<blockquote><strong>Original Inquiry from ${escapeHtml(meta.buyerName)}:</strong><br>${escapeHtml(snippet).replace(/\n/g, '<br>')}</blockquote><p><br></p>`;
        document.execCommand('insertHTML', false, qHtml);
        editor.dataset.dirty = '1';
        syncFormPayload();
        saveDraft();
      });

      // Write vs Preview Final Corporate Email tabs
      modal.querySelectorAll('[data-compose-mode]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const isPreview = btn.dataset.composeMode === 'preview';
          modal.querySelectorAll('[data-compose-mode]').forEach(b => b.classList.toggle('active', b === btn));
          const fmtGroup = modal.querySelector('[data-format-buttons]');
          if (fmtGroup) fmtGroup.style.visibility = isPreview ? 'hidden' : 'visible';
          if (isPreview) {
            syncFormPayload();
            previewEl.innerHTML = renderCorporateEmailPreview(
              subjectInput?.value || 'Commercial Quotation',
              fromSelect?.value || 'sales@balochistanminerals.com',
              toInput?.value || meta.buyerEmail,
              ccInput?.value || '',
              editor?.innerHTML || ''
            );
            editor.classList.add('d-none');
            previewEl.classList.remove('d-none');
          } else {
            previewEl.classList.add('d-none');
            editor.classList.remove('d-none');
          }
        });
      });

      // B2B Template Switcher with dirty guard
      if (tplSelect) {
        tplSelect.dataset.prevVal = tplSelect.value;
        tplSelect.addEventListener('change', () => {
          if (editor?.dataset.dirty === '1') {
            if (!window.confirm('Replace your current edited message with the selected template?')) {
              tplSelect.value = tplSelect.dataset.prevVal || 'quote';
              return;
            }
          }
          const html = formatReplyTemplateHtml(tplSelect.value, meta);
          if (editor) {
            editor.innerHTML = html;
            delete editor.dataset.dirty;
          }
          tplSelect.dataset.prevVal = tplSelect.value;
          syncFormPayload();
          saveDraft();
        });
      }

      // Multi-File Drag-and-Drop synced to native <input type="file">
      const dropzone = modal.querySelector('[data-reply-dropzone]');
      const fileInput = modal.querySelector('[data-reply-file-input]');
      const attList = modal.querySelector('[data-reply-att-list]');
      const attMeta = modal.querySelector('[data-att-meta]');
      let filesArr = [];

      function syncAttachmentInput() {
        if (!fileInput || typeof DataTransfer === 'undefined') return;
        const dt = new DataTransfer();
        let totalBytes = 0;
        filesArr.forEach((f) => {
          dt.items.add(f);
          totalBytes += f.size;
        });
        fileInput.files = dt.files;

        if (attMeta) {
          attMeta.textContent = filesArr.length > 0
            ? `${filesArr.length} file${filesArr.length > 1 ? 's' : ''} (${fmtSize(totalBytes)} / 20 MB)`
            : 'Drop files here • Max 10 files (20 MB)';
        }
        if (attList) {
          attList.innerHTML = filesArr.map((f, idx) => `
            <span class="reply-att-chip">
              <i class="bi bi-file-earmark-check text-primary"></i>
              <span class="text-truncate" style="max-width: 170px;">${escapeHtml(f.name)}</span>
              <span class="text-muted">(${fmtSize(f.size)})</span>
              <button type="button" class="reply-att-rm" data-rm-file="${idx}" aria-label="Remove attachment">&times;</button>
            </span>
          `).join('');
          attList.querySelectorAll('[data-rm-file]').forEach((b) => {
            b.addEventListener('click', () => {
              filesArr.splice(Number(b.dataset.rmFile), 1);
              syncAttachmentInput();
            });
          });
        }
      }

      function addFiles(fileList) {
        let total = filesArr.reduce((acc, f) => acc + f.size, 0);
        const warnings = [];
        Array.from(fileList || []).forEach((file) => {
          if (filesArr.length >= MAX_FILES) {
            warnings.push(`Maximum ${MAX_FILES} attachments allowed.`);
            return;
          }
          if (file.size > MAX_FILE_BYTES) {
            warnings.push(`"${file.name}" exceeds the 10 MB limit.`);
            return;
          }
          if (total + file.size > MAX_TOTAL_BYTES) {
            warnings.push(`Adding "${file.name}" exceeds the 20 MB total limit.`);
            return;
          }
          if (!filesArr.some(f => f.name === file.name && f.size === file.size)) {
            filesArr.push(file);
            total += file.size;
          }
        });
        if (warnings.length && window.BMNotify) {
          window.BMNotify.toast('warning', 'Attachment Limit', warnings[0]);
        }
        syncAttachmentInput();
      }

      modal.querySelector('[data-pick-files]')?.addEventListener('click', () => fileInput?.click());
      fileInput?.addEventListener('change', (ev) => {
        if (ev.target.files && ev.target.files.length) addFiles(ev.target.files);
      });
      if (dropzone) {
        ['dragenter', 'dragover'].forEach(evt => dropzone.addEventListener(evt, (e) => {
          e.preventDefault();
          dropzone.classList.add('is-dragover');
        }));
        ['dragleave', 'drop'].forEach(evt => dropzone.addEventListener(evt, (e) => {
          e.preventDefault();
          dropzone.classList.remove('is-dragover');
        }));
        dropzone.addEventListener('drop', (e) => {
          if (e.dataTransfer?.files?.length) addFiles(e.dataTransfer.files);
        });
      }

      // Pre-submit sync & clear draft
      form?.addEventListener('submit', () => {
        syncFormPayload();
        syncAttachmentInput();
        try { localStorage.removeItem(draftKey); } catch {}
      });

      syncFormPayload();
    }

    // Hydrate any modal opened during initial page load race
    document.querySelectorAll('[data-reply-modal]').forEach((modal) => {
      setupReplyModal(modal);
      if (modal.classList.contains('show')) {
        hydrateLeftContext(modal, modal.dataset.inquiryId);
      }
    });

    const handleModalOpen = (e) => {
      const modal = e.target;
      if (modal && modal.matches('[data-reply-modal]')) {
        setupReplyModal(modal);
        hydrateLeftContext(modal, modal.dataset.inquiryId);
      }
    };
    document.addEventListener('show.bs.modal', handleModalOpen);
    document.addEventListener('shown.bs.modal', handleModalOpen);

    window.BMReply = {
      applyTemplate: (select, inquiryId) => {
        const modal = document.getElementById('replyModal' + inquiryId);
        if (!modal) return;
        setupReplyModal(modal);
        const tplSelect = modal.querySelector('[data-template-select]');
        if (tplSelect) {
          tplSelect.value = select.value;
          tplSelect.dispatchEvent(new Event('change'));
        }
      }
    };
  }

  // Initialize once DOM is ready
  initEmailImageFallback();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      initSidebar();
      initLiveClock();
      initResizableModals();
      initReplyModals();
      initTooltips();
      initNotificationCenter();
    });
  } else {
    initSidebar();
    initLiveClock();
    initResizableModals();
    initReplyModals();
    initTooltips();
    initNotificationCenter();
  }
})();
