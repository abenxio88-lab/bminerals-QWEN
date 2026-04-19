export function highlightActiveLink() {
  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  const links = document.querySelectorAll('.navbar__link, .navbar__dropdown-item');

  links.forEach(link => {
    const href = link.getAttribute('href');
    if (href === currentPath || href === `./${currentPath}`) {
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
  return window.innerWidth <= 1024;
}
