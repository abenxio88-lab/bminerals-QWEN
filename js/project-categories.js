document.querySelectorAll('.project-category-switch').forEach((switcher) => {
  const buttons = Array.from(switcher.querySelectorAll('[data-project-category]'));
  const panels = Array.from(document.querySelectorAll('[data-project-category-panel]'));

  if (!buttons.length || !panels.length) return;

  const categoryByHashPrefix = {
    '#muslim-bagh': 'metallic',
    '#dilband': 'metallic',
    '#chagai': 'metallic',
    '#antimony': 'metallic',
    '#lead-zinc': 'metallic',
    '#industrial-': 'industrial',
    '#stone-': 'stones',
    '#energy-': 'energy'
  };

  const activateCategory = (category) => {
    buttons.forEach((button) => {
      const isActive = button.dataset.projectCategory === category;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', String(isActive));
    });

    panels.forEach((panel) => {
      const isActive = panel.dataset.projectCategoryPanel === category;
      panel.classList.toggle('is-active', isActive);
      panel.hidden = !isActive;
    });
  };

  const activateCategoryFromHash = () => {
    const hash = window.location.hash;
    if (!hash) return;

    const match = Object.entries(categoryByHashPrefix)
      .find(([prefix]) => hash.startsWith(prefix));

    if (match) {
      activateCategory(match[1]);
      window.requestAnimationFrame(() => {
        document.querySelector(hash)?.scrollIntoView({ block: 'start' });
      });
    }
  };

  buttons.forEach((button) => {
    button.addEventListener('click', () => activateCategory(button.dataset.projectCategory));
  });

  activateCategoryFromHash();
  window.addEventListener('hashchange', activateCategoryFromHash);
});
