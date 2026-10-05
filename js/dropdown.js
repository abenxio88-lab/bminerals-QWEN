import { isMobile } from './utils.js';

export function initDropdownMenus() {
  // Guard: Skip if already initialized
  if (window.__dropdownMenusInitialized) return;
  window.__dropdownMenusInitialized = true;

  const dropdownGroups = document.querySelectorAll('.navbar__dropdown-group');
  const dropdownTriggers = document.querySelectorAll('.navbar__dropdown-trigger');

  const getControlledMenu = (trigger) => {
    const mobileHead = trigger.closest('.navbar__mobile-dropdown-head');
    const menu = mobileHead ? mobileHead.nextElementSibling : trigger.nextElementSibling;
    return menu && menu.classList.contains('navbar__dropdown-menu') ? menu : null;
  };

  const resetMenuScroll = (menuElement) => {
    if (menuElement && menuElement.scrollTop !== 0) {
      menuElement.scrollTop = 0;
    }
  };

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
              resetMenuScroll(otherMenu);
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
        resetMenuScroll(menu);
        const trigger = group.querySelector('.navbar__dropdown-trigger');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
      }, 350); // Generous delay enables 'Hover Safe Tunnel' across gaps
    }

    // Show on hover of the group (trigger area)
    group.addEventListener('mouseenter', () => {
      if (isMobile()) return;
      if (!menu.classList.contains('dropdown-active')) {
        resetMenuScroll(menu);
      }
      showDropdown();
    });
    group.addEventListener('mouseleave', hideDropdown);

    // Keep open while hovering the menu itself
    menu.addEventListener('mouseenter', () => {
      if (isMobile()) return;
      clearTimeout(hideTimeout);
    });
    menu.addEventListener('mouseleave', hideDropdown);

    // Keyboard: close on Escape when menu is focused
    menu.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        hideDropdown();
        resetMenuScroll(menu);
      }
    });
  });

  // ============================================
  // MOBILE: Click-based toggle (unchanged logic)
  // ============================================
  dropdownTriggers.forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      const mobile = isMobile();

      if (mobile) {
        const isMobileTrigger = trigger.classList.contains('navbar__dropdown-trigger--mobile') || trigger.tagName === 'BUTTON';
        const nextMenu = getControlledMenu(trigger);
        const clickedArrow = Boolean(e.target.closest('.navbar__dropdown-arrow') || e.target.closest('svg'));

        // Keep desktop nav links navigable on mobile widths.
        if (!isMobileTrigger && !clickedArrow) {
          return;
        }

        e.preventDefault();
        e.stopPropagation();

        if (nextMenu && nextMenu.classList.contains('navbar__dropdown-menu')) {
          const isOpen = trigger.classList.contains('open');

          // Close all other dropdowns
          dropdownTriggers.forEach(t => {
            if (t !== trigger) {
              t.classList.remove('open');
              t.setAttribute('aria-expanded', 'false');
              const menu = getControlledMenu(t);
              if (menu) {
                menu.classList.remove('open');
                resetMenuScroll(menu);
              }
            }
          });

          // Toggle current dropdown
          if (isOpen) {
            trigger.classList.remove('open');
            nextMenu.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
            resetMenuScroll(nextMenu);
          } else {
            resetMenuScroll(nextMenu);
            trigger.classList.add('open');
            nextMenu.classList.add('open');
            trigger.setAttribute('aria-expanded', 'true');
          }
        }
      } else {
        const clickedArrow = Boolean(e.target.closest('.navbar__dropdown-arrow') || e.target.closest('svg'));
        if (!clickedArrow) return;

        e.preventDefault();
        e.stopPropagation();

        // Desktop: toggle aria-expanded and menu visibility for accessibility
        const isExpanded = trigger.getAttribute('aria-expanded') === 'true';
        trigger.setAttribute('aria-expanded', String(!isExpanded));
        const menu = getControlledMenu(trigger);
        if (menu) {
          if (isExpanded) {
            menu.classList.remove('dropdown-active');
            resetMenuScroll(menu);
          } else {
            resetMenuScroll(menu);
            menu.classList.add('dropdown-active');
          }
        }
      }
    });

    // Keyboard support for trigger (Enter / Space / Escape)
    trigger.addEventListener('keydown', (e) => {
      if (!isMobile() && trigger.tagName === 'A' && e.key === 'Enter') {
        return;
      }
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        trigger.click();
      }
      if (e.key === 'Escape') {
        trigger.setAttribute('aria-expanded', 'false');
        const menu = getControlledMenu(trigger);
        if (menu) {
          menu.classList.remove('dropdown-active', 'open');
          resetMenuScroll(menu);
        }
      }
    });
  });

  // Close dropdowns when clicking outside (desktop only)
  document.addEventListener('click', (e) => {
    if (!isMobile()) {
      dropdownGroups.forEach(group => {
        if (!group.contains(e.target)) {
          const menu = group.querySelector('.navbar__dropdown-menu');
          if (menu) {
            menu.classList.remove('dropdown-active');
            resetMenuScroll(menu);
          }
        }
      });
      dropdownTriggers.forEach(trigger => {
        if (!trigger.contains(e.target)) {
          trigger.setAttribute('aria-expanded', 'false');
        }
      });
    }
  });

  // Close dropdowns when item is clicked
  const dropdownItems = document.querySelectorAll('.navbar__dropdown-item');
  dropdownItems.forEach(item => {
    item.addEventListener('click', () => {
      const parentMenu = item.closest('.navbar__dropdown-menu');
      if (parentMenu) {
        parentMenu.classList.remove('dropdown-active');
        resetMenuScroll(parentMenu);
      }
      if (isMobile()) {
        dropdownTriggers.forEach(trigger => {
          trigger.classList.remove('open');
          trigger.setAttribute('aria-expanded', 'false');
          const menu = getControlledMenu(trigger);
          if (menu) {
            menu.classList.remove('open');
            resetMenuScroll(menu);
          }
        });
      }
    });
  });
}
