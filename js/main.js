import { initNavbar } from './navbar.js';
import { initScrollReveal } from './scroll-reveal.js';
import { initDropdownMenus } from './dropdown.js';
import { initHeroSlider } from './hero-slider.js';
import { initBorderBeam } from './border-beam.js';
import { initTactileFeedback } from './tactile-feedback.js';
import { initEarthTechCore } from './earth-tech-core.js';

function isLocalDebug() {
  return ['localhost', '127.0.0.1'].includes(window.location.hostname) || window.location.protocol === 'file:';
}

function reportInitError(featureName, error) {
  window.__bmHillInitErrors = window.__bmHillInitErrors || [];
  window.__bmHillInitErrors.push({ featureName, error });

  if (isLocalDebug()) {
    console.error(`${featureName} init error:`, error);
  }
}

function runInit(featureName, initializer) {
  try {
    initializer();
  } catch (error) {
    reportInitError(featureName, error);
  }
}

// ============================================
// BM Hill Preloader Logic
// ============================================
// Ensure loader is removed even if errors occur
function removeLoader() {
  const loader = document.getElementById('bm-hill-loader');
  if (loader) {
    document.body.classList.remove('is-loading');
    document.body.classList.add('loaded');
  }
}

// We use DOMContentLoaded for a much faster initial reveal on mobile
document.addEventListener('DOMContentLoaded', () => {
  // Snappier 800ms baseline for a professional institucional feel
  setTimeout(() => {
    removeLoader();
  }, 800);
});

// Failsafe: Force open in case of heavy asset hanging
setTimeout(() => {
  if (document.body.classList.contains('is-loading')) {
    removeLoader();
  }
}, 3000);

// ============================================
// Initialize All Features
// ============================================
document.addEventListener('DOMContentLoaded', async () => {
  // 1. Initialize feature logic (now that elements exist in DOM)
  // One failing module should never block the rest of the page boot sequence.
  runInit('initNavbar', initNavbar);
  runInit('initScrollReveal', initScrollReveal);
  runInit('initDropdownMenus', initDropdownMenus);
  runInit('initHeroSlider', initHeroSlider);
  runInit('initCounterAnimation', initCounterAnimation);
  runInit('initSmoothScrolling', initSmoothScrolling);
  runInit('initParallaxEffect', initParallaxEffect);
  runInit('initMineMarkers', initMineMarkers);
  runInit('initNewsletterForm', initNewsletterForm);
  runInit('initBorderBeam', initBorderBeam);
  runInit('initTactileFeedback', initTactileFeedback);
  runInit('initEarthTechCore', initEarthTechCore);
  runInit('initStepper', initStepper);
  // Ensure loader is removed after all inits complete
  setTimeout(() => removeLoader(), 100);
});

// ============================================
// Interactive Stepper for Operations
// ============================================
function initStepper() {
  // Guard: Skip if already initialized
  if (window.__stepperInitialized) return;
  window.__stepperInitialized = true;

  const indicators = document.querySelectorAll('.step-indicator');
  const cards = document.querySelectorAll('.step-card');

  if (!indicators.length || !cards.length) return;

  indicators.forEach(indicator => {
    indicator.addEventListener('click', () => {
      const step = indicator.getAttribute('data-step');

      // Update Indicators
      indicators.forEach(ind => ind.classList.remove('step-indicator--active'));
      indicator.classList.add('step-indicator--active');

      // Update Cards
      cards.forEach(card => {
        card.classList.remove('step-card--active');
        if (card.getAttribute('data-step') === step) {
          card.classList.add('step-card--active');

          // Re-trigger reveal animation if GSAP is available
          if (window.gsap) {
            gsap.fromTo(card,
              { opacity: 0, y: 20 },
              { opacity: 1, y: 0, duration: 0.5, ease: "power2.out" }
            );
          }
        }
      });
    });
  });
}

// ============================================
// Counter Animation for Stats
// ============================================
function initCounterAnimation() {
  // Guard: Skip if already initialized
  if (window.__counterAnimationInitialized) return;
  window.__counterAnimationInitialized = true;

  const counters = document.querySelectorAll('[data-target]');
  if (!counters.length || typeof IntersectionObserver === 'undefined') return;

  const animateCounter = (element) => {
    const target = parseInt(element.getAttribute('data-target'), 10);
    const suffix = element.getAttribute('data-suffix') || '';
    const duration = 2000;
    const increment = target / (duration / 16);
    let current = 0;

    const updateCounter = () => {
      current += increment;
      if (current < target) {
        element.textContent = Math.floor(current).toLocaleString() + suffix;
        requestAnimationFrame(updateCounter);
      } else {
        element.textContent = target.toLocaleString() + suffix;
      }
    };

    updateCounter();
  };

  // Intersection Observer for counters
  const observerOptions = {
    threshold: 0.5,
    rootMargin: '0px'
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        animateCounter(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  counters.forEach(counter => observer.observe(counter));
}

// ============================================
// Smooth Scrolling for Anchor Links
// ============================================
function initSmoothScrolling() {
  // Guard: Skip if already initialized
  if (window.__smoothScrollingInitialized) return;
  window.__smoothScrollingInitialized = true;

  const anchorLinks = document.querySelectorAll('a[href^="#"]');

  anchorLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      const href = link.getAttribute('href');
      if (href && href !== '#') {
        e.preventDefault();
        const target = document.querySelector(href);
        if (target) {
          const navbarHeight = document.querySelector('.navbar')?.offsetHeight || 80;
          const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - navbarHeight;

          window.scrollTo({
            top: targetPosition,
            behavior: 'smooth'
          });
        }
      }
    });
  });
}

// ============================================
// Parallax Effect for Hero Section
// ============================================
function initParallaxEffect() {
  // Guard: Skip if already initialized
  if (window.__parallaxEffectInitialized) return;
  window.__parallaxEffectInitialized = true;

  const heroSection = document.querySelector('.hero');
  if (!heroSection) return;

  let ticking = false;

  const updateParallax = () => {
    const scrolled = window.pageYOffset;
    const heroHeight = heroSection.offsetHeight;

    if (scrolled < heroHeight) {
      const parallaxLayer = heroSection.querySelector('.hero__mountain-bg');
      const parallaxValue = scrolled * 0.5;
      if (parallaxLayer) {
        parallaxLayer.style.transform = `translateY(${parallaxValue}px)`;
      }
    }
    ticking = false;
  };

  window.addEventListener('scroll', () => {
    if (!ticking) {
      requestAnimationFrame(updateParallax);
      ticking = true;
    }
  });
}

// ============================================
// Interactive Mine Markers on Map
// ============================================
function initMineMarkers() {
  // Guard: Skip if already initialized
  if (window.__mineMarkersInitialized) return;
  window.__mineMarkersInitialized = true;

  const markers = document.querySelectorAll('.mine-marker');

  markers.forEach(marker => {
    marker.addEventListener('click', () => {
      // Add a subtle animation on click
      marker.style.transform = 'scale(1.3)';
      setTimeout(() => {
        marker.style.transform = 'scale(1)';
      }, 300);
    });

    // Add hover effect
    marker.addEventListener('mouseenter', () => {
      marker.style.cursor = 'pointer';
    });
  });
}

// ============================================
// Newsletter Form Handling
// ============================================
function initNewsletterForm() {
  // Guard: Skip if already initialized
  if (window.__newsletterFormInitialized) return;
  window.__newsletterFormInitialized = true;

  const form = document.querySelector('.footer__newsletter');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const emailInput = form.querySelector('.footer__newsletter-input');
    const email = emailInput.value;

    if (email && validateEmail(email)) {

      // Show success feedback
      emailInput.value = '';
      emailInput.placeholder = 'Subscribed';
      setTimeout(() => {
        emailInput.placeholder = 'Your email';
      }, 3000);
    } else {
      emailInput.style.borderColor = '#ef4444';
      setTimeout(() => {
        emailInput.style.borderColor = '';
      }, 2000);
    }
  });
}

function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

// ============================================
// Export for potential use in other modules
// ============================================
export {
  initCounterAnimation,
  initSmoothScrolling,
  initParallaxEffect,
  initMineMarkers,
  initNewsletterForm
};

