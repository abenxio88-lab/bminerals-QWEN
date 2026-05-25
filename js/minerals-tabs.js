const categoryButtons = document.querySelectorAll('[data-mineral-category]');
const categoryPanels = document.querySelectorAll('[data-mineral-panel]');
const mineralCards = document.querySelectorAll('#minerals .mineral-showcase');

let mediaModal = null;
let lastMediaTrigger = null;

const reportLinksBySpecHref = {
  'product-metallic.html#copper': 'images/pdf-reports/copper-ore.png',
  'product-metallic.html#antimony': 'images/pdf-reports/anitmony-SGS-report.pdf',
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

function createMediaButton(card) {
  const button = document.createElement('button');
  const title = card.querySelector('.mineral-showcase__title')?.textContent?.trim() || 'Mineral media';
  const image = card.querySelector('.mineral-showcase__image img')?.getAttribute('src') || '';

  button.type = 'button';
  button.className = 'mineral-showcase__cta mineral-showcase__cta--secondary';
  button.innerHTML = `<span>Media Gallery</span>${actionIcons.media}`;
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
    const reportHref = reportLinksBySpecHref[specHref];
    const reportLink = createActionLink(
      'Test Reports',
      reportHref || `contact.html?subject=${encodeURIComponent(`${title} test reports`)}`
    );

    if (reportHref) {
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
