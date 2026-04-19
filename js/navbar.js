import { highlightActiveLink, isMobile } from './utils.js';

export function initNavbar() {
  // Guard: Skip if already initialized
  if (window.__navbarInitialized) return;
  window.__navbarInitialized = true;

  const navbar = document.querySelector('.navbar');
  const hamburger = document.querySelector('.navbar__hamburger');
  const mobileMenu = document.querySelector('.navbar__nav');

  if (!navbar) return;

  // Highlight active link
  highlightActiveLink();

  // Scroll effect (lightweight) - guard against duplicate listeners
  if (!window.__navbarScrollHandler) {
    let lastScroll = 0;
    window.__navbarScrollHandler = () => {
      const currentScroll = window.scrollY;
      if (currentScroll > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
      lastScroll = currentScroll;
    };
    window.addEventListener('scroll', window.__navbarScrollHandler, { passive: true });
  }

  // Mobile menu toggle (use body class instead of direct style manipulation)
  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => {
      hamburger.classList.toggle('active');
      mobileMenu.classList.toggle('open');
      document.documentElement.classList.toggle('mobile-menu-open', mobileMenu.classList.contains('open'));
    });

    // Close mobile menu on REAL link click (ignore dropdown triggers)
    const menuLinks = mobileMenu.querySelectorAll('.navbar__link:not(.navbar__dropdown-trigger), .navbar__dropdown-item');
    menuLinks.forEach(link => {
      link.addEventListener('click', () => {
        if (isMobile()) {
          hamburger.classList.remove('active');
          mobileMenu.classList.remove('open');
          document.documentElement.classList.remove('mobile-menu-open');
        }
      });
    });
  }
}
