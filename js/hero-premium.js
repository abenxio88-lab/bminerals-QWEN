/**
 * Balochistan Minerals - Premium Hero Interactions
 * Million Dollar Experience Enhancement
 * Senior UI Developer Implementation
 */

document.addEventListener('DOMContentLoaded', () => {
  initMineralCards();
  initParticles();
  initStatsCounter();
});

/**
 * 3D Mineral Cards Parallax Effect
 * Creates subtle mouse-follow movement for premium feel
 */
function initMineralCards() {
  const showcase = document.querySelector('.mineral-showcase');
  const cards = document.querySelectorAll('.mineral-card');
  
  if (!showcase || !cards.length) return;
  
  let mouseX = 0;
  let mouseY = 0;
  let currentX = 0;
  let currentY = 0;
  
  // Track mouse position relative to showcase center
  showcase.addEventListener('mousemove', (e) => {
    const rect = showcase.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;
    
    mouseX = (e.clientX - centerX) / 30;
    mouseY = (e.clientY - centerY) / 30;
  });
  
  showcase.addEventListener('mouseleave', () => {
    mouseX = 0;
    mouseY = 0;
  });
  
  // Smooth animation loop
  function animate() {
    currentX += (mouseX - currentX) * 0.05;
    currentY += (mouseY - currentY) * 0.05;
    
    cards.forEach((card, index) => {
      const depth = (index + 1) * 0.5;
      const rotateY = currentX * depth;
      const rotateX = -currentY * depth;
      
      card.style.transform = `translateX(${currentX * depth}px) translateY(${currentY * depth}px) rotateY(${rotateY}deg) rotateX(${rotateX}deg)`;
    });
    
    requestAnimationFrame(animate);
  }
  
  animate();
}

/**
 * Enhanced Particle System
 * Dynamic mineral particle generation with optimized performance
 */
function initParticles() {
  const container = document.querySelector('.hero-particle-container');
  if (!container) return;
  
  // Create additional particles dynamically
  const particleTypes = ['', '--copper', '--chromite', '--barite'];
  const particleCount = 12;
  
  for (let i = 0; i < particleCount; i++) {
    const particle = document.createElement('div');
    const type = particleTypes[Math.floor(Math.random() * particleTypes.length)];
    const size = Math.random() * 6 + 4;
    const left = Math.random() * 100;
    const delay = Math.random() * -20;
    const duration = Math.random() * 10 + 15;
    
    particle.className = `hero-particle${type}`;
    particle.style.cssText = `
      width: ${size}px;
      height: ${size}px;
      left: ${left}%;
      animation-delay: ${delay}s;
      animation-duration: ${duration}s;
    `;
    
    container.appendChild(particle);
  }
}

/**
 * Animated Stats Counter
 * Smooth number counting animation with easing
 */
function initStatsCounter() {
  const statNumbers = document.querySelectorAll('.stat-item__number');
  if (!statNumbers.length) return;
  
  const observerOptions = {
    threshold: 0.5,
    rootMargin: '0px'
  };
  
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const statNumber = entry.target;
        const text = statNumber.textContent.trim();
        const match = text.match(/(\d+[KMB]?)(\+)?/);
        
        if (match) {
          const target = parseNumber(match[1]);
          const suffix = match[2] || '';
          
          animateCounter(statNumber, 0, target, suffix);
          observer.unobserve(statNumber);
        }
      }
    });
  }, observerOptions);
  
  statNumbers.forEach(stat => observer.observe(stat));
}

/**
 * Parse number with K/M/B suffixes
 */
function parseNumber(str) {
  const suffixMultipliers = {
    'K': 1000,
    'M': 1000000,
    'B': 1000000000
  };
  
  const suffix = str.slice(-1).toUpperCase();
  const multiplier = suffixMultipliers[suffix] || 1;
  const number = parseFloat(suffix.match(/[KMB]/i) ? str.slice(0, -1) : str);
  
  return number * multiplier;
}

/**
 * Animate counter with smooth easing
 */
function animateCounter(element, start, end, suffix) {
  const duration = 2000;
  const startTime = performance.now();
  
  // Easing function: easeOutExpo
  const easeOutExpo = (t) => {
    return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
  };
  
  function update(currentTime) {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);
    const easedProgress = easeOutExpo(progress);
    
    const current = Math.floor(start + (end - start) * easedProgress);
    
    // Format number with K suffix if needed
    const displayValue = current >= 1000 
      ? `${(current / 1000).toFixed(0)}K` 
      : current.toString();
    
    element.innerHTML = `${displayValue}<span class="stat-item__suffix">${suffix}</span>`;
    
    if (progress < 1) {
      requestAnimationFrame(update);
    } else {
      // Ensure final value is exact
      const finalDisplay = end >= 1000 
        ? `${(end / 1000).toFixed(0)}K` 
        : end.toString();
      element.innerHTML = `${finalDisplay}<span class="stat-item__suffix">${suffix}</span>`;
    }
  }
  
  requestAnimationFrame(update);
}

/**
 * Add hover sound effect (optional, muted by default)
 * For premium tactile feedback
 */
function playHoverSound() {
  // Uncomment to enable subtle hover sounds
  // const audio = new Audio('/sounds/hover.mp3');
  // audio.volume = 0.1;
  // audio.play().catch(() => {});
}

// Optional: Add to CTA buttons
document.querySelectorAll('.hero-premium__cta').forEach(btn => {
  btn.addEventListener('mouseenter', playHoverSound);
});
