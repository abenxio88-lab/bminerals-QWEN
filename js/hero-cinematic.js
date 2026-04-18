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
   * Mineral card hover interactions
   */
  function initMineralCards(container) {
    const cards = container.querySelectorAll('.mineral-card');
    
    cards.forEach(card => {
      card.addEventListener('mouseenter', () => {
        cards.forEach(c => {
          if (c !== card) {
            c.style.opacity = '0.3';
            c.style.transform = 'scale(0.95) translateZ(-100px)';
          }
        });
      });
      
      card.addEventListener('mouseleave', () => {
        cards.forEach(c => {
          c.style.opacity = '';
          c.style.transform = '';
        });
      });
    });
  }

  /**
   * Subtle mouse parallax effect
   */
  function initParallax(container) {
    const visual = container.querySelector('.hero-visual');
    const cards = container.querySelectorAll('.mineral-card');
    
    if (!visual || cards.length === 0) return;

    let mouseX = 0;
    let mouseY = 0;
    let currentX = 0;
    let currentY = 0;

    document.addEventListener('mousemove', (e) => {
      const rect = visual.getBoundingClientRect();
      const x = (e.clientX - rect.left) / rect.width - 0.5;
      const y = (e.clientY - rect.top) / rect.height - 0.5;
      
      mouseX = x * 30;
      mouseY = y * 20;
    });

    function animate() {
      // Smooth interpolation
      currentX += (mouseX - currentX) * 0.05;
      currentY += (mouseY - currentY) * 0.05;
      
      cards.forEach((card, index) => {
        const depth = (index + 1) * 0.5;
        const rotateY = currentX * depth;
        const rotateX = -currentY * depth;
        
        if (card.classList.contains('mineral-card--primary')) {
          card.style.transform = `translate(-50%, -50%) translateZ(0) rotateY(${rotateY}deg) rotateX(${rotateX}deg)`;
        } else if (card.classList.contains('mineral-card--secondary')) {
          card.style.transform = `translateZ(-50px) rotateY(${-10 + rotateY * 1.5}deg) rotateX(${rotateX}deg)`;
        } else if (card.classList.contains('mineral-card--tertiary')) {
          card.style.transform = `translateZ(-80px) rotateY(${10 + rotateY * 2}deg) rotateX(${rotateX}deg)`;
        }
      });
      
      requestAnimationFrame(animate);
    }
    
    animate();
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
