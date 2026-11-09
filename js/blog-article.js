(() => {
  const progress = document.querySelector(".article-progress span");
  const article = document.querySelector(".article-content");
  const copyButtons = document.querySelectorAll("[data-copy-link]");
  const shareLinks = document.querySelectorAll("[data-share-service]");
  const pageUrl = window.location.href;
  const pageTitle = document.querySelector(".article-title")?.textContent?.trim() || document.title;
  let copyStatus;
  let copyStatusTimer;

  const updateProgress = () => {
    if (!progress || !article) return;
    const rect = article.getBoundingClientRect();
    const distance = article.offsetHeight - window.innerHeight;
    const read = Math.min(Math.max((window.innerHeight - rect.top) / Math.max(distance, 1), 0), 1);
    progress.style.transform = `scaleX(${read})`;
  };

  const shareUrlFor = (service) => {
    const url = encodeURIComponent(pageUrl);
    const title = encodeURIComponent(pageTitle);

    if (service === "facebook") return `https://www.facebook.com/sharer/sharer.php?u=${url}`;
    if (service === "x") return `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
    if (service === "linkedin") return `https://www.linkedin.com/shareArticle?mini=true&url=${url}&title=${title}`;
    if (service === "whatsapp") return `https://wa.me/?text=${title}%20${url}`;
    return pageUrl;
  };

  const getCopyUrl = () => {
    const canonicalUrl = document.querySelector('link[rel="canonical"]')?.href;
    if (canonicalUrl) return canonicalUrl;

    const url = new URL(window.location.href);
    url.hash = "";
    return url.href;
  };

  const copyWithFallback = (text) => {
    const textarea = document.createElement("textarea");
    textarea.value = text;
    textarea.readOnly = true;
    textarea.setAttribute("aria-hidden", "true");
    textarea.style.position = "fixed";
    textarea.style.top = "-9999px";
    textarea.style.left = "-9999px";
    textarea.style.opacity = "0";
    document.body.appendChild(textarea);
    textarea.select();
    textarea.setSelectionRange(0, textarea.value.length);

    let copied = false;
    try {
      copied = document.execCommand("copy");
    } catch (error) {
      copied = false;
    } finally {
      textarea.remove();
    }

    return copied;
  };

  const copyText = async (text) => {
    if (navigator.clipboard && window.isSecureContext) {
      try {
        await navigator.clipboard.writeText(text);
        return true;
      } catch (error) {
        return copyWithFallback(text);
      }
    }

    return copyWithFallback(text);
  };

  const getCopyStatus = () => {
    if (copyStatus) return copyStatus;

    copyStatus = document.createElement("span");
    copyStatus.setAttribute("role", "status");
    copyStatus.setAttribute("aria-live", "polite");
    copyStatus.style.position = "fixed";
    copyStatus.style.zIndex = "9999";
    copyStatus.style.pointerEvents = "none";
    copyStatus.style.opacity = "0";
    copyStatus.style.transform = "translate(-50%, -0.75rem)";
    copyStatus.style.transition = "opacity 140ms ease";
    copyStatus.style.padding = "0.35rem 0.55rem";
    copyStatus.style.borderRadius = "999px";
    copyStatus.style.background = "rgba(12, 31, 27, 0.92)";
    copyStatus.style.color = "#fff";
    copyStatus.style.font = "600 0.75rem/1.2 system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
    copyStatus.style.whiteSpace = "nowrap";
    document.body.appendChild(copyStatus);

    return copyStatus;
  };

  const showCopyStatus = (button, message) => {
    const status = getCopyStatus();
    const rect = button.getBoundingClientRect();
    status.textContent = message;
    status.style.left = `${rect.left + rect.width / 2}px`;
    status.style.top = `${Math.max(rect.top - 8, 8)}px`;
    status.style.opacity = "1";
    button.setAttribute("aria-label", message);

    window.clearTimeout(copyStatusTimer);
    copyStatusTimer = window.setTimeout(() => {
      status.style.opacity = "0";
      status.textContent = "";
      button.setAttribute("aria-label", button.dataset.copyOriginalLabel || "Copy link");
    }, 2000);
  };

  updateProgress();
  window.addEventListener("scroll", updateProgress, { passive: true });
  window.addEventListener("resize", updateProgress);

  shareLinks.forEach((link) => {
    link.href = shareUrlFor(link.dataset.shareService);
  });

  copyButtons.forEach((copyButton) => {
    copyButton.dataset.copyOriginalLabel = copyButton.getAttribute("aria-label") || "Copy link";

    copyButton.addEventListener("click", async (event) => {
      event.preventDefault();

      const copied = await copyText(getCopyUrl());
      showCopyStatus(copyButton, copied ? "Link copied" : "Copy failed");
    });
  });

  // ----------------------------------------------------
  // DYNAMIC TECHNICAL Q&A & SOURCING DISCUSSION ENGINE
  // Connects blog readers directly to the Trade Desk
  // ----------------------------------------------------
  const escapeQaHtml = (text) => {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
  };

  const initArticleDiscussions = async () => {
    const rawPath = window.location.pathname;
    const slug = rawPath.split('/').pop().replace(/\.html$/, '') || 'general';
    const targetEl = document.querySelector('article.article-content') 
      || document.querySelector('.article-content') 
      || document.querySelector('article')
      || document.querySelector('.article-main')
      || document.querySelector('main');
    if (!targetEl) return;

    if (document.getElementById('articleQaSection')) return;

    const qaSection = document.createElement('section');
    qaSection.id = 'articleQaSection';
    qaSection.className = 'article-qa-section';
    qaSection.innerHTML = `
      <div class="article-qa-header">
        <div class="article-qa-eyebrow">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
          Direct Export Desk Discussion
        </div>
        <h3 class="article-qa-title">Questions &amp; Reader Reviews</h3>
        <p class="article-qa-subtitle">Ask questions about assays, FOB logistics, or leave your review on this analysis. Direct replies are dispatched via email.</p>
      </div>

      <div id="articleQaStream" class="article-qa-stream">
        <div style="font-size:0.80rem; color:#94A3B8; text-align:center; padding:0.75rem;">Loading discussions...</div>
      </div>

      <div class="article-qa-form-wrap">
        <h4 class="article-qa-form-title">Leave a Question or Review</h4>

        <form id="articleQaForm">
          <input type="hidden" name="article_slug" value="${escapeQaHtml(slug)}">
          <div style="display:none !important;" aria-hidden="true">
            <input type="text" name="botcheck" tabindex="-1" autocomplete="off">
            <input type="text" name="bot_verification_field" tabindex="-1" autocomplete="off">
            <input type="hidden" name="form_time" id="articleQaFormTime" value="${Date.now()}">
          </div>

          <div class="article-qa-grid">
            <div class="article-qa-field">
              <label>Your Full Name *</label>
              <input type="text" name="author_name" class="article-qa-input" placeholder="e.g. Alexander Richter" required maxlength="100">
            </div>
            <div class="article-qa-field">
              <label>Corporate Email <span style="font-size:0.65rem; color:#94A3B8; text-transform:none; font-weight:normal;">(Optional - for direct email reply)</span></label>
              <input type="email" name="author_email" class="article-qa-input" placeholder="e.g. procurement@company.com" maxlength="150">
            </div>
            <div class="article-qa-field">
              <label>Company <span style="font-size:0.65rem; color:#94A3B8; text-transform:none; font-weight:normal;">(Optional)</span></label>
              <input type="text" name="author_company" class="article-qa-input" placeholder="e.g. Euro-Alloy Smelters" maxlength="100">
            </div>
            <div class="article-qa-field">
              <label>Country <span style="font-size:0.65rem; color:#94A3B8; text-transform:none; font-weight:normal;">(Optional)</span></label>
              <input type="text" name="author_country" class="article-qa-input" placeholder="e.g. Germany, China, UAE" maxlength="80">
            </div>
          </div>

          <div class="article-qa-field" style="margin-bottom:0.75rem;">
            <label>Your Question or Review *</label>
            <textarea name="comment_text" class="article-qa-textarea" rows="3" placeholder="Inquire about chemical grades, FOB Karachi rates, sampling procedures, or share your technical review..." required maxlength="2500"></textarea>
          </div>

          <div class="article-qa-submit-wrap">
            <button type="submit" id="articleQaSubmitBtn" class="article-qa-submit-btn">
              <span>Submit to Export Desk</span>
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            </button>
          </div>
          <div id="articleQaFeedback"></div>
        </form>
      </div>
    `;

    targetEl.appendChild(qaSection);

    // Fetch existing Q&A
    const streamEl = document.getElementById('articleQaStream');
    try {
      const res = await fetch(`/api/blog-discussion.php?slug=${encodeURIComponent(slug)}`);
      const data = await res.json();
      if (data.success && data.discussions && data.discussions.length) {
        streamEl.innerHTML = data.discussions.map(d => `
          <div class="article-qa-card">
            <div class="article-qa-meta">
              <div>
                <span class="article-qa-author">${escapeQaHtml(d.author_name)}</span>
                ${d.author_company ? `<span class="article-qa-author-sub">&bull; ${escapeQaHtml(d.author_company)} (${escapeQaHtml(d.author_country)})</span>` : ''}
              </div>
              <span class="article-qa-date">${d.created_at ? d.created_at.split(' ')[0] : ''}</span>
            </div>
            <p class="article-qa-question">${escapeQaHtml(d.comment_text)}</p>
            ${d.reply_text ? `
              <div class="article-qa-reply">
                <div class="article-qa-reply-header">
                  <span class="article-qa-reply-badge">Verified Response</span>
                  <span>${escapeQaHtml(d.replied_by || 'Balochistan Minerals Desk')}</span>
                </div>
                <p class="article-qa-reply-text">${escapeQaHtml(d.reply_text)}</p>
              </div>
            ` : ''}
          </div>
        `).join('');
      } else {
        streamEl.innerHTML = `<div style="font-size:0.80rem; color:#64748B; text-align:center; padding:0.9rem; background:#F8FAFC; border-radius:6px; border:1px dashed #CBD5E1;">No questions published for this article yet. Be the first to ask below!</div>`;
      }
    } catch(err) {
      streamEl.innerHTML = '';
    }

    // Form submit listener with anti-spam & double-click protection
    const form = document.getElementById('articleQaForm');
    const feedback = document.getElementById('articleQaFeedback');
    const submitBtn = document.getElementById('articleQaSubmitBtn');
    let isSubmitting = false;
    let cooldownTimer = null;

    const startCooldown = (seconds) => {
      let remaining = seconds;
      submitBtn.disabled = true;
      submitBtn.classList.add('is-cooldown');
      
      if (cooldownTimer) clearInterval(cooldownTimer);
      submitBtn.innerHTML = `<span>Submitted (Wait ${remaining}s)</span>`;
      
      cooldownTimer = setInterval(() => {
        remaining -= 1;
        if (remaining <= 0) {
          clearInterval(cooldownTimer);
          submitBtn.disabled = false;
          submitBtn.classList.remove('is-cooldown');
          submitBtn.innerHTML = '<span>Submit to Export Desk</span> <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
        } else {
          submitBtn.innerHTML = `<span>Submitted (Wait ${remaining}s)</span>`;
        }
      }, 1000);
    };

    // Check if previous cooldown is still active
    const savedCooldown = parseInt(localStorage.getItem('bm_qa_cooldown') || '0', 10);
    const now = Date.now();
    if (savedCooldown > now) {
      startCooldown(Math.ceil((savedCooldown - now) / 1000));
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (isSubmitting) return;

      // Rate limit check
      const currentCooldown = parseInt(localStorage.getItem('bm_qa_cooldown') || '0', 10);
      if (currentCooldown > Date.now()) {
        const left = Math.ceil((currentCooldown - Date.now()) / 1000);
        feedback.innerHTML = `<div class="article-qa-alert article-qa-alert-danger">Please wait ${left} seconds before submitting another question.</div>`;
        return;
      }

      isSubmitting = true;
      submitBtn.disabled = true;
      submitBtn.innerHTML = `
        <svg class="article-qa-spinner" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-linecap="round"></circle>
        </svg>
        <span>Transmitting to Export Desk...</span>
      `;
      feedback.innerHTML = '';

      const formData = new FormData(form);
      try {
        const resp = await fetch('/api/blog-discussion.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
          feedback.innerHTML = `<div class="article-qa-alert article-qa-alert-success">✓ Question or review submitted to Balochistan Minerals Trade Desk. Our technical team will review and publish guidance.</div>`;
          form.reset();
          localStorage.setItem('bm_qa_cooldown', Date.now() + 30000);
          startCooldown(30);
        } else {
          feedback.innerHTML = `<div class="article-qa-alert article-qa-alert-danger">${escapeQaHtml(res.error || 'Submission failed')}</div>`;
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<span>Submit to Export Desk</span> <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
        }
      } catch(err) {
        feedback.innerHTML = `<div class="article-qa-alert article-qa-alert-danger">Connection error. Please connect directly via WhatsApp: +92 334 8888104</div>`;
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Submit to Export Desk</span> <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
      } finally {
        isSubmitting = false;
      }
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initArticleDiscussions);
  } else {
    initArticleDiscussions();
  }
})();
