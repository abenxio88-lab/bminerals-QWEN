export function initNavbar() {
  const navbar = document.querySelector('.navbar');
  const hamburger = document.querySelector('.navbar__hamburger');
  const mobileMenu = document.querySelector('.navbar__nav');

  // Highlight active link
  highlightActiveLink();

  // Scroll effect
  let lastScroll = 0;
  window.addEventListener('scroll', () => {
    const currentScroll = window.scrollY;
    
    if (currentScroll > 50) {
      navbar?.classList.add('scrolled');
    } else {
      navbar?.classList.remove('scrolled');
    }
    
    lastScroll = currentScroll;
  });

  // Mobile menu toggle
  hamburger?.addEventListener('click', () => {
    hamburger.classList.toggle('active');
    mobileMenu?.classList.toggle('open');
    document.body.style.overflow = mobileMenu?.classList.contains('open') ? 'hidden' : '';
  });

  // Close mobile menu on REAL link click (ignore dropdown triggers)
  const menuLinks = mobileMenu?.querySelectorAll('.navbar__link:not(.navbar__dropdown-trigger), .navbar__dropdown-item');
  menuLinks?.forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 1024) {
        hamburger.classList.remove('active');
        if (mobileMenu) mobileMenu.classList.remove('open');
        document.body.style.overflow = '';
      }
    });
  });
}

/**
 * Automatically adds 'active' class to the navigation link matching current URL
 */
function highlightActiveLink() {
  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  const links = document.querySelectorAll('.navbar__link, .navbar__dropdown-item');

  links.forEach(link => {
    const href = link.getAttribute('href');
    if (href === currentPath || href === `./${currentPath}`) {
      link.classList.add('active');
      
      // If it's a dropdown item, also highlight the parent trigger
      const dropdownGroup = link.closest('.navbar__dropdown-group');
      if (dropdownGroup) {
        const trigger = dropdownGroup.querySelector('.navbar__dropdown-trigger');
        if (trigger) trigger.classList.add('active');
      }
    }
  });
}
