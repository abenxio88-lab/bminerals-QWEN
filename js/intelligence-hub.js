/**
 * Balochistan Minerals - Intelligence Hub Logic
 * Handles ESG bar animations and interactive hub components.
 */

export function initIntelligenceHub() {
  // 1. ESG ProgressBar Animation on Reveal
  const esgSection = document.querySelector('.esg-dashboard');
  if (esgSection) {
    const bars = esgSection.querySelectorAll('.esg-card__bar-fill');
    
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          // The CSS transition handles the smooth fill
          // We just need to ensure the width is set
          bars.forEach(bar => {
            const width = bar.getAttribute('data-width');
            if (width) bar.style.width = width;
          });
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.2 });

    observer.observe(esgSection);
  }

  // 2. Virtual Mine Tours - Play Button Interaction
  const tourCards = document.querySelectorAll('.tour-card');
  tourCards.forEach(card => {
    card.addEventListener('click', () => {
      const tourName = card.querySelector('.tour-card__name').textContent;
      showTourFeedback(tourName);
    });
  });

  // 3. Shipment Tracker - Simulation Logic (Optional/Premium feel)
  const trackerBtn = document.querySelector('.b2b-card__cta[href="logistics.html"]');
  if (trackerBtn) {
    trackerBtn.addEventListener('click', (e) => {
      // Just a subtle console hint for now to show it's "wired"
      console.log('Shipment tracking simulation requested.');
    });
  }
}

/**
 * Placeholder feedback for tour interactions
 */
function showTourFeedback(name) {
  if (window.tactileFeedback) window.tactileFeedback('heavy');
  
  // Simple toast or alert if needed, but for now we just log
  // In a real app, this would open a Video Modal
  console.log(`Initializing Virtual Tour: ${name}`);
}
