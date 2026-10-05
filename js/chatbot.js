/**
 * Balochistan Minerals - AI Chatbot Module
 * Enterprise-grade conversational assistant for B2B mineral inquiries, specs, and logistics.
 */

import { findBestAnswer, buildWhatsAppRfqUrl, buildEmailRfqUrl, COMPANY_INFO } from './chatbot-knowledge.js';

const STORAGE_KEY = 'bm_chatbot_history_v1';
const STYLESHEET_ID = 'bm-chatbot-stylesheet';

class BMChatbot {
  constructor() {
    this.isOpen = false;
    this.isMinimized = false;
    this.isTyping = false;
    this.messages = [];
    this.container = null;
    this.launcher = null;
    this.window = null;
    this.body = null;
    this.input = null;
    this.sendBtn = null;
    this.typingElement = null;
    this.overlay = null;
    this.savedScrollY = 0;
    this.onVisualViewportChange = null;
    this.onTouchMove = null;
    this.onTouchStart = null;

    this.init();
  }

  init() {
    this.ensureStyles();
    this.loadHistory();
    this.render();
    this.bindEvents();
    this.initScrollVisibility();

    if (this.messages.length === 0) {
      this.addWelcomeMessage();
    } else {
      this.restoreMessages();
    }
  }

  resolveAssetPath(assetPath) {
    if (!assetPath) return '';
    if (assetPath.startsWith('http://') || assetPath.startsWith('https://') || assetPath.startsWith('//')) {
      return assetPath;
    }
    const isSubdir = window.location.pathname.includes('/blog/') || window.location.pathname.includes('/documents/');
    return isSubdir ? `../${assetPath.replace(/^\//, '')}` : assetPath;
  }

  ensureStyles() {
    const existingLink = document.getElementById(STYLESHEET_ID);
    if (existingLink && existingLink.sheet) {
      return;
    }

    if (!document.getElementById('bm-chatbot-guard-styles')) {
      const guard = document.createElement('style');
      guard.id = 'bm-chatbot-guard-styles';
      guard.textContent = `
        #bm-chatbot-root { display: none !important; }
      `;
      document.head.appendChild(guard);
    }

    const revealWhenReady = () => {
      const guard = document.getElementById('bm-chatbot-guard-styles');
      if (guard) guard.remove();
      const root = document.getElementById('bm-chatbot-root');
      if (root) root.style.display = '';
    };

    if (existingLink) {
      existingLink.addEventListener('load', revealWhenReady);
      setTimeout(revealWhenReady, 400);
      return;
    }

    const link = document.createElement('link');
    link.id = STYLESHEET_ID;
    link.rel = 'stylesheet';
    link.href = this.resolveAssetPath('css/components/chatbot.css');

    link.addEventListener('load', revealWhenReady);
    link.addEventListener('error', revealWhenReady);

    setTimeout(revealWhenReady, 600);

    document.head.appendChild(link);
  }

  loadHistory() {
    try {
      const saved = sessionStorage.getItem(STORAGE_KEY);
      if (saved) {
        this.messages = JSON.parse(saved);
      }
    } catch {
      this.messages = [];
    }
  }

  saveHistory() {
    try {
      const trimmed = this.messages.slice(-20);
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(trimmed));
    } catch {
      // Ignore sessionStorage exceptions
    }
  }

  render() {
    if (document.getElementById('bm-chatbot-root')) return;

    const root = document.createElement('div');
    root.id = 'bm-chatbot-root';
    root.innerHTML = `
      <!-- Launcher Button -->
      <button type="button" class="bm-chat-launcher" aria-label="Open Balochistan Minerals AI Assistant" aria-haspopup="dialog">
        <div class="bm-chat-launcher__pill">
          <span>Ask AI • Mineral Desk</span>
        </div>
        <div class="bm-chat-launcher__btn">
          <svg class="bm-chat-launcher__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 3h12l4 6-10 12L2 9z"></path>
            <path d="M11 3v6l-4 3"></path>
            <path d="M13 3v6l4 3"></path>
          </svg>
        </div>
      </button>

      <!-- Outer Solid Backdrop Overlay (Layer 1) -->
      <div class="bm-chat-overlay" aria-hidden="true"></div>

      <!-- Chat Modal Window (Layer 2) -->
      <div class="bm-chat-window" role="dialog" aria-modal="true" aria-label="Balochistan Minerals AI Assistant"
           data-lenis-prevent data-lenis-prevent-wheel data-lenis-prevent-touch>
        
        <!-- Header -->
        <div class="bm-chat-header">
          <div class="bm-chat-header__brand">
            <div class="bm-chat-header__avatar" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 3h12l4 6-10 12L2 9z"></path>
                <path d="M11 3v6l-4 3"></path>
                <path d="M13 3v6l4 3"></path>
              </svg>
            </div>
            <div class="bm-chat-header__meta">
              <h2 class="bm-chat-header__title">
                Balochistan Minerals AI
              </h2>
              <span class="bm-chat-header__status">
                <span class="bm-chat-header__white-light"></span>
                Assay &amp; Logistics Verified
              </span>
            </div>
          </div>
          <div class="bm-chat-header__actions">
            <a href="https://wa.me/923348888104?text=Hello%20Balochistan%20Minerals%2C%20I%20would%20like%20to%20discuss%20mineral%20sourcing%20and%20export%20availability."
               target="_blank" rel="noopener noreferrer"
               class="bm-chat-btn-icon bm-chat-btn-whatsapp"
               title="Chat on WhatsApp" aria-label="Chat on WhatsApp">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true">
                <path d="M20.52 3.49A11.86 11.86 0 0 0 12.06 0C5.48 0 .13 5.35.13 11.93c0 2.1.55 4.15 1.59 5.96L0 24l6.29-1.65a11.9 11.9 0 0 0 5.77 1.47h.01c6.58 0 11.93-5.35 11.93-11.93 0-3.19-1.24-6.19-3.48-8.4Zm-8.46 18.31h-.01a9.9 9.9 0 0 1-5.05-1.38l-.36-.21-3.73.98 1-3.64-.24-.37a9.88 9.88 0 0 1-1.52-5.24C2.15 6.48 6.61 2.02 12.07 2.02c2.64 0 5.11 1.03 6.98 2.9a9.8 9.8 0 0 1 2.89 6.98c0 5.46-4.45 9.9-9.88 9.9Zm5.43-7.42c-.3-.15-1.77-.87-2.05-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.39-1.48-.88-.78-1.47-1.75-1.64-2.05-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.5h-.57c-.2 0-.52.08-.79.37-.27.3-1.04 1.02-1.04 2.49 0 1.47 1.07 2.9 1.22 3.1.15.2 2.1 3.21 5.09 4.5.71.31 1.27.49 1.7.63.72.23 1.37.2 1.89.12.58-.09 1.77-.72 2.02-1.42.25-.7.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35Z"/>
              </svg>
            </a>
            <button type="button" class="bm-chat-btn-icon bm-chat-btn-reset" title="Restart conversation" aria-label="Reset conversation">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 12a9 9 0 0 1 15-6.7L21 8M21 3v5h-5M21 12a9 9 0 0 1-15 6.7L3 16M3 21v-5h5"></path>
              </svg>
            </button>
            <button type="button" class="bm-chat-btn-icon bm-chat-btn-close" title="Close" aria-label="Close chat">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>
        </div>

        <!-- Chat Stream Body -->
        <div class="bm-chat-body" data-lenis-prevent data-lenis-prevent-wheel data-lenis-prevent-touch>
          <div class="bm-chat-messages-container"></div>
        </div>

        <!-- Chat Footer & Input Form -->
        <div class="bm-chat-footer">
          <form class="bm-chat-form">
            <textarea class="bm-chat-input" rows="1" placeholder="Inquire about barite, chromite, copper, ports..." aria-label="Message" autocomplete="off" autocorrect="off" autocapitalize="sentences" spellcheck="false"></textarea>
            <button type="submit" class="bm-chat-send" aria-label="Send message" disabled>
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13"></line>
                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
              </svg>
            </button>
          </form>
        </div>

      </div>
    `;

    document.body.appendChild(root);

    this.container = root;
    this.launcher = root.querySelector('.bm-chat-launcher');
    this.overlay = root.querySelector('.bm-chat-overlay');
    this.window = root.querySelector('.bm-chat-window');
    this.body = root.querySelector('.bm-chat-body');
    this.messagesContainer = root.querySelector('.bm-chat-messages-container');
    this.input = root.querySelector('.bm-chat-input');
    this.sendBtn = root.querySelector('.bm-chat-send');
    this.form = root.querySelector('.bm-chat-form');
    this.whatsappBtn = root.querySelector('.bm-chat-btn-whatsapp');
  }

  bindEvents() {
    this.launcher.addEventListener('click', () => this.toggleOpen());

    if (this.overlay) {
      this.overlay.addEventListener('click', () => this.close());
      this.overlay.addEventListener('wheel', (e) => e.preventDefault(), { passive: false });
    }

    const closeBtn = this.container.querySelector('.bm-chat-btn-close');
    closeBtn.addEventListener('click', () => this.close());

    const minimizeBtn = this.container.querySelector('.bm-chat-btn-minimize');
    if (minimizeBtn) {
      minimizeBtn.addEventListener('click', () => this.toggleMinimize());
    }

    if (this.whatsappBtn) {
      this.whatsappBtn.addEventListener('animationend', () => {
        this.whatsappBtn.classList.remove('is-blinking');
      });
    }

    const resetBtn = this.container.querySelector('.bm-chat-btn-reset');
    resetBtn.addEventListener('click', () => this.resetConversation());

    // Input handling
    this.input.addEventListener('input', () => {
      this.sendBtn.disabled = !this.input.value.trim();
      this.autoResizeInput();
    });

    this.input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        this.submitMessage();
      }
    });

    this.form.addEventListener('submit', (e) => {
      e.preventDefault();
      this.submitMessage();
    });

    // Escape key closes modal
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.isOpen) {
        this.close();
      }
    });

    // Delegate chip clicks and source links inside chat body
    this.body.addEventListener('click', (e) => {
      // Direct product showroom card click
      const productCard = e.target.closest('.bm-chat-product-card');
      if (productCard) {
        const query = productCard.getAttribute('data-query');
        if (query) this.sendUserPrompt(query);
        return;
      }

      const chip = e.target.closest('.bm-chat-chip');
      if (chip) {
        const text = chip.getAttribute('data-query') || chip.textContent.trim();
        this.sendUserPrompt(text);
        return;
      }

      // Direct page/section source link navigation
      const sourceLink = e.target.closest('.bm-chat-source__link');
      if (sourceLink) {
        const href = sourceLink.getAttribute('href');
        if (href) {
          const parts = href.split('#');
          const pagePath = parts[0];
          const hashId = parts[1];
          const currentFile = window.location.pathname.split('/').pop() || 'index.html';

          // If targeting a section on the current page, smooth scroll to it
          if (hashId && (!pagePath || pagePath === currentFile)) {
            const targetEl = document.getElementById(hashId);
            if (targetEl) {
              e.preventDefault();
              targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
              targetEl.style.transition = 'outline 0.3s ease';
              targetEl.style.outline = '2px solid #D4AF37';
              setTimeout(() => { targetEl.style.outline = ''; }, 2000);
            }
          }
        }
      }
    });
  }

  initScrollVisibility() {
    if (!this.launcher) return;

    const findHero = () => document.querySelector('.hero, .parallax-hero, .detail-hero, #hero, [class*="hero"]');

    const updateVisibility = () => {
      // If modal is open, launcher display is handled by .bm-chat-open
      if (this.isOpen) return;

      const hero = findHero();
      if (hero) {
        const rect = hero.getBoundingClientRect();
        // Visible when hero has mostly scrolled past top of viewport
        const isPastHero = rect.bottom <= 120;
        this.launcher.classList.toggle('is-visible', isPastHero);
        return;
      }

      // Fallback for pages without a hero section
      const currentScroll = window.scrollY || document.documentElement.scrollTop || 0;
      this.launcher.classList.toggle('is-visible', currentScroll > 250);
    };

    this.updateScrollVisibility = updateVisibility;

    window.addEventListener('scroll', updateVisibility, { passive: true });
    window.addEventListener('resize', updateVisibility, { passive: true });

    // Initial check in case page loaded already scrolled
    updateVisibility();
  }

  autoResizeInput() {
    this.input.style.height = 'auto';
    this.input.style.height = `${Math.min(this.input.scrollHeight, 84)}px`;
  }

  toggleOpen() {
    if (this.isOpen) {
      this.close();
    } else {
      this.open();
    }
  }

  open() {
    this.isOpen = true;
    this.isMinimized = false;
    this.window.classList.remove('bm-chat-minimized');

    // 1. Store scroll position before locking
    this.savedScrollY = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;

    // 2. Set architectural lock attributes on html and body (mobile only)
    if (window.innerWidth <= 768) {
      document.documentElement.setAttribute('data-assistant-open', 'true');
      document.body.setAttribute('data-assistant-open', 'true');
    }
    document.body.classList.add('bm-chat-open');

    // 3. Pause Lenis smooth scrolling to eliminate background touch physics
    if (window.__bmLenis && typeof window.__bmLenis.stop === 'function') {
      window.__bmLenis.stop();
    }

    // 4. Bind visual viewport for dynamic height & top synchronization on mobile
    this.bindVisualViewport();

    // 5. Isolate touch events to eliminate background rubber-banding
    this.bindTouchGuards();

    this.scrollToBottom();

    if (window.tactileFeedback) {
      window.tactileFeedback('light');
    }

    // 6. Blink WhatsApp icon twice with green highlight
    if (this.whatsappBtn) {
      this.whatsappBtn.classList.remove('is-blinking');
      void this.whatsappBtn.offsetWidth;
      this.whatsappBtn.classList.add('is-blinking');
    }

    // Focus input on desktop; on touch devices avoid auto-triggering keyboard immediately on open
    setTimeout(() => {
      if (window.innerWidth > 768) {
        this.input.focus();
      }
    }, 180);
  }

  close() {
    this.isOpen = false;

    // Reset WhatsApp icon blinking animation if closed mid-animation
    if (this.whatsappBtn) {
      this.whatsappBtn.classList.remove('is-blinking');
    }

    // 1. Remove architectural lock attributes
    document.documentElement.removeAttribute('data-assistant-open');
    document.body.removeAttribute('data-assistant-open');
    document.body.classList.remove('bm-chat-open');

    // 2. Unbind visual viewport listeners and reset coordinates
    this.unbindVisualViewport();

    // 3. Unbind touch guards
    this.unbindTouchGuards();

    // 4. Resume Lenis smooth scrolling
    if (window.__bmLenis && typeof window.__bmLenis.start === 'function') {
      window.__bmLenis.start();
    }

    // 5. Restore scroll position
    window.scrollTo(0, this.savedScrollY);

    if (typeof this.updateScrollVisibility === 'function') {
      this.updateScrollVisibility();
    }

    this.launcher.focus();
  }

  toggleMinimize() {
    this.isMinimized = !this.isMinimized;
    this.window.classList.toggle('bm-chat-minimized', this.isMinimized);

    if (this.isMinimized) {
      document.documentElement.removeAttribute('data-assistant-open');
      document.body.removeAttribute('data-assistant-open');
      if (window.__bmLenis && typeof window.__bmLenis.start === 'function') {
        window.__bmLenis.start();
      }
      this.unbindVisualViewport();
      this.unbindTouchGuards();
    } else {
      this.savedScrollY = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
      document.documentElement.setAttribute('data-assistant-open', 'true');
      document.body.setAttribute('data-assistant-open', 'true');
      if (window.__bmLenis && typeof window.__bmLenis.stop === 'function') {
        window.__bmLenis.stop();
      }
      this.bindVisualViewport();
      this.bindTouchGuards();
      this.scrollToBottom();
    }
  }

  bindVisualViewport() {
    this.unbindVisualViewport();
    if (typeof window === 'undefined') return;

    this.onVisualViewportChange = () => {
      this.updateVisualViewport();
    };

    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', this.onVisualViewportChange);
      window.visualViewport.addEventListener('scroll', this.onVisualViewportChange);
    }
    window.addEventListener('resize', this.onVisualViewportChange);
    window.addEventListener('orientationchange', this.onVisualViewportChange);

    this.updateVisualViewport();
  }

  unbindVisualViewport() {
    if (this.onVisualViewportChange) {
      if (window.visualViewport) {
        window.visualViewport.removeEventListener('resize', this.onVisualViewportChange);
        window.visualViewport.removeEventListener('scroll', this.onVisualViewportChange);
      }
      window.removeEventListener('resize', this.onVisualViewportChange);
      window.removeEventListener('orientationchange', this.onVisualViewportChange);
      this.onVisualViewportChange = null;
    }
    this.resetMobileViewportStyles();
  }

  updateVisualViewport() {
    if (!this.isOpen || this.isMinimized || window.innerWidth > 768) {
      this.resetMobileViewportStyles();
      return;
    }

    if (window.visualViewport) {
      const vv = window.visualViewport;
      // Precisely bind top and height to the visual viewport above virtual keyboard
      this.window.style.top = `${vv.offsetTop}px`;
      this.window.style.height = `${vv.height}px`;
    } else {
      this.window.style.top = '0px';
      this.window.style.height = `${window.innerHeight}px`;
    }
  }

  resetMobileViewportStyles() {
    if (this.window) {
      this.window.style.top = '';
      this.window.style.height = '';
    }
  }

  bindTouchGuards() {
    this.unbindTouchGuards();
    if (typeof document === 'undefined') return;

    let startTouchY = 0;

    this.onTouchStart = (e) => {
      if (e.touches && e.touches.length === 1) {
        startTouchY = e.touches[0].clientY;
      }
    };

    this.onTouchMove = (e) => {
      if (!this.isOpen || this.isMinimized || window.innerWidth > 768) return;

      const scroller = e.target.closest('.bm-chat-body');
      if (!scroller) {
        // Touches on header, overlay, footer etc. must NEVER chain to window
        e.preventDefault();
        return;
      }

      // Check boundary conditions inside .bm-chat-body
      if (scroller.scrollHeight <= scroller.clientHeight) {
        e.preventDefault();
        return;
      }

      if (e.touches && e.touches.length === 1) {
        const currentY = e.touches[0].clientY;
        const deltaY = currentY - startTouchY;
        const isAtTop = scroller.scrollTop <= 0;
        const isAtBottom = scroller.scrollTop + scroller.clientHeight >= scroller.scrollHeight - 1;

        // Swiping down while at top boundary -> prevent rubber-banding
        if (isAtTop && deltaY > 0) {
          e.preventDefault();
          return;
        }
        // Swiping up while at bottom boundary -> prevent rubber-banding
        if (isAtBottom && deltaY < 0) {
          e.preventDefault();
          return;
        }
      }
    };

    document.addEventListener('touchstart', this.onTouchStart, { capture: true, passive: true });
    document.addEventListener('touchmove', this.onTouchMove, { capture: true, passive: false });
  }

  unbindTouchGuards() {
    if (this.onTouchMove) {
      document.removeEventListener('touchmove', this.onTouchMove, { capture: true, passive: false });
      this.onTouchMove = null;
    }
    if (this.onTouchStart) {
      document.removeEventListener('touchstart', this.onTouchStart, true);
      this.onTouchStart = null;
    }
  }

  resetConversation() {
    sessionStorage.removeItem(STORAGE_KEY);
    this.messages = [];
    this.messagesContainer.innerHTML = '';
    this.addWelcomeMessage();
  }

  addWelcomeMessage() {
    const welcomeHtml = `
      <div class="bm-chat-welcome">
        <div class="bm-chat-welcome__header">
          <div class="bm-chat-welcome__icon">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
              <path d="m9 12 2 2 4-4"></path>
            </svg>
          </div>
          <div>
            <h3 class="bm-chat-welcome__title">Balochistan Minerals Desk</h3>
            <span class="bm-chat-welcome__subtitle">Direct Export & Technical Intelligence</span>
          </div>
        </div>
        <p class="bm-chat-welcome__text">
          Access verified lot assays, mine-to-port logistics schedules, and direct commercial quotations for Pakistan minerals.
        </p>
        <div class="bm-chat-chips">
          <button type="button" class="bm-chat-chip bm-chat-chip--thumb" data-query="Barite 4.2+ SG">
            <img src="${this.resolveAssetPath('images/barite-card-480.avif')}" alt="" class="bm-chat-chip__img" width="15" height="15" loading="lazy">
            <span>Barite 4.2+ SG</span>
          </button>
          <button type="button" class="bm-chat-chip bm-chat-chip--thumb" data-query="Chromite 42-52%">
            <img src="${this.resolveAssetPath('images/chromite-new-480.avif')}" alt="" class="bm-chat-chip__img" width="15" height="15" loading="lazy">
            <span>Chromite 42-52%</span>
          </button>
          <button type="button" class="bm-chat-chip bm-chat-chip--thumb" data-query="Chagai Copper">
            <img src="${this.resolveAssetPath('images/copper-new-480.avif')}" alt="" class="bm-chat-chip__img" width="15" height="15" loading="lazy">
            <span>Chagai Copper</span>
          </button>
          <button type="button" class="bm-chat-chip" data-query="Logistics & Ports">
            <span>Logistics & Ports</span>
          </button>
          <button type="button" class="bm-chat-chip" data-query="Request Quote">
            <span>Request Quote</span>
          </button>
        </div>
      </div>
    `;

    this.appendHtml(welcomeHtml);
  }

  restoreMessages() {
    this.messagesContainer.innerHTML = '';
    this.addWelcomeMessage();

    for (const msg of this.messages) {
      if (msg.role === 'user') {
        this.renderUserMessage(msg.text, msg.time);
      } else {
        this.renderBotMessage(msg.data, msg.time);
      }
    }
    this.scrollToBottom();
  }

  submitMessage() {
    const text = this.input.value.trim();
    if (!text || this.isTyping) return;

    this.input.value = '';
    this.sendBtn.disabled = true;
    this.autoResizeInput();
    this.sendUserPrompt(text);
  }

  sendUserPrompt(text) {
    if (this.isTyping) return;

    const time = this.formatTime();
    this.renderUserMessage(text, time);
    this.messages.push({ role: 'user', text, time });
    this.saveHistory();
    this.scrollToBottom();

    // Show typing indicator
    this.showTyping();

    // Natural assistant thinking cadence
    const delay = Math.min(650, Math.max(280, text.length * 10));
    setTimeout(() => {
      this.hideTyping();
      const responseData = findBestAnswer(text);
      const botTime = this.formatTime();
      this.renderBotMessage(responseData, botTime);
      this.messages.push({ role: 'bot', data: responseData, time: botTime });
      this.saveHistory();
      this.scrollToBottom();
    }, delay);
  }

  renderUserMessage(text, time) {
    const el = document.createElement('div');
    el.className = 'bm-chat-msg bm-chat-msg--user';
    el.innerHTML = `
      <div class="bm-chat-msg__bubble">
        <p>${this.escapeHtml(text)}</p>
      </div>
      <span class="bm-chat-msg__meta">${time}</span>
    `;
    this.messagesContainer.appendChild(el);
  }

  renderBotMessage(data, time) {
    const el = document.createElement('div');
    el.className = 'bm-chat-msg bm-chat-msg--bot';

    // 1. Compact Micro-Thumbnail Pill Badge (34px tailored)
    let thumbHtml = '';
    if (data.image) {
      thumbHtml = `
        <div class="bm-chat-thumb-badge">
          <img src="${this.escapeHtml(this.resolveAssetPath(data.image))}" alt="${this.escapeHtml(data.title || 'Mineral Specimen')}" class="bm-chat-thumb-badge__img" width="34" height="34" loading="lazy" decoding="async">
          <div class="bm-chat-thumb-badge__meta">
            <strong class="bm-chat-thumb-badge__title">${this.escapeHtml(data.title)}</strong>
            <span class="bm-chat-thumb-badge__sub">${this.escapeHtml(data.badge || '')}${data.origin ? ` • ${this.escapeHtml(data.origin)}` : ''}</span>
          </div>
        </div>
      `;
    }

    // 2. Concise Body Content
    const contentHtml = this.formatMarkdown(data.text);

    // 2.1 Interactive Product Mini-Cards Grid (When products are shown)
    let productGridHtml = '';
    if (data.productsList && data.productsList.length > 0) {
      productGridHtml = `
        <div class="bm-chat-product-grid">
          ${data.productsList.map(p => `
            <button type="button" class="bm-chat-product-card" data-query="${this.escapeHtml(p.query || p.name)}" title="View ${this.escapeHtml(p.name)} specifications">
              <img src="${this.escapeHtml(this.resolveAssetPath(p.image))}" alt="${this.escapeHtml(p.name)}" class="bm-chat-product-card__img" width="32" height="32" loading="lazy" decoding="async">
              <div class="bm-chat-product-card__meta">
                <strong class="bm-chat-product-card__name">${this.escapeHtml(p.name)}</strong>
                <span class="bm-chat-product-card__grade">${this.escapeHtml(p.grade)}</span>
              </div>
            </button>
          `).join('')}
        </div>
      `;
    }

    // 2.2 Direct Navigation Link to Page / Section (Requested by User)
    let sourceLinkHtml = '';
    if (data.url) {
      const linkLabel = data.urlLabel || `View ${data.title || 'Official'} Specifications & Section →`;
      sourceLinkHtml = `
        <div class="bm-chat-source">
          <a href="${this.escapeHtml(this.resolveAssetPath(data.url))}" class="bm-chat-source__link" title="Navigate directly to page / section">
            <svg class="bm-chat-source__icon" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
              <polyline points="15 3 21 3 21 9"></polyline>
              <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
            <span class="bm-chat-source__text">${this.escapeHtml(linkLabel)}</span>
          </a>
        </div>
      `;
    }

    // 3. Redesigned, High-End RFQ Card
    let rfqHtml = '';
    if (data.showRfqCard) {
      const defaultMineral = data.rfqData?.mineralName || data.title || 'Export Minerals';
      const waUrl = buildWhatsAppRfqUrl({ mineral: defaultMineral, quantity: '1,000 - 5,000 MT', destinationPort: 'CIF Target Port' });
      const emailUrl = buildEmailRfqUrl({ mineral: defaultMineral, quantity: '1,000 - 5,000 MT', destinationPort: 'CIF Target Port' });

      rfqHtml = `
        <div class="bm-chat-rfq-card">
          <div class="bm-chat-rfq-card__header">
            <div class="bm-chat-rfq-card__title-group">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
              </svg>
              <span class="bm-chat-rfq-card__title">Direct Quotation Request</span>
            </div>
            <span class="bm-chat-rfq-card__badge">${this.escapeHtml(defaultMineral.split(' ')[0])}</span>
          </div>

          <div class="bm-chat-rfq-card__grid">
            <div class="bm-chat-rfq-card__field">
              <label class="bm-chat-rfq-card__label">Quantity (MT)</label>
              <input type="text" class="bm-chat-rfq-card__input bm-rfq-qty" value="1,000 - 5,000 MT" placeholder="Tonnage required">
            </div>
            <div class="bm-chat-rfq-card__field">
              <label class="bm-chat-rfq-card__label">Destination Port</label>
              <input type="text" class="bm-chat-rfq-card__input bm-rfq-port" placeholder="e.g. Tianjin, Houston, Jebel Ali">
            </div>
          </div>

          <div class="bm-chat-rfq-card__actions">
            <a href="${waUrl}" target="_blank" rel="noopener noreferrer" class="bm-chat-rfq-btn bm-chat-rfq-btn--wa">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="#25D366" aria-hidden="true">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662c1.746.953 3.71 1.456 5.71 1.457h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
              </svg>
              <span>WhatsApp</span>
            </a>
            <a href="${emailUrl}" class="bm-chat-rfq-btn bm-chat-rfq-btn--email">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#D4AF37" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
              </svg>
              <span>Email Desk</span>
            </a>
          </div>
        </div>
      `;
    }

    // 4. Interactive Action Chips
    let chipsHtml = '';
    if (data.chips && data.chips.length > 0) {
      chipsHtml = `
        <div class="bm-chat-chips">
          ${data.chips.map(chip => {
            const thumb = this.getChipThumbnail(chip);
            if (thumb) {
              return `<button type="button" class="bm-chat-chip bm-chat-chip--thumb" data-query="${this.escapeHtml(chip)}"><img src="${this.escapeHtml(thumb)}" alt="" class="bm-chat-chip__img" width="14" height="14" loading="lazy"><span>${this.escapeHtml(chip)}</span></button>`;
            }
            return `<button type="button" class="bm-chat-chip" data-query="${this.escapeHtml(chip)}"><span>${this.escapeHtml(chip)}</span></button>`;
          }).join('')}
        </div>
      `;
    }

    el.innerHTML = `
      <div class="bm-chat-msg__bubble">
        ${thumbHtml}
        ${contentHtml}
        ${productGridHtml}
        ${sourceLinkHtml}
        ${rfqHtml}
        ${chipsHtml}
      </div>
      <span class="bm-chat-msg__meta">${time} • Trade Specialist</span>
    `;

    // Hook up dynamic inputs in the RFQ card
    if (data.showRfqCard) {
      const defaultMineral = data.rfqData?.mineralName || data.title || 'Export Minerals';
      const qtyInput = el.querySelector('.bm-rfq-qty');
      const portInput = el.querySelector('.bm-rfq-port');
      const waBtn = el.querySelector('.bm-chat-rfq-btn--wa');
      const emailBtn = el.querySelector('.bm-chat-rfq-btn--email');

      const updateLinks = () => {
        const qty = qtyInput.value.trim() || 'Custom Order';
        const port = portInput.value.trim() || 'FOB Karachi / CIF';
        waBtn.href = buildWhatsAppRfqUrl({ mineral: defaultMineral, quantity: qty, destinationPort: port });
        emailBtn.href = buildEmailRfqUrl({ mineral: defaultMineral, quantity: qty, destinationPort: port });
      };

      qtyInput?.addEventListener('input', updateLinks);
      portInput?.addEventListener('input', updateLinks);
    }

    this.messagesContainer.appendChild(el);
  }

  showTyping() {
    this.isTyping = true;
    if (this.typingElement) return;

    this.typingElement = document.createElement('div');
    this.typingElement.className = 'bm-chat-typing';
    this.typingElement.innerHTML = `
      <div class="bm-chat-typing__dot"></div>
      <div class="bm-chat-typing__dot"></div>
      <div class="bm-chat-typing__dot"></div>
    `;
    this.messagesContainer.appendChild(this.typingElement);
    this.scrollToBottom();
  }

  hideTyping() {
    this.isTyping = false;
    if (this.typingElement) {
      this.typingElement.remove();
      this.typingElement = null;
    }
  }

  appendHtml(html) {
    const temp = document.createElement('div');
    temp.innerHTML = html;
    while (temp.firstChild) {
      this.messagesContainer.appendChild(temp.firstChild);
    }
    this.scrollToBottom();
  }

  scrollToBottom() {
    requestAnimationFrame(() => {
      this.body.scrollTop = this.body.scrollHeight;
    });
  }

  getChipThumbnail(chipText) {
    if (!chipText) return null;
    const lower = chipText.toLowerCase();
    let img = null;
    if (lower.includes('chromite') || lower.includes('chrome')) img = 'images/chromite-new-480.avif';
    else if (lower.includes('barite') || lower.includes('baryte')) img = 'images/barite-card-480.avif';
    else if (lower.includes('copper') || lower.includes('chagai')) img = 'images/copper-new-480.avif';
    else if (lower.includes('iron')) img = 'images/iron-ore-new-480.avif';
    else if (lower.includes('fluorite') || lower.includes('fluorspar')) img = 'images/fluorite-480.avif';
    else if (lower.includes('marble') || lower.includes('stone')) img = 'images/silver-steam-white-marble-1-480.avif';
    else if (lower.includes('antimony')) img = 'images/antimony-480.avif';
    else if (lower.includes('gypsum')) img = 'images/gypsum-480.avif';
    else if (lower.includes('coal')) img = 'images/coal-480.avif';
    return img ? this.resolveAssetPath(img) : null;
  }

  formatTime() {
    const d = new Date();
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  escapeHtml(str) {
    if (!str) return '';
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  formatMarkdown(markdown) {
    if (!markdown) return '';
    let html = this.escapeHtml(markdown);

    // Bold
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

    // Italic
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');

    // Bullet lists
    html = html.replace(/(?:^• (.*$)\n?)+/gim, (match) => {
      const items = match
        .trim()
        .split('\n')
        .map(line => `<li>${line.replace(/^• /, '')}</li>`)
        .join('');
      return `<ul>${items}</ul>`;
    });

    // Links [Text](URL)
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="bm-chat-doc-link">$1</a>');

    // Line breaks to paragraphs
    const paragraphs = html
      .split(/\n{2,}/)
      .filter(p => p.trim())
      .map(p => {
        if (p.startsWith('<ul>')) return p;
        return `<p>${p.replace(/\n/g, '<br>')}</p>`;
      });

    return paragraphs.join('');
  }
}

// Global bootstrap instance
let chatbotInstance = null;

export function initChatbot() {
  if (chatbotInstance) return chatbotInstance;
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      chatbotInstance = new BMChatbot();
    });
  } else {
    chatbotInstance = new BMChatbot();
  }
  return chatbotInstance;
}

// Auto-boot if loaded in browser
if (typeof window !== 'undefined') {
  window.BMChatbot = { init: initChatbot };
}
