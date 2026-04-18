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

    // Initialize particles
    initParticles(heroSection);
    
    // Initialize mineral card interactions
    initMineralCards(heroSection);
    
    // Initialize parallax effect
    initParallax(heroSection);
    
    // Animate stats counter
    animateStats();
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
   * Initialize mineral card interactions
   * Handles hover emphasis and subtle depth effects for elegant interaction
   */
  function initMineralCards(container) {
    const mineralCards = container.querySelectorAll('.mineral-card');
    
    mineralCards.forEach(card => {
      // On hover: dim non-focused cards for visual focus
      card.addEventListener('mouseenter', () => {
        mineralCards.forEach(sibling => {
          if (sibling !== card) {
            sibling.style.opacity = '0.3';
            sibling.style.transform = 'scale(0.95) translateZ(-100px)';
          }
        });
      });
      
      // On hover exit: restore all cards to full visibility
      card.addEventListener('mouseleave', () => {
        mineralCards.forEach(sibling => {
          sibling.style.opacity = '';
          sibling.style.transform = '';
        });
      });
    });
  }

  /**
   * Initialize subtle parallax effect with mouse movement
   * Creates elegant depth layering for mineral card showcase
   */
  function initParallax(container) {
    const heroVisual = container.querySelector('.hero-visual');
    const mineralCards = container.querySelectorAll('.mineral-card');
    
    if (!heroVisual || mineralCards.length === 0) return;

    let targetMouseX = 0;
    let targetMouseY = 0;
    let currentInterpolX = 0;
    let currentInterpolY = 0;

    // Track mouse position relative to hero visual
    document.addEventListener('mousemove', (event) => {
      const viewportBounds = heroVisual.getBoundingClientRect();
      const normalizedX = (event.clientX - viewportBounds.left) / viewportBounds.width - 0.5;
      const normalizedY = (event.clientY - viewportBounds.top) / viewportBounds.height - 0.5;
      
      targetMouseX = normalizedX * 30;
      targetMouseY = normalizedY * 20;
    });

    // Smooth animation loop with interpolation
    function updateParallax() {
      // Smooth interpolation for fluid motion
      currentInterpolX += (targetMouseX - currentInterpolX) * 0.05;
      currentInterpolY += (targetMouseY - currentInterpolY) * 0.05;
      
      mineralCards.forEach((card, index) => {
        const depthMultiplier = (index + 1) * 0.5;
        const rotationY = currentInterpolX * depthMultiplier;
        const rotationX = -currentInterpolY * depthMultiplier;
        
        // Apply mineral-specific parallax transformations
        if (card.classList.contains('mineral-card--chromite')) {
          card.style.transform = `translate(-50%, -50%) translateZ(0) rotateY(${rotationY}deg) rotateX(${rotationX}deg)`;
        } else if (card.classList.contains('mineral-card--iron-ore')) {
          card.style.transform = `translateZ(-50px) rotateY(${-10 + rotationY * 1.5}deg) rotateX(${rotationX}deg)`;
        } else if (card.classList.contains('mineral-card--barite')) {
          card.style.transform = `translateZ(-80px) rotateY(${10 + rotationY * 2}deg) rotateX(${rotationX}deg)`;
        } else if (card.classList.contains('mineral-card--copper')) {
          card.style.transform = `translateZ(-120px) rotateY(${-15 + rotationY * 2.5}deg) rotateX(${rotationX}deg)`;
        }
      });
      
      requestAnimationFrame(updateParallax);
    }
    
    updateParallax();
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
