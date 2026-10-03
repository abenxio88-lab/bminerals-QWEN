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

    this.init();
  }

  init() {
    this.ensureStyles();
    this.loadHistory();
    this.render();
    this.bindEvents();

    if (this.messages.length === 0) {
      this.addWelcomeMessage();
    } else {
      this.restoreMessages();
    }
  }

  ensureStyles() {
    if (document.getElementById(STYLESHEET_ID)) return;
    const link = document.createElement('link');
    link.id = STYLESHEET_ID;
    link.rel = 'stylesheet';
    link.href = 'css/components/chatbot.css';
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
          <span class="bm-chat-launcher__pill-dot"></span>
          <span>Ask AI • Mineral Desk</span>
        </div>
        <div class="bm-chat-launcher__btn">
          <svg class="bm-chat-launcher__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 3h12l4 6-10 12L2 9z"></path>
            <path d="M11 3v6l-4 3"></path>
            <path d="M13 3v6l4 3"></path>
          </svg>
          <span class="bm-chat-launcher__badge"></span>
        </div>
      </button>

      <!-- Chat Modal Window -->
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
            <button type="button" class="bm-chat-btn-icon bm-chat-btn-reset" title="Restart conversation" aria-label="Reset conversation">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 12a9 9 0 0 1 15-6.7L21 8M21 3v5h-5M21 12a9 9 0 0 1-15 6.7L3 16M3 21v-5h5"></path>
              </svg>
            </button>
            <button type="button" class="bm-chat-btn-icon bm-chat-btn-minimize" title="Minimize" aria-label="Minimize chat">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="5" y1="12" x2="19" y2="12"></line>
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
            <textarea class="bm-chat-input" rows="1" placeholder="Inquire about barite, chromite, copper, ports..." aria-label="Message"></textarea>
            <button type="submit" class="bm-chat-send" aria-label="Send message" disabled>
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13"></line>
                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
              </svg>
            </button>
          </form>
          <div class="bm-chat-disclaimer">
            Verified Assay Specifications • Port of Karachi & Qasim Export Support
          </div>
        </div>

      </div>
    `;

    document.body.appendChild(root);

    this.container = root;
    this.launcher = root.querySelector('.bm-chat-launcher');
    this.window = root.querySelector('.bm-chat-window');
    this.body = root.querySelector('.bm-chat-body');
    this.messagesContainer = root.querySelector('.bm-chat-messages-container');
    this.input = root.querySelector('.bm-chat-input');
    this.sendBtn = root.querySelector('.bm-chat-send');
    this.form = root.querySelector('.bm-chat-form');
  }

  bindEvents() {
    this.launcher.addEventListener('click', () => this.toggleOpen());

    const closeBtn = this.container.querySelector('.bm-chat-btn-close');
    closeBtn.addEventListener('click', () => this.close());

    const minimizeBtn = this.container.querySelector('.bm-chat-btn-minimize');
    minimizeBtn.addEventListener('click', () => this.toggleMinimize());

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

    // Delegate chip clicks inside chat body
    this.body.addEventListener('click', (e) => {
      const chip = e.target.closest('.bm-chat-chip');
      if (chip) {
        const text = chip.getAttribute('data-query') || chip.textContent.trim();
        this.sendUserPrompt(text);
      }
    });
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
    document.body.classList.add('bm-chat-open');
    this.scrollToBottom();

    if (window.tactileFeedback) {
      window.tactileFeedback('light');
    }

    setTimeout(() => {
      this.input.focus();
    }, 180);
  }

  close() {
    this.isOpen = false;
    document.body.classList.remove('bm-chat-open');
    this.launcher.focus();
  }

  toggleMinimize() {
    this.isMinimized = !this.isMinimized;
    this.window.classList.toggle('bm-chat-minimized', this.isMinimized);
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
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
              <path d="m9 12 2 2 4-4"></path>
            </svg>
          </div>
          <div>
            <h3 class="bm-chat-welcome__title">Balochistan Minerals Sourcing</h3>
            <span class="bm-chat-welcome__subtitle">Direct Export & Technical Intelligence</span>
          </div>
        </div>
        <p class="bm-chat-welcome__text">
          Access verified lot assays, mine-to-port logistics schedules, and direct commercial quotations for Pakistan minerals.
        </p>
        <div class="bm-chat-chips">
          <button type="button" class="bm-chat-chip bm-chat-chip--accent" data-query="Barite 4.2+ SG">Barite 4.2+ SG</button>
          <button type="button" class="bm-chat-chip" data-query="Chromite 42-52%">Chromite 42-52%</button>
          <button type="button" class="bm-chat-chip" data-query="Chagai Copper">Chagai Copper</button>
          <button type="button" class="bm-chat-chip" data-query="Logistics & Ports">Logistics & Ports</button>
          <button type="button" class="bm-chat-chip" data-query="Request Quote">Request Quote (RFQ)</button>
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

    // 1. Compact Micro-Thumbnail Pill Badge
    let thumbHtml = '';
    if (data.image) {
      thumbHtml = `
        <div class="bm-chat-thumb-badge">
          <img src="${this.escapeHtml(data.image)}" alt="${this.escapeHtml(data.title || 'Mineral Specimen')}" class="bm-chat-thumb-badge__img" width="44" height="44" loading="lazy" decoding="async">
          <div class="bm-chat-thumb-badge__meta">
            <strong class="bm-chat-thumb-badge__title">${this.escapeHtml(data.title)}</strong>
            <span class="bm-chat-thumb-badge__sub">${this.escapeHtml(data.badge || '')}${data.origin ? ` • ${this.escapeHtml(data.origin)}` : ''}</span>
          </div>
        </div>
      `;
    }

    // 2. Concise Body Content
    const contentHtml = this.formatMarkdown(data.text);

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
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
            <a href="${waUrl}" target="_blank" rel="noopener noreferrer" class="bm-chat-rfq-btn bm-chat-rfq-btn--wa" style="color: #ffffff !important; text-decoration: none !important;">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662c1.746.953 3.71 1.456 5.71 1.457h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
              </svg>
              <span>WhatsApp RFQ</span>
            </a>
            <a href="${emailUrl}" class="bm-chat-rfq-btn bm-chat-rfq-btn--email" style="color: #ffffff !important; text-decoration: none !important;">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
          ${data.chips.map(chip => `<button type="button" class="bm-chat-chip" data-query="${this.escapeHtml(chip)}">${this.escapeHtml(chip)}</button>`).join('')}
        </div>
      `;
    }

    el.innerHTML = `
      <div class="bm-chat-msg__bubble">
        ${thumbHtml}
        ${contentHtml}
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
