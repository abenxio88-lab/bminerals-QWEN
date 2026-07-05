import { highlightActiveLink, isMobile } from './utils.js';

const productMenuItems = [
  { title: 'Copper', detail: '2% - 10% Cu lumps', url: 'product-metallic.html#copper', code: 'Cu', group: 'Metallic' },
  { title: 'Chromite', detail: '12% - 52% Cr2O3', url: 'product-metallic.html#chromite', code: 'Cr', group: 'Metallic' },
  { title: 'Iron Ore', detail: '40% - 62% Fe', url: 'product-metallic.html#iron-ore', code: 'Fe', group: 'Metallic' },
  { title: 'Antimony', detail: '4% - 58% Sb lumps', url: 'product-metallic.html#antimony', code: 'Sb', group: 'Metallic' },
  { title: 'Barite', detail: '92% - 98% BaSO4', url: 'product-industrial.html#barite', code: 'Ba', group: 'Industrial' },
  { title: 'Fluorite', detail: '50% - 90%+ CaF2', url: 'product-industrial.html#fluorite', code: 'Ca', group: 'Industrial' },
  { title: 'Gypsum', detail: '90% - 95% typical', url: 'product-industrial.html#gypsum', code: 'Gy', group: 'Industrial' },
  { title: 'Magnesite', detail: '42% - 47% raw MgO', url: 'product-industrial.html#magnesite', code: 'Mg', group: 'Industrial' },
  { title: 'Phosphate Rock', detail: 'P2O5 assay based', url: 'product-industrial.html#phosphate-rock', code: 'P', group: 'Industrial' },
  { title: 'Bauxite', detail: '45% - 62% Al2O3', url: 'product-industrial.html#bauxite', code: 'Al', group: 'Industrial' },
  { title: 'Marble', detail: 'Blocks, slabs and tiles', url: 'product-stones.html#marble', code: 'Mr', group: 'Stones' },
  { title: 'White Marble', detail: 'Premium bright selection', url: 'product-stones.html#white-marble', code: 'Wm', group: 'Stones' },
  { title: 'Persian Silk Tundra Grey', detail: 'Iranian grey marble slabs', url: 'product-stones.html#persian-silk-tundra-grey', code: 'Ps', group: 'Stones' },
  { title: 'Persian Silk Block', detail: 'Iranian marble blocks', url: 'product-stones.html#persian-silk-block', code: 'Pb', group: 'Stones' },
  { title: 'Pietra Grey Block', detail: 'Iranian dark-grey blocks', url: 'product-stones.html#pietra-grey-block', code: 'Pg', group: 'Stones' },
  { title: 'Pietra Grey Slab', detail: 'Iranian polished slabs', url: 'product-stones.html#pietra-grey-slab', code: 'Ps', group: 'Stones' },
  { title: 'Silver Steam', detail: 'Premium Iranian marble', url: 'product-stones.html#irani-marble', code: 'Ss', group: 'Stones' },
  { title: 'Coal Fields', detail: 'Quetta, Mach', url: 'product-energy.html#energy-minerals', code: 'Co', group: 'Energy' }
];

const detailDropdownMenus = {
  Projects: [
    { title: 'Muslim Bagh', detail: 'Chromite mine - active', url: 'projects.html#muslim-bagh', code: 'MB', group: 'Metallic' },
    { title: 'Dilband', detail: 'Iron ore steel feedstock', url: 'projects.html#dilband', code: 'Fe', group: 'Metallic' },
    { title: 'Chagai', detail: 'Copper-gold exploration', url: 'projects.html#chagai', code: 'Cu', group: 'Metallic' },
    { title: 'Washuk-Zhob', detail: 'Antimony specialty metal', url: 'projects.html#antimony', code: 'Sb', group: 'Metallic' },
    { title: 'Khuzdar Barite', detail: 'Industrial barite processing', url: 'projects.html#industrial-barite', code: 'Ba', group: 'Industrial' },
    { title: 'Fluorspar', detail: 'Metallurgical and ceramic feed', url: 'projects.html#industrial-fluorspar', code: 'Ca', group: 'Industrial' },
    { title: 'Gypsum', detail: 'Cement and plaster markets', url: 'projects.html#industrial-gypsum', code: 'Gy', group: 'Industrial' },
    { title: 'Magnesite', detail: 'Refractory minerals', url: 'projects.html#industrial-magnesite', code: 'Mg', group: 'Industrial' },
    { title: 'Phosphate Rock', detail: 'Fertilizer and chemical feed', url: 'projects.html#industrial-phosphate-rock', code: 'P', group: 'Industrial' },
    { title: 'Bauxite', detail: 'Refractory and alumina buyers', url: 'projects.html#industrial-bauxite', code: 'Al', group: 'Industrial' },
    { title: 'Marble', detail: 'Blocks, slabs and tiles', url: 'projects.html#stone-marble', code: 'Mr', group: 'Stone' },
    { title: 'White Marble', detail: 'Premium block selection', url: 'projects.html#stone-white-marble', code: 'Wm', group: 'Stone' },
    { title: 'Persian Silk Tundra Grey', detail: 'Iranian grey marble slabs', url: 'projects.html#stone-persian-silk-tundra-grey', code: 'Ps', group: 'Stone' },
    { title: 'Persian Silk Block', detail: 'Iranian marble blocks', url: 'projects.html#stone-persian-silk-block', code: 'Pb', group: 'Stone' },
    { title: 'Pietra Grey Block', detail: 'Iranian dark-grey blocks', url: 'projects.html#stone-pietra-grey-block', code: 'Pg', group: 'Stone' },
    { title: 'Pietra Grey Slab', detail: 'Iranian polished slabs', url: 'projects.html#stone-pietra-grey-slab', code: 'Ps', group: 'Stone' },
    { title: 'Silver Steam', detail: 'Premium Iranian marble', url: 'projects.html#stone-silver-steam', code: 'Ss', group: 'Stone' },
    { title: 'Sorange-Degari', detail: 'Quetta coalfield', url: 'projects.html#energy-sorange-degari', code: 'Co', group: 'Energy' },
    { title: 'Mach-Anjira', detail: 'Mach corridor coal', url: 'projects.html#energy-mach-anjira', code: 'Ma', group: 'Energy' },
    { title: 'View All Projects', detail: 'Project portfolio', url: 'projects.html', code: 'All', cta: true }
  ],
  Investors: [
    { title: 'Financial Results', detail: 'Latest company performance', url: 'investors.html', code: 'FR' },
    { title: 'Annual Reports', detail: 'Reports and documents', url: 'investors.html', code: 'AR' },
    { title: 'Sustainability', detail: 'ESG impact and practices', url: 'sustainability.html', code: 'ESG' },
    { title: 'Investor Portal', detail: 'Investor information', url: 'investors.html', code: 'IP', cta: true }
  ],
  'Our Mines': [
    { title: 'Muslim Bagh', detail: 'Chromite mine - active', url: 'our-mines.html#muslim-bagh', code: 'MB', group: 'Active sites' },
    { title: 'Khuzdar', detail: 'Barite processing facility', url: 'our-mines.html#khuzdar', code: 'Kh', group: 'Active sites' },
    { title: 'Chagai', detail: 'Copper-gold exploration', url: 'our-mines.html#chagai', code: 'Ch', group: 'Exploration' },
    { title: 'Kharan Iron Ore', detail: 'Iron ore sourcing zone', url: 'our-mines.html#kharan-iron-ore', code: 'Fe', group: 'Sourcing zones' },
    { title: 'Washuk Antimony', detail: 'Antimony prospect', url: 'our-mines.html#washuk-antimony', code: 'Sb', group: 'Sourcing zones' },
    { title: 'Lasbela Stone', detail: 'Stone and limestone corridor', url: 'our-mines.html#lasbela-stone', code: 'Ls', group: 'Stone corridors' },
    { title: 'View All Mines', detail: 'Mine locations and operations', url: 'our-mines.html', code: 'All', cta: true }
  ],
  Logistics: [
    { title: 'Road Transport', detail: 'Fleet and dispatch planning', url: 'logistics.html#transport-modes', code: 'RT' },
    { title: 'Freight Analytics', detail: 'Route timing simulator', url: 'logistics.html#freight-analytics', code: 'FA' },
    { title: 'Warehousing', detail: 'Storage and lot control', url: 'logistics.html#warehousing', code: 'Wh' },
    { title: 'Corridors', detail: 'Karachi and Gwadar routing', url: 'logistics.html#corridors', code: 'Co' },
    { title: 'Export Capacity', detail: 'Annual movement capability', url: 'logistics.html#capacity', code: 'Ex' },
    { title: 'Request Shipping Quote', detail: 'Freight and delivery inquiry', url: 'logistics.html#shipping-quote', code: 'Go', cta: true }
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
  const prefix = getProductsPathPrefix();
  let currentGroup = '';

  return items.map((item, index) => {
    const divider = item.cta && index > 0 ? '<div class="navbar__dropdown-divider"></div>' : '';
    const ctaClass = item.cta ? ' navbar__dropdown-item--cta' : '';
    const groupLabel = item.group && item.group !== currentGroup
      ? `<div class="navbar__dropdown-section-label">${item.group}</div>`
      : '';
    currentGroup = item.group || currentGroup;

    return `${divider}${groupLabel}
      <a href="${prefix}${item.url}" class="navbar__dropdown-item${ctaClass}">
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
  const desktopNav = document.querySelector('.navbar__nav');
  if (desktopNav) {
    desktopNav.querySelectorAll('.navbar__dropdown-group').forEach((group) => {
      const trigger = group.querySelector(':scope > .navbar__dropdown-trigger');
      const menu = group.querySelector(':scope > .navbar__dropdown-menu');
      if (!trigger || !menu) return;

      const label = getDirectLinkLabel(trigger);
      if (!detailDropdownMenus[label]) return;

      menu.innerHTML = buildDetailDropdown(label);
      prepareLongDropdown(label, menu);
    });

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
      prepareLongDropdown(label, menu);

      group.append(link, menu);
      desktopNav.insertBefore(group, nextNode);
    });
  }

  const mobileMenu = document.querySelector('.navbar__mobile-menu');
  if (mobileMenu) {
    mobileMenu.querySelectorAll(':scope > .navbar__dropdown-trigger--mobile').forEach((trigger) => {
      const label = getDirectLinkLabel(trigger);
      if (!detailDropdownMenus[label]) return;

      const menu = trigger.nextElementSibling;
      if (!menu || !menu.classList.contains('navbar__dropdown-menu')) return;

      menu.innerHTML = buildDetailDropdown(label);
      prepareLongDropdown(label, menu);
    });

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
      prepareLongDropdown(label, menu);

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
    trapDropdownWheel(menu);
  });

  const mobileTriggers = document.querySelectorAll('.navbar__dropdown-trigger--mobile');
  mobileTriggers.forEach((trigger) => {
    if (!trigger.textContent.trim().startsWith('Products')) return;
    const menu = trigger.nextElementSibling;
    if (!menu || !menu.classList.contains('navbar__dropdown-menu')) return;
    menu.classList.add('navbar__dropdown-menu--products');
    menu.setAttribute('data-lenis-prevent', '');
    menu.innerHTML = buildProductDropdown({ mobile: true });
    trapDropdownWheel(menu);
  });
}

function prepareLongDropdown(label, menu) {
  const itemCount = detailDropdownMenus[label]?.filter((item) => !item.cta).length || 0;
  if (itemCount < 6) return;

  menu.classList.add('navbar__dropdown-menu--long');
  menu.setAttribute('data-lenis-prevent', '');
  trapDropdownWheel(menu);
}

function trapDropdownWheel(menu) {
  if (menu.dataset.dropdownWheelReady === 'true') return;
  menu.dataset.dropdownWheelReady = 'true';

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
