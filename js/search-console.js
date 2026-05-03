/**
 * Search Console Module
 * Handles site-wide search functionality with modal interface
 */

export function initSearchConsole() {
  if (window.__searchConsoleInitialized) return;
  window.__searchConsoleInitialized = true;

  const searchBtn = document.querySelector('.navbar__search-btn');
  const searchConsole = document.querySelector('.search-console');
  const closeBtn = document.querySelector('.search-console__close');
  const searchInput = document.querySelector('.search-console__input');
  const resultsContainer = document.querySelector('.search-console__results');

  if (!searchBtn || !searchConsole || !searchInput || !resultsContainer) return;

  const searchDatabase = [
    // Pages
    { id: 'home', title: 'Home', description: 'Homepage', url: 'index.html', category: 'Pages', icon: 'HM' },
    { id: 'products', title: 'Products', description: 'Our mineral products', url: 'products.html', category: 'Pages', icon: 'PR' },
    { id: 'projects', title: 'Projects', description: 'Mining projects', url: 'projects.html', category: 'Pages', icon: 'PJ' },
    { id: 'our-mines', title: 'Our Mines', description: 'Mining operations', url: 'our-mines.html', category: 'Pages', icon: 'MN' },
    { id: 'logistics', title: 'Logistics', description: 'Supply chain & transport', url: 'logistics.html', category: 'Pages', icon: 'LG' },
    { id: 'investors', title: 'Investors', description: 'Investor information & reports', url: 'investors.html', category: 'Pages', icon: 'IR' },
    { id: 'about', title: 'About Us', description: 'Company information', url: 'about.html', category: 'Pages', icon: 'AB' },
    { id: 'blogs', title: 'Blogs', description: 'Industry insights & articles', url: 'blogs.html', category: 'Pages', icon: 'BL' },
    { id: 'contact', title: 'Contact', description: 'Get in touch', url: 'contact.html', category: 'Pages', icon: 'CT' },
    { id: 'sustainability', title: 'Sustainability', description: 'ESG & environmental practices', url: 'sustainability.html', category: 'Pages', icon: 'ESG' },

    // Products
    { id: 'iron-ore', title: 'Iron Ore', description: '62-68% Fe Grade from Pakistan', url: 'products.html#iron-ore', category: 'Products', icon: 'Fe' },
    { id: 'barite', title: 'Barite', description: 'API Standard 95% purity', url: 'products.html#barite', category: 'Products', icon: 'Ba' },
    { id: 'chromite', title: 'Chromite', description: '46-48% Cr2O3 concentration', url: 'products.html#chromite', category: 'Products', icon: 'Cr' },
    { id: 'copper', title: 'Copper', description: 'Chagai exploration opportunities', url: 'products.html#copper', category: 'Products', icon: 'Cu' },

    // Mines/Projects
    { id: 'muslim-bagh', title: 'Muslim Bagh', description: 'Chromite Mine - Active Operations', url: 'our-mines.html#muslim-bagh', category: 'Mines', icon: 'MB' },
    { id: 'khuzdar', title: 'Khuzdar', description: 'Barite Operations in Balochistan', url: 'our-mines.html#khuzdar', category: 'Mines', icon: 'KZ' },
    { id: 'chagai', title: 'Chagai', description: 'Copper-Gold Exploration Belt', url: 'our-mines.html#chagai', category: 'Mines', icon: 'CH' },

    // Blogs
    { id: 'blog-1', title: 'AI Automation in Deep Pit Mining', description: 'Exploring automation technologies', url: 'blog/ai-automation-deep-pit-mining.html', category: 'Articles', icon: 'AI' },
    { id: 'blog-2', title: 'Barite for Modern Drilling', description: 'Barite applications in oil & gas', url: 'blog/barite-modern-drilling.html', category: 'Articles', icon: 'AR' },
    { id: 'blog-3', title: 'Chromite for High-Grade Alloys', description: 'Industrial applications of chromite', url: 'blog/chromite-high-grade-alloys.html', category: 'Articles', icon: 'AR' },
    { id: 'blog-4', title: 'Copper in the Electric Revolution', description: 'Copper demand and EV market', url: 'blog/copper-electric-revolution.html', category: 'Articles', icon: 'AR' },
    { id: 'blog-5', title: 'Empowering Local Communities', description: 'Mining ethics and community impact', url: 'blog/empowering-local-communities-mining.html', category: 'Articles', icon: 'AR' },
    { id: 'blog-6', title: 'Land Reclamation After Mining', description: 'Environmental restoration practices', url: 'blog/land-reclamation-life-after-mining.html', category: 'Articles', icon: 'AR' },
    { id: 'blog-7', title: 'Pakistan in Global Supply Chain', description: 'Pakistan\'s mineral market position', url: 'blog/pakistan-global-mineral-supply-chain.html', category: 'Articles', icon: 'AR' },
    { id: 'blog-8', title: 'Gwadar Port & Exports', description: 'Streamlining exports through Gwadar', url: 'blog/streamlining-exports-gwadar-port.html', category: 'Articles', icon: 'AR' },
    { id: 'blog-9', title: 'Sustainable Mining in Balochistan', description: 'Sustainability initiatives', url: 'blog/sustainable-mining-balochistan.html', category: 'Articles', icon: 'AR' },
    { id: 'blog-10', title: 'Vision Zero Workplace Safety', description: 'Safety practices and HSE', url: 'blog/vision-zero-workplace-accidents.html', category: 'Articles', icon: 'AR' },

    // Investor Resources
    { id: 'annual-report', title: 'Annual Report 2025', description: 'Financial statements & performance', url: 'documents/annual-report-2025.html', category: 'Resources', icon: 'FY' },
    { id: 'esg-report', title: 'ESG Sustainability Report', description: 'Environmental & social governance', url: 'documents/esg-sustainability-report.html', category: 'Resources', icon: 'ESG' },
    { id: 'hse-policy', title: 'HSE Policy', description: 'Health, Safety & Environment', url: 'documents/hse-policy-performance.html', category: 'Resources', icon: 'HSE' },
  ];

  function escapeHtml(value) {
    return value.replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    }[char]));
  }

  function filterResults(query) {
    if (!query.trim()) return [];

    const lowerQuery = query.toLowerCase();

    return searchDatabase.filter((item) => {
      const titleMatch = item.title.toLowerCase().includes(lowerQuery);
      const descMatch = item.description.toLowerCase().includes(lowerQuery);
      return titleMatch || descMatch;
    });
  }

  function groupByCategory(results) {
    return results.reduce((grouped, item) => {
      if (!grouped[item.category]) grouped[item.category] = [];
      grouped[item.category].push(item);
      return grouped;
    }, {});
  }

  function highlightMatch(text, query) {
    if (!query.trim()) return escapeHtml(text);

    const safeQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${safeQuery})`, 'gi');
    return escapeHtml(text).replace(regex, '<span class="search-result-item__highlight">$1</span>');
  }

  function renderResults(query) {
    const results = filterResults(query);
    const safeQuery = escapeHtml(query);

    if (!results.length) {
      resultsContainer.innerHTML = `
        <div class="search-console__no-results">
          <div class="search-console__no-results-icon" aria-hidden="true"></div>
          <p>No results found for "${safeQuery}"</p>
          <p style="font-size: 0.85rem; margin-top: 0.5rem; opacity: 0.7;">Try searching for products, pages, or articles</p>
        </div>
      `;
      return;
    }

    const grouped = groupByCategory(results);
    let html = '';

    Object.keys(grouped).forEach((category) => {
      html += `<div class="search-results-category">
        <div class="search-results-category__title">${escapeHtml(category)}</div>
      `;

      grouped[category].forEach((item) => {
        html += `
          <a href="${escapeHtml(item.url)}" class="search-result-item">
            <div class="search-result-item__icon" aria-hidden="true">${escapeHtml(item.icon)}</div>
            <div class="search-result-item__content">
              <div class="search-result-item__title">${highlightMatch(item.title, query)}</div>
              <div class="search-result-item__description">${highlightMatch(item.description, query)}</div>
            </div>
          </a>
        `;
      });

      html += '</div>';
    });

    resultsContainer.innerHTML = html;
  }

  function openSearch() {
    searchConsole.classList.add('open');
    searchInput.focus();
    document.body.style.overflow = 'hidden';
  }

  function closeSearch() {
    searchConsole.classList.remove('open');
    searchInput.value = '';
    resultsContainer.innerHTML = '';
    document.body.style.overflow = '';
  }

  searchBtn.addEventListener('click', openSearch);
  closeBtn?.addEventListener('click', closeSearch);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && searchConsole.classList.contains('open')) {
      closeSearch();
    }

    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      openSearch();
    }
  });

  searchConsole.addEventListener('click', (e) => {
    if (e.target === searchConsole) {
      closeSearch();
    }
  });

  searchInput.addEventListener('input', (e) => {
    renderResults(e.target.value);
  });

  resultsContainer.addEventListener('click', (e) => {
    const resultItem = e.target.closest('.search-result-item');
    if (resultItem) closeSearch();
  });
}
