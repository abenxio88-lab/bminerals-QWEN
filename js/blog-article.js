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
    const mainEl = document.querySelector('main') || document.querySelector('.article-shell');
    if (!mainEl) return;

    if (document.getElementById('articleQaSection')) return;

    const qaSection = document.createElement('section');
    qaSection.id = 'articleQaSection';
    qaSection.className = 'article-qa-section';
    qaSection.innerHTML = `
      <div class="article-qa-shell">
        <div class="article-qa-header">
          <div class="article-qa-eyebrow">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            Technical Q&A & Sourcing Discussion
          </div>
          <h2 class="article-qa-title">Questions & Verified Sourcing Guidance</h2>
          <p class="article-qa-subtitle">Ask questions regarding mineral specifications, port logistics, minimum order quantities (MOQ), and laboratory assays directly to our technical desk.</p>
        </div>

        <div id="articleQaStream" class="article-qa-stream">
          <div style="font-size:0.82rem; color:#94A3B8; text-align:center; padding:1.5rem;">Loading technical discussions...</div>
        </div>

        <div class="article-qa-form-wrap">
          <h3 class="article-qa-form-title">Ask a Technical or Sourcing Question</h3>
          <p class="article-qa-form-intro">Your inquiry is routed directly to the Balochistan Minerals Commercial Export Desk. Questions are answered by email and published upon review.</p>

          <form id="articleQaForm">
            <input type="hidden" name="article_slug" value="${escapeQaHtml(slug)}">
            <div style="display:none;"><input type="text" name="botcheck" tabindex="-1" autocomplete="off"></div>

            <div class="article-qa-grid">
              <div class="article-qa-field">
                <label>Your Full Name *</label>
                <input type="text" name="author_name" class="article-qa-input" placeholder="e.g. Alexander Richter" required>
              </div>
              <div class="article-qa-field">
                <label>Corporate Email *</label>
                <input type="email" name="author_email" class="article-qa-input" placeholder="e.g. procurement@company.com" required>
              </div>
              <div class="article-qa-field">
                <label>Company Name</label>
                <input type="text" name="author_company" class="article-qa-input" placeholder="e.g. Euro-Alloy Smelters">
              </div>
              <div class="article-qa-field">
                <label>Country</label>
                <input type="text" name="author_country" class="article-qa-input" placeholder="e.g. Germany, China, UAE">
              </div>
            </div>

            <div class="article-qa-field" style="margin-bottom:1rem;">
              <label>Your Question or Sourcing Requirement *</label>
              <textarea name="comment_text" class="article-qa-textarea" rows="3" placeholder="Inquire about chemical grades, FOB Karachi rates, sampling procedures, or packing requirements..." required></textarea>
            </div>

            <button type="submit" id="articleQaSubmitBtn" class="article-qa-submit-btn">
              <span>Submit Question to Export Desk</span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            </button>
            <div id="articleQaFeedback"></div>
          </form>
        </div>
      </div>
    `;

    mainEl.appendChild(qaSection);

    // Fetch existing Q&A
    const streamEl = document.getElementById('articleQaStream');
    try {
      const res = await fetch(`../api/blog-discussion.php?slug=${encodeURIComponent(slug)}`);
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
        streamEl.innerHTML = `<div style="font-size:0.84rem; color:#64748B; text-align:center; padding:1.2rem; background:#F8FAFC; border-radius:6px; border:1px dashed #CBD5E1;">No questions published for this article yet. Be the first international trader or researcher to ask below!</div>`;
      }
    } catch(err) {
      streamEl.innerHTML = '';
    }

    // Form submit listener
    const form = document.getElementById('articleQaForm');
    const feedback = document.getElementById('articleQaFeedback');
    const submitBtn = document.getElementById('articleQaSubmitBtn');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Transmitting to Desk...';
      feedback.innerHTML = '';

      const formData = new FormData(form);
      try {
        const resp = await fetch('../api/blog-discussion.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
          feedback.innerHTML = `<div class="article-qa-alert article-qa-alert-success">✓ ${res.message}</div>`;
          form.reset();
        } else {
          feedback.innerHTML = `<div class="article-qa-alert article-qa-alert-danger">${res.error || 'Submission failed'}</div>`;
        }
      } catch(err) {
        feedback.innerHTML = `<div class="article-qa-alert article-qa-alert-danger">Connection error. Please connect directly via WhatsApp: +92 334 8888104</div>`;
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Submit Question to Export Desk</span> <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
      }
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initArticleDiscussions);
  } else {
    initArticleDiscussions();
  }
})();
