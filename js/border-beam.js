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

  let angle = 0;
  let running = false;
  let frameId = null;

  function animate() {
    if (!running) return;
    angle = (angle + 1) % 360;
    beams.forEach(beam => {
      beam.style.setProperty('--current-angle', `${angle}deg`);
    });
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

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stop();
    else start();
  });

  start();
}

