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

    if (toggleBtn) {
      toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();
        toggleSidebar();
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        closeMobileSidebar();
      });
    }

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
          if (modalBody) {
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

  // Initialize once DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      initSidebar();
      initLiveClock();
      initResizableModals();
    });
  } else {
    initSidebar();
    initLiveClock();
    initResizableModals();
  }
})();
