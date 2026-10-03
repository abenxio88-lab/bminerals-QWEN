export function highlightActiveLink() {
  const parseRoute = value => {
    try {
      const url = new URL(value, window.location.origin);
      if (url.origin !== window.location.origin) return null;

      let path = url.pathname.replace(/\/+$/, '') || '/';
      path = path.replace(/\.html$/i, '') || '/';
      path = path === '/index' ? '/' : path;
      return {
        path,
        hash: url.hash ? decodeURIComponent(url.hash) : ''
      };
    } catch {
      return null;
    }
  };

  const current = parseRoute(window.location.href);
  if (!current) return;

  const links = document.querySelectorAll('.navbar__link, .navbar__dropdown-item');

  // Reset active classes
  links.forEach(link => link.classList.remove('active'));
  document.querySelectorAll('.navbar__dropdown-trigger.active, .navbar__mobile-parent-link.active')
    .forEach(el => el.classList.remove('active'));

  links.forEach(link => {
    const href = link.getAttribute('href');
    if (!href) return;

    const target = parseRoute(href);
    if (!target || target.path !== current.path) return;

    const isDropdownItem = link.classList.contains('navbar__dropdown-item');

    let isMatch = false;
    if (target.hash) {
      isMatch = target.hash === current.hash;
    } else if (!isDropdownItem) {
      isMatch = true;
    } else {
      isMatch = !current.hash;
    }

    if (isMatch) {
      link.classList.add('active');

      // Desktop: mark parent dropdown trigger active
      const dropdownGroup = link.closest('.navbar__dropdown-group');
      if (dropdownGroup) {
        const trigger = dropdownGroup.querySelector('.navbar__dropdown-trigger');
        if (trigger) trigger.classList.add('active');
      }

      // Mobile: mark parent dropdown head link active
      const mobileMenu = link.closest('.navbar__dropdown-menu--mobile');
      if (mobileMenu) {
        const head = mobileMenu.previousElementSibling;
        if (head && head.classList.contains('navbar__mobile-dropdown-head')) {
          const mobileParent = head.querySelector('.navbar__mobile-parent-link');
          if (mobileParent) mobileParent.classList.add('active');
        }
      }
    }
  });
}

export function isMobile() {
  return window.innerWidth <= 1180;
}
