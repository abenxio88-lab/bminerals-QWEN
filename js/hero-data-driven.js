/**
 * Balochistan Minerals - Data-Driven Hero JavaScript
 * Interactive Geological Visualization & Live Data
 * Senior UI Developer Implementation
 */

class HeroDataDriven {
  constructor() {
    this.heroSection = document.querySelector('.hero-data-driven');
    if (!this.heroSection) return;

    this.geoLayers = document.querySelectorAll('.geo-layer');
    this.mineralCards = document.querySelectorAll('.mineral-stat-card');
    this.floatCards = document.querySelectorAll('.hero-float-card');
    this.dataPills = document.querySelectorAll('.data-pill');
    
    this.init();
  }

  init() {
    this.setupGeoLayersInteraction();
    this.setupMineralCardsAnimation();
    this.setupParallaxEffect();
    this.animateCounters();
    this.setupHoverEffects();
  }

  /**
   * 3D Geo Layers Interaction
   * Adds depth-based hover effects and click interactions
   */
  setupGeoLayersInteraction() {
    const container = document.querySelector('.geo-layers-container');
    if (!container) return;

    this.geoLayers.forEach((layer, index) => {
      layer.addEventListener('mouseenter', () => {
        // Dim other layers
        this.geoLayers.forEach((l, i) => {
          if (i !== index) {
            l.style.opacity = '0.5';
            l.style.filter = 'blur(2px)';
          }
        });
        
        // Highlight active layer
        layer.style.zIndex = '10';
      });

      layer.addEventListener('mouseleave', () => {
        // Reset all layers
        this.geoLayers.forEach(l => {
          l.style.opacity = '1';
          l.style.filter = 'blur(0)';
          l.style.zIndex = '';
        });
      });

      layer.addEventListener('click', () => {
        // Trigger mineral detail view (future enhancement)
        const mineralName = layer.classList.contains('geo-layer--chromite') ? 'chromite' :
                           layer.classList.contains('geo-layer--iron') ? 'iron' :
                           layer.classList.contains('geo-layer--copper') ? 'copper' :
                           layer.classList.contains('geo-layer--barite') ? 'barite' : 'surface';
        
        console.log(`Mineral selected: ${mineralName}`);
        // Could navigate to products.html#mineralName
      });
    });
  }

  /**
   * Animate Mineral Stat Cards on Scroll
   */
  setupMineralCardsAnimation() {
    const observerOptions = {
      root: null,
      rootMargin: '0px',
      threshold: 0.3
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const card = entry.target;
          const fillBar = card.querySelector('.mineral-stat-card__fill');
          const percentage = card.dataset.percentage || '75';
          
          if (fillBar) {
            setTimeout(() => {
              fillBar.style.width = `${percentage}%`;
            }, 300);
          }
          
          observer.unobserve(card);
        }
      });
    }, observerOptions);

    this.mineralCards.forEach(card => {
      observer.observe(card);
    });
  }

  /**
   * Mouse-based Parallax Effect for Float Cards
   */
  setupParallaxEffect() {
    let mouseX = 0;
    let mouseY = 0;
    let currentX = 0;
    let currentY = 0;

    document.addEventListener('mousemove', (e) => {
      const centerX = window.innerWidth / 2;
      const centerY = window.innerHeight / 2;
      
      mouseX = (e.clientX - centerX) / centerX;
      mouseY = (e.clientY - centerY) / centerY;
    });

    const animate = () => {
      // Smooth interpolation
      currentX += (mouseX - currentX) * 0.05;
      currentY += (mouseY - currentY) * 0.05;

      this.floatCards.forEach((card, index) => {
        const depth = index === 0 ? 20 : 15;
        const x = currentX * depth;
        const y = currentY * depth;
        
        card.style.transform = `translate(${x}px, ${y}px)`;
      });

      requestAnimationFrame(animate);
    };

    animate();
  }

  /**
   * Animate Counter Numbers
   */
  animateCounters() {
    const counters = document.querySelectorAll('[data-counter]');
    
    const animateCounter = (counter) => {
      const target = parseInt(counter.dataset.counter);
      const suffix = counter.dataset.suffix || '';
      const duration = 2000;
      const step = target / (duration / 16);
      let current = 0;

      const updateCounter = () => {
        current += step;
        if (current < target) {
          counter.textContent = Math.floor(current).toLocaleString() + suffix;
          requestAnimationFrame(updateCounter);
        } else {
          counter.textContent = target.toLocaleString() + suffix;
        }
      };

      updateCounter();
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });

    counters.forEach(counter => {
      observer.observe(counter);
    });
  }

  /**
   * Enhanced Hover Effects for Data Pills
   */
  setupHoverEffects() {
    this.dataPills.forEach(pill => {
      pill.addEventListener('mouseenter', function() {
        const icon = this.querySelector('.data-pill__icon');
        if (icon) {
          icon.style.transform = 'scale(1.1) rotate(5deg)';
        }
      });

      pill.addEventListener('mouseleave', function() {
        const icon = this.querySelector('.data-pill__icon');
        if (icon) {
          icon.style.transform = 'scale(1) rotate(0deg)';
        }
      });
    });
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    new HeroDataDriven();
  });
} else {
  new HeroDataDriven();
}

// Export for module usage
export default HeroDataDriven;
