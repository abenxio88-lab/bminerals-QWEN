/**
 * Balochistan Minerals Component Loader
 * Standardizes layout across all pages and handles component-specific logic.
 */

export async function initLayout() {
  // Navigation and Footer are now injected directly into the HTML to avoid file:/// CORS issues
  // Call highlight active link to properly mark current page
  highlightActiveLink();
}

/**
 * Automatically adds 'active' class to the navigation link matching current URL
 */
function highlightActiveLink() {
  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  const links = document.querySelectorAll('.navbar__link, .navbar__dropdown-item');

  links.forEach(link => {
    const href = link.getAttribute('href');
    if (href === currentPath || href === `./${currentPath}`) {
      link.classList.add('active');
      
      // If it's a dropdown item, also highlight the parent trigger
      const dropdownGroup = link.closest('.navbar__dropdown-group');
      if (dropdownGroup) {
        const trigger = dropdownGroup.querySelector('.navbar__dropdown-trigger');
        if (trigger) trigger.classList.add('active');
      }
    }
  });
}
