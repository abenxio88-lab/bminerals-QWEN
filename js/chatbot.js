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
      // Save last 25 messages to keep session clean
      const trimmed = this.messages.slice(-25);
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
          <span>Ask AI • Mineral Sourcing</span>
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
              <span class="bm-chat-header__avatar-dot"></span>
            </div>
            <div class="bm-chat-header__meta">
              <h2 class="bm-chat-header__title">
                Balochistan Minerals AI
                <span class="bm-chat-header__title-tag">B2B</span>
              </h2>
              <span class="bm-chat-header__status">
                <span class="bm-chat-header__status-pulse"></span>
                Assay & Logistics Verified
              </span>
            </div>
          </div>
          <div class="bm-chat-header__actions">
            <button type="button" class="bm-chat-btn-icon bm-chat-btn-reset" title="Restart conversation" aria-label="Reset conversation">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>
              </svg>
            </button>
            <button type="button" class="bm-chat-btn-icon bm-chat-btn-minimize" title="Minimize" aria-label="Minimize chat">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"></line>
              </svg>
            </button>
            <button type="button" class="bm-chat-btn-icon bm-chat-btn-close" title="Close" aria-label="Close chat">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
            <textarea class="bm-chat-input" rows="1" placeholder="Ask about mineral specs, mines, logistics..." aria-label="Message"></textarea>
            <button type="submit" class="bm-chat-send" aria-label="Send message" disabled>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13"></line>
                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
              </svg>
            </button>
          </form>
          <div class="bm-chat-disclaimer">
            Official Balochistan Minerals (Pvt) Ltd B2B Desk • Certified Assay Support
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
    this.input.style.height = `${Math.min(this.input.scrollHeight, 90)}px`;
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
    }, 200);
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
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
            </svg>
          </div>
          <h3 class="bm-chat-welcome__title">Welcome to Balochistan Minerals</h3>
        </div>
        <p class="bm-chat-welcome__text">
          Sourcing export-ready mineral commodities from Pakistan? I can provide verified assay specifications, mine origin details, and commercial quotations.
        </p>
        <div class="bm-chat-chips">
          <button type="button" class="bm-chat-chip bm-chat-chip--accent" data-query="Barite 4.2+ SG Specs">⚡ Barite 4.2+ SG</button>
          <button type="button" class="bm-chat-chip" data-query="Chromite Ore Grades">⛏️ Chromite 42-52%</button>
          <button type="button" class="bm-chat-chip" data-query="Chagai Copper Ore">🔬 Chagai Copper Ore</button>
          <button type="button" class="bm-chat-chip" data-query="Mine-to-Port Logistics">🚢 Logistics & Ports</button>
          <button type="button" class="bm-chat-chip" data-query="Request a Quote">📋 Request RFQ</button>
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

    // Natural assistant thinking delay
    const delay = Math.min(800, Math.max(350, text.length * 15));
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

    let contentHtml = this.formatMarkdown(data.text);

    // Optional RFQ Card
    let rfqHtml = '';
    if (data.showRfqCard) {
      const defaultMineral = data.rfqData?.mineralName || 'Export Minerals';
      const waUrl = buildWhatsAppRfqUrl({ mineral: defaultMineral, quantity: '5,000 MT', destinationPort: 'CIF Target Port' });
      const emailUrl = buildEmailRfqUrl({ mineral: defaultMineral, quantity: '5,000 MT', destinationPort: 'CIF Target Port' });

      rfqHtml = `
        <div class="bm-chat-rfq-card">
          <div class="bm-chat-rfq-card__header">
            <h4 class="bm-chat-rfq-card__title">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
              </svg>
              Fast Commercial RFQ
            </h4>
            <span class="bm-chat-rfq-card__badge">${this.escapeHtml(defaultMineral.split(' ')[0])}</span>
          </div>
          <div class="bm-chat-rfq-card__grid">
            <div class="bm-chat-rfq-card__field">
              <label class="bm-chat-rfq-card__label">Quantity (MT)</label>
              <input type="text" class="bm-chat-rfq-card__input bm-rfq-qty" value="1,000 - 5,000 MT">
            </div>
            <div class="bm-chat-rfq-card__field">
              <label class="bm-chat-rfq-card__label">Destination Port</label>
              <input type="text" class="bm-chat-rfq-card__input bm-rfq-port" placeholder="e.g. Tianjin, Houston">
            </div>
          </div>
          <div class="bm-chat-rfq-card__actions">
            <a href="${waUrl}" target="_blank" rel="noopener noreferrer" class="bm-chat-rfq-card__btn bm-chat-rfq-card__btn--wa">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2zm.01 1.67c2.2 0 4.26.86 5.82 2.42a8.225 8.225 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.196 8.196 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24zm4.52 11.66c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.02-1.25-.75-.67-1.26-1.5-1.41-1.75-.14-.25-.02-.39.11-.51.11-.11.25-.29.37-.44.13-.14.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.56-1.34-.76-1.84-.2-.49-.4-.42-.56-.43h-.47c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.03 2.61.13.17 1.77 2.7 4.29 3.79.6.26 1.07.41 1.44.53.6.19 1.15.16 1.58.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.18-.47-.3z"/></svg>
              WhatsApp RFQ
            </a>
            <a href="${emailUrl}" class="bm-chat-rfq-card__btn bm-chat-rfq-card__btn--email">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
              Email Desk
            </a>
          </div>
        </div>
      `;
    }

    // Interactive Action Chips
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
        ${contentHtml}
        ${rfqHtml}
        ${chipsHtml}
      </div>
      <span class="bm-chat-msg__meta">${time} • AI Specialist</span>
    `;

    // Hook up dynamic inputs in the newly rendered RFQ card
    if (data.showRfqCard) {
      const defaultMineral = data.rfqData?.mineralName || 'Export Minerals';
      const qtyInput = el.querySelector('.bm-rfq-qty');
      const portInput = el.querySelector('.bm-rfq-port');
      const waBtn = el.querySelector('.bm-chat-rfq-card__btn--wa');
      const emailBtn = el.querySelector('.bm-chat-rfq-card__btn--email');

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

    // Headers
    html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');

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
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

    // Line breaks to paragraphs
    const paragraphs = html
      .split(/\n{2,}/)
      .filter(p => p.trim())
      .map(p => {
        if (p.startsWith('<h3>') || p.startsWith('<ul>')) return p;
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
