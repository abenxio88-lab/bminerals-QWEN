/**
 * Balochistan Minerals - Tactile Feedback
 * Magnetic Buttons & Slow Ghost-Crawl Beams
 */

export function initTactileFeedback() {
  // 1. MAGNETIC BUTTONS - REMOVED PER USER REQUEST FOR PROFESSIONAL STABILITY
  // (Buttons now use high-end CSS hover effects in premium-polish.css)

  // 2. SLOW GHOST-CRAWL BORDER BEAMS
  const bentoPanels = document.querySelectorAll('.glass-bento');
  
  bentoPanels.forEach(panel => {
    // We target the pseudo-element's background angle if possible, 
    // but a cleaner way is to use a CSS variable for the gradient angle.
    panel.style.setProperty('--beam-angle', '0deg');
    
    gsap.to(panel, {
      '--beam-angle': '360deg',
      duration: 15, // Ultra slow ghost crawl
      repeat: -1,
      ease: "none",
      onUpdate: function() {
        const beam = panel.querySelector('.border-beam');
        if (beam) {
           const angle = panel.style.getPropertyValue('--beam-angle');
           beam.style.setProperty('--current-angle', angle);
        }
      }
    });
  });
}

// Auto-init
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initTactileFeedback);
} else {
  initTactileFeedback();
}
