export function highlightActiveLink() {
  const normalizePath = value => {
    try {
      const url = new URL(value, window.location.origin);
      if (url.origin !== window.location.origin) return null;

      let path = url.pathname.replace(/\/+$/, '') || '/';
      path = path.replace(/\.html$/i, '') || '/';
      return path === '/index' ? '/' : path;
    } catch {
      return null;
    }
  };

  const currentPath = normalizePath(window.location.href);
  const links = document.querySelectorAll('.navbar__link, .navbar__dropdown-item');

  links.forEach(link => {
    const href = link.getAttribute('href');
    if (href && normalizePath(href) === currentPath) {
      link.classList.add('active');
      const dropdownGroup = link.closest('.navbar__dropdown-group');
      if (dropdownGroup) {
        const trigger = dropdownGroup.querySelector('.navbar__dropdown-trigger');
        if (trigger) trigger.classList.add('active');
      }
    }
  });
}

export function isMobile() {
  return window.innerWidth <= 1180;
}
