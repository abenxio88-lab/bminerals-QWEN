const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const gsap = window.gsap;
const ScrollTrigger = window.ScrollTrigger;
const hasGsap = Boolean(gsap && ScrollTrigger);
const mobileExtraMotion = window.matchMedia('(max-width: 768px)').matches;

window.__sceneHomeEnhanced = true;

function initHomepageScroll() {
  if (window.__smoothScrollingInitialized || window.__lenisEnabled || prefersReduced || !window.Lenis) return null;
  window.__smoothScrollingInitialized = true;
  window.__homepageNativeScroll = false;
  window.__homepageLenisResizeSync = null;

  const lenis = new window.Lenis({
    lerp: 0.08,
    duration: 1.15,
    wheelMultiplier: 0.88,
    smoothWheel: true,
    smoothTouch: false,
    prevent: (node) => Boolean(node.closest?.('[data-lenis-prevent], .mineral-media-modal, .mineral-report-modal, .stone-lightbox'))
  });

  const allowBrowserZoom = (event) => {
    if (!event.ctrlKey && !event.metaKey) return;
    event.stopImmediatePropagation();
  };

  window.addEventListener('wheel', allowBrowserZoom, { capture: true, passive: true });

  if (hasGsap) {
    lenis.on('scroll', () => ScrollTrigger.update());
    gsap.ticker.add((time) => {
      lenis.raf(time * 1000);
    });
    gsap.ticker.lagSmoothing(0);
  } else {
    function raf(time) {
      lenis.raf(time);
      requestAnimationFrame(raf);
    }
    requestAnimationFrame(raf);
  }

  window.__bmLenis = lenis;
  window.__lenisEnabled = true;
  window.__homepageLenisStable = true;
  window.__homepageSmoothWheel = false;
  window.__homepageStopSmoothWheel = null;
  document.documentElement.classList.add('lenis-enhanced');

  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', (event) => {
      const href = anchor.getAttribute('href');
      if (!href || href === '#') return;
      const target = document.querySelector(href);
      if (!target) return;
      event.preventDefault();
      const navbarHeight = document.querySelector('.navbar')?.offsetHeight || 88;
      lenis.scrollTo(target, {
        offset: -navbarHeight,
        duration: 1.15
      });
    });
  });

  return lenis;
}

function animateCounterValue(el, options = {}) {
  if (!el || el.dataset.sceneCounted === 'true') return;
  const target = Number(el.getAttribute('data-target') || 0);
  const suffix = el.getAttribute('data-suffix') || '';
  const delay = options.delay || 0;

  el.dataset.sceneCounted = 'true';

  if (!hasGsap || prefersReduced || options.skipAnimation) {
    el.textContent = `${target.toLocaleString()}${suffix}`;
    return;
  }

  gsap.fromTo(
    el,
    { innerText: 0 },
    {
      innerText: target,
      delay,
      duration: options.duration || 1.7,
      snap: { innerText: 1 },
      ease: 'power2.out',
      onUpdate: () => {
        const n = Math.round(Number(el.innerText));
        el.textContent = `${n.toLocaleString()}${suffix}`;
      },
      onComplete: () => {
        el.textContent = `${target.toLocaleString()}${suffix}`;
      }
    }
  );
}

function initHeroSnapshotStats() {
  document
    .querySelectorAll('.hero__stats-panel .panel-stat__number[data-target]')
    .forEach((el, index) => animateCounterValue(el, {
      delay: 0.55 + index * 0.08,
      duration: 1.45,
      skipAnimation: mobileExtraMotion
    }));
}

function initHeroScene() {
  const hero = document.querySelector('.hero');
  if (!hero || !hasGsap || prefersReduced) return;

  const bgImages = hero.querySelectorAll('.hero__background-image');

  bgImages.forEach((image, idx) => {
    gsap.to(image, {
      yPercent: 10 + idx * 1.2,
      scale: 1.1,
      ease: 'none',
      scrollTrigger: {
        trigger: hero,
        start: 'top top',
        end: 'bottom top',
        scrub: 1.4
      }
    });
  });
}

function initDataFreshnessFade() {
  const banner = document.querySelector('.data-freshness');
  const hero = document.querySelector('.hero');
  if (!banner || !hero || window.innerWidth <= 768) return;

  let ticking = false;

  const updateBannerState = () => {
    ticking = false;

    const scrollY = window.scrollY || window.pageYOffset || 0;
    const heroTop = hero.offsetTop;
    const heroHeight = hero.offsetHeight;
    const fadeStart = heroTop + heroHeight * 0.58;
    const fadeEnd = heroTop + heroHeight * 0.96;
    const fadeRange = Math.max(1, fadeEnd - fadeStart);
    const progress = Math.min(Math.max((scrollY - fadeStart) / fadeRange, 0), 1);
    const opacity = 1 - progress;

    banner.style.opacity = opacity.toFixed(3);
    banner.style.transform = `translate3d(0, ${(-10 * progress).toFixed(2)}px, 0)`;
    banner.classList.toggle('data-freshness--hidden', progress > 0.98);
    banner.setAttribute('aria-hidden', progress > 0.98 ? 'true' : 'false');
  };

  const requestUpdate = () => {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(updateBannerState);
  };

  updateBannerState();
  window.addEventListener('scroll', requestUpdate, { passive: true });
  window.addEventListener('resize', requestUpdate);
  window.addEventListener('load', requestUpdate);
}

function initCitableReveals() {
  const items = document.querySelectorAll('.reveal');
  if (!items.length) return;

  items.forEach((item) => item.classList.add('revealed'));

  if (!hasGsap || prefersReduced || mobileExtraMotion) {
    return;
  }

  gsap.utils.toArray(items).forEach((el) => {
    if (el.closest('.expertise, #minerals')) {
      return;
    }

    gsap.from(
      el,
      {
        y: 16,
        opacity: 0,
        duration: 0.55,
        ease: 'power2.out',
        immediateRender: false,
        clearProps: 'transform,opacity,visibility,clipPath',
        scrollTrigger: {
          trigger: el,
          start: 'top 96%',
          once: true
        }
      }
    );
  });
}

function buildOperationsScene() {
  // Keep the original process stepper visible; the generated pinned scene drifted from the site styling.
  return;

  const section = document.querySelector('.operations');
  const stepper = section?.querySelector('.operations__stepper');
  if (!section || !stepper) return;
  if (window.innerWidth < 992 || prefersReduced || !hasGsap) return;

  const sourceSteps = Array.from(stepper.querySelectorAll('.step-card'));
  if (!sourceSteps.length) return;

  const scene = document.createElement('div');
  scene.className = 'ops-scene';
  scene.innerHTML = `
    <div class="ops-scene__track">
      <div class="ops-scene__panel">
        <div class="ops-scene__hud">
          <div class="ops-scene__path-wrap">
            <svg class="ops-scene__path" viewBox="0 0 900 220" aria-hidden="true">
              <path id="ore-route" d="M20 170 C130 160, 180 40, 300 60 C420 84, 460 184, 580 170 C670 160, 760 56, 880 72" />
              <circle class="ops-scene__ore-marker" r="8" cx="20" cy="170"></circle>
            </svg>
          </div>
          <div class="ops-scene__labels"></div>
        </div>
        <div class="ops-scene__steps"></div>
      </div>
    </div>
    <div class="ops-scene__spacer" aria-hidden="true"></div>
  `;

  stepper.classList.add('operations__stepper--legacy');
  stepper.setAttribute('aria-hidden', 'true');
  section.querySelector('.operations__container')?.appendChild(scene);

  const labels = scene.querySelector('.ops-scene__labels');
  const stepsContainer = scene.querySelector('.ops-scene__steps');

  sourceSteps.forEach((stepCard, index) => {
    const title = stepCard.querySelector('.step-card__title')?.textContent?.trim() || `Step ${index + 1}`;
    const step = stepCard.querySelector('.step-card__step')?.textContent?.trim() || `Step 0${index + 1}`;
    const desc = stepCard.querySelector('.step-card__description')?.textContent?.trim() || '';
    const label = document.createElement('button');
    label.className = `ops-scene__label ${index === 0 ? 'is-active' : ''}`;
    label.type = 'button';
    label.textContent = title.replace('&', '&');
    labels.appendChild(label);

    const card = document.createElement('article');
    card.className = `ops-scene__step ${index === 0 ? 'is-active' : ''}`;
    card.innerHTML = `
      <span class="ops-scene__step-no">${step}</span>
      <h3 class="ops-scene__step-title">${title}</h3>
      <p class="ops-scene__step-copy">${desc}</p>
    `;
    stepsContainer.appendChild(card);
  });

  const cards = Array.from(stepsContainer.querySelectorAll('.ops-scene__step'));
  const labelNodes = Array.from(labels.querySelectorAll('.ops-scene__label'));
  const route = scene.querySelector('#ore-route');
  const marker = scene.querySelector('.ops-scene__ore-marker');
  const routeLength = route.getTotalLength();
  route.style.strokeDasharray = String(routeLength);
  route.style.strokeDashoffset = String(routeLength);

  const tl = gsap.timeline({
    scrollTrigger: {
      trigger: scene.querySelector('.ops-scene__track'),
      start: 'top top',
      end: '+=260%',
      pin: true,
      scrub: 1.1
    }
  });

  tl.to(route, { strokeDashoffset: 0, duration: 5, ease: 'none' }, 0);

  const markerProxy = { progress: 0 };
  tl.to(
    markerProxy,
    {
      progress: 1,
      duration: 5,
      ease: 'none',
      onUpdate: () => {
        const p = route.getPointAtLength(markerProxy.progress * routeLength);
        marker.setAttribute('cx', String(p.x));
        marker.setAttribute('cy', String(p.y));
      }
    },
    0
  );

  cards.forEach((card, index) => {
    const start = index * 0.92;
    tl.to(card, { yPercent: 0, autoAlpha: 1, duration: 0.5, ease: 'power2.out' }, start);
    if (index > 0) {
      tl.to(cards[index - 1], { yPercent: -18, autoAlpha: 0.25, duration: 0.5, ease: 'power2.out' }, start);
    }
    tl.call(
      () => {
        labelNodes.forEach((n, i) => n.classList.toggle('is-active', i === index));
      },
      [],
      start
    );
  });
}

function initStatsDashboard() {
  const section = document.querySelector('.stats-section');
  if (!section) return;
  window.__counterAnimationInitialized = true;
  window.__sceneHomeCounterManaged = true;

  if (mobileExtraMotion) {
    section.querySelectorAll('.metric-strip__number[data-target]').forEach((el) => {
      animateCounterValue(el, { skipAnimation: true });
    });
    return;
  }

  if (hasGsap && !prefersReduced) {
    ScrollTrigger.create({
      trigger: section,
      start: 'top center',
      once: true,
      onEnter: () => {
        section.querySelectorAll('.metric-strip__number[data-target]').forEach(animateCounterValue);
      }
    });
  } else {
    section.querySelectorAll('.metric-strip__number[data-target]').forEach(animateCounterValue);
  }
}

function initStatsEditorialOverlap() {
  const section = document.querySelector('.stats-section');
  section?.classList.remove('stats-section--metrics-overlap');
}

function init() {
  initHomepageScroll();
  if (hasGsap) gsap.registerPlugin(ScrollTrigger);
  initHeroScene();
  initDataFreshnessFade();
  initHeroSnapshotStats();
  initCitableReveals();
  buildOperationsScene();
  initStatsDashboard();
  initStatsEditorialOverlap();
  if (hasGsap) ScrollTrigger.refresh();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
