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

  // Initialize once DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      initSidebar();
      initLiveClock();
    });
  } else {
    initSidebar();
    initLiveClock();
  }
})();
