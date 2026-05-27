document.querySelectorAll('.project-category-switch').forEach((switcher) => {
  const buttons = Array.from(switcher.querySelectorAll('[data-project-category]'));
  const panels = Array.from(document.querySelectorAll('[data-project-category-panel]'));

  if (!buttons.length || !panels.length) return;

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

  buttons.forEach((button) => {
    button.addEventListener('click', () => activateCategory(button.dataset.projectCategory));
  });
});
