(() => {
  const progress = document.querySelector(".article-progress span");
  const article = document.querySelector(".article-content");
  const copyButton = document.querySelector("[data-copy-link]");
  const shareLinks = document.querySelectorAll("[data-share-service]");
  const pageUrl = window.location.href;
  const pageTitle = document.querySelector(".article-title")?.textContent?.trim() || document.title;

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
    if (service === "whatsapp") return `https://api.whatsapp.com/send?text=${title}%20${url}`;
    return pageUrl;
  };

  updateProgress();
  window.addEventListener("scroll", updateProgress, { passive: true });
  window.addEventListener("resize", updateProgress);

  shareLinks.forEach((link) => {
    link.href = shareUrlFor(link.dataset.shareService);
  });

  if (copyButton) {
    copyButton.addEventListener("click", async () => {
      try {
        await navigator.clipboard.writeText(pageUrl);
        copyButton.classList.add("is-copied");
        setTimeout(() => {
          copyButton.classList.remove("is-copied");
        }, 1500);
      } catch (error) {
        copyButton.classList.add("is-copy-failed");
        setTimeout(() => {
          copyButton.classList.remove("is-copy-failed");
        }, 1500);
      }
    });
  }
})();
