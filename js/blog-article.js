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
})();
