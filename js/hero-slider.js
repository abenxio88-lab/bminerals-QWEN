/**
 * Hero Image Slider
 * Keeps the first hero image eager, preloads the next slide, and only switches
 * after the next image is ready.
 */
export function initHeroSlider() {
  const slides = Array.from(document.querySelectorAll('.hero__slide'));
  const dots = Array.from(document.querySelectorAll('.hero__slider-dot'));
  const prevArrow = document.querySelector('.hero__slider-arrow--prev');
  const nextArrow = document.querySelector('.hero__slider-arrow--next');
  const heroSection = document.querySelector('.hero');

  if (!slides.length) return;

  let currentSlide = Math.max(0, slides.findIndex(slide => slide.classList.contains('active')));
  let autoPlayTimeout = null;
  let isTransitioning = false;
  let autoPlayToken = 0;
  const SLIDE_INTERVAL = 7000;
  const RETRY_DELAY = 300;
  const loadPromises = new WeakMap();

  function getImage(index) {
    return slides[index]?.querySelector('.hero__background-image') || null;
  }

  function getImageSource(img) {
    return img?.currentSrc || img?.getAttribute('src') || img?.dataset.src || '';
  }

  function markLoaded(index) {
    slides[index]?.classList.add('hero__slide--loaded');
  }

  function loadSlideImage(index) {
    const normalizedIndex = (index + slides.length) % slides.length;
    const img = getImage(normalizedIndex);
    if (!img) return Promise.resolve();

    const src = getImageSource(img);
    if (!src) return Promise.resolve();

    if (img.complete && img.naturalWidth > 0) {
      markLoaded(normalizedIndex);
      return Promise.resolve(img);
    }

    if (loadPromises.has(img)) {
      return loadPromises.get(img);
    }

    const promise = new Promise((resolve) => {
      const finish = () => {
        markLoaded(normalizedIndex);
        resolve(img);
      };

      img.addEventListener('load', finish, { once: true });
      img.addEventListener('error', finish, { once: true });

      if (!img.getAttribute('src') && img.dataset.src) {
        img.src = img.dataset.src;
      }
    });

    loadPromises.set(img, promise);
    return promise;
  }

  function preloadAdjacentSlides() {
    loadSlideImage(currentSlide + 1);
  }

  function setActiveSlide(index) {
    slides.forEach(slide => slide.classList.remove('active'));
    dots.forEach(dot => dot.classList.remove('active'));

    currentSlide = (index + slides.length) % slides.length;
    slides[currentSlide].classList.add('active');
    dots[currentSlide]?.classList.add('active');
    markLoaded(currentSlide);
    preloadAdjacentSlides();
  }

  async function goToSlide(index) {
    const targetIndex = (index + slides.length) % slides.length;
    if (targetIndex === currentSlide || isTransitioning) return;

    isTransitioning = true;
    await loadSlideImage(targetIndex);
    setActiveSlide(targetIndex);
    isTransitioning = false;
  }

  function scheduleAutoPlay(delay = SLIDE_INTERVAL) {
    stopAutoPlay();
    const token = ++autoPlayToken;
    autoPlayTimeout = window.setTimeout(async () => {
      if (token !== autoPlayToken) return;
      const targetIndex = (currentSlide + 1) % slides.length;
      await loadSlideImage(targetIndex);
      if (token !== autoPlayToken) return;
      await goToSlide(targetIndex);
      if (token !== autoPlayToken) return;
      scheduleAutoPlay();
    }, delay);
  }

  function stopAutoPlay() {
    autoPlayToken += 1;
    if (autoPlayTimeout) {
      window.clearTimeout(autoPlayTimeout);
      autoPlayTimeout = null;
    }
  }

  function restartAutoPlay() {
    scheduleAutoPlay(RETRY_DELAY + SLIDE_INTERVAL);
  }

  nextArrow?.addEventListener('click', async () => {
    stopAutoPlay();
    await goToSlide(currentSlide + 1);
    restartAutoPlay();
  });

  prevArrow?.addEventListener('click', async () => {
    stopAutoPlay();
    await goToSlide(currentSlide - 1);
    restartAutoPlay();
  });

  dots.forEach((dot, index) => {
    dot.addEventListener('click', async () => {
      stopAutoPlay();
      await goToSlide(index);
      restartAutoPlay();
    });
  });

  document.addEventListener('keydown', async (event) => {
    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
    stopAutoPlay();
    await goToSlide(currentSlide + (event.key === 'ArrowRight' ? 1 : -1));
    restartAutoPlay();
  });

  let touchStartX = 0;
  let touchEndX = 0;

  heroSection?.addEventListener('touchstart', (event) => {
    touchStartX = event.changedTouches[0].screenX;
    stopAutoPlay();
  }, { passive: true });

  heroSection?.addEventListener('touchend', async (event) => {
    touchEndX = event.changedTouches[0].screenX;
    const diff = touchStartX - touchEndX;

    if (Math.abs(diff) > 50) {
      await goToSlide(currentSlide + (diff > 0 ? 1 : -1));
    }

    restartAutoPlay();
  }, { passive: true });

  markLoaded(currentSlide);
  preloadAdjacentSlides();
  scheduleAutoPlay();
}
