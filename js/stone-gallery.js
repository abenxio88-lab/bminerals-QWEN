document.querySelectorAll('[data-stone-gallery]').forEach((gallery) => {
  const slides = Array.from(gallery.querySelectorAll('.stone-gallery__slide'));
  const dots = Array.from(gallery.querySelectorAll('.stone-gallery__dots span'));
  const previous = gallery.querySelector('[data-gallery-prev]');
  const next = gallery.querySelector('[data-gallery-next]');

  if (slides.length < 2 || !previous || !next) return;

  let activeIndex = 0;

  const showSlide = (index) => {
    activeIndex = (index + slides.length) % slides.length;

    slides.forEach((slide, slideIndex) => {
      slide.classList.toggle('is-active', slideIndex === activeIndex);
    });

    dots.forEach((dot, dotIndex) => {
      dot.classList.toggle('is-active', dotIndex === activeIndex);
    });
  };

  previous.addEventListener('click', () => showSlide(activeIndex - 1));
  next.addEventListener('click', () => showSlide(activeIndex + 1));
});
