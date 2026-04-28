/**
 * Balochistan Minerals Premium Animations
 * Powered by GSAP & ScrollTrigger when available.
 */

/**
 * Initialize all premium animations
 */
export function initPremiumAnimations() {
  const gsap = window.gsap;
  const ScrollTrigger = window.ScrollTrigger;

  if (!gsap || !ScrollTrigger) {
    return;
  }

  gsap.registerPlugin(ScrollTrigger);

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

  // 7. INTERACTIVE TIMELINE (ScrollTrigger)
  const timelineItems = document.querySelectorAll('.timeline__item');
  if (timelineItems.length > 0) {
    timelineItems.forEach((item, i) => {
        gsap.from(item, {
            scrollTrigger: {
                trigger: item,
                start: "top 80%",
            },
            x: i % 2 === 0 ? -100 : 100,
            opacity: 0,
            duration: 1.2,
            ease: "power2.out"
        });
    });
  }

  // 8. B2B FREIGHT CALCULATOR MOCK LOGIC
  const originSelect = document.getElementById('origin-select');
  const destSelect = document.getElementById('dest-select');
  const freightDays = document.getElementById('freight-days');

  if (originSelect && destSelect && freightDays) {
    const updateFreight = () => {
        const o = originSelect.value;
        const d = destSelect.value;
        let days = 10;
        
        if (o === 'chagai') days += 5;
        if (o === 'muslim-bagh' && d === 'gwadar') days += 4;
        if (d === 'karachi') days -= 2;

        gsap.to(freightDays, {
            innerText: days,
            duration: 1,
            snap: { innerText: 1 },
            ease: "power2.inOut"
        });
    };

    originSelect.addEventListener('change', updateFreight);
    destSelect.addEventListener('change', updateFreight);
  }

  // 9. 3D MINERAL PREVIEW MOUSE-TRACKING (MOCK)
  const mineral3D = document.querySelector('.mineral-object');
  const modal3D = document.getElementById('modal-3d');
  
  if (mineral3D && modal3D) {
    modal3D.addEventListener('mousemove', (e) => {
        const x = (e.clientX / window.innerWidth - 0.5) * 60;
        const y = (e.clientY / window.innerHeight - 0.5) * -60;
        gsap.to(mineral3D, {
            duration: 0.5,
            rotateY: x,
            rotateX: y,
            ease: "power2.out"
        });
    });
  }
}

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPremiumAnimations);
} else {
  initPremiumAnimations();
}
