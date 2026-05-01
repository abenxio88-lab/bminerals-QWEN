/**
 * Balochistan Minerals - Intelligence Hub Logic
 * Handles ESG bar animations and interactive hub components.
 */

export function initIntelligenceHub() {
  if (window.__intelligenceHubInitialized) return;
  window.__intelligenceHubInitialized = true;

  // 1. ESG ProgressBar Animation on Reveal
  const esgSection = document.querySelector('.esg-dashboard');
  if (esgSection) {
    const bars = esgSection.querySelectorAll('.esg-card__bar-fill');
    const fillBars = () => {
      bars.forEach(bar => {
        const width = bar.getAttribute('data-width');
        if (width) bar.style.width = width;
      });
    };

    if (typeof IntersectionObserver === 'undefined') {
      fillBars();
    } else {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            fillBars();
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.2 });

      observer.observe(esgSection);
    }
  }

  // 2. Virtual Mine Tours - Preview Modal
  const tourCards = document.querySelectorAll('.tour-card');
  tourCards.forEach(card => {
    card.addEventListener('click', () => openTourModal(card));
    card.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openTourModal(card);
      }
    });
  });

  // 3. Shipment Tracker - keep the CTA honest while routing to logistics.
  const trackerBtn = document.querySelector('.b2b-card__cta[href="logistics.html"]');
  if (trackerBtn) {
    trackerBtn.addEventListener('click', () => {
      if (window.tactileFeedback) window.tactileFeedback('light');
    });
  }
}

let activeTourTrigger = null;
let tourModal = null;

function openTourModal(card) {
  if (window.tactileFeedback) window.tactileFeedback('heavy');

  activeTourTrigger = card;
  const name = card.querySelector('.tour-card__name')?.textContent?.trim() || 'Virtual Mine Tour';
  const description = card.querySelector('.tour-card__desc')?.textContent?.trim() || '';
  const badge = card.querySelector('.tour-card__badge')?.textContent?.trim() || 'Preview';
  const image = card.dataset.tourImage || '';
  const location = card.dataset.tourLocation || 'Balochistan, Pakistan';
  const focus = card.dataset.tourFocus || 'Mine operations, quality controls, and logistics flow';
  const duration = card.dataset.tourDuration || 'Preview';

  if (!tourModal) {
    tourModal = createTourModal();
    document.body.appendChild(tourModal);
  }

  setModalText('.tour-modal__eyebrow', badge);
  setModalText('.tour-modal__title', name);
  setModalText('[data-tour-modal-description]', description);
  setModalText('[data-tour-modal-location]', location);
  setModalText('[data-tour-modal-focus]', focus);
  setModalText('[data-tour-modal-duration]', duration);

  const media = tourModal.querySelector('.tour-modal__media');
  if (media) media.style.backgroundImage = image ? `url("${image}")` : '';

  tourModal.classList.add('is-open');
  tourModal.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
  tourModal.querySelector('.tour-modal__close')?.focus();
}

function setModalText(selector, value) {
  const element = tourModal?.querySelector(selector);
  if (element) element.textContent = value;
}

function createTourModal() {
  const modal = document.createElement('div');
  modal.className = 'tour-modal';
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('aria-hidden', 'true');

  const backdrop = document.createElement('div');
  backdrop.className = 'tour-modal__backdrop';
  backdrop.dataset.tourClose = '';

  const dialog = document.createElement('div');
  dialog.className = 'tour-modal__dialog';
  dialog.setAttribute('role', 'document');

  const closeX = document.createElement('button');
  closeX.className = 'tour-modal__x';
  closeX.type = 'button';
  closeX.dataset.tourClose = '';
  closeX.setAttribute('aria-label', 'Close tour preview');
  closeX.textContent = 'x';

  const media = document.createElement('div');
  media.className = 'tour-modal__media';

  const play = document.createElement('div');
  play.className = 'tour-modal__play';
  play.setAttribute('aria-hidden', 'true');
  play.textContent = '>';
  media.appendChild(play);

  const body = document.createElement('div');
  body.className = 'tour-modal__body';

  const eyebrow = document.createElement('div');
  eyebrow.className = 'tour-modal__eyebrow';

  const title = document.createElement('h3');
  title.className = 'tour-modal__title';

  const description = document.createElement('p');
  description.className = 'tour-modal__note';
  description.dataset.tourModalDescription = '';

  const meta = document.createElement('div');
  meta.className = 'tour-modal__meta';

  [
    ['Location', 'tourModalLocation'],
    ['Preview Covers', 'tourModalFocus'],
    ['Runtime', 'tourModalDuration']
  ].forEach(([label, dataKey]) => {
    const item = document.createElement('div');
    item.className = 'tour-modal__meta-item';

    const labelEl = document.createElement('span');
    labelEl.className = 'tour-modal__meta-label';
    labelEl.textContent = label;

    const value = document.createElement('span');
    value.className = 'tour-modal__meta-value';
    value.dataset[dataKey] = '';

    item.append(labelEl, value);
    meta.appendChild(item);
  });

  const note = document.createElement('p');
  note.className = 'tour-modal__note';
  note.textContent = 'Full site walkthroughs are shared with qualified buyers during technical due diligence. This preview shows the tour scope without overstating live video availability.';

  const actions = document.createElement('div');
  actions.className = 'tour-modal__actions';

  const requestLink = document.createElement('a');
  requestLink.className = 'tour-modal__link';
  requestLink.href = 'contact.html';
  requestLink.textContent = 'Request full access';

  const closeButton = document.createElement('button');
  closeButton.className = 'tour-modal__close';
  closeButton.type = 'button';
  closeButton.dataset.tourClose = '';
  closeButton.textContent = 'Close preview';

  actions.append(requestLink, closeButton);
  body.append(eyebrow, title, description, meta, note, actions);
  dialog.append(closeX, media, body);
  modal.append(backdrop, dialog);

  modal.addEventListener('click', (event) => {
    if (event.target.matches('[data-tour-close]')) closeTourModal();
  });

  modal.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeTourModal();
  });

  return modal;
}

function closeTourModal() {
  if (!tourModal) return;
  tourModal.classList.remove('is-open');
  tourModal.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
  activeTourTrigger?.focus();
  activeTourTrigger = null;
}
