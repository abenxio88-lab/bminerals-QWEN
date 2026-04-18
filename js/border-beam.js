/**
 * Border Beam Animation
 * Animates the conic-gradient angle for a premium border effect.
 */

export function initBorderBeam() {
  const beams = document.querySelectorAll('.border-beam');
  if (beams.length === 0) return;

  let angle = 0;
  function animate() {
    angle = (angle + 1) % 360;
    beams.forEach(beam => {
      beam.style.setProperty('--current-angle', `${angle}deg`);
    });
    requestAnimationFrame(animate);
  }

  // Optimize: Only animate when cards are visible or hovered
  // For simplicity, we'll run it globally but we could use IntersectionObserver
  animate();
}


