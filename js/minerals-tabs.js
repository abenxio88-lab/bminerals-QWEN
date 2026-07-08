const categoryButtons = document.querySelectorAll('[data-mineral-category]');
const categoryPanels = document.querySelectorAll('[data-mineral-panel]');
const mineralCards = document.querySelectorAll('#minerals .mineral-showcase');

let mediaModal = null;
let reportModal = null;
let lastMediaTrigger = null;
let lastReportTrigger = null;
let activeMediaItems = [];
let activeMediaIndex = 0;
let mediaModalScrollY = 0;
let mediaModalScrollLocked = false;
let previousBodyScrollStyles = null;

const additionalMediaBySpecHref = {
  'product-metallic.html#copper': [
    {
      type: 'video',
      src: 'images/mineral-vids/copper-lump-mine-video-1.mp4',
      title: 'Copper Ore at the Mine',
      description: 'Field footage showing copper-bearing material at the Balochistan mine site.',
    },
    {
      type: 'video',
      src: 'images/mineral-vids/copper-lump-mine-video-2.mp4',
      title: 'Copper Lumps — Field View',
      description: 'A closer operational view of copper ore lumps before sampling and dispatch preparation.',
    },
  ],
  'product-metallic.html#chromite': [
    {
      type: 'image',
      src: 'images/chrome-concentrate.avif',
      title: 'Chrome Concentrate',
      description: 'Upgraded chromite concentrate prepared for consistent Cr2O3 feed.',
    },
  ],
  'product-metallic.html#iron-ore': [
    {
      type: 'image',
      src: 'images/iron-concentrate.avif',
      title: 'Iron Concentrate',
      description: 'Beneficiated iron concentrate prepared for stronger Fe content and cleaner sizing.',
    },
  ],
  'product-metallic.html#antimony': [
    {
      type: 'image',
      src: 'images/antimony-concentrate.avif',
      title: 'Antimony Concentrate',
      description: 'Processed antimony concentrate prepared for specialty metal and alloy buyers.',
    },
  ],
  'product-stones.html#white-marble': [
    {
      type: 'video',
      src: 'images/mineral-vids/white-marble-mining-site-1.mp4',
      title: 'Bright White Marble — Mining Site 1',
      description: 'An overview of bright white marble extraction at the mining site.',
    },
    {
      type: 'video',
      src: 'images/mineral-vids/white-marble-mining-site-2.mp4',
      title: 'Bright White Marble — Mining Site 2',
      description: 'Field footage showing marble faces and material selection during mining.',
    },
    {
      type: 'video',
      src: 'images/mineral-vids/white-marble-mining-site-3.mp4',
      title: 'Bright White Marble — Mining Site 3',
      description: 'A closer view of bright white marble blocks and active site conditions.',
    },
  ],
  'product-energy.html#sorange-degari': [
    {
      type: 'video',
      src: 'images/mineral-vids/quetta-coal-active-mine-1.mp4',
      title: 'Quetta Coal — Active Mine 1',
      description: 'An operational overview of active coal mining in the Quetta coalfield.',
    },
    {
      type: 'video',
      src: 'images/mineral-vids/quetta-coal-active-mine-2.mp4',
      title: 'Quetta Coal — Active Mine 2',
      description: 'Field footage documenting coal extraction and current mine conditions.',
    },
    {
      type: 'video',
      src: 'images/mineral-vids/quetta-coal-active-mine-3.mp4',
      title: 'Quetta Coal — Active Mine 3',
      description: 'A closer operational view of coal material at the active mine site.',
    },
    {
      type: 'video',
      src: 'images/mineral-vids/quetta-coal-active-mine-4.mp4',
      title: 'Quetta Coal — Active Mine 4',
      description: 'Additional site footage showing the Quetta coal mining operation.',
    },
  ],
};

const reportLinksBySpecHref = {
  'product-metallic.html#copper': [
    {
      title: 'Copper Ore Test Report',
      href: 'images/pdf-reports/copper-ore.pdf',
    },
  ],
  'product-metallic.html#chromite': [
    {
      title: 'Chrome Ore Test Report',
      href: 'images/pdf-reports/chrome-ore/sgs-doc-2026-06-09-wa0052.pdf.pdf',
    },
  ],
  'product-metallic.html#iron-ore': [
    {
      title: 'Iron Ore Test Report',
      href: 'images/pdf-reports/iron-ore/sgs-ironore-wa0051.pdf.pdf',
    },
  ],
  'product-metallic.html#antimony': [
    {
      title: 'Antimony Test Report',
      href: 'images/pdf-reports/antimony/antimony_sgs-report_combined.pdf',
    },
  ],
  'product-industrial.html#bauxite': [
    {
      title: 'Bauxite Test Report: Baluchistan Enterprises',
      href: 'images/pdf-reports/bauxite/baluchistan-enterprises-20260609-wa0053-2026.pdf.pdf',
    },
    {
      title: 'Bauxite Test Report: MS Al Azan Pak China',
      href: 'images/pdf-reports/bauxite/ms-al-azan-pak-china-sgs.pdf.pdf',
    },
    {
      title: 'Bauxite Test Report: SEP SGS',
      href: 'images/pdf-reports/bauxite/sep-sgs-bauxite.pdf.pdf',
    },
  ],
  'product-industrial.html#phosphate-rock': [
    {
      title: 'Phosphate Rock Test Report',
      href: 'images/pdf-reports/phosphate-rock/sgs-rock-phosphate-doc-wa0050.pdf.pdf',
    },
  ],
  'product-industrial.html#fluorite': [
    {
      title: 'Fluorite Test Report',
      href: 'images/pdf-reports/flourite/flourite-report.pdf.pdf',
    },
  ],
};

const actionIcons = {
  report: '<svg class="mineral-showcase__action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 3h7l4 4v14H7V3Z"/><path d="M14 3v5h5"/><path d="M10 12h5"/><path d="M10 16h7"/></svg>',
  route: '<svg class="mineral-showcase__action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 18c3.5-6 8.5 0 12-6"/><path d="M6 18h.01"/><path d="M18 12h.01"/><path d="M8 6h8"/><path d="m14 4 2 2-2 2"/></svg>',
  media: '<svg class="mineral-showcase__action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="5" width="16" height="14" rx="1"/><path d="m8 15 2.5-3 2 2.5 1.5-1.8L18 17"/><path d="M8 9h.01"/></svg>',
};

function showMineralCategory(category) {
  categoryButtons.forEach((button) => {
    const isActive = button.dataset.mineralCategory === category;
    button.classList.toggle('is-active', isActive);
    button.setAttribute('aria-selected', String(isActive));
  });

  categoryPanels.forEach((panel) => {
    const isActive = panel.dataset.mineralPanel === category;
    panel.classList.toggle('is-active', isActive);
    panel.hidden = !isActive;
  });
}

categoryButtons.forEach((button) => {
  button.addEventListener('click', () => {
    showMineralCategory(button.dataset.mineralCategory);
  });
});

function createActionLink(label, href, icon = 'report') {
  const link = document.createElement('a');
  link.href = href;
  link.className = 'mineral-showcase__cta mineral-showcase__cta--secondary';
  link.innerHTML = `<span>${label}</span>${actionIcons[icon] || actionIcons.report}`;
  return link;
}

function createActionButton(label, icon = 'report') {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'mineral-showcase__cta mineral-showcase__cta--secondary';
  button.innerHTML = `<span>${label}</span>${actionIcons[icon] || actionIcons.report}`;
  return button;
}

function createMediaButton(card, href) {
  const button = createActionButton('Media Gallery', 'media');
  const title = card.querySelector('.mineral-showcase__title')?.textContent?.trim() || 'Mineral media';
  const image = card.querySelector('.mineral-showcase__image img')?.getAttribute('src') || '';
  const description = card.querySelector('.mineral-showcase__description')?.textContent?.trim() || '';
  const items = [
    {
      type: 'image',
      src: image,
      title,
      description,
    },
    ...(additionalMediaBySpecHref[href] || []),
  ].filter((item) => item.src);

  button.addEventListener('click', () => openMediaModal({ title, href, items }));

  return button;
}

function enableImageViewer(card, href) {
  const imageWrap = card.querySelector('.mineral-showcase__image');
  const image = imageWrap?.querySelector('img');
  if (!imageWrap || !image || imageWrap.dataset.viewerReady === 'true') return;

  imageWrap.dataset.viewerReady = 'true';
  imageWrap.setAttribute('role', 'button');
  imageWrap.setAttribute('tabindex', '0');

  const title = card.querySelector('.mineral-showcase__title')?.textContent?.trim() || image.alt || 'Mineral image';
  const description = card.querySelector('.mineral-showcase__description')?.textContent?.trim() || '';
  imageWrap.setAttribute('aria-label', `Open ${title} image`);

  const openViewer = () => {
    openMediaModal({
      title,
      href,
      imageOnly: true,
      items: [{
        type: 'image',
        src: image.getAttribute('src'),
        title,
        description,
      }],
    });
  };

  imageWrap.addEventListener('click', (event) => {
    if (event.target.closest('button, a')) return;
    openViewer();
  });

  imageWrap.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    event.preventDefault();
    openViewer();
  });
}

function enhanceMineralCards() {
  mineralCards.forEach((card) => {
    if (card.querySelector('.mineral-showcase__actions')) return;

    const specLink = card.querySelector('.mineral-showcase__cta');
    if (!specLink) return;

    const title = card.querySelector('.mineral-showcase__title')?.textContent?.trim() || 'Mineral';
    const specHref = specLink.getAttribute('href');
    const reports = reportLinksBySpecHref[specHref] || [];
    const reportLink = reports.length > 1
      ? createReportButton('3 Test Reports', 'Bauxite', reports)
      : createActionLink(
        'Test Reports',
        reports[0]?.href || `contact.html?subject=${encodeURIComponent(`${title} test reports`)}`
      );

    if (reports.length === 1) {
      reportLink.target = '_blank';
      reportLink.rel = 'noopener';
    }

    enableImageViewer(card, specHref);

    const actions = document.createElement('div');
    actions.className = 'mineral-showcase__actions';

    specLink.replaceWith(actions);
    actions.append(
      specLink,
      reportLink,
      createActionLink('Shipping Route', 'logistics.html', 'route'),
      createMediaButton(card, specHref)
    );
  });
}

function createReportButton(label, title, reports) {
  const button = createActionButton(label, 'report');
  button.addEventListener('click', () => openReportModal({ title, reports }));
  return button;
}

function createReportModal() {
  const modal = document.createElement('div');
  modal.className = 'mineral-report-modal';
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('aria-hidden', 'true');
  modal.innerHTML = `
    <div class="mineral-report-modal__backdrop" data-report-close></div>
    <div class="mineral-report-modal__dialog" role="document">
      <button class="mineral-report-modal__close" type="button" aria-label="Close test reports" data-report-close>x</button>
      <div class="mineral-report-modal__header">
        <span>Test Reports</span>
        <h3 data-report-title></h3>
        <p>Choose a Bauxite lab report to open in a new browser tab.</p>
      </div>
      <div class="mineral-report-modal__list" data-report-list></div>
    </div>
  `;

  modal.addEventListener('click', (event) => {
    if (event.target.matches('[data-report-close]')) closeReportModal();
  });

  modal.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeReportModal();
  });

  document.body.appendChild(modal);
  return modal;
}

function openReportModal({ title, reports }) {
  if (!reportModal) reportModal = createReportModal();

  lastReportTrigger = document.activeElement;

  reportModal.querySelector('[data-report-title]').textContent = title;

  const reportList = reportModal.querySelector('[data-report-list]');
  reportList.replaceChildren(...reports.map((report, index) => {
    const link = document.createElement('a');
    link.className = 'mineral-report-modal__item';
    link.href = report.href;
    link.target = '_blank';
    link.rel = 'noopener';
    link.innerHTML = `
      <span class="mineral-report-modal__index">${String(index + 1).padStart(2, '0')}</span>
      <span class="mineral-report-modal__name">${report.title}</span>
      <span class="mineral-report-modal__action">Open PDF</span>
    `;
    return link;
  }));

  reportModal.classList.add('is-open');
  reportModal.setAttribute('aria-hidden', 'false');
  reportModal.querySelector('[data-report-close]')?.focus();
}

function closeReportModal() {
  if (!reportModal) return;

  reportModal.classList.remove('is-open');
  reportModal.setAttribute('aria-hidden', 'true');
  lastReportTrigger?.focus();
}

function createMediaModal() {
  const modal = document.createElement('div');
  modal.className = 'mineral-media-modal';
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('aria-hidden', 'true');
  modal.innerHTML = `
    <div class="mineral-media-modal__backdrop" data-media-close></div>
    <div class="mineral-media-modal__dialog" role="document">
      <button class="mineral-media-modal__close" type="button" aria-label="Close media gallery" data-media-close>&times;</button>
      <div class="mineral-media-modal__stage">
        <img class="mineral-media-modal__media" src="" alt="" data-media-image>
        <video class="mineral-media-modal__media" controls playsinline preload="metadata" hidden data-media-video></video>
        <button class="mineral-media-modal__arrow mineral-media-modal__arrow--prev" type="button"
          aria-label="Previous media" data-media-prev>&larr;</button>
        <button class="mineral-media-modal__arrow mineral-media-modal__arrow--next" type="button"
          aria-label="Next media" data-media-next>&rarr;</button>
      </div>
      <div class="mineral-media-modal__body">
        <div class="mineral-media-modal__meta">
          <span>Media Gallery</span>
          <span data-media-count></span>
        </div>
        <h3 data-media-title></h3>
        <p data-media-description></p>
        <a class="mineral-media-modal__section-link" href="products.html" data-media-section>
          View full mineral section <span aria-hidden="true">&rarr;</span>
        </a>
      </div>
    </div>
  `;

  modal.addEventListener('click', (event) => {
    if (event.target.matches('[data-media-close]')) closeMediaModal();
  });

  modal.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeMediaModal();
    if (event.key === 'ArrowLeft') showMediaItem(activeMediaIndex - 1);
    if (event.key === 'ArrowRight') showMediaItem(activeMediaIndex + 1);
  });
  modal.querySelector('[data-media-prev]').addEventListener('click', () => showMediaItem(activeMediaIndex - 1));
  modal.querySelector('[data-media-next]').addEventListener('click', () => showMediaItem(activeMediaIndex + 1));

  document.body.appendChild(modal);
  return modal;
}

function showMediaItem(index) {
  if (!mediaModal || !activeMediaItems.length) return;

  activeMediaIndex = (index + activeMediaItems.length) % activeMediaItems.length;
  const item = activeMediaItems[activeMediaIndex];
  const imageElement = mediaModal.querySelector('[data-media-image]');
  const videoElement = mediaModal.querySelector('[data-media-video]');
  const isVideo = item.type === 'video';

  videoElement.pause();
  imageElement.hidden = isVideo;
  videoElement.hidden = !isVideo;

  if (isVideo) {
    if (videoElement.getAttribute('src') !== item.src) {
      videoElement.src = item.src;
      videoElement.load();
    }
  } else {
    imageElement.src = item.src;
    imageElement.alt = `${item.title} media preview`;
  }

  mediaModal.querySelector('[data-media-title]').textContent = item.title;
  mediaModal.querySelector('[data-media-description]').textContent = item.description;
  mediaModal.querySelector('[data-media-count]').textContent =
    `${String(activeMediaIndex + 1).padStart(2, '0')} / ${String(activeMediaItems.length).padStart(2, '0')}`;

  const hasMultipleItems = activeMediaItems.length > 1;
  mediaModal.querySelector('[data-media-prev]').hidden = !hasMultipleItems;
  mediaModal.querySelector('[data-media-next]').hidden = !hasMultipleItems;
}

function lockPageScroll() {
  if (mediaModalScrollLocked) return;

  mediaModalScrollY = window.scrollY || document.documentElement.scrollTop || 0;
  previousBodyScrollStyles = {
    position: document.body.style.position,
    top: document.body.style.top,
    left: document.body.style.left,
    right: document.body.style.right,
    width: document.body.style.width,
  };

  window.__bmLenis?.stop?.();
  document.documentElement.classList.add('is-media-modal-open');
  document.body.classList.add('is-media-modal-open');
  document.body.style.position = 'fixed';
  document.body.style.top = `-${mediaModalScrollY}px`;
  document.body.style.left = '0';
  document.body.style.right = '0';
  document.body.style.width = '100%';
  mediaModalScrollLocked = true;
}

function unlockPageScroll() {
  if (!mediaModalScrollLocked) return;

  document.documentElement.classList.remove('is-media-modal-open');
  document.body.classList.remove('is-media-modal-open');

  if (previousBodyScrollStyles) {
    document.body.style.position = previousBodyScrollStyles.position;
    document.body.style.top = previousBodyScrollStyles.top;
    document.body.style.left = previousBodyScrollStyles.left;
    document.body.style.right = previousBodyScrollStyles.right;
    document.body.style.width = previousBodyScrollStyles.width;
  }

  window.scrollTo(0, mediaModalScrollY);
  window.__bmLenis?.start?.();
  previousBodyScrollStyles = null;
  mediaModalScrollLocked = false;
}

function openMediaModal({ title, href, items, imageOnly = false }) {
  if (!mediaModal) mediaModal = createMediaModal();

  lastMediaTrigger = document.activeElement;
  activeMediaItems = items;
  activeMediaIndex = 0;
  mediaModal.classList.toggle('mineral-media-modal--image-viewer', imageOnly);
  mediaModal.querySelector('.mineral-media-modal__meta span:first-child').textContent =
    imageOnly ? 'Product Image' : 'Media Gallery';
  mediaModal.querySelector('[data-media-section]').href = href || 'products.html';
  mediaModal.setAttribute('aria-label', `${title} media gallery`);
  showMediaItem(0);

  mediaModal.classList.add('is-open');
  mediaModal.setAttribute('aria-hidden', 'false');
  lockPageScroll();
  mediaModal.querySelector('[data-media-close]')?.focus();
}

function closeMediaModal() {
  if (!mediaModal) return;

  mediaModal.querySelector('[data-media-video]')?.pause();
  mediaModal.classList.remove('is-open');
  mediaModal.setAttribute('aria-hidden', 'true');
  unlockPageScroll();
  lastMediaTrigger?.focus();
}

enhanceMineralCards();
