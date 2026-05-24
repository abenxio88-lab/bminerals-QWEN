import { highlightActiveLink, isMobile } from './utils.js';

const productMenuItems = [
  { title: 'Copper', detail: '2% - 10% Cu lumps', url: 'product-metallic.html#copper', code: 'Cu', group: 'Metallic' },
  { title: 'Chromite', detail: '12% - 52% Cr2O3', url: 'product-metallic.html#chromite', code: 'Cr', group: 'Metallic' },
  { title: 'Iron Ore', detail: '40% - 62% Fe', url: 'product-metallic.html#iron-ore', code: 'Fe', group: 'Metallic' },
  { title: 'Antimony', detail: '4% - 58% Sb lumps', url: 'product-metallic.html#antimony', code: 'Sb', group: 'Metallic' },
  { title: 'Lead / Zinc', detail: '10% - 60% Pb/Zn', url: 'product-metallic.html#lead-zinc', code: 'Pb', group: 'Metallic' },
  { title: 'Barite', detail: '92% - 98% BaSO4', url: 'product-industrial.html#barite', code: 'Ba', group: 'Industrial' },
  { title: 'Fluorite', detail: '50% - 90%+ CaF2', url: 'product-industrial.html#fluorite', code: 'Ca', group: 'Industrial' },
  { title: 'Gypsum', detail: '90% - 95% typical', url: 'product-industrial.html#gypsum', code: 'Gy', group: 'Industrial' },
  { title: 'Magnesite', detail: '42% - 47% raw MgO', url: 'product-industrial.html#magnesite', code: 'Mg', group: 'Industrial' },
  { title: 'Sulphur', detail: 'COA based grade', url: 'product-industrial.html#sulphur', code: 'S', group: 'Industrial' },
  { title: 'Bauxite', detail: '45% - 62% Al2O3', url: 'product-industrial.html#bauxite', code: 'Al', group: 'Industrial' },
  { title: 'Celestite', detail: '80% - 95% SrSO4', url: 'product-industrial.html#celestite', code: 'Sr', group: 'Industrial' },
  { title: 'Marble', detail: 'Blocks, slabs and tiles', url: 'product-stones.html#marble', code: 'Mr', group: 'Stones' },
  { title: 'White Marble', detail: 'Premium bright selection', url: 'product-stones.html#white-marble', code: 'Wm', group: 'Stones' },
  { title: 'Onyx', detail: 'Decorative translucent stone', url: 'product-stones.html#onyx', code: 'Ox', group: 'Stones' },
  { title: 'Granite', detail: 'Blocks and slabs', url: 'product-stones.html#granite', code: 'Gr', group: 'Stones' },
  { title: 'Coal Fields', detail: 'Quetta, Harnai, Duki, Mach', url: 'product-energy.html#energy-minerals', code: 'Co', group: 'Energy' }
];

const detailDropdownMenus = {
  Projects: [
    { title: 'Muslim Bagh', detail: 'Chromite Mine - Active', url: 'projects.html#muslim-bagh', code: 'MB' },
    { title: 'Khuzdar', detail: 'Barite Operations', url: 'projects.html#khuzdar', code: 'Kh' },
    { title: 'Chagai', detail: 'Copper-Gold Exploration', url: 'projects.html#chagai', code: 'Ch' },
    { title: 'View All Projects', detail: 'Project portfolio', url: 'projects.html', code: 'All', cta: true }
  ],
  Investors: [
    { title: 'Financial Results', detail: 'Latest company performance', url: 'investors.html', code: 'FR' },
    { title: 'Annual Reports', detail: 'Reports and documents', url: 'investors.html', code: 'AR' },
    { title: 'Sustainability', detail: 'ESG impact and practices', url: 'sustainability.html', code: 'ESG' },
    { title: 'Investor Portal', detail: 'Investor information', url: 'investors.html', code: 'IP', cta: true }
  ],
  'Our Mines': [
    { title: 'Muslim Bagh', detail: 'Chromite Mine - Active', url: 'our-mines.html', code: 'MB' },
    { title: 'Khuzdar', detail: 'Barite Operations', url: 'our-mines.html', code: 'Kh' },
    { title: 'Chagai', detail: 'Copper-Gold Exploration', url: 'our-mines.html', code: 'Ch' },
    { title: 'View All Mines', detail: 'Mine locations and operations', url: 'our-mines.html', code: 'All', cta: true }
  ],
  Logistics: [
    { title: 'Road Transport', detail: 'Fleet and dispatch planning', url: 'logistics.html', code: 'RT' },
    { title: 'Rail Network', detail: 'Rail-linked movement', url: 'logistics.html', code: 'RN' },
    { title: 'Port Access', detail: 'Karachi and Gwadar routes', url: 'logistics.html', code: 'PA' },
    { title: 'Logistics Network', detail: 'Export handling overview', url: 'logistics.html', code: 'All', cta: true }
  ],
  'About Us': [
    { title: 'Our Story', detail: 'Since 2008 in Balochistan', url: 'about.html', code: 'OS' },
    { title: 'Leadership Team', detail: 'Industry veterans', url: 'about.html#leadership', code: 'LT' },
    { title: 'Vision & Mission', detail: 'Ethical mining excellence', url: 'about.html#vision', code: 'VM' },
    { title: 'Learn More About Us', detail: 'Company profile', url: 'about.html', code: 'All', cta: true }
  ]
};

function getProductsPathPrefix() {
  return window.location.pathname.includes('/blog/') ? '../' : '';
}

function buildProductDropdown({ mobile = false } = {}) {
  const prefix = getProductsPathPrefix();
  let currentGroup = '';

  const rows = productMenuItems.map((item) => {
    const groupLabel = item.group !== currentGroup
      ? `<div class="navbar__dropdown-section-label">${item.group}</div>`
      : '';
    currentGroup = item.group;

    return `${groupLabel}
      <a href="${prefix}${item.url}" class="navbar__dropdown-item" data-mineral="${item.title.toLowerCase().replace(/[^a-z0-9]+/g, '-')}">
        <span class="navbar__dropdown-item-icon navbar__dropdown-item-icon--text">${item.code}</span>
        <span class="navbar__dropdown-item-text">
          <strong>${item.title}</strong>
          <span>${item.detail}</span>
        </span>
      </a>`;
  }).join('');

  const cta = mobile ? '' : `
    <div class="navbar__dropdown-divider"></div>
    <a href="${prefix}products.html" class="navbar__dropdown-item navbar__dropdown-item--cta">
      <span>View All Products -></span>
    </a>`;

  return rows + cta;
}

function buildDetailDropdown(label) {
  const items = detailDropdownMenus[label] || [];

  return items.map((item, index) => {
    const divider = item.cta && index > 0 ? '<div class="navbar__dropdown-divider"></div>' : '';
    const ctaClass = item.cta ? ' navbar__dropdown-item--cta' : '';

    return `${divider}
      <a href="${item.url}" class="navbar__dropdown-item${ctaClass}">
        <span class="navbar__dropdown-item-icon navbar__dropdown-item-icon--text">${item.code}</span>
        <span class="navbar__dropdown-item-text">
          <strong>${item.title}</strong>
          <span>${item.detail}</span>
        </span>
      </a>`;
  }).join('');
}

function createChevronSvg() {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.classList.add('navbar__dropdown-arrow');
  svg.setAttribute('width', '12');
  svg.setAttribute('height', '12');

  const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
  use.setAttribute('href', 'images/icons.svg#icon-chevron-down');
  svg.appendChild(use);

  return svg;
}

function getDirectLinkLabel(link) {
  return Array.from(link.childNodes)
    .filter((node) => node.nodeType === Node.TEXT_NODE)
    .map((node) => node.textContent)
    .join(' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function initDetailPageMenus() {
  if (!document.querySelector('.detail-hero')) return;

  const desktopNav = document.querySelector('.navbar__nav');
  if (desktopNav) {
    desktopNav.querySelectorAll(':scope > a.navbar__link').forEach((link) => {
      const label = getDirectLinkLabel(link);
      if (!detailDropdownMenus[label]) return;

      const group = document.createElement('div');
      group.className = 'navbar__dropdown-group';

      link.classList.add('navbar__dropdown-trigger');
      link.setAttribute('aria-haspopup', 'true');
      link.setAttribute('aria-expanded', 'false');

      if (!link.querySelector('.navbar__dropdown-arrow')) {
        link.appendChild(createChevronSvg());
      }

      const nextNode = link.nextSibling;
      const menu = document.createElement('div');
      menu.className = 'navbar__dropdown-menu';
      menu.innerHTML = buildDetailDropdown(label);

      group.append(link, menu);
      desktopNav.insertBefore(group, nextNode);
    });
  }

  const mobileMenu = document.querySelector('.navbar__mobile-menu');
  if (mobileMenu) {
    mobileMenu.querySelectorAll(':scope > a.navbar__link').forEach((link) => {
      const label = getDirectLinkLabel(link);
      if (!detailDropdownMenus[label]) return;

      const button = document.createElement('button');
      button.className = 'navbar__link navbar__dropdown-trigger navbar__dropdown-trigger--mobile';
      button.setAttribute('aria-haspopup', 'true');
      button.setAttribute('aria-expanded', 'false');
      button.append(document.createTextNode(label), createChevronSvg());

      const menu = document.createElement('div');
      menu.className = 'navbar__dropdown-menu navbar__dropdown-menu--mobile';
      menu.innerHTML = buildDetailDropdown(label);

      link.replaceWith(button, menu);
    });
  }
}

function initProductMenus() {
  const desktopTriggers = document.querySelectorAll('.navbar__dropdown-group > .navbar__dropdown-trigger');
  desktopTriggers.forEach((trigger) => {
    if (!trigger.textContent.trim().startsWith('Products')) return;
    const menu = trigger.nextElementSibling;
    if (!menu || !menu.classList.contains('navbar__dropdown-menu')) return;
    menu.classList.add('navbar__dropdown-menu--products');
    menu.setAttribute('data-lenis-prevent', '');
    menu.innerHTML = buildProductDropdown();
    trapProductMenuWheel(menu);
  });

  const mobileTriggers = document.querySelectorAll('.navbar__dropdown-trigger--mobile');
  mobileTriggers.forEach((trigger) => {
    if (!trigger.textContent.trim().startsWith('Products')) return;
    const menu = trigger.nextElementSibling;
    if (!menu || !menu.classList.contains('navbar__dropdown-menu')) return;
    menu.classList.add('navbar__dropdown-menu--products');
    menu.setAttribute('data-lenis-prevent', '');
    menu.innerHTML = buildProductDropdown({ mobile: true });
    trapProductMenuWheel(menu);
  });
}

function trapProductMenuWheel(menu) {
  if (menu.dataset.productWheelReady === 'true') return;
  menu.dataset.productWheelReady = 'true';

  menu.addEventListener('wheel', (event) => {
    const canScroll = menu.scrollHeight > menu.clientHeight;
    if (!canScroll) return;

    const multiplier = event.deltaMode === WheelEvent.DOM_DELTA_LINE
      ? 16
      : event.deltaMode === WheelEvent.DOM_DELTA_PAGE
        ? menu.clientHeight
        : 1;

    event.preventDefault();
    event.stopPropagation();
    menu.scrollTop += event.deltaY * multiplier;
  }, { passive: false });
}

export function initNavbar() {
  // Guard: Skip if already initialized
  if (window.__navbarInitialized) return;
  window.__navbarInitialized = true;

  const navbar = document.querySelector('.navbar');
  const hamburger = document.querySelector('.navbar__hamburger');
  const mobileMenu = document.querySelector('.navbar__mobile-menu');

  if (!navbar) return;

  initDetailPageMenus();
  initProductMenus();

  // Highlight active link
  highlightActiveLink();

  // Scroll effect (lightweight) - guard against duplicate listeners
  if (!window.__navbarScrollHandler) {
    window.__navbarScrollHandler = () => {
      const currentScroll = window.scrollY;
      if (currentScroll > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    };
    window.addEventListener('scroll', window.__navbarScrollHandler, { passive: true });
  }

  // Mobile menu toggle (use body class instead of direct style manipulation)
  if (hamburger && mobileMenu) {
    const closeMobileMenu = () => {
      hamburger.classList.remove('active');
      hamburger.setAttribute('aria-expanded', 'false');
      mobileMenu.classList.remove('open');

      mobileMenu.querySelectorAll('.navbar__dropdown-trigger.open').forEach(trigger => {
        trigger.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
      });

      mobileMenu.querySelectorAll('.navbar__dropdown-menu.open').forEach(menu => {
        menu.classList.remove('open');
      });
    };

    hamburger.addEventListener('click', () => {
      const isOpen = mobileMenu.classList.toggle('open');
      hamburger.classList.toggle('active', isOpen);
      hamburger.setAttribute('aria-expanded', String(isOpen));
    });

    // Close mobile menu on REAL link click (ignore dropdown triggers)
    const menuLinks = mobileMenu.querySelectorAll('.navbar__link:not(.navbar__dropdown-trigger), .navbar__dropdown-item');
    menuLinks.forEach(link => {
      link.addEventListener('click', () => {
        if (isMobile()) {
          closeMobileMenu();
        }
      });
    });

    window.addEventListener('resize', () => {
      if (!isMobile() && mobileMenu.classList.contains('open')) {
        closeMobileMenu();
      }
    }, { passive: true });
  }
}
