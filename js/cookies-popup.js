/**
 * Cookies Policy Popup Component
 * A professional, premium cookies consent popup for Balochistan Minerals
 */

class CookiesPopup {
  constructor(options = {}) {
    this.options = {
      expiryDays: 365,
      storageKey: 'bm_cookies_accepted',
      desktopShowDelayMs: 650,
      mobileShowDelayMs: 1000,
      ...options
    };
    
    this.init();
  }

  init() {
    if (this.hasUserAccepted()) return;

    const schedulePopup = () => {
      window.setTimeout(() => {
        this.createPopup();
        this.attachEventListeners();
      }, this.getShowDelay());
    };

    if (document.readyState === 'complete') {
      schedulePopup();
    } else {
      window.addEventListener('load', schedulePopup, { once: true });
    }
  }

  getShowDelay() {
    return window.matchMedia('(max-width: 640px)').matches
      ? this.options.mobileShowDelayMs
      : this.options.desktopShowDelayMs;
  }

  hasUserAccepted() {
    return localStorage.getItem(this.options.storageKey) === 'true';
  }

  markAsAccepted() {
    const expiryDate = new Date();
    expiryDate.setDate(expiryDate.getDate() + this.options.expiryDays);
    localStorage.setItem(this.options.storageKey, 'true');
  }

  createPopup() {
    const popup = document.createElement('div');
    popup.id = 'bm-cookies-popup';
    popup.innerHTML = `
      <div class="bm-cookies-overlay"></div>
      <div class="bm-cookies-container" data-lenis-prevent data-lenis-prevent-wheel data-lenis-prevent-touch>
        <div class="bm-cookies-content" data-lenis-prevent data-lenis-prevent-wheel data-lenis-prevent-touch>
          <div class="bm-cookies-header">
            <h2>Cookie Preferences</h2>
            <button class="bm-cookies-close" aria-label="Close cookies popup">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>

          <div class="bm-cookies-body" data-lenis-prevent data-lenis-prevent-wheel data-lenis-prevent-touch>
            <p class="bm-cookies-title">We Use Cookies to Enhance Your Experience</p>
            <p class="bm-cookies-description">
              We use cookies and similar technologies to improve your browsing experience, analyze site usage, and deliver personalized content. By continuing to use our website, you consent to our use of cookies.
            </p>

            <div class="bm-cookies-types">
              <div class="bm-cookie-type">
                <div class="bm-cookie-type-header">
                  <input type="checkbox" id="essential-cookies" name="essential" checked disabled>
                  <label for="essential-cookies">
                    <strong>Essential Cookies</strong>
                    <span class="bm-cookie-type-badge">Required</span>
                  </label>
                </div>
                <p class="bm-cookie-type-description">
                  Necessary for website functionality, security, and to remember your preferences. These cannot be disabled.
                </p>
              </div>

              <div class="bm-cookie-type">
                <div class="bm-cookie-type-header">
                  <input type="checkbox" id="analytics-cookies" name="analytics" checked>
                  <label for="analytics-cookies">
                    <strong>Analytics Cookies</strong>
                  </label>
                </div>
                <p class="bm-cookie-type-description">
                  Help us understand how you use our website so we can improve your experience and our services.
                </p>
              </div>

              <div class="bm-cookie-type">
                <div class="bm-cookie-type-header">
                  <input type="checkbox" id="marketing-cookies" name="marketing" checked>
                  <label for="marketing-cookies">
                    <strong>Marketing Cookies</strong>
                  </label>
                </div>
                <p class="bm-cookie-type-description">
                  Used to deliver personalized advertisements and marketing content tailored to your interests.
                </p>
              </div>
            </div>

            <p class="bm-cookies-legal">
              Learn more about our cookie practices in our <a href="privacy.html" target="_blank">Privacy Policy</a>.
            </p>
          </div>

          <div class="bm-cookies-footer">
            <button class="bm-cookies-btn bm-cookies-btn-secondary" id="bm-cookies-settings">
              Customize Preferences
            </button>
            <button class="bm-cookies-btn bm-cookies-btn-primary" id="bm-cookies-accept">
              Accept All Cookies
            </button>
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(popup);
    this.popup = popup;
    this.lockPageScroll();

    // Let the browser paint the hidden state before starting the slide-in.
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        popup.classList.add('bm-cookies-show');
      });
    });
  }

  attachEventListeners() {
    const acceptBtn = this.popup?.querySelector('#bm-cookies-accept');
    const closeBtn = this.popup?.querySelector('.bm-cookies-close');
    const settingsBtn = this.popup?.querySelector('#bm-cookies-settings');

    if (acceptBtn) {
      acceptBtn.addEventListener('click', () => this.acceptAll());
    }

    if (closeBtn) {
      closeBtn.addEventListener('click', () => this.close());
    }

    if (settingsBtn) {
      settingsBtn.addEventListener('click', () => this.openSettings());
    }

    // Close on overlay click
    const overlay = this.popup?.querySelector('.bm-cookies-overlay');
    if (overlay) {
      overlay.addEventListener('click', () => this.close());
    }

    const scrollTargets = this.popup?.querySelectorAll('.bm-cookies-container, .bm-cookies-content, .bm-cookies-body');
    scrollTargets?.forEach((element) => {
      element.addEventListener('wheel', (event) => event.stopPropagation(), { passive: true });
      element.addEventListener('touchmove', (event) => event.stopPropagation(), { passive: true });
    });

    // ESC key to close
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.popup) {
        this.close();
      }
    });
  }

  acceptAll() {
    // Mark cookies as accepted
    this.markAsAccepted();
    
    // Initialize tracking scripts if needed
    this.initializeTracking();
    
    // Close popup
    this.close();
  }

  openSettings() {
    // For future implementation - open a more detailed settings modal
    console.log('Settings panel - to be implemented');
  }

  initializeTracking() {
    // Initialize Google Analytics or other tracking services
    // This is where you'd load GTM, GA, etc.
    console.log('Tracking scripts initialized');
  }

  lockPageScroll() {
    document.documentElement.classList.add('bm-cookies-modal-open');
    document.body.classList.add('bm-cookies-modal-open');

    if (window.__bmLenis && typeof window.__bmLenis.stop === 'function') {
      window.__bmLenis.stop();
    }
  }

  unlockPageScroll() {
    document.documentElement.classList.remove('bm-cookies-modal-open');
    document.body.classList.remove('bm-cookies-modal-open');

    if (window.__bmLenis && typeof window.__bmLenis.start === 'function') {
      window.__bmLenis.start();
    }
  }

  close() {
    if (!this.popup) return;
    
    this.popup.classList.remove('bm-cookies-show');
    setTimeout(() => {
      this.popup?.remove();
      this.unlockPageScroll();
    }, window.matchMedia('(max-width: 640px)').matches ? 1700 : 1400);
  }
}

// Initialize popup when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  if (!localStorage.getItem('bm_cookies_accepted')) {
    new CookiesPopup();
  }
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
  module.exports = CookiesPopup;
}

