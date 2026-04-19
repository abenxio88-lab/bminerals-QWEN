import { isMobile } from './utils.js';

export function initDropdownMenus() {
  // Guard: Skip if already initialized
  if (window.__dropdownMenusInitialized) return;
  window.__dropdownMenusInitialized = true;

  const dropdownGroups = document.querySelectorAll('.navbar__dropdown-group');
  const dropdownTriggers = document.querySelectorAll('.navbar__dropdown-trigger');

  // ============================================
  // DESKTOP: JavaScript-driven hover handling
  // This ensures dropdowns work reliably on ALL pages,
  // not just relying on CSS :hover which can be inconsistent.
  // ============================================
  dropdownGroups.forEach(group => {
    const menu = group.querySelector('.navbar__dropdown-menu');
    if (!menu) return;

    let hideTimeout = null;
    let showTimeout = null;

    function showDropdown() {
      if (isMobile()) return; // Skip on mobile
      clearTimeout(hideTimeout);
      showTimeout = setTimeout(() => {
        // Close all other dropdowns first
        dropdownGroups.forEach(otherGroup => {
          if (otherGroup !== group) {
            const otherMenu = otherGroup.querySelector('.navbar__dropdown-menu');
            if (otherMenu) {
              otherMenu.classList.remove('dropdown-active');
              const otherTrigger = otherGroup.querySelector('.navbar__dropdown-trigger');
              if (otherTrigger) otherTrigger.setAttribute('aria-expanded', 'false');
            }
          }
        });
        menu.classList.add('dropdown-active');
        const trigger = group.querySelector('.navbar__dropdown-trigger');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
      }, 150);
    }

    function hideDropdown() {
      if (isMobile()) return;
      clearTimeout(showTimeout); // Cancel showing if mouse leaves quickly
      hideTimeout = setTimeout(() => {
        menu.classList.remove('dropdown-active');
        const trigger = group.querySelector('.navbar__dropdown-trigger');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
      }, 350); // Generous delay enables 'Hover Safe Tunnel' across gaps
    }

    // Show on hover of the group (trigger area)
    group.addEventListener('mouseenter', showDropdown);
    group.addEventListener('mouseleave', hideDropdown);

    // Keep open while hovering the menu itself
    menu.addEventListener('mouseenter', () => {
      if (isMobile()) return;
      clearTimeout(hideTimeout);
    });
    menu.addEventListener('mouseleave', hideDropdown);

    // Keyboard: close on Escape when menu is focused
    menu.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') hideDropdown();
    });
  });

  // ============================================
  // MOBILE: Click-based toggle (unchanged logic)
  // ============================================
  dropdownTriggers.forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      const mobile = isMobile();

      if (mobile) {
        // Allow link navigation if user clicked the text, not the arrow SVG
        if (!e.target.closest('svg') && !e.target.classList.contains('navbar__dropdown-arrow')) {
          return;
        }

        e.preventDefault();
        e.stopPropagation();

        const nextMenu = trigger.nextElementSibling;
        if (nextMenu && nextMenu.classList.contains('navbar__dropdown-menu')) {
          const isOpen = trigger.classList.contains('open');

          // Close all other dropdowns
          dropdownTriggers.forEach(t => {
            if (t !== trigger) {
              t.classList.remove('open');
              t.setAttribute('aria-expanded', 'false');
              const menu = t.nextElementSibling;
              if (menu && menu.classList.contains('navbar__dropdown-menu')) {
                menu.classList.remove('open');
              }
            }
          });

          // Toggle current dropdown
          if (isOpen) {
            trigger.classList.remove('open');
            nextMenu.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
          } else {
            trigger.classList.add('open');
            nextMenu.classList.add('open');
            trigger.setAttribute('aria-expanded', 'true');
            // focus first link for keyboard users
            const firstItem = nextMenu.querySelector('.navbar__dropdown-item');
            if (firstItem) firstItem.focus();
          }
        }
      } else {
        // Desktop: toggle aria-expanded and menu visibility for accessibility
        const isExpanded = trigger.getAttribute('aria-expanded') === 'true';
        trigger.setAttribute('aria-expanded', String(!isExpanded));
        const menu = trigger.nextElementSibling;
        if (menu && menu.classList.contains('navbar__dropdown-menu')) {
          if (isExpanded) {
            menu.classList.remove('dropdown-active');
          } else {
            menu.classList.add('dropdown-active');
          }
        }
      }
    });

    // Keyboard support for trigger (Enter / Space / Escape)
    trigger.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        trigger.click();
      }
      if (e.key === 'Escape') {
        trigger.setAttribute('aria-expanded', 'false');
        const menu = trigger.nextElementSibling;
        if (menu) menu.classList.remove('dropdown-active', 'open');
      }
    });
  });

  // Close dropdowns when clicking outside (desktop only)
  document.addEventListener('click', (e) => {
    const isMobile = window.innerWidth <= 1024;
    
    if (!isMobile) {
      dropdownGroups.forEach(group => {
        if (!group.contains(e.target)) {
          const menu = group.querySelector('.navbar__dropdown-menu');
          if (menu) menu.classList.remove('dropdown-active');
        }
      });
      dropdownTriggers.forEach(trigger => {
        if (!trigger.contains(e.target)) {
          trigger.setAttribute('aria-expanded', 'false');
        }
      });
    }
  });

  // Close dropdowns on mobile when item is clicked
  const dropdownItems = document.querySelectorAll('.navbar__dropdown-item');
  dropdownItems.forEach(item => {
    item.addEventListener('click', () => {
      const isMobile = window.innerWidth <= 1024;
      
      if (isMobile) {
        dropdownTriggers.forEach(trigger => {
          trigger.classList.remove('open');
          trigger.setAttribute('aria-expanded', 'false');
          const menu = trigger.nextElementSibling;
          if (menu && menu.classList.contains('navbar__dropdown-menu')) {
            menu.classList.remove('open');
          }
        });
      }
    });
  });
}
