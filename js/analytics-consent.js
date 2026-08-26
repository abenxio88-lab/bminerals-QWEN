(() => {
  'use strict';

  const STORAGE_KEY = 'bm_cookie_preferences';
  const LEGACY_STORAGE_KEY = 'bm_cookies_accepted';
  const MEASUREMENT_ID = 'G-55KSGPLX2R';
  const CONSENT_VERSION = 1;
  const CONSENT_LIFETIME_MS = 365 * 24 * 60 * 60 * 1000;
  let analyticsLoaded = false;

  const storage = {
    get(key) {
      try {
        return window.localStorage.getItem(key);
      } catch {
        return null;
      }
    },
    set(key, value) {
      try {
        window.localStorage.setItem(key, value);
        return true;
      } catch {
        return false;
      }
    },
    remove(key) {
      try {
        window.localStorage.removeItem(key);
      } catch {
        // Storage can be unavailable in restricted browsing modes.
      }
    }
  };

  function normalizePreferences(value) {
    if (!value || typeof value !== 'object') return null;
    if (value.version !== CONSENT_VERSION || Number(value.expiresAt) <= Date.now()) return null;

    return {
      version: CONSENT_VERSION,
      essential: true,
      analytics: value.analytics === true,
      marketing: value.marketing === true,
      savedAt: Number(value.savedAt) || Date.now(),
      expiresAt: Number(value.expiresAt)
    };
  }

  function readPreferences() {
    const stored = storage.get(STORAGE_KEY);
    if (stored) {
      try {
        const preferences = normalizePreferences(JSON.parse(stored));
        if (preferences) return preferences;
      } catch {
        storage.remove(STORAGE_KEY);
      }
    }

    if (storage.get(LEGACY_STORAGE_KEY) === 'true') {
      const migrated = createPreferences({ analytics: true, marketing: false });
      storage.set(STORAGE_KEY, JSON.stringify(migrated));
      storage.remove(LEGACY_STORAGE_KEY);
      return migrated;
    }

    return null;
  }

  function createPreferences(value = {}) {
    const savedAt = Date.now();
    return {
      version: CONSENT_VERSION,
      essential: true,
      analytics: value.analytics === true,
      marketing: value.marketing === true,
      savedAt,
      expiresAt: savedAt + CONSENT_LIFETIME_MS
    };
  }

  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function gtag() {
    window.dataLayer.push(arguments);
  };

  function consentPayload(preferences) {
    const analyticsGranted = preferences?.analytics === true;
    const marketingGranted = preferences?.marketing === true;

    return {
      analytics_storage: analyticsGranted ? 'granted' : 'denied',
      ad_storage: marketingGranted ? 'granted' : 'denied',
      ad_user_data: marketingGranted ? 'granted' : 'denied',
      ad_personalization: marketingGranted ? 'granted' : 'denied',
      functionality_storage: 'granted',
      personalization_storage: 'denied',
      security_storage: 'granted'
    };
  }

  function loadAnalytics() {
    if (analyticsLoaded || document.getElementById('bm-google-analytics')) return;
    analyticsLoaded = true;

    window.gtag('js', new Date());
    window.gtag('config', MEASUREMENT_ID, { anonymize_ip: true });

    const script = document.createElement('script');
    script.id = 'bm-google-analytics';
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(MEASUREMENT_ID)}`;
    document.head.appendChild(script);
  }

  const initialPreferences = readPreferences();
  window.gtag('consent', 'default', {
    ...consentPayload(initialPreferences),
    wait_for_update: 500
  });
  if (initialPreferences?.analytics) loadAnalytics();

  window.BMCookieConsent = {
    hasChoice() {
      return readPreferences() !== null;
    },
    getPreferences() {
      return readPreferences();
    },
    savePreferences(value) {
      const preferences = createPreferences(value);
      storage.set(STORAGE_KEY, JSON.stringify(preferences));
      storage.remove(LEGACY_STORAGE_KEY);
      window.gtag('consent', 'update', consentPayload(preferences));
      if (preferences.analytics) loadAnalytics();
      window.dispatchEvent(new CustomEvent('bm:cookie-consent-changed', { detail: preferences }));
      return preferences;
    },
    reset() {
      storage.remove(STORAGE_KEY);
      storage.remove(LEGACY_STORAGE_KEY);
      window.gtag('consent', 'update', consentPayload(null));
    },
    openPreferences() {
      window.dispatchEvent(new CustomEvent('bm:open-cookie-preferences'));
    }
  };

  document.addEventListener('click', event => {
    const trigger = event.target.closest?.('[data-bm-cookie-settings]');
    if (!trigger) return;
    event.preventDefault();
    window.BMCookieConsent.openPreferences();
  });

  if (!document.getElementById('bm-cookie-styles')) {
    const stylesheet = document.createElement('link');
    stylesheet.id = 'bm-cookie-styles';
    stylesheet.rel = 'stylesheet';
    stylesheet.href = '/css/cookies-popup.css?v=ffb891aa41f5';
    document.head.appendChild(stylesheet);
  }

  if (!document.getElementById('bm-cookie-popup-script')) {
    const popupScript = document.createElement('script');
    popupScript.id = 'bm-cookie-popup-script';
    popupScript.src = '/js/cookies-popup.js?v=552832218bb3';
    popupScript.defer = true;
    document.head.appendChild(popupScript);
  }
})();
