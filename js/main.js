import { initNavbar } from './navbar.js';
import { initScrollReveal } from './scroll-reveal.js';
import { initDropdownMenus } from './dropdown.js';
import { initHeroSlider } from './hero-slider.js';
import { initBorderBeam } from './border-beam.js';
import { initTactileFeedback } from './tactile-feedback.js';
import { initEarthTechCore } from './earth-tech-core.js';
import { initSearchConsole } from './search-console.js';

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

function preloadImages(views) {
  views.forEach((view) => {
    if (!view || !view.image) return;

    const preloader = new Image();
    preloader.decoding = 'async';
    preloader.src = view.image;
  });
}

function preloadImageUrls(urls) {
  urls.forEach((url) => {
    if (!url) return;

    const preloader = new Image();
    preloader.decoding = 'async';
    preloader.src = url;
  });
}

function swapGalleryImage({ image, imageWrap, content, nextButton, view, updateContent }) {
  imageWrap.classList.add('is-changing');
  if (content) content.classList.add('is-changing');

  image.alt = view.alt;
  image.src = view.image;
  nextButton.setAttribute('aria-label', view.nextLabel);

  if (typeof updateContent === 'function') {
    updateContent();
  }

  const finishSwap = () => {
    imageWrap.classList.remove('is-changing');
    if (content) content.classList.remove('is-changing');
  };

  if (typeof image.decode === 'function') {
    image.decode().catch(() => {}).finally(finishSwap);
    return;
  }

  window.requestAnimationFrame(finishSwap);
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
  runInit('initTradeSpecsWheelScroll', initTradeSpecsWheelScroll);
  runInit('initMineCarouselPreload', initMineCarouselPreload);
  runInit('initMineralAtlasWheelScroll', initMineralAtlasWheelScroll);
  runInit('initStoneCardImageToggles', initStoneCardImageToggles);
  // Ensure loader is removed after all inits complete
  setTimeout(() => removeLoader(), 100);
});

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
