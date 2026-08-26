/**
 * Site-wide cookie consent dialog.
 * Analytics remains denied until the visitor makes an explicit choice.
 */
(() => {
  'use strict';

  if (window.__bmCookiesPopupBootstrapped) return;
  window.__bmCookiesPopupBootstrapped = true;

  class CookiesPopup {
    constructor(options = {}) {
      this.options = {
        desktopShowDelayMs: 250,
        mobileShowDelayMs: 900,
        forceDisplay: false,
        ...options
      };
      this.popup = null;
      this.previouslyFocusedElement = null;
      this.handleKeydown = this.handleKeydown.bind(this);
      this.init();
    }

    init() {
      if (!this.options.forceDisplay && window.BMCookieConsent?.hasChoice()) return;

      window.setTimeout(() => {
        if (document.getElementById('bm-cookies-popup')) return;
        this.createPopup();
        this.attachEventListeners();
      }, this.getShowDelay());
    }

    getShowDelay() {
      return window.matchMedia('(max-width: 640px)').matches
        ? this.options.mobileShowDelayMs
        : this.options.desktopShowDelayMs;
    }

    createPopup() {
      this.previouslyFocusedElement = document.activeElement;
      const popup = document.createElement('div');
      popup.id = 'bm-cookies-popup';
      popup.innerHTML = `
        <div class="bm-cookies-overlay"></div>
        <div class="bm-cookies-container" role="dialog" aria-modal="true" tabindex="-1"
          aria-labelledby="bm-cookies-title" aria-describedby="bm-cookies-description"
          data-lenis-prevent data-lenis-prevent-wheel data-lenis-prevent-touch>
          <div class="bm-cookies-content" data-lenis-prevent data-lenis-prevent-wheel data-lenis-prevent-touch>
            <div class="bm-cookies-header">
              <h2 id="bm-cookies-title">Cookie Preferences</h2>
              <button type="button" class="bm-cookies-close" aria-label="Use necessary cookies only">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <line x1="18" y1="6" x2="6" y2="18"></line>
                  <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
              </button>
            </div>

            <div class="bm-cookies-body" data-lenis-prevent data-lenis-prevent-wheel data-lenis-prevent-touch>
              <p class="bm-cookies-title">Your privacy choices</p>
              <p class="bm-cookies-description" id="bm-cookies-description">
                We use necessary storage for website functionality and your privacy choice. With your permission,
                Google Analytics helps us understand site usage. Analytics remains off unless you allow it.
              </p>

              <div class="bm-cookies-types">
                <div class="bm-cookie-type">
                  <div class="bm-cookie-type-header">
                    <input type="checkbox" id="essential-cookies" name="essential" checked disabled>
                    <label for="essential-cookies">
                      <strong>Essential storage</strong>
                      <span class="bm-cookie-type-badge">Required</span>
                    </label>
                  </div>
                  <p class="bm-cookie-type-description">
                    Required for security, core website behavior, and remembering this preference.
                  </p>
                </div>

                <div class="bm-cookie-type">
                  <div class="bm-cookie-type-header">
                    <input type="checkbox" id="analytics-cookies" name="analytics">
                    <label for="analytics-cookies">
                      <strong>Analytics cookies</strong>
                    </label>
                  </div>
                  <p class="bm-cookie-type-description">
                    Allows Google Analytics to measure visits and interactions so we can improve the website.
                  </p>
                </div>
              </div>

              <p class="bm-cookies-legal">
                Read our <a href="/privacy#cookies-and-tracking-technologies">Privacy Policy</a> for more information.
              </p>
            </div>

            <div class="bm-cookies-footer">
              <button type="button" class="bm-cookies-btn bm-cookies-btn-secondary" id="bm-cookies-reject">
                Necessary Only
              </button>
              <button type="button" class="bm-cookies-btn bm-cookies-btn-secondary" id="bm-cookies-save">
                Save Preference
              </button>
              <button type="button" class="bm-cookies-btn bm-cookies-btn-primary" id="bm-cookies-accept">
                Accept Analytics
              </button>
            </div>
          </div>
        </div>
      `;

      document.body.appendChild(popup);
      this.popup = popup;
      const savedPreferences = window.BMCookieConsent?.getPreferences();
      const analyticsCheckbox = popup.querySelector('#analytics-cookies');
      if (analyticsCheckbox) analyticsCheckbox.checked = savedPreferences?.analytics === true;
      this.lockPageScroll();

      // Commit the hidden state before revealing the dialog so the transition
      // remains smooth even when animation frames are throttled in a background tab.
      void popup.offsetHeight;
      window.setTimeout(() => {
        popup.classList.add('bm-cookies-show');
        popup.querySelector('.bm-cookies-container')?.focus({ preventScroll: true });
      }, 24);
    }

    attachEventListeners() {
      this.popup?.querySelector('#bm-cookies-accept')?.addEventListener('click', () => this.acceptAnalytics());
      this.popup?.querySelector('#bm-cookies-reject')?.addEventListener('click', () => this.rejectAnalytics());
      this.popup?.querySelector('#bm-cookies-save')?.addEventListener('click', () => this.saveSelection());
      this.popup?.querySelector('.bm-cookies-close')?.addEventListener('click', () => this.rejectAnalytics());
      this.popup?.querySelector('.bm-cookies-overlay')?.addEventListener('click', () => this.rejectAnalytics());

      const scrollTargets = this.popup?.querySelectorAll('.bm-cookies-container, .bm-cookies-content, .bm-cookies-body');
      scrollTargets?.forEach(element => {
        element.addEventListener('wheel', event => event.stopPropagation(), { passive: true });
        element.addEventListener('touchmove', event => event.stopPropagation(), { passive: true });
      });

      document.addEventListener('keydown', this.handleKeydown);
    }

    handleKeydown(event) {
      if (!this.popup) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        this.rejectAnalytics();
        return;
      }

      if (event.key !== 'Tab') return;
      const focusable = [...this.popup.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])')];
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }

    acceptAnalytics() {
      this.savePreferences(true);
    }

    rejectAnalytics() {
      this.savePreferences(false);
    }

    saveSelection() {
      const analytics = this.popup?.querySelector('#analytics-cookies')?.checked === true;
      this.savePreferences(analytics);
    }

    savePreferences(analytics) {
      window.BMCookieConsent?.savePreferences({ analytics, marketing: false });
      this.close();
    }

    lockPageScroll() {
      document.documentElement.classList.add('bm-cookies-modal-open');
      document.body.classList.add('bm-cookies-modal-open');
      window.__bmLenis?.stop?.();
    }

    unlockPageScroll() {
      document.documentElement.classList.remove('bm-cookies-modal-open');
      document.body.classList.remove('bm-cookies-modal-open');
      window.__bmLenis?.start?.();
    }

    close() {
      if (!this.popup) return;
      const popup = this.popup;
      this.popup = null;
      document.removeEventListener('keydown', this.handleKeydown);
      popup.classList.remove('bm-cookies-show');

      window.setTimeout(() => {
        popup.remove();
        this.unlockPageScroll();
        if (this.previouslyFocusedElement instanceof HTMLElement) {
          this.previouslyFocusedElement.focus({ preventScroll: true });
        }
      }, window.matchMedia('(max-width: 640px)').matches ? 520 : 450);
    }
  }

  const initialize = () => {
    if (!window.BMCookieConsent?.hasChoice()) new CookiesPopup();
  };

  window.addEventListener('bm:open-cookie-preferences', () => {
    if (!document.getElementById('bm-cookies-popup')) {
      new CookiesPopup({ forceDisplay: true, desktopShowDelayMs: 0, mobileShowDelayMs: 0 });
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
