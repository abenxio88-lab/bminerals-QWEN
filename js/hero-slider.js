/**
 * Hero Image Slider
 * Auto-rotates every 10 seconds with smooth fade transitions
 * Supports manual navigation via arrows and dots
 */
export function initHeroSlider() {
  // Guard: Skip if already initialized
  if (window.__heroSliderInitialized) return;
  window.__heroSliderInitialized = true;

  const slides = document.querySelectorAll('.hero__slide');
  const dots = document.querySelectorAll('.hero__slider-dot');
  const prevArrow = document.querySelector('.hero__slider-arrow--prev');
  const nextArrow = document.querySelector('.hero__slider-arrow--next');

  if (!slides.length) return;

  let currentSlide = 0;
  let autoPlayInterval = null;
  const SLIDE_INTERVAL = 10000; // 10 seconds

  function goToSlide(index) {
    // Remove active state from current
    slides[currentSlide].classList.remove('active');
    if (dots[currentSlide]) dots[currentSlide].classList.remove('active');

    // Wrap around
    currentSlide = (index + slides.length) % slides.length;

    // Add active state to new slide
    slides[currentSlide].classList.add('active');
    if (dots[currentSlide]) dots[currentSlide].classList.add('active');

    // Update dynamic text every 2 slides
    const newDataIndex = Math.min(Math.floor(currentSlide / 2), 2);
    updateContentState(newDataIndex);
  }

  // ==== Dynamic Content Injection logic ====
  const slideContentData = [
    {
      overline: "From the Heart of Balochistan",
      headline: "Extracting Excellence.<br><span class=\"gradient-text\">Powering Pakistan's Future.</span>",
      subheadline: "For over two decades, we've been unlocking Balochistan's mineral wealth—iron ore, chromite, barite & more—with unwavering commitment to ethical practices, community uplift, and global standards.",
      statsTitle: "Operational Snapshot",
      stats: [
        { num: 50000, suffix: "+", label: "MT Annual Capacity", context: "Iron ore, barite, chromite" },
        { num: 25, suffix: "+", label: "Years of Excellence", context: "Since 2001" },
        { num: 60, suffix: "+", label: "Countries Served", context: "Middle East, Asia, Europe" }
      ]
    },
    {
      overline: "Scale & Precision",
      headline: "Advanced Processing.<br><span class=\"gradient-text\">Sustainable Core.</span>",
      subheadline: "Leveraging state-of-the-art heavy machinery and expert geological planning across our Chagai and Muslim Bagh concessions to maximize yield while minimizing footprint.",
      statsTitle: "Extraction Metrics",
      stats: [
        { num: 15, suffix: "+", label: "Active Concessions", context: "Balochistan terrain" },
        { num: 500, suffix: "+", label: "Local Workforce", context: "Community employment" },
        { num: 99, suffix: "%", label: "Safety Compliant", context: "Zero major incidents" }
      ]
    },
    {
      overline: "Seamless Supply Chain",
      headline: "Global Reach.<br><span class=\"gradient-text\">Unmatched Reliability.</span>",
      subheadline: "From the rugged mountains to Gwadar Port, our fully integrated logistics fleet ensures uninterrupted supply chains, delivering premium raw materials worldwide.",
      statsTitle: "Logistics Hub",
      stats: [
        { num: 200, suffix: "+", label: "Fleet Vehicles", context: "Heavy transport" },
        { num: 24, suffix: "/7", label: "Port Logistics", context: "Gwadar & Karachi" },
        { num: 100, suffix: "%", label: "Export Grade", context: "Global quality standards" }
      ]
    }
  ];

  const heroLeftContent = document.querySelector('.hero__left');
  const heroRightContent = document.querySelector('.hero__right');
  
  const overlineEl = heroLeftContent?.querySelector('.hero__overline');
  const headlineEl = heroLeftContent?.querySelector('.hero__headline');
  const subheadlineEl = heroLeftContent?.querySelector('.hero__subheadline');
  
  const statsTitleEl = heroRightContent?.querySelector('.hero__stats-panel-title');
  const statNumEls = heroRightContent?.querySelectorAll('.panel-stat__number');
  const statLabelEls = heroRightContent?.querySelectorAll('.panel-stat__label');
  const statCtxEls = heroRightContent?.querySelectorAll('.panel-stat__context');

  let currentDataIndex = 0;

  function updateContentState(newIndex) {
    if (newIndex === currentDataIndex) return; // Skip if it's the exact same data group
    currentDataIndex = newIndex;

    const data = slideContentData[newIndex];
    if (typeof window.gsap === 'undefined' || !heroLeftContent || !heroRightContent) return;

    // Smooth staggered fade out
    window.gsap.to([heroLeftContent, heroRightContent], {
      opacity: 0,
      y: 15,
      duration: 0.3,
      onComplete: () => {
        // Swap core text
        if (overlineEl) overlineEl.innerHTML = data.overline;
        if (headlineEl) headlineEl.innerHTML = data.headline;
        if (subheadlineEl) subheadlineEl.innerHTML = data.subheadline;
        if (statsTitleEl) statsTitleEl.innerHTML = data.statsTitle;

        // Swap stats and recount
        if (statNumEls && statLabelEls && statCtxEls) {
          data.stats.forEach((statObj, i) => {
            if (statNumEls[i]) {
              statNumEls[i].dataset.target = statObj.num;
              statNumEls[i].dataset.suffix = statObj.suffix;
              statNumEls[i].textContent = "0" + statObj.suffix; // Reset instantly
              
              // Count up using GSAP proxy
              window.gsap.to({ value: 0 }, {
                value: statObj.num,
                duration: 1.5,
                delay: 0.2, // wait for fade in
                ease: "power3.out",
                onUpdate: function() {
                  statNumEls[i].textContent = Math.floor(this.targets()[0].value).toLocaleString() + statObj.suffix;
                }
              });
            }
            if (statLabelEls[i]) statLabelEls[i].textContent = statObj.label;
            if (statCtxEls[i]) statCtxEls[i].textContent = statObj.context;
          });
        }

        // Fade gracefully back in
        window.gsap.to([heroLeftContent, heroRightContent], {
          opacity: 1,
          y: 0,
          duration: 0.5,
          ease: "power2.out"
        });
      }
    });
  }

  function nextSlide() {
    goToSlide(currentSlide + 1);
  }

  function prevSlide() {
    goToSlide(currentSlide - 1);
  }

  function startAutoPlay() {
    stopAutoPlay();
    autoPlayInterval = setInterval(nextSlide, SLIDE_INTERVAL);
  }

  function stopAutoPlay() {
    if (autoPlayInterval) {
      clearInterval(autoPlayInterval);
      autoPlayInterval = null;
    }
  }

  // Event listeners
  if (nextArrow) {
    nextArrow.addEventListener('click', () => {
      nextSlide();
      startAutoPlay(); // Reset timer
    });
  }

  if (prevArrow) {
    prevArrow.addEventListener('click', () => {
      prevSlide();
      startAutoPlay(); // Reset timer
    });
  }

  dots.forEach((dot, index) => {
    dot.addEventListener('click', () => {
      goToSlide(index);
      startAutoPlay(); // Reset timer
    });
  });

  // Keyboard navigation
  document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') {
      prevSlide();
      startAutoPlay();
    } else if (e.key === 'ArrowRight') {
      nextSlide();
      startAutoPlay();
    }
  });

  // Pause on hover
  const heroSection = document.querySelector('.hero');
  if (heroSection) {
    heroSection.addEventListener('mouseenter', stopAutoPlay);
    heroSection.addEventListener('mouseleave', startAutoPlay);
  }

  // Touch support for mobile
  let touchStartX = 0;
  let touchEndX = 0;

  if (heroSection) {
    heroSection.addEventListener('touchstart', (e) => {
      touchStartX = e.changedTouches[0].screenX;
      stopAutoPlay();
    }, { passive: true });

    heroSection.addEventListener('touchend', (e) => {
      touchEndX = e.changedTouches[0].screenX;
      const diff = touchStartX - touchEndX;

      if (Math.abs(diff) > 50) {
        if (diff > 0) {
          nextSlide();
        } else {
          prevSlide();
        }
      }

      startAutoPlay();
    }, { passive: true });
  }

  // Start autoplay
  startAutoPlay();
}
