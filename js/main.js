import { initNavbar } from './navbar.js';
import { initScrollReveal } from './scroll-reveal.js';
import { initDropdownMenus } from './dropdown.js';
import { initHeroSlider } from './hero-slider.js';
import { initBorderBeam } from './border-beam.js';

// ============================================
// BM Hill Preloader Logic
// ============================================
// We use DOMContentLoaded for a much faster initial reveal on mobile
document.addEventListener('DOMContentLoaded', () => {
  // Snappier 800ms baseline for a professional institucional feel
  setTimeout(() => {
    document.body.classList.remove('is-loading');
    document.body.classList.add('loaded');
  }, 800);
});

// Failsafe: Force open in case of heavy asset hanging
setTimeout(() => {
  if (document.body.classList.contains('is-loading')) {
    document.body.classList.remove('is-loading');
    document.body.classList.add('loaded');
  }
}, 3000);

// ============================================
// Initialize All Features
// ============================================
document.addEventListener('DOMContentLoaded', async () => {
  // 1. Initialize feature logic (now that elements exist in DOM)
  initNavbar();
  initScrollReveal();
  initDropdownMenus();
  initHeroSlider();
  initCounterAnimation();
  initSmoothScrolling();
  initParallaxEffect();
  initMineMarkers();
  initNewsletterForm();
  initBorderBeam();
  initStepper();
});

// ============================================
// Interactive Stepper for Operations
// ============================================
function initStepper() {
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
  const counters = document.querySelectorAll('[data-target]');

  const animateCounter = (element) => {
    const target = parseInt(element.getAttribute('data-target'));
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
  const markers = document.querySelectorAll('.mine-marker');

  markers.forEach(marker => {
    marker.addEventListener('click', () => {
      const siteName = marker.getAttribute('data-site');
      // You can expand this to show a popup or navigate to project details
      console.log('Clicked mine site:', siteName);

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
  const form = document.querySelector('.footer__newsletter');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const emailInput = form.querySelector('.footer__newsletter-input');
    const email = emailInput.value;

    if (email && validateEmail(email)) {
      // You would typically send this to your backend
      console.log('Newsletter signup:', email);

      // Show success feedback
      emailInput.value = '';
      emailInput.placeholder = '✓ Subscribed!';
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
