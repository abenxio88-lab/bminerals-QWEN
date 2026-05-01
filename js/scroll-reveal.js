export function initScrollReveal() {
  // Guard: Skip if already initialized
  if (window.__scrollRevealInitialized) return;

  const reveals = document.querySelectorAll('.reveal');
  if (!reveals.length) return;

  window.__scrollRevealInitialized = true;

  if (typeof IntersectionObserver === 'undefined') {
    reveals.forEach(el => el.classList.add('revealed'));
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('revealed');
        observer.unobserve(entry.target);
      }
    });
  }, {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  });

  reveals.forEach(el => observer.observe(el));
}
