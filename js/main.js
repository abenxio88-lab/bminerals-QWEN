import { initNavbar } from './navbar.js';
import { initScrollReveal } from './scroll-reveal.js';
import { initDropdownMenus } from './dropdown.js';
import { initHeroSlider } from './hero-slider.js?v=hero-ready-20260709';
import { initBorderBeam } from './border-beam.js';
import { initTactileFeedback } from './tactile-feedback.js';
import { initEarthTechCore } from './earth-tech-core.js';
import { initSearchConsole } from './search-console.js';
import { createImageModalGuard } from './image-modal-guard.js?v=20260713';

function isLocalDebug() {
  return ['localhost', '127.0.0.1'].includes(window.location.hostname) || window.location.protocol === 'file:';
}

function reportInitError(featureName, error) {
  window.__bmHillInitErrors = window.__bmHillInitErrors || [];
  window.__bmHillInitErrors.push({ featureName, error });

  if (isLocalDebug()) {
    console.error(`${featureName} init error:`, error);
  }
}

function runInit(featureName, initializer) {
  try {
    initializer();
  } catch (error) {
    reportInitError(featureName, error);
  }
}

function runWhenIdle(callback) {
  if (typeof window.requestIdleCallback === 'function') {
    window.requestIdleCallback(callback, { timeout: 2500 });
    return;
  }

  window.setTimeout(callback, 1200);
}

function preloadImagesNow(views) {
  views.forEach((view) => {
    if (!view || !view.image) return;

    const preloader = new Image();
    preloader.decoding = 'async';
    preloader.src = view.image;
  });
}

function preloadImages(views) {
  runWhenIdle(() => preloadImagesNow(views));
}

function preloadImageUrlsNow(urls) {
  urls.forEach((url) => {
    if (!url) return;

    const preloader = new Image();
    preloader.decoding = 'async';
    preloader.src = url;
  });
}

function preloadImageUrls(urls) {
  runWhenIdle(() => preloadImageUrlsNow(urls));
}

const productImageSets = {
  copper: [
    { image: 'images/copper-new.webp', alt: 'Copper ore from Chaghi', title: 'Copper Lumps' }
  ],
  chromite: [
    { image: 'images/chromite-new.webp', alt: 'Chromite lumps from Muslim Bagh mining operations in Balochistan', title: 'Chrome Lumps' },
    { image: 'images/chrome-concentrate.avif', alt: 'Chrome concentrate sample', title: 'Chrome Concentrate' }
  ],
  'iron-ore': [
    { image: 'images/iron-ore-new.webp', alt: 'Iron ore lumps from Balochistan mining sites', title: 'Iron Ore Lumps' },
    { image: 'images/iron-concentrate.avif', alt: 'Iron ore concentrate sample', title: 'Iron Concentrate' }
  ],
  antimony: [
    { image: 'images/antimony.avif', alt: 'Antimony lumps sample', title: 'Antimony Lumps' },
    { image: 'images/antimony-concentrate.avif', alt: 'Antimony concentrate sample', title: 'Antimony Concentrate' }
  ],
  barite: [
    { image: 'images/barite-card.jpg', alt: 'Barite specimen', title: 'Drilling and Industrial Barite' }
  ],
  fluorite: [
    { image: 'images/fluorite.avif', alt: 'Fluorite mineral', title: 'Fluorite / Fluorspar' }
  ],
  gypsum: [
    { image: 'images/gypsum.avif', alt: 'Gypsum crystal', title: 'Gypsum' }
  ],
  magnesite: [
    { image: 'images/magnesite.avif', alt: 'Magnesite rock', title: 'Magnesite' }
  ],
  'phosphate-rock': [
    { image: 'images/phosphate-rock.webp', alt: 'Phosphate rock mineral sample', title: 'Phosphate Rock' }
  ],
  bauxite: [
    { image: 'images/bauxite.avif', alt: 'Bauxite mineral', title: 'Bauxite' }
  ],
  'sorange-degari': [
    { image: 'images/coal-sorange.avif', alt: 'Sorange-Degari coal sample', title: 'Sorange-Degari' }
  ],
  'mach-anjira': [
    { image: 'images/Mach-Anjira-coal4.avif', alt: 'Mach-Anjira coal sample', title: 'Mach-Anjira' }
  ],
  marble: [
    { image: 'images/marble.avif', alt: 'Marble quarry', title: 'Commercial and Premium Marble' }
  ]
};

function swapGalleryImage({ image, imageWrap, content, nextButton, view, updateContent }) {
  const setPreview = () => {
    const src = image.currentSrc || image.getAttribute('src') || image.src;
    if (src) imageWrap.style.setProperty('--mineral-preview', `url("${String(src).replace(/"/g, '\\"')}")`);
  };

  const loadNextImage = () => {
    const nextImage = new Image();
    nextImage.decoding = 'async';
    nextImage.src = view.image;

    if (typeof nextImage.decode === 'function') {
      return nextImage.decode().catch(() => {}).then(() => nextImage);
    }

    return new Promise((resolve) => {
      nextImage.onload = () => resolve(nextImage);
      nextImage.onerror = () => resolve(nextImage);
    });
  };

  setPreview();
  imageWrap.classList.add('is-changing');
  if (content) content.classList.add('is-changing');

  nextButton.disabled = true;
  nextButton.setAttribute('aria-label', view.nextLabel);

  const finishSwap = (nextImage) => {
    image.alt = view.alt;
    image.src = nextImage.src;

    if (typeof updateContent === 'function') {
      updateContent();
    }

    setPreview();
    window.requestAnimationFrame(() => {
      imageWrap.classList.remove('is-changing');
      if (content) content.classList.remove('is-changing');
      nextButton.disabled = false;
    });
  };

  loadNextImage().then(finishSwap);
}

// ============================================
// BM Hill Preloader Logic
// ============================================
// Ensure loader is removed even if errors occur
function removeLoader() {
  const loader = document.getElementById('bm-hill-loader');
  if (loader) {
    document.body.classList.remove('is-loading');
    document.body.classList.add('loaded');
  }
}

// We use DOMContentLoaded for a much faster initial reveal on mobile
document.addEventListener('DOMContentLoaded', () => {
  // Snappier 800ms baseline for a professional institucional feel
  setTimeout(() => {
    removeLoader();
  }, 800);
});

// Failsafe: Force open in case of heavy asset hanging
setTimeout(() => {
  if (document.body.classList.contains('is-loading')) {
    removeLoader();
  }
}, 3000);

// ============================================
// Initialize All Features
// ============================================
document.addEventListener('DOMContentLoaded', async () => {
  // 1. Initialize feature logic (now that elements exist in DOM)
  // One failing module should never block the rest of the page boot sequence.
  runInit('initNavbar', initNavbar);
  runInit('initSearchConsole', initSearchConsole);
  runInit('initScrollReveal', initScrollReveal);
  runInit('initDropdownMenus', initDropdownMenus);
  runInit('initHeroSlider', initHeroSlider);
  runInit('initCounterAnimation', initCounterAnimation);
  runInit('initCommodityTickerVisibility', initCommodityTickerVisibility);
  runInit('initSmoothScrolling', initSmoothScrolling);
  runInit('initParallaxEffect', initParallaxEffect);
  runInit('initMineMarkers', initMineMarkers);
  runInit('initNewsletterForm', initNewsletterForm);
  runInit('initHomepageWhatsAppButton', initHomepageWhatsAppButton);
  runInit('initBorderBeam', initBorderBeam);
  runInit('initTactileFeedback', initTactileFeedback);
  runInit('initEarthTechCore', initEarthTechCore);
  runInit('initStepper', initStepper);
  runInit('initProductDetailLinks', initProductDetailLinks);
  runInit('initChromiteCardToggle', initChromiteCardToggle);
  runInit('initIronCardToggle', initIronCardToggle);
  runInit('initAntimonyCardToggle', initAntimonyCardToggle);
  runInit('initProductsMetallicCardToggles', initProductsMetallicCardToggles);
  runInit('initProductImageLightbox', initProductImageLightbox);
  runInit('initTradeSpecsWheelScroll', initTradeSpecsWheelScroll);
  runInit('initMineCarouselPreload', initMineCarouselPreload);
  runInit('initMineralAtlasWheelScroll', initMineralAtlasWheelScroll);
  runInit('initStoneCardImageToggles', initStoneCardImageToggles);
  runInit('initInvestorMetricSliderIndicator', initInvestorMetricSliderIndicator);
  // Ensure loader is removed after all inits complete
  setTimeout(() => removeLoader(), 100);
});

function initInvestorMetricSliderIndicator() {
  const slider = document.querySelector('.investors__metrics-grid');
  const indicator = document.querySelector('.investors__slider-lines');
  if (!slider || !indicator) return;

  const cards = Array.from(slider.querySelectorAll('.investor-metric'));
  const lines = Array.from(indicator.querySelectorAll('span'));
  if (!cards.length || !lines.length) return;

  let frame = 0;

  const setActiveLine = () => {
    frame = 0;
    const maxIndex = Math.min(cards.length, lines.length) - 1;
    const cardStep = cards[1]
      ? cards[1].offsetLeft - cards[0].offsetLeft
      : cards[0].getBoundingClientRect().width;
    const activeIndex = Math.min(maxIndex, Math.max(0, Math.round(slider.scrollLeft / Math.max(1, cardStep))));

    lines.forEach((line, index) => {
      line.classList.toggle('is-active', index === activeIndex);
    });
  };

  slider.addEventListener('scroll', () => {
    if (frame) return;
    frame = window.requestAnimationFrame(setActiveLine);
  }, { passive: true });

  window.addEventListener('resize', setActiveLine, { passive: true });
  setActiveLine();
}

function initStoneCardImageToggles() {
  if (window.__stoneCardImageTogglesInitialized) return;

  const stoneImageMap = {
    marble: ['images/marble.avif'],
    'white-marble': ['images/white-marble.avif', 'images/white-marble2.avif'],
    'persian-silk-tundra-grey': ['images/persian-silk marble-tundra-grey.jpeg', 'images/persian-silk marble-tundra-grey2.jpeg', 'images/persian-silk marble-tundra-grey-3.jpeg'],
    'persian-silk-block': ['images/persian-silk-block-1.jpeg', 'images/persian-silk-block-2.jpeg', 'images/persian-silk-block-3.jpeg', 'images/persian-silk-block-4.jpeg'],
    'pietra-grey-block': ['images/pietra-grey marble-block (1).jpeg', 'images/pietra-grey marble-block (2).jpeg'],
    'pietra-grey-slab': ['images/pietra-grey marble-slab.jpeg', 'images/pietra-grey marble-slab2.jpeg'],
    'irani-marble': ['images/silver-steam-white-marble-1.jpeg', 'images/silver-steam-white-marble-2.jpeg', 'images/silver-steam-white-marble-3.webp']
  };

  const cards = document.querySelectorAll('[data-stone-card]');
  if (!cards.length) return;
  window.__stoneCardImageTogglesInitialized = true;

  cards.forEach((card) => {
    const key = card.getAttribute('data-stone-card');
    const images = stoneImageMap[key] || [];
    const nextButton = card.querySelector('[data-stone-next]');
    if (!images.length || !nextButton) return;

    let activeIndex = 0;

    if (images.length <= 1) {
      nextButton.hidden = true;
      return;
    }

    preloadImageUrls(images);

    nextButton.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();

      activeIndex = (activeIndex + 1) % images.length;
      card.classList.add('is-changing');
      card.style.setProperty('--stone-image', `url('../../${images[activeIndex]}')`);
      window.requestAnimationFrame(() => {
        card.classList.remove('is-changing');
      });
    });
  });
}

function initTradeSpecsWheelScroll() {
  if (window.__tradeSpecsWheelScrollInitialized) return;

  const scrollerSelector = '.trade-specs__table';
  const scroller = document.querySelector(scrollerSelector);
  if (!scroller) return;

  window.__tradeSpecsWheelScrollInitialized = true;

  const handleWheel = (event) => {
    const activeScroller = event.target instanceof Element
      ? event.target.closest(scrollerSelector)
      : null;
    if (!activeScroller) return;

    if (Math.abs(event.deltaY) < Math.abs(event.deltaX)) return;

    const canScroll = activeScroller.scrollHeight > activeScroller.clientHeight;
    if (!canScroll) return;

    event.preventDefault();
    event.stopPropagation();
    activeScroller.scrollTop += event.deltaY;
  };

  // Capture phase prevents the page from consuming wheel first.
  document.addEventListener('wheel', handleWheel, { passive: false, capture: true });
}

function clearAccidentalMediaSelection() {
  const selection = window.getSelection?.();
  if (selection && selection.rangeCount) selection.removeAllRanges();
}

function initProductImageLightbox() {
  if (window.__productImageLightboxInitialized) return;

  const getSource = (target) => {
    if (target instanceof HTMLImageElement) return target.currentSrc || target.getAttribute('src') || target.src;

    const image = target.querySelector?.('img');
    if (image) return image.currentSrc || image.getAttribute('src') || image.src;

    const background = window.getComputedStyle(target).backgroundImage;
    const match = background.match(/url\(["']?(.+?)["']?\)/);
    return match ? match[1] : '';
  };

  const normalizePath = (src) => {
    try {
      return new URL(src, window.location.href).pathname.split('/').pop();
    } catch {
      return String(src).split('/').pop();
    }
  };

  let modal = null;
  let activeItems = [];
  let activeIndex = 0;
  const lightboxGuard = createImageModalGuard('is-stone-lightbox-open');

  const createModal = () => {
    const element = document.createElement('div');
    element.className = 'stone-lightbox';
    element.setAttribute('role', 'dialog');
    element.setAttribute('aria-modal', 'true');
    element.setAttribute('aria-hidden', 'true');
    element.innerHTML = `
      <div class="stone-lightbox__backdrop" data-product-lightbox-close></div>
      <div class="stone-lightbox__dialog" role="document">
        <div class="stone-lightbox__stage">
          <img class="stone-lightbox__image" src="" alt="" data-product-lightbox-image>
          <button class="stone-lightbox__close" type="button" aria-label="Close image viewer" data-product-lightbox-close>&times;</button>
          <button class="stone-lightbox__arrow stone-lightbox__arrow--prev" type="button" aria-label="Previous image" data-product-lightbox-prev></button>
          <button class="stone-lightbox__arrow stone-lightbox__arrow--next" type="button" aria-label="Next image" data-product-lightbox-next></button>
        </div>
        <div class="stone-lightbox__footer">
          <div>
            <span>Product Gallery</span>
            <h3 data-product-lightbox-title></h3>
          </div>
          <p data-product-lightbox-count></p>
        </div>
      </div>
    `;

    element.addEventListener('click', (event) => {
      if (event.target.matches('[data-product-lightbox-close]')) {
        event.preventDefault();
        event.stopPropagation();
        closeModal();
      }
      if (event.target.closest('[data-product-lightbox-prev]')) showImage(activeIndex - 1);
      if (event.target.closest('[data-product-lightbox-next]')) showImage(activeIndex + 1);
    });

    element.querySelector('.stone-lightbox__backdrop').addEventListener('pointerup', (event) => {
      event.preventDefault();
      event.stopPropagation();
      window.requestAnimationFrame(closeModal);
    });

    element.querySelector('.stone-lightbox__close').addEventListener('pointerup', (event) => {
      if (event.pointerType === 'mouse') return;
      event.preventDefault();
      event.stopPropagation();
      window.requestAnimationFrame(closeModal);
    });

    element.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') closeModal();
      if (event.key === 'ArrowLeft') showImage(activeIndex - 1);
      if (event.key === 'ArrowRight') showImage(activeIndex + 1);
    });

    document.body.appendChild(element);
    return element;
  };

  const showImage = (index) => {
    if (!modal || !activeItems.length) return;

    activeIndex = (index + activeItems.length) % activeItems.length;
    const item = activeItems[activeIndex];
    const hasMultiple = activeItems.length > 1;

    modal.querySelector('[data-product-lightbox-image]').src = item.image;
    modal.querySelector('[data-product-lightbox-image]').alt = item.alt || item.title || 'Product image';
    modal.querySelector('[data-product-lightbox-title]').textContent = item.title || 'Product image';
    modal.querySelector('[data-product-lightbox-count]').textContent = `${activeIndex + 1} / ${activeItems.length}`;
    modal.querySelector('[data-product-lightbox-prev]').hidden = !hasMultiple;
    modal.querySelector('[data-product-lightbox-next]').hidden = !hasMultiple;
  };

  const openModal = ({ items, index, trigger, restoreFocus = false }) => {
    if (!items.length || lightboxGuard.isOpen()) return;

    modal = modal || createModal();
    activeItems = items;
    showImage(index);

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

  const resolveItems = (trigger, record) => {
    const id = record?.id;
    const mappedItems = id ? productImageSets[id] : null;
    const fallbackSource = getSource(trigger);

    if (mappedItems?.length) return mappedItems;

    if (!fallbackSource) return [];

    const title = record?.querySelector('h3')?.textContent?.trim() || trigger.alt || 'Product image';
    return [{ image: fallbackSource, alt: trigger.alt || title, title }];
  };

  const resolveIndex = (trigger, items) => {
    const currentName = normalizePath(getSource(trigger));
    const matchedIndex = items.findIndex((item) => normalizePath(item.image) === currentName);
    return matchedIndex >= 0 ? matchedIndex : 0;
  };

  const triggers = Array.from(document.querySelectorAll([
    '.detail-record > img',
    '.detail-record > picture img',
    '.detail-profile > picture img',
    '.mineral-showcase__image > img',
    '.metallic-card__image-link',
    '.industrial-card__image-link',
    '.industrial-card__compact-image'
  ].join(','))).filter((trigger) => !trigger.closest('[data-stone-gallery]'));

  if (!triggers.length) return;

  window.__productImageLightboxInitialized = true;

  triggers.forEach((trigger) => {
    const record = trigger.closest('article[id]');
    if (!record) return;

    const items = resolveItems(trigger, record);
    if (!items.length) return;

    trigger.setAttribute('role', 'button');
    trigger.setAttribute('tabindex', '0');
    trigger.setAttribute('aria-label', `Open ${record.querySelector('h3')?.textContent?.trim() || 'product'} image gallery`);
    trigger.addEventListener('pointerdown', clearAccidentalMediaSelection, { passive: true });
    trigger.addEventListener('touchend', clearAccidentalMediaSelection, { passive: true });

    trigger.addEventListener('click', (event) => {
      event.preventDefault();
      openModal({
        items,
        index: resolveIndex(trigger, items),
        trigger,
        restoreFocus: event.detail === 0,
      });
    });

    trigger.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      openModal({ items, index: resolveIndex(trigger, items), trigger, restoreFocus: true });
    });
  });
}

function initMineCarouselPreload() {
  if (window.__mineCarouselPreloadInitialized) return;

  const carousels = document.querySelectorAll('[data-mine-carousel]');
  if (!carousels.length) return;

  window.__mineCarouselPreloadInitialized = true;

  carousels.forEach((carousel) => {
    carousel.querySelectorAll('.mine-profile__slide img').forEach((img) => {
      img.decoding = 'async';

      const preloader = new Image();
      preloader.decoding = 'async';
      preloader.src = img.currentSrc || img.src;
    });
  });
}

// ============================================
// Mineral Atlas: Wheel Scroll & Visual Scrollbar
// ============================================
function initMineralAtlasWheelScroll() {
  if (window.__mineralAtlasWheelScrollInitialized) return;

  const scroller = document.querySelector('.mineral-atlas__categories');
  const thumb = document.querySelector('.mineral-atlas__custom-scrollbar-thumb');
  const track = document.querySelector('.mineral-atlas__custom-scrollbar');
  if (!scroller) return;

  window.__mineralAtlasWheelScrollInitialized = true;

  // 1. Wheel boundary logic to work with Lenis
  scroller.addEventListener('wheel', (event) => {
    if (Math.abs(event.deltaY) < Math.abs(event.deltaX)) return;

    const { scrollTop, scrollHeight, clientHeight } = scroller;
    const delta = event.deltaY;

    const atTop = scrollTop <= 0;
    const atBottom = scrollTop + clientHeight >= scrollHeight - 1;

    const scrollingDown = delta > 0;
    const scrollingUp = delta < 0;

    if ((scrollingDown && !atBottom) || (scrollingUp && !atTop)) {
      scroller.setAttribute('data-lenis-prevent', '');
    } else {
      scroller.removeAttribute('data-lenis-prevent');
    }
  }, { passive: true });

  // 2. Custom Visual Scrollbar Synchronization
  if (thumb && track) {
    function updateScrollbar() {
      const { scrollTop, scrollHeight, clientHeight } = scroller;
      
      // If content is not scrollable, hide the scrollbar track
      if (scrollHeight <= clientHeight) {
        track.style.opacity = '0';
        track.style.pointerEvents = 'none';
        return;
      }
      
      track.style.opacity = '1';
      track.style.pointerEvents = 'auto';

      const scrollPercent = scrollTop / (scrollHeight - clientHeight);
      const trackHeight = track.clientHeight;
      const thumbHeight = Math.max(30, (clientHeight / scrollHeight) * trackHeight);
      const maxScrollTop = trackHeight - thumbHeight;
      const thumbTop = scrollPercent * maxScrollTop;

      thumb.style.height = `${thumbHeight}px`;
      thumb.style.transform = `translateY(${thumbTop}px)`;
    }

    scroller.addEventListener('scroll', updateScrollbar);
    window.addEventListener('resize', updateScrollbar);
    updateScrollbar();

    // Fire again when window fully loaded / transitions finished
    window.addEventListener('load', updateScrollbar);
    setTimeout(updateScrollbar, 500);
    setTimeout(updateScrollbar, 2000);
  }
}

function initProductsMetallicCardToggles() {
  if (window.__productsMetallicCardTogglesInitialized) return;

  const toggles = [
    {
      card: '[data-products-chromite-card]',
      image: '[data-products-chromite-image]',
      caption: '[data-products-chromite-caption]',
      next: '[data-products-chromite-next]',
      views: [
        {
          image: 'images/chromite-new.webp',
          alt: 'Chromite lumps from Muslim Bagh mining operations in Balochistan',
          caption: 'Chrome Lumps',
          nextLabel: 'Show chrome concentrate',
          tag: 'High Demand',
          title: 'Chrome Lumps',
          description: 'Chrome lumps are supplied for ferrochrome, refractory, and foundry buyers, with size and Cr:Fe terms confirmed per lot.',
          stats: [
            ['Grade', '12% - 52% Cr₂O₃'],
            ['Region', 'Muslim Bagh / Dalbandin'],
            ['Form', 'Sized Lumps']
          ]
        },
        {
          image: 'images/chrome-concentrate.avif',
          alt: 'Chrome concentrate sample',
          caption: 'Chrome Concentrate',
          nextLabel: 'Show chrome lumps',
          tag: 'Upgraded',
          title: 'Chrome Concentrate',
          description: 'Chrome concentrate is prepared as an upgraded chromite product for buyers needing stronger and more consistent Cr2O3 feed.',
          stats: [
            ['Grade', '32% - 52% Cr₂O₃'],
            ['Region', 'Muslim Bagh / Dalbandin'],
            ['Form', 'Concentrate']
          ]
        }
      ]
    },
    {
      card: '[data-products-iron-card]',
      image: '[data-products-iron-image]',
      caption: '[data-products-iron-caption]',
      next: '[data-products-iron-next]',
      views: [
        {
          image: 'images/iron-ore-new.avif',
          alt: 'Iron ore lumps from Balochistan mining sites',
          caption: 'Iron Ore Lumps',
          nextLabel: 'Show iron concentrate',
          tag: 'Steel Feed',
          title: 'Iron Ore Lumps',
          description: 'Hematite and magnetite iron ore lumps are supplied for steel buyers, infrastructure projects, and regional trading programs.',
          stats: [
            ['Grade', '40% - 58% Fe'],
            ['Region', 'Kharan / Dilband'],
            ['Form', 'Lumps']
          ]
        },
        {
          image: 'images/iron-concentrate.avif',
          alt: 'Iron ore concentrate sample',
          caption: 'Iron Concentrate',
          nextLabel: 'Show iron ore lumps',
          tag: 'Steel Feed',
          title: 'Iron Concentrate',
          description: 'Iron concentrate is prepared through beneficiation for buyers requiring stronger Fe feed and cleaner sizing.',
          stats: [
            ['Grade', '50% - 62% Fe'],
            ['Region', 'Kharan / Dilband'],
            ['Form', 'Concentrate']
          ]
        }
      ]
    },
    {
      card: '[data-products-antimony-card]',
      image: '[data-products-antimony-image]',
      caption: '[data-products-antimony-caption]',
      next: '[data-products-antimony-next]',
      views: [
        {
          image: 'images/antimony.avif',
          alt: 'Antimony lumps sample',
          caption: 'Antimony Lumps',
          nextLabel: 'Show antimony concentrate',
          tag: 'Specialty Metal',
          title: 'Antimony Lumps',
          description: 'Sorted antimony lumps are supplied for specialty metal, alloy, and chemical buyers with lot-based assay terms.',
          stats: [
            ['Grade', '4% - 58% Sb'],
            ['Region', 'Washuk'],
            ['Form', 'Sorted Lumps']
          ]
        },
        {
          image: 'images/antimony-concentrate.avif',
          alt: 'Antimony concentrate sample',
          caption: 'Antimony Concentrate',
          nextLabel: 'Show antimony lumps',
          tag: 'Specialty Metal',
          title: 'Antimony Concentrate',
          description: 'Antimony concentrate is the upgraded product after sorting and processing, prepared for buyers who require stronger Sb content.',
          stats: [
            ['Grade', '20% - 60% Sb'],
            ['Region', 'Washuk'],
            ['Form', 'Concentrate']
          ]
        }
      ]
    }
  ];

  let hasAnyToggle = false;

  toggles.forEach((config) => {
    const card = document.querySelector(config.card);
    if (!card) return;

    const imageWrap = card.querySelector('.metallic-card__image');
    const image = card.querySelector(config.image);
    const caption = card.querySelector(config.caption);
    const nextButton = card.querySelector(config.next);
    if (!imageWrap || !image || !caption || !nextButton) return;

    // Content parts
    const content = card.querySelector('.metallic-card__content');
    const tag = card.querySelector('.metallic-card__tag');
    const title = card.querySelector('.metallic-card__content h3');
    const description = card.querySelector('.metallic-card__content p');
    const specElements = Array.from(card.querySelectorAll('.metallic-card__spec'));

    hasAnyToggle = true;
    let activeIndex = 0;
    preloadImages(config.views);

    const renderView = (index) => {
      const view = config.views[index];
      swapGalleryImage({
        image,
        imageWrap,
        content,
        nextButton,
        view,
        updateContent: () => {
        caption.textContent = view.caption;

        if (tag && view.tag) tag.textContent = view.tag;
        if (title && view.title) title.textContent = view.title;
        if (description && view.description) description.textContent = view.description;

        if (view.stats && specElements.length > 0) {
          view.stats.forEach(([label, value], statIndex) => {
            const specEl = specElements[statIndex];
            if (specEl) {
              const dt = specEl.querySelector('dt');
              const dd = specEl.querySelector('dd');
              if (dt) dt.textContent = label;
              if (dd) dd.textContent = value;
            }
          });
        }
        }
      });
    };

    nextButton.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      activeIndex = (activeIndex + 1) % config.views.length;
      renderView(activeIndex);
    });
  });

  if (hasAnyToggle) window.__productsMetallicCardTogglesInitialized = true;
}

function initIronCardToggle() {
  if (window.__ironCardToggleInitialized) return;

  const card = document.querySelector('[data-iron-card]');
  if (!card) return;

  const imageWrap = card.querySelector('.mineral-showcase__image');
  const content = card.querySelector('.mineral-showcase__content');
  const image = card.querySelector('[data-iron-image]');
  const caption = card.querySelector('[data-iron-caption]');
  const title = card.querySelector('[data-iron-title]');
  const description = card.querySelector('[data-iron-description]');
  const nextButton = card.querySelector('[data-iron-next]');
  const statValues = Array.from(card.querySelectorAll('[data-iron-stat-value]'));
  const statLabels = Array.from(card.querySelectorAll('[data-iron-stat-label]'));

  if (!imageWrap || !content || !image || !caption || !title || !description || !nextButton || !statValues.length || !statLabels.length) return;

  window.__ironCardToggleInitialized = true;

  const views = [
    {
      image: 'images/iron-ore-new.webp',
      alt: 'Iron ore lumps from Balochistan mining sites',
      caption: 'Iron Ore Lumps',
      title: 'Iron Ore Lumps',
      description: 'Hematite and magnetite iron ore lumps are supplied for steel buyers, infrastructure projects and regional trading programs, with typical Fe purity ranging from 40% - 58% depending on lot assay.',
      stats: [
        ['40-58%', 'Fe Lumps'],
        ['Hem / Mag', 'Type']
      ],
      nextLabel: 'Show iron concentrate'
    },
    {
      image: 'images/iron-concentrate.avif',
      alt: 'Iron ore concentrate sample',
      caption: 'Iron Concentrate',
      title: 'Iron Concentrate',
      description: 'Iron concentrate is prepared through beneficiation for buyers requiring stronger Fe feed and cleaner sizing. Typical concentrate purity ranges from 50% - 62% Fe, with hematite or magnetite character confirmed by lot testing.',
      stats: [
        ['50-62%', 'Fe Concentrate'],
        ['Assay', 'Lot Check']
      ],
      nextLabel: 'Show iron ore lumps'
    }
  ];

  let activeIndex = 0;
  preloadImages(views);

  const renderView = (index) => {
    const view = views[index];
    swapGalleryImage({
      image,
      imageWrap,
      content,
      nextButton,
      view,
      updateContent: () => {
      caption.textContent = view.caption;
      title.textContent = view.title;
      description.textContent = view.description;

      view.stats.forEach(([value, label], statIndex) => {
        if (statValues[statIndex]) statValues[statIndex].textContent = value;
        if (statLabels[statIndex]) statLabels[statIndex].textContent = label;
      });
      }
    });
  };

  nextButton.addEventListener('click', () => {
    activeIndex = (activeIndex + 1) % views.length;
    renderView(activeIndex);
  });
}

function initChromiteCardToggle() {
  if (window.__chromiteCardToggleInitialized) return;

  const card = document.querySelector('[data-chromite-card]');
  if (!card) return;

  const imageWrap = card.querySelector('.mineral-showcase__image');
  const content = card.querySelector('.mineral-showcase__content');
  const image = card.querySelector('[data-chromite-image]');
  const caption = card.querySelector('[data-chromite-caption]');
  const title = card.querySelector('[data-chromite-title]');
  const description = card.querySelector('[data-chromite-description]');
  const nextButton = card.querySelector('[data-chromite-next]');
  const statValues = Array.from(card.querySelectorAll('[data-chromite-stat-value]'));
  const statLabels = Array.from(card.querySelectorAll('[data-chromite-stat-label]'));

  if (!imageWrap || !content || !image || !caption || !title || !description || !nextButton || !statValues.length || !statLabels.length) return;

  window.__chromiteCardToggleInitialized = true;

  const views = [
    {
      image: 'images/chromite-new.webp',
      alt: 'Chromite lumps from Muslim Bagh mining operations in Balochistan',
      caption: 'Chrome Lumps',
      title: 'Chrome Lumps',
      description: 'Chrome lumps are supplied for ferrochrome, refractory and foundry buyers, with typical Cr2O3 purity ranging from 12% - 52% depending on lot grade, sizing and final assay.',
      stats: [
        ['12-52%', 'Cr2O3 Lumps'],
        ['Muslim Bagh', 'Region']
      ],
      nextLabel: 'Show chrome concentrate'
    },
    {
      image: 'images/chrome-concentrate.avif',
      alt: 'Chrome concentrate sample',
      caption: 'Chrome Concentrate',
      title: 'Chrome Concentrate',
      description: 'Chrome concentrate is prepared as an upgraded chromite product for buyers needing stronger and more consistent Cr2O3 feed. Typical concentrate purity ranges from 32% - 52%, with final shipment terms confirmed by assay.',
      stats: [
        ['32-52%', 'Cr2O3 Concentrate'],
        ['Assay', 'Lot Check']
      ],
      nextLabel: 'Show chrome lumps'
    }
  ];

  let activeIndex = 0;
  preloadImages(views);

  const renderView = (index) => {
    const view = views[index];
    swapGalleryImage({
      image,
      imageWrap,
      content,
      nextButton,
      view,
      updateContent: () => {
      caption.textContent = view.caption;
      title.textContent = view.title;
      description.textContent = view.description;

      view.stats.forEach(([value, label], statIndex) => {
        if (statValues[statIndex]) statValues[statIndex].textContent = value;
        if (statLabels[statIndex]) statLabels[statIndex].textContent = label;
      });
      }
    });
  };

  nextButton.addEventListener('click', () => {
    activeIndex = (activeIndex + 1) % views.length;
    renderView(activeIndex);
  });
}

function initAntimonyCardToggle() {
  if (window.__antimonyCardToggleInitialized) return;

  const card = document.querySelector('[data-antimony-card]');
  if (!card) return;

  const imageWrap = card.querySelector('.mineral-showcase__image');
  const content = card.querySelector('.mineral-showcase__content');
  const image = card.querySelector('[data-antimony-image]');
  const caption = card.querySelector('[data-antimony-caption]');
  const title = card.querySelector('[data-antimony-title]');
  const description = card.querySelector('[data-antimony-description]');
  const nextButton = card.querySelector('[data-antimony-next]');
  const statValues = Array.from(card.querySelectorAll('[data-antimony-stat-value]'));
  const statLabels = Array.from(card.querySelectorAll('[data-antimony-stat-label]'));

  if (!imageWrap || !content || !image || !caption || !title || !description || !nextButton || !statValues.length || !statLabels.length) return;

  window.__antimonyCardToggleInitialized = true;

  const views = [
    {
      key: 'lumps',
      image: 'images/antimony.avif',
      alt: 'Antimony lumps sample',
      caption: 'Antimony Lumps',
      title: 'Antimony Lumps',
      description: 'Sorted antimony lumps are supplied for specialty metal, alloy and chemical buyers, with typical Sb purity ranging from 4% - 58% depending on the lot and final assay.',
      stats: [
        ['4-58%', 'Sb Lumps'],
        ['Sorted', 'Form'],
        ['Assay', 'Lot Check']
      ],
      nextLabel: 'Show antimony concentrate'
    },
    {
      key: 'concentrate',
      image: 'images/antimony-concentrate.avif',
      alt: 'Antimony concentrate sample',
      caption: 'Antimony Concentrate',
      title: 'Antimony Concentrate',
      description: 'Antimony concentrate is the upgraded product after sorting and processing, prepared for buyers who require stronger Sb content. Typical concentrate purity ranges from 20% - 60% Sb, subject to fresh lot testing.',
      stats: [
        ['20-60%', 'Sb Concentrate'],
        ['Upgraded', 'Form'],
        ['Assay', 'Lot Check']
      ],
      nextLabel: 'Show antimony lumps'
    }
  ];

  let activeIndex = 0;
  preloadImages(views);

  const renderView = (index) => {
    const view = views[index];
    swapGalleryImage({
      image,
      imageWrap,
      content,
      nextButton,
      view,
      updateContent: () => {
      caption.textContent = view.caption;
      title.textContent = view.title;
      description.textContent = view.description;

      view.stats.forEach(([value, label], statIndex) => {
        if (statValues[statIndex]) statValues[statIndex].textContent = value;
        if (statLabels[statIndex]) statLabels[statIndex].textContent = label;
      });
      }
    });
  };

  nextButton.addEventListener('click', () => {
    activeIndex = (activeIndex + 1) % views.length;
    renderView(activeIndex);
  });
}

function initProductDetailLinks() {
  if (window.__productDetailLinksInitialized) return;

  const detailTargets = {
    copper: 'product-metallic.html#copper',
    chromite: 'product-metallic.html#chromite',
    'iron-ore': 'product-metallic.html#iron-ore',
    antimony: 'product-metallic.html#antimony',
    barite: 'product-industrial.html#barite',
    fluorite: 'product-industrial.html#fluorite',
    gypsum: 'product-industrial.html#gypsum',
    magnesite: 'product-industrial.html#magnesite',
    'phosphate-rock': 'product-industrial.html#phosphate-rock',
    bauxite: 'product-industrial.html#bauxite',
    marble: 'product-stones.html#marble',
    'white-marble': 'product-stones.html#white-marble',
    'persian-silk-tundra-grey': 'product-stones.html#persian-silk-tundra-grey',
    'persian-silk-block': 'product-stones.html#persian-silk-block',
    'pietra-grey-block': 'product-stones.html#pietra-grey-block',
    'pietra-grey-slab': 'product-stones.html#pietra-grey-slab'
  };

  const cards = document.querySelectorAll('.product-card[id], .stone-card[id]');
  if (!cards.length) return;

  window.__productDetailLinksInitialized = true;

  cards.forEach((card) => {
    const target = detailTargets[card.id];
    if (!target) return;

    card.setAttribute('role', 'link');
    card.setAttribute('tabindex', '0');
    card.dataset.href = target;

    card.addEventListener('click', (event) => {
      if (event.target.closest('a, button')) return;
      window.location.href = target;
    });

    card.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      window.location.href = target;
    });
  });
}

// ============================================
// Interactive Stepper for Operations
// ============================================
function initStepper() {
  // Guard: Skip if already initialized
  if (window.__stepperInitialized) return;

  const indicators = document.querySelectorAll('.step-indicator');
  const cards = document.querySelectorAll('.step-card');

  if (!indicators.length || !cards.length) return;

  window.__stepperInitialized = true;

  indicators.forEach(indicator => {
    indicator.addEventListener('click', () => {
      const step = indicator.getAttribute('data-step');

      // Update Indicators
      indicators.forEach(ind => ind.classList.remove('step-indicator--active'));
      indicator.classList.add('step-indicator--active');

      // Update Cards
      cards.forEach(card => {
        card.classList.remove('step-card--active');
        if (card.getAttribute('data-step') === step) {
          card.classList.add('step-card--active');

          // Re-trigger reveal animation if GSAP is available
          if (window.gsap) {
            gsap.fromTo(card,
              { opacity: 0, y: 20 },
              { opacity: 1, y: 0, duration: 0.5, ease: "power2.out" }
            );
          }
        }
      });
    });
  });
}

// ============================================
// Counter Animation for Stats
// ============================================
function initCounterAnimation() {
  // Guard: Skip if already initialized
  if (window.__counterAnimationInitialized || window.__sceneHomeCounterManaged) return;

  const counters = document.querySelectorAll('[data-target]');
  if (!counters.length) return;

  window.__counterAnimationInitialized = true;

  const animateCounter = (element) => {
    const target = parseInt(element.getAttribute('data-target'), 10);
    const suffix = element.getAttribute('data-suffix') || '';
    const duration = 2000;
    const increment = target / (duration / 16);
    let current = 0;

    const updateCounter = () => {
      current += increment;
      if (current < target) {
        element.textContent = Math.floor(current).toLocaleString() + suffix;
        requestAnimationFrame(updateCounter);
      } else {
        element.textContent = target.toLocaleString() + suffix;
      }
    };

    updateCounter();
  };

  if (typeof IntersectionObserver === 'undefined') {
    counters.forEach(animateCounter);
    return;
  }

  // Intersection Observer for counters
  const observerOptions = {
    threshold: 0.5,
    rootMargin: '0px'
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        animateCounter(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  counters.forEach(counter => observer.observe(counter));
}

function initCommodityTickerVisibility() {
  if (window.__commodityTickerVisibilityInitialized) return;
  window.__commodityTickerVisibilityInitialized = true;

  const ticker = document.querySelector('.commodity-ticker');
  if (!ticker) return;

  if (typeof IntersectionObserver === 'undefined') {
    ticker.classList.add('is-in-view');
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      entry.target.classList.toggle('is-in-view', entry.isIntersecting);
    });
  }, { threshold: 0.01 });

  observer.observe(ticker);
}

// ============================================
// Smooth Scrolling for Anchor Links
// ============================================
function initSmoothScrolling() {
  // Guard: Skip if already initialized
  if (window.__smoothScrollingInitialized || window.__lenisEnabled) return;
  window.__smoothScrollingInitialized = true;

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const hasLenis = typeof window.Lenis === 'function';
  const hasGsapScrollTrigger = Boolean(window.gsap && window.ScrollTrigger);
  let lenis = null;

  if (hasLenis && !prefersReducedMotion) {
    lenis = new window.Lenis({
      lerp: 0.08,
      duration: 1.12,
      wheelMultiplier: 0.9,
      smoothWheel: true,
      smoothTouch: false
    });

    window.__bmLenis = lenis;
    window.__lenisEnabled = true;

    if (hasGsapScrollTrigger) {
      window.gsap.registerPlugin(window.ScrollTrigger);
      lenis.on('scroll', () => window.ScrollTrigger.update());
      window.gsap.ticker.add((time) => {
        lenis.raf(time * 1000);
      });
      window.gsap.ticker.lagSmoothing(0);
    } else {
      const raf = (time) => {
        lenis.raf(time);
        requestAnimationFrame(raf);
      };
      requestAnimationFrame(raf);
    }
  }

  const anchorLinks = document.querySelectorAll('a[href^="#"]');

  anchorLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      const href = link.getAttribute('href');
      if (href && href !== '#') {
        let target = null;
        try {
          target = document.querySelector(href);
        } catch (error) {
          return;
        }

        if (target) {
          e.preventDefault();
          const navbarHeight = document.querySelector('.navbar')?.offsetHeight || 80;
          if (lenis) {
            lenis.scrollTo(target, {
              offset: -navbarHeight,
              duration: 1.1
            });
            return;
          }

          const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - navbarHeight;
          window.scrollTo({
            top: targetPosition,
            behavior: 'smooth'
          });
        }
      }
    });
  });
}

// ============================================
// Parallax Effect for Hero Section
// ============================================
function initParallaxEffect() {
  // Guard: Skip if already initialized
  if (window.__parallaxEffectInitialized) return;
  window.__parallaxEffectInitialized = true;

  const heroSection = document.querySelector('.hero');
  if (!heroSection) return;
  const parallaxLayer = heroSection.querySelector('.hero__mountain-bg');
  if (!parallaxLayer) return;

  let ticking = false;

  const updateParallax = () => {
    const scrolled = window.pageYOffset;
    const heroHeight = heroSection.offsetHeight;

    if (scrolled < heroHeight) {
      const parallaxValue = scrolled * 0.5;
      parallaxLayer.style.transform = `translateY(${parallaxValue}px)`;
    }
    ticking = false;
  };

  window.addEventListener('scroll', () => {
    if (!ticking) {
      requestAnimationFrame(updateParallax);
      ticking = true;
    }
  });
}

// ============================================
// Interactive Mine Markers on Map
// ============================================
function initMineMarkers() {
  // Guard: Skip if already initialized
  if (window.__mineMarkersInitialized) return;
  window.__mineMarkersInitialized = true;

  const markers = document.querySelectorAll('.mine-marker');

  markers.forEach(marker => {
    marker.addEventListener('click', () => {
      // Add a subtle animation on click
      marker.style.transform = 'scale(1.3)';
      setTimeout(() => {
        marker.style.transform = 'scale(1)';
      }, 300);
    });

    // Add hover effect
    marker.addEventListener('mouseenter', () => {
      marker.style.cursor = 'pointer';
    });
  });
}

// ============================================
// Newsletter Form Handling
// ============================================
function initNewsletterForm() {
  // Guard: Skip if already initialized
  if (window.__newsletterFormInitialized) return;
  window.__newsletterFormInitialized = true;

  const form = document.querySelector('.footer__newsletter');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const emailInput = form.querySelector('.footer__newsletter-input');
    const email = emailInput.value;

    if (email && validateEmail(email)) {

      // Show success feedback
      emailInput.value = '';
      emailInput.placeholder = 'Subscribed';
      setTimeout(() => {
        emailInput.placeholder = 'Your email';
      }, 3000);
    } else {
      emailInput.style.borderColor = '#ef4444';
      setTimeout(() => {
        emailInput.style.borderColor = '';
      }, 2000);
    }
  });
}

function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

function initHomepageWhatsAppButton() {
  if (window.__homepageWhatsAppInitialized) return;
  window.__homepageWhatsAppInitialized = true;

  if (!document.body.classList.contains('homepage')) return;

  const button = document.getElementById('homepage-whatsapp');
  const footer = document.querySelector('footer.footer');
  const phoneLink = document.querySelector('.navbar__phone-link[href^="tel:"]');

  if (!button || !footer || !phoneLink) return;

  const rawPhone = phoneLink.getAttribute('href') || '';
  const waNumber = rawPhone.replace(/^tel:/i, '').replace(/[^\d]/g, '');
  if (!waNumber) return;

  const message = 'Hello Balochistan Minerals, I would like to discuss mineral sourcing and export availability.';
  button.href = `https://wa.me/${waNumber}?text=${encodeURIComponent(message)}`;

  if (typeof IntersectionObserver === 'undefined') {
    const onScroll = () => {
      const footerTop = footer.getBoundingClientRect().top;
      const triggerPoint = window.innerHeight * 0.9;
      button.classList.toggle('is-visible', footerTop <= triggerPoint);
    };

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      button.classList.toggle('is-visible', entry.isIntersecting);
    });
  }, {
    root: null,
    threshold: 0.2
  });

  observer.observe(footer);
}

// ============================================
// Export for potential use in other modules
// ============================================
export {
  initCounterAnimation,
  initSmoothScrolling,
  initParallaxEffect,
  initMineMarkers,
  initNewsletterForm,
  initHomepageWhatsAppButton,
  initProductDetailLinks,
  initChromiteCardToggle,
  initIronCardToggle,
  initAntimonyCardToggle
};
