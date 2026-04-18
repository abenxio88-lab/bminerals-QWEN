/**
 * Balochistan Minerals Premium Animations
 * Powered by GSAP & ScrollTrigger with Lenis Smooth Scroll
 */

// Register ScrollTrigger plugin
gsap.registerPlugin(ScrollTrigger);

/**
 * Initialize all premium animations
 */
export function initPremiumAnimations() {
  // 0. Initialize Lenis for Smooth Scrolling
  const lenis = new Lenis({
    duration: 1.2,
    easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)), // https://www.desmos.com/calculator/brs54l4xou
    direction: 'vertical',
    gestureDirection: 'vertical',
    smooth: true,
    mouseMultiplier: 1,
    smoothTouch: false,
    touchMultiplier: 2,
    infinite: false,
  });

  // Keep Lenis and ScrollTrigger in sync
  lenis.on('scroll', ScrollTrigger.update);

  gsap.ticker.add((time) => {
    lenis.raf(time * 1000);
  });

  gsap.ticker.lagSmoothing(0);

  // 1. Smooth Staggered Reveals for Hero Text
  const heroTitle = document.querySelector('.hero__title');
  const heroSubtitle = document.querySelector('.hero__subtitle');
  const heroButtons = document.querySelector('.hero__buttons');

  if (heroTitle) {
    gsap.from(heroTitle, {
      y: 50,
      opacity: 0,
      duration: 1.2,
      ease: "power4.out"
    });
  }

  if (heroSubtitle) {
    gsap.from(heroSubtitle, {
      y: 30,
      opacity: 0,
      duration: 1.2,
      delay: 0.3,
      ease: "power4.out"
    });
  }

  if (heroButtons) {
    gsap.from(heroButtons, {
      y: 20,
      opacity: 0,
      duration: 1,
      delay: 0.6,
      ease: "power4.out"
    });
  }

  // If the cinematic hero module is present, avoid duplicating hero-specific animations
  const hasCinematicHero = !!document.querySelector('.hero-cinematic');

  // 2. Liquid Scroll Reveal for Mineral Cards (skip when cinematic hero handles them)
  if (!hasCinematicHero) {
    const mineralCards = document.querySelectorAll('.mineral-card');
    if (mineralCards.length > 0) {
      gsap.from(mineralCards, {
        scrollTrigger: {
          trigger: ".products__grid",
          start: "top 80%",
          toggleActions: "play none none none"
        },
        y: 60,
        opacity: 0,
        duration: 1,
        stagger: 0.2,
        ease: "power3.out"
      });
    }
  }

  // 3. Staggered Reveals for Sustainability Pillars
  const pillars = document.querySelectorAll('.pillar-item');
  if (pillars.length > 0) {
    gsap.from(pillars, {
      scrollTrigger: {
        trigger: ".sustainability__pillars",
        start: "top 85%",
      },
      scale: 0.9,
      opacity: 0,
      duration: 0.8,
      stagger: 0.15,
      ease: "back.out(1.7)"
    });
  }

  // 4. Parallax Effect for Background Elements
  const parallaxBgs = document.querySelectorAll('.hero__mountain-bg');
  parallaxBgs.forEach(bg => {
    gsap.to(bg, {
      scrollTrigger: {
        trigger: ".hero",
        start: "top top",
        end: "bottom top",
        scrub: true
      },
      y: 200,
      ease: "none"
    });
  });

  // 5. Infinite Trust Marquee
  const trustMarquee = document.querySelector('.trust-bar__marquee');
  if (trustMarquee) {
    gsap.to(trustMarquee, {
      xPercent: -50,
      repeat: -1,
      duration: 20,
      ease: "linear"
    });
  }

  // 6. Premium 3D Magnetic Hover for Hero Glass Card (skip if cinematic hero controls hero)
  if (!hasCinematicHero) {
    const heroLeft = document.querySelector('.hero');
    const glassCard = document.querySelector('.hero__stats-panel');

    if (heroLeft && glassCard) {
      heroLeft.addEventListener('mousemove', (e) => {
        const { clientX, clientY } = e;
        const { left, top, width, height } = glassCard.getBoundingClientRect();
        
        const centerX = left + width / 2;
        const centerY = top + height / 2;
        
        const mouseX = clientX - centerX;
        const mouseY = clientY - centerY;
        
        // Limit rotation to a subtle, premium 5 degrees
        const rotateX = (mouseY / (height / 2)) * -5; 
        const rotateY = (mouseX / (width / 2)) * 5;  
        
        gsap.to(glassCard, {
          duration: 0.5,
          rotateX: rotateX,
          rotateY: rotateY,
          transformPerspective: 1000,
          ease: 'power2.out'
        });
      });

      heroLeft.addEventListener('mouseleave', () => {
        gsap.to(glassCard, {
          duration: 1,
          rotateX: 0,
          rotateY: 0,
          ease: 'elastic.out(1, 0.3)'
        });
      });
    }
  }
}

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPremiumAnimations);
} else {
  initPremiumAnimations();
}
