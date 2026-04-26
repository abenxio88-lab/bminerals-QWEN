import { highlightActiveLink, isMobile } from './utils.js';

export function initNavbar() {
  // Guard: Skip if already initialized
  if (window.__navbarInitialized) return;
  window.__navbarInitialized = true;

  const navbar = document.querySelector('.navbar');
  const hamburger = document.querySelector('.navbar__hamburger');
  const mobileMenu = document.querySelector('.navbar__mobile-menu');

  if (!navbar) return;

  // Highlight active link
  highlightActiveLink();

  // Scroll effect (lightweight) - guard against duplicate listeners
  if (!window.__navbarScrollHandler) {
    window.__navbarScrollHandler = () => {
      const currentScroll = window.scrollY;
      if (currentScroll > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    };
    window.addEventListener('scroll', window.__navbarScrollHandler, { passive: true });
  }

  // Mobile menu toggle (use body class instead of direct style manipulation)
  if (hamburger && mobileMenu) {
    const closeMobileMenu = () => {
      hamburger.classList.remove('active');
      hamburger.setAttribute('aria-expanded', 'false');
      mobileMenu.classList.remove('open');

      mobileMenu.querySelectorAll('.navbar__dropdown-trigger.open').forEach(trigger => {
        trigger.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
      });

      mobileMenu.querySelectorAll('.navbar__dropdown-menu.open').forEach(menu => {
        menu.classList.remove('open');
      });
    };

    hamburger.addEventListener('click', () => {
      const isOpen = mobileMenu.classList.toggle('open');
      hamburger.classList.toggle('active', isOpen);
      hamburger.setAttribute('aria-expanded', String(isOpen));
    });

    // Close mobile menu on REAL link click (ignore dropdown triggers)
    const menuLinks = mobileMenu.querySelectorAll('.navbar__link:not(.navbar__dropdown-trigger), .navbar__dropdown-item');
    menuLinks.forEach(link => {
      link.addEventListener('click', () => {
        if (isMobile()) {
          closeMobileMenu();
        }
      });
    });

    window.addEventListener('resize', () => {
      if (!isMobile() && mobileMenu.classList.contains('open')) {
        closeMobileMenu();
      }
    }, { passive: true });
  }
}
