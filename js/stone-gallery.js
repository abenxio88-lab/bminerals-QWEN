import { createImageModalGuard } from './image-modal-guard.js?v=20260713';

(() => {
  let modal = null;
  let activeItems = [];
  let activeIndex = 0;
  const lightboxGuard = createImageModalGuard('is-stone-lightbox-open');

  const getImageSource = (img) => img.currentSrc || img.getAttribute('src') || img.src;
  const clearAccidentalMediaSelection = () => {
    const selection = window.getSelection?.();
    if (selection && selection.rangeCount) selection.removeAllRanges();
  };

  const createModal = () => {
    const element = document.createElement('div');
    element.className = 'stone-lightbox';
    element.setAttribute('role', 'dialog');
    element.setAttribute('aria-modal', 'true');
    element.setAttribute('aria-hidden', 'true');
    element.innerHTML = `
      <div class="stone-lightbox__backdrop" data-stone-lightbox-close></div>
      <button class="stone-lightbox__close" type="button" aria-label="Close image viewer" data-stone-lightbox-close>&times;</button>
      <div class="stone-lightbox__dialog" role="document">
        <button class="stone-lightbox__arrow stone-lightbox__arrow--prev" type="button" aria-label="Previous image" data-stone-lightbox-prev></button>
        <img class="stone-lightbox__image" src="" alt="" data-stone-lightbox-image>
        <button class="stone-lightbox__arrow stone-lightbox__arrow--next" type="button" aria-label="Next image" data-stone-lightbox-next></button>
        <div class="stone-lightbox__footer">
          <div>
            <span data-stone-lightbox-kicker>Stone Gallery</span>
            <h3 data-stone-lightbox-title></h3>
          </div>
          <p data-stone-lightbox-count></p>
        </div>
      </div>
    `;

    element.addEventListener('click', (event) => {
      if (event.target.matches('[data-stone-lightbox-close]')) {
        event.preventDefault();
        closeModal();
      }
      if (event.target.closest('[data-stone-lightbox-prev]')) showModalImage(activeIndex - 1);
      if (event.target.closest('[data-stone-lightbox-next]')) showModalImage(activeIndex + 1);
    });

    element.querySelector('.stone-lightbox__backdrop').addEventListener('pointerup', (event) => {
      event.preventDefault();
      window.requestAnimationFrame(closeModal);
    });

    element.querySelector('.stone-lightbox__close').addEventListener('pointerup', (event) => {
      if (event.pointerType === 'mouse') return;
      event.preventDefault();
      window.requestAnimationFrame(closeModal);
    });

    element.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') closeModal();
      if (event.key === 'ArrowLeft') showModalImage(activeIndex - 1);
      if (event.key === 'ArrowRight') showModalImage(activeIndex + 1);
    });

    document.body.appendChild(element);
    return element;
  };

  const showModalImage = (index) => {
    if (!modal || !activeItems.length) return;

    activeIndex = (index + activeItems.length) % activeItems.length;

    const item = activeItems[activeIndex];
    const image = modal.querySelector('[data-stone-lightbox-image]');
    const count = modal.querySelector('[data-stone-lightbox-count]');
    const hasMultiple = activeItems.length > 1;

    image.src = item.src;
    image.alt = item.alt;
    count.textContent = `${activeIndex + 1} / ${activeItems.length}`;
    modal.querySelector('[data-stone-lightbox-prev]').hidden = !hasMultiple;
    modal.querySelector('[data-stone-lightbox-next]').hidden = !hasMultiple;
  };

  const openModal = ({ title, items, index, trigger, restoreFocus = false }) => {
    if (!items.length || lightboxGuard.isOpen()) return;

    modal = modal || createModal();
    activeItems = items;
    activeIndex = index;

    modal.querySelector('[data-stone-lightbox-title]').textContent = title || 'Stone gallery';
    showModalImage(activeIndex);

    lightboxGuard.open({
      modal,
      trigger,
      restoreFocus,
      closeButton: modal.querySelector('.stone-lightbox__close'),
    });
  };

  const closeModal = () => {
    if (!modal?.classList.contains('is-open')) return;
    lightboxGuard.close({ modal });
  };

  document.querySelectorAll('[data-stone-gallery]').forEach((gallery) => {
    const slides = Array.from(gallery.querySelectorAll('.stone-gallery__slide'));
    const dots = Array.from(gallery.querySelectorAll('.stone-gallery__dots span'));
    const previous = gallery.querySelector('[data-gallery-prev]');
    const next = gallery.querySelector('[data-gallery-next]');
    const record = gallery.closest('.detail-record');
    const title = record?.querySelector('h3')?.textContent?.trim() || gallery.getAttribute('aria-label') || 'Stone gallery';
    const items = slides
      .map((slide) => slide.querySelector('img'))
      .filter(Boolean)
      .map((img) => ({
        src: getImageSource(img),
        alt: img.alt || title
      }));

    if (!slides.length || !items.length) return;

    let activeSlide = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));

    slides.forEach((slide, index) => {
      const img = slide.querySelector('img');
      if (!img) return;

      img.decoding = 'async';
      slide.setAttribute('role', 'button');
      slide.setAttribute('tabindex', index === activeSlide ? '0' : '-1');
      slide.setAttribute('aria-label', `Open ${title} image ${index + 1}`);

      const preloader = new Image();
      preloader.decoding = 'async';
      preloader.src = items[index].src;

      slide.addEventListener('pointerdown', clearAccidentalMediaSelection, { passive: true });
      slide.addEventListener('touchend', clearAccidentalMediaSelection, { passive: true });
      slide.addEventListener('click', (event) => openModal({
        title,
        items,
        index,
        trigger: slide,
        restoreFocus: event.detail === 0,
      }));
      slide.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        openModal({ title, items, index, trigger: slide, restoreFocus: true });
      });
    });

    const showSlide = (index) => {
      activeSlide = (index + slides.length) % slides.length;

      slides.forEach((slide, slideIndex) => {
        slide.classList.toggle('is-active', slideIndex === activeSlide);
        slide.setAttribute('tabindex', slideIndex === activeSlide ? '0' : '-1');
      });

      dots.forEach((dot, dotIndex) => {
        dot.classList.toggle('is-active', dotIndex === activeSlide);
      });
    };

    if (slides.length > 1 && previous && next) {
      previous.addEventListener('click', (event) => {
        event.stopPropagation();
        showSlide(activeSlide - 1);
      });

      next.addEventListener('click', (event) => {
        event.stopPropagation();
        showSlide(activeSlide + 1);
      });
    }
  });

})();
