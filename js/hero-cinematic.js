/**
 * Hero Cinematic - Premium Mineral Showcase
 * Professional, smooth, million-dollar interactions
 */

(function() {
  'use strict';

  // Wait for DOM
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function init() {
    const heroSection = document.querySelector('.hero-cinematic');
    if (!heroSection) return;

    // Initialize particles (Disabled as per request)
    // initParticles(heroSection);
    
    // Initialize Hero Carousel
    initHeroCarousel(heroSection);
    
    // Animate stats counter
    animateStats();

    // Handle Background Video Transition (10s delay)
    initHeroBackgroundTransition(heroSection);
  }

  /**
   * Transition from static image to video after 10 seconds
   */
  function initHeroBackgroundTransition(container) {
    const bgImage = container.querySelector('.hero-bg-image');
    const bgVideo = container.querySelector('.hero-bg-video');
    const bgOverlay = container.querySelector('.hero-bg-video-overlay');

    if (!bgVideo) return;

    // 10 second delay as requested
    setTimeout(() => {
      // Start video play
      bgVideo.play().then(() => {
        // Fade in video and overlay
        bgVideo.classList.add('is-visible');
        if (bgOverlay) bgOverlay.classList.add('is-visible');
        
        // Slightly delay image fade out for smoother blend
        setTimeout(() => {
          if (bgImage) bgImage.style.opacity = '0';
        }, 500);
      }).catch(err => {
        console.warn('Hero video auto-play blocked or failed:', err);
      });
    }, 10000);
  }

  /**
   * Create floating mineral particles
   */
  function initParticles(container) {
    const particlesContainer = container.querySelector('.hero-particles');
    if (!particlesContainer) return;

    const particleCount = 15;
    const colors = ['#c9a962', '#b87333', '#6495ed'];

    for (let i = 0; i < particleCount; i++) {
      const particle = document.createElement('div');
      particle.className = 'hero-particle';
      
      // Random properties
      const size = Math.random() * 60 + 20;
      const left = Math.random() * 100;
      const delay = Math.random() * 10;
      const duration = Math.random() * 10 + 15;
      const color = colors[Math.floor(Math.random() * colors.length)];
      
      particle.style.cssText = `
        width: ${size}px;
        height: ${size}px;
        left: ${left}%;
        animation-delay: ${delay}s;
        animation-duration: ${duration}s;
        background: radial-gradient(circle at 30% 30%, ${color}40 0%, transparent 70%);
      `;
      
      particlesContainer.appendChild(particle);
    }
  }

  /**
   * Hero Carousel Logic
   */
  function initHeroCarousel(container) {
    const carouselArr = container.querySelectorAll('.mineral-card');
    const indicators = container.querySelectorAll('.indicator');
    const prevBtn = container.querySelector('.carousel-nav-btn.prev');
    const nextBtn = container.querySelector('.carousel-nav-btn.next');
    
    if (!carouselArr.length) return;

    let currentIndex = 0;
    let isTransitioning = false;

    function updateCarousel() {
      carouselArr.forEach((card, index) => {
        // Remove all state classes
        card.classList.remove('is-active', 'is-next', 'is-prev', 'is-hidden-right', 'is-hidden-left');
        
        if (index === currentIndex) {
          card.classList.add('is-active');
        } else if (index === (currentIndex + 1) % carouselArr.length) {
          card.classList.add('is-next');
        } else if (index === (currentIndex - 1 + carouselArr.length) % carouselArr.length) {
          card.classList.add('is-prev');
        } else if (index > currentIndex) {
          card.classList.add('is-hidden-right');
        } else {
          card.classList.add('is-hidden-left');
        }
      });

      // Update indicators
      indicators.forEach((indicator, index) => {
        indicator.classList.toggle('is-active', index === currentIndex);
      });
      
      // Feedback
      if (window.tactileFeedback) window.tactileFeedback('soft');

      // Dispatch event for other components (like 3D explorer if re-enabled)
      const activeMineral = carouselArr[currentIndex].getAttribute('data-mineral');
      window.dispatchEvent(new CustomEvent('mineralChanged', { 
        detail: { mineral: activeMineral } 
      }));
    }

    function nextSlide() {
      if (isTransitioning) return;
      isTransitioning = true;
      currentIndex = (currentIndex + 1) % carouselArr.length;
      updateCarousel();
      setTimeout(() => isTransitioning = false, 800);
    }

    function prevSlide() {
      if (isTransitioning) return;
      isTransitioning = true;
      currentIndex = (currentIndex - 1 + carouselArr.length) % carouselArr.length;
      updateCarousel();
      setTimeout(() => isTransitioning = false, 800);
    }

    // Event Listeners
    if (nextBtn) nextBtn.addEventListener('click', nextSlide);
    if (prevBtn) prevBtn.addEventListener('click', prevSlide);

    indicators.forEach((indicator, index) => {
      indicator.addEventListener('click', () => {
        if (currentIndex === index || isTransitioning) return;
        currentIndex = index;
        updateCarousel();
      });
    });

    // Option: Click card to go next
    carouselArr.forEach((card, index) => {
      card.addEventListener('click', () => {
        if (card.classList.contains('is-next')) {
          nextSlide();
        } else if (card.classList.contains('is-prev')) {
          prevSlide();
        }
      });
    });

    // Initialize first state
    updateCarousel();

    // Auto-advance
    let autoPlay = setInterval(nextSlide, 8000);

    container.addEventListener('mouseenter', () => clearInterval(autoPlay));
    container.addEventListener('mouseleave', () => autoPlay = setInterval(nextSlide, 8000));
  }

  /**
   * Animate statistics counter
   */
  function animateStats() {
    const statValues = document.querySelectorAll('.stat-value');
    
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const target = entry.target;
          const finalValue = target.textContent;
          const numericValue = parseInt(finalValue.replace(/[^0-9]/g, ''));
          
          if (!isNaN(numericValue)) {
            animateCounter(target, numericValue, finalValue);
            observer.unobserve(target);
          }
        }
      });
    }, { threshold: 0.5 });
    
    statValues.forEach(stat => observer.observe(stat));
  }

  function animateCounter(element, target, formatted) {
    const duration = 2000;
    const start = 0;
    const startTime = performance.now();
    
    function easeOutQuart(t) {
      return 1 - Math.pow(1 - t, 4);
    }
    
    function update(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const easedProgress = easeOutQuart(progress);
      
      const currentValue = Math.round(start + (target - start) * easedProgress);
      
      if (formatted.includes('+')) {
        element.textContent = currentValue + '+';
      } else if (formatted.includes('%')) {
        element.textContent = currentValue + '%';
      } else if (formatted.includes('$')) {
        element.textContent = '$' + currentValue.toLocaleString();
      } else {
        element.textContent = currentValue.toLocaleString();
      }
      
      if (progress < 1) {
        requestAnimationFrame(update);
      } else {
        element.textContent = formatted;
      }
    }
    
    requestAnimationFrame(update);
  }

})();
