/**
 * Hero Image Slider
 * Auto-rotates every 10 seconds with smooth fade transitions
 * Supports manual navigation via arrows and dots
 */
export function initHeroSlider() {
  const slides = document.querySelectorAll('.hero__slide');
  const dots = document.querySelectorAll('.hero__slider-dot');
  const prevArrow = document.querySelector('.hero__slider-arrow--prev');
  const nextArrow = document.querySelector('.hero__slider-arrow--next');

  if (!slides.length) return;

  let currentSlide = 0;
  let autoPlayInterval = null;
  const SLIDE_INTERVAL = 6000; // Snappier 6 seconds

  function goToSlide(index) {
    // Failsafe: Remove active state from ALL slides/dots to prevent desync
    slides.forEach(slide => slide.classList.remove('active'));
    dots.forEach(dot => dot.classList.remove('active'));

    // Wrap around
    currentSlide = (index + slides.length) % slides.length;

    // Add active state to new slide
    slides[currentSlide].classList.add('active');
    if (dots[currentSlide]) dots[currentSlide].classList.add('active');
  }

  function nextSlide() {
    goToSlide(currentSlide + 1);
  }

  function prevSlide() {
    goToSlide(currentSlide - 1);
  }

  function startAutoPlay() {
    stopAutoPlay();
    autoPlayInterval = setInterval(nextSlide, SLIDE_INTERVAL);
  }

  function stopAutoPlay() {
    if (autoPlayInterval) {
      clearInterval(autoPlayInterval);
      autoPlayInterval = null;
    }
  }

  // Event listeners
  if (nextArrow) {
    nextArrow.addEventListener('click', () => {
      nextSlide();
      startAutoPlay(); // Reset timer
    });
  }

  if (prevArrow) {
    prevArrow.addEventListener('click', () => {
      prevSlide();
      startAutoPlay(); // Reset timer
    });
  }

  dots.forEach((dot, index) => {
    dot.addEventListener('click', () => {
      goToSlide(index);
      startAutoPlay(); // Reset timer
    });
  });

  // Keyboard navigation
  document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') {
      prevSlide();
      startAutoPlay();
    } else if (e.key === 'ArrowRight') {
      nextSlide();
      startAutoPlay();
    }
  });

  // Pause on hover
  const heroSection = document.querySelector('.hero');
  if (heroSection) {
    heroSection.addEventListener('mouseenter', stopAutoPlay);
    heroSection.addEventListener('mouseleave', startAutoPlay);
  }

  // Touch support for mobile
  let touchStartX = 0;
  let touchEndX = 0;

  if (heroSection) {
    heroSection.addEventListener('touchstart', (e) => {
      touchStartX = e.changedTouches[0].screenX;
      stopAutoPlay();
    }, { passive: true });

    heroSection.addEventListener('touchend', (e) => {
      touchEndX = e.changedTouches[0].screenX;
      const diff = touchStartX - touchEndX;

      if (Math.abs(diff) > 50) {
        if (diff > 0) {
          nextSlide();
        } else {
          prevSlide();
        }
      }

      startAutoPlay();
    }, { passive: true });
  }

  // Start autoplay
  startAutoPlay();
}
