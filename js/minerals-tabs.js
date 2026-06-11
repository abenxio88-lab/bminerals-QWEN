const categoryButtons = document.querySelectorAll('[data-mineral-category]');
const categoryPanels = document.querySelectorAll('[data-mineral-panel]');
const mineralCards = document.querySelectorAll('#minerals .mineral-showcase');

let mediaModal = null;
let reportModal = null;
let lastMediaTrigger = null;
let lastReportTrigger = null;

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

function createMediaButton(card) {
  const button = createActionButton('Media Gallery', 'media');
  const title = card.querySelector('.mineral-showcase__title')?.textContent?.trim() || 'Mineral media';
  const image = card.querySelector('.mineral-showcase__image img')?.getAttribute('src') || '';

  button.addEventListener('click', () => openMediaModal({ title, image }));

  return button;
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

    const actions = document.createElement('div');
    actions.className = 'mineral-showcase__actions';

    specLink.replaceWith(actions);
    actions.append(
      specLink,
      reportLink,
      createActionLink('Shipping Route', 'logistics.html', 'route'),
      createMediaButton(card)
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
      <button class="mineral-media-modal__close" type="button" aria-label="Close media gallery" data-media-close>x</button>
      <img class="mineral-media-modal__image" src="" alt="" data-media-image>
      <div class="mineral-media-modal__body">
        <span>Media Gallery</span>
        <h3 data-media-title></h3>
        <p>Photos, videos and inspection media can be added here for instant buyer preview.</p>
      </div>
    </div>
  `;

  modal.addEventListener('click', (event) => {
    if (event.target.matches('[data-media-close]')) closeMediaModal();
  });

  modal.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeMediaModal();
  });

  document.body.appendChild(modal);
  return modal;
}

function openMediaModal({ title, image }) {
  if (!mediaModal) mediaModal = createMediaModal();

  lastMediaTrigger = document.activeElement;

  const imageElement = mediaModal.querySelector('[data-media-image]');
  imageElement.src = image;
  imageElement.alt = `${title} media preview`;
  mediaModal.querySelector('[data-media-title]').textContent = title;

  mediaModal.classList.add('is-open');
  mediaModal.setAttribute('aria-hidden', 'false');
  mediaModal.querySelector('[data-media-close]')?.focus();
}

function closeMediaModal() {
  if (!mediaModal) return;

  mediaModal.classList.remove('is-open');
  mediaModal.setAttribute('aria-hidden', 'true');
  lastMediaTrigger?.focus();
}

enhanceMineralCards();
