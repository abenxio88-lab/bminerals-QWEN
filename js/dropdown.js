export function initDropdownMenus() {
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
      if (window.innerWidth <= 1024) return; // Skip on mobile
      
      clearTimeout(hideTimeout);
      
      // Delay showing to avoid flash when accidentally clipping other buttons
      showTimeout = setTimeout(() => {
        // Close all other dropdowns first
        dropdownGroups.forEach(otherGroup => {
          if (otherGroup !== group) {
            const otherMenu = otherGroup.querySelector('.navbar__dropdown-menu');
            if (otherMenu) {
              otherMenu.classList.remove('dropdown-active');
            }
          }
        });
        menu.classList.add('dropdown-active');
      }, 150);
    }

    function hideDropdown() {
      if (window.innerWidth <= 1024) return;
      
      clearTimeout(showTimeout); // Cancel showing if mouse leaves quickly
      
      hideTimeout = setTimeout(() => {
        menu.classList.remove('dropdown-active');
      }, 350); // Generous delay enables 'Hover Safe Tunnel' across gaps
    }

    // Show on hover of the group (trigger area)
    group.addEventListener('mouseenter', showDropdown);
    group.addEventListener('mouseleave', hideDropdown);

    // Keep open while hovering the menu itself
    menu.addEventListener('mouseenter', () => {
      if (window.innerWidth <= 1024) return;
      clearTimeout(hideTimeout);
    });
    menu.addEventListener('mouseleave', hideDropdown);
  });

  // ============================================
  // MOBILE: Click-based toggle (unchanged logic)
  // ============================================
  dropdownTriggers.forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      const isMobile = window.innerWidth <= 1024;

      if (isMobile) {
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
              if (menu && menu.classList.contains('navbar__dropdown-menu--mobile')) {
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
          }
        }
      } else {
        // Desktop: Just update aria-expanded for accessibility
        const isExpanded = trigger.getAttribute('aria-expanded') === 'true';
        trigger.setAttribute('aria-expanded', !isExpanded);
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
          if (menu && menu.classList.contains('navbar__dropdown-menu--mobile')) {
            menu.classList.remove('open');
          }
        });
      }
    });
  });
}
