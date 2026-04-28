/**
 * Balochistan Minerals - Intelligence Hub Logic
 * Handles ESG bar animations and interactive hub components.
 */

export function initIntelligenceHub() {
  // 1. ESG ProgressBar Animation on Reveal
  const esgSection = document.querySelector('.esg-dashboard');
  if (esgSection) {
    const bars = esgSection.querySelectorAll('.esg-card__bar-fill');
    
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          // The CSS transition handles the smooth fill
          // We just need to ensure the width is set
          bars.forEach(bar => {
            const width = bar.getAttribute('data-width');
            if (width) bar.style.width = width;
          });
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.2 });

    observer.observe(esgSection);
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

  tourModal.querySelector('.tour-modal__media').style.backgroundImage = `url("${image}")`;
  tourModal.querySelector('.tour-modal__eyebrow').textContent = badge;
  tourModal.querySelector('.tour-modal__title').textContent = name;
  tourModal.querySelector('[data-tour-modal-description]').textContent = description;
  tourModal.querySelector('[data-tour-modal-location]').textContent = location;
  tourModal.querySelector('[data-tour-modal-focus]').textContent = focus;
  tourModal.querySelector('[data-tour-modal-duration]').textContent = duration;

  tourModal.classList.add('is-open');
  tourModal.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
  tourModal.querySelector('.tour-modal__close').focus();
}

function createTourModal() {
  const modal = document.createElement('div');
  modal.className = 'tour-modal';
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('aria-hidden', 'true');
  modal.innerHTML = `
    <div class="tour-modal__backdrop" data-tour-close></div>
    <div class="tour-modal__dialog" role="document">
      <button class="tour-modal__x" type="button" data-tour-close aria-label="Close tour preview">&times;</button>
      <div class="tour-modal__media">
        <div class="tour-modal__play" aria-hidden="true">></div>
      </div>
      <div class="tour-modal__body">
        <div class="tour-modal__eyebrow"></div>
        <h3 class="tour-modal__title"></h3>
        <p class="tour-modal__note" data-tour-modal-description></p>
        <div class="tour-modal__meta">
          <div class="tour-modal__meta-item">
            <span class="tour-modal__meta-label">Location</span>
            <span class="tour-modal__meta-value" data-tour-modal-location></span>
          </div>
          <div class="tour-modal__meta-item">
            <span class="tour-modal__meta-label">Preview Covers</span>
            <span class="tour-modal__meta-value" data-tour-modal-focus></span>
          </div>
          <div class="tour-modal__meta-item">
            <span class="tour-modal__meta-label">Runtime</span>
            <span class="tour-modal__meta-value" data-tour-modal-duration></span>
          </div>
        </div>
        <p class="tour-modal__note">Full site walkthroughs are shared with qualified buyers during technical due diligence. This preview shows the tour scope without overstating live video availability.</p>
        <div class="tour-modal__actions">
          <a class="tour-modal__link" href="contact.html">Request full access</a>
          <button class="tour-modal__close" type="button" data-tour-close>Close preview</button>
        </div>
      </div>
    </div>
  `;

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
