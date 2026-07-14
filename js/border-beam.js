/**
 * Border Beam Animation
 * Animates the conic-gradient angle for a premium border effect.
 * Guard: Only initializes once to prevent duplicate animation frames.
 */

export function initBorderBeam() {
  // Guard: Skip if already initialized
  if (window.__borderBeamInitialized) return;
  window.__borderBeamInitialized = true;

  const beams = document.querySelectorAll('.border-beam');
  if (beams.length === 0) return;

  const activeBeams = new Set();
  let angle = 0;
  let running = false;
  let frameId = null;

  function animate() {
    if (!running) return;
    angle = (angle + 1) % 360;
    activeBeams.forEach(beam => {
      beam.style.setProperty('--current-angle', `${angle}deg`);
    });
    if (!activeBeams.size) {
      stop();
      return;
    }
    frameId = requestAnimationFrame(animate);
  }

  function start() {
    if (running) return;
    running = true;
    frameId = requestAnimationFrame(animate);
  }

  function stop() {
    running = false;
    if (frameId) cancelAnimationFrame(frameId);
    frameId = null;
  }

  function activate(beam) {
    activeBeams.add(beam);
    if (!document.hidden) start();
  }

  function deactivate(beam) {
    activeBeams.delete(beam);
    if (!activeBeams.size) stop();
  }

  beams.forEach((beam) => {
    const card = beam.closest('.glass-bento') || beam.parentElement;
    if (!card) return;

    card.addEventListener('pointerenter', () => activate(beam));
    card.addEventListener('pointerleave', () => deactivate(beam));
    card.addEventListener('focusin', () => activate(beam));
    card.addEventListener('focusout', () => deactivate(beam));
  });

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stop();
    else if (activeBeams.size) start();
  });
}
