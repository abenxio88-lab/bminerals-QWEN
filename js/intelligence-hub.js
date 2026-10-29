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
  const tourCards = document.querySelectorAll('.tour-card, .drone-item');
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

  // 4. Mineral Index carousel
  initMineralCarousel();
}

function initMineralCarousel() {
  if (window.__mineralCarouselInitialized) return;

  const carousel = document.querySelector('[data-mineral-carousel]');
  if (!carousel) return;

  const viewport = carousel.querySelector('[data-mineral-carousel-viewport]');
  const cards = Array.from(carousel.querySelectorAll('.encyclopedia-card'));
  const prevButton = carousel.querySelector('[data-mineral-carousel-prev]');
  const nextButton = carousel.querySelector('[data-mineral-carousel-next]');
  const count = carousel.querySelector('[data-mineral-carousel-count]');
  const rail = carousel.querySelector('[data-mineral-carousel-rail]');

  if (!viewport || !cards.length || !prevButton || !nextButton) return;

  window.__mineralCarouselInitialized = true;
  let activeIndex = 0;
  let scrollFrame = 0;

  const getActiveIndex = () => {
    const viewportLeft = viewport.getBoundingClientRect().left;
    let nearestIndex = 0;
    let nearestDistance = Number.POSITIVE_INFINITY;

    cards.forEach((card, index) => {
      const distance = Math.abs(card.getBoundingClientRect().left - viewportLeft);
      if (distance < nearestDistance) {
        nearestDistance = distance;
        nearestIndex = index;
      }
    });

    return nearestIndex;
  };

  const updateState = () => {
    activeIndex = getActiveIndex();
    const maxScrollLeft = viewport.scrollWidth - viewport.clientWidth - 2;
    const atStart = viewport.scrollLeft <= 2;
    const atEnd = viewport.scrollLeft >= maxScrollLeft;

    prevButton.disabled = atStart;
    nextButton.disabled = atEnd;

    if (count) {
      count.textContent = `${String(activeIndex + 1).padStart(2, '0')} / ${String(cards.length).padStart(2, '0')}`;
    }

    if (rail) {
      const visibleRatio = Math.min(1, viewport.clientWidth / viewport.scrollWidth);
      const scrollableWidth = Math.max(1, viewport.scrollWidth - viewport.clientWidth);
      const progress = viewport.scrollLeft / scrollableWidth;
      const railTrack = rail.parentElement;
      const railTrackWidth = railTrack?.clientWidth || 0;
      const railWidth = Math.max(railTrackWidth * visibleRatio, railTrackWidth * 0.12);
      const railTravel = Math.max(0, railTrackWidth - railWidth);

      rail.style.width = `${railWidth}px`;
      rail.style.transform = `translateX(${progress * railTravel}px)`;
    }
  };

  const goToCard = (index) => {
    const targetIndex = Math.max(0, Math.min(cards.length - 1, index));
    cards[targetIndex].scrollIntoView({
      behavior: 'smooth',
      block: 'nearest',
      inline: 'start'
    });

    if (window.tactileFeedback) window.tactileFeedback('light');
  };

  prevButton.addEventListener('click', () => {
    goToCard(getActiveIndex() - 1);
  });

  nextButton.addEventListener('click', () => {
    goToCard(getActiveIndex() + 1);
  });

  viewport.addEventListener('keydown', (event) => {
    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
    event.preventDefault();
    goToCard(getActiveIndex() + (event.key === 'ArrowRight' ? 1 : -1));
  });

  viewport.addEventListener('scroll', () => {
    if (scrollFrame) return;
    scrollFrame = window.requestAnimationFrame(() => {
      scrollFrame = 0;
      updateState();
    });
  }, { passive: true });

  window.addEventListener('resize', updateState);
  updateState();
}

let activeTourTrigger = null;
let tourModal = null;

function openTourModal(card) {
  if (window.tactileFeedback) window.tactileFeedback('heavy');

  activeTourTrigger = card;
  const name = card.querySelector('.tour-card__name, .drone-item__caption')?.textContent?.trim() || 'Virtual Mine Tour';
  const description = card.querySelector('.tour-card__desc')?.textContent?.trim() || card.dataset.tourFocus || '';
  const badge = 'CONFIDENTIAL B2B DUE DILIGENCE';
  const image = card.dataset.tourImage || card.querySelector('img')?.getAttribute('src') || '';
  const location = card.dataset.tourLocation || 'Balochistan, Pakistan';
  const focus = card.dataset.tourFocus || 'Mine operations, quality controls, and logistics flow';
  const duration = card.dataset.tourDuration || 'Restricted B2B Access';

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

  const waLink = tourModal.querySelector('[data-tour-modal-whatsapp]');
  if (waLink) {
    const waText = encodeURIComponent(`Hello Balochistan Minerals, I am requesting verified due-diligence video footage and site walkthrough access for: ${name} (${location}).`);
    waLink.href = `https://wa.me/923348888104?text=${waText}`;
  }

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
  closeX.setAttribute('aria-label', 'Close preview');
  closeX.textContent = '×';

  const media = document.createElement('div');
  media.className = 'tour-modal__media';

  const play = document.createElement('div');
  play.className = 'tour-modal__play';
  play.setAttribute('aria-hidden', 'true');
  play.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>';
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
    ['Coverage Scope', 'tourModalFocus'],
    ['Access Status', 'tourModalDuration']
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
  note.textContent = 'Full unedited aerial flyovers, 360° extraction pit panoramas, and certified assay logs are released exclusively to qualified commercial off-takers, bulk buyers, and joint-venture partners during technical due diligence.';

  const actions = document.createElement('div');
  actions.className = 'tour-modal__actions';

  const waLink = document.createElement('a');
  waLink.className = 'tour-modal__link tour-modal__link--whatsapp';
  waLink.dataset.tourModalWhatsapp = '';
  waLink.target = '_blank';
  waLink.rel = 'noopener noreferrer';
  waLink.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block;vertical-align:-3px;margin-right:6px"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>Request Access via WhatsApp';

  const requestLink = document.createElement('a');
  requestLink.className = 'tour-modal__link tour-modal__link--rfq';
  requestLink.href = 'contact.html?type=due-diligence';
  requestLink.textContent = 'Submit Sourcing RFQ';

  const closeButton = document.createElement('button');
  closeButton.className = 'tour-modal__close';
  closeButton.type = 'button';
  closeButton.dataset.tourClose = '';
  closeButton.textContent = 'Close Preview';

  actions.append(waLink, requestLink, closeButton);
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
