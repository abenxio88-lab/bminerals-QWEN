import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function getSection(source, startMarker, endMarker) {
  const start = source.indexOf(startMarker);
  const end = source.indexOf(endMarker, start + startMarker.length);
  assert(start >= 0 && end > start, `Could not inspect section: ${startMarker}`);
  return source.slice(start, end);
}

function assertSafeModalSection(name, source) {
  const bannedPatterns = [
    [/window\.scrollTo\s*\(/, 'window.scrollTo'],
    [/document\.body\.style\.(?:position|top|left|right|width)/, 'fixed-body positioning'],
    [/\.focus\s*\(/, 'direct focus without the shared guard'],
  ];

  for (const [pattern, description] of bannedPatterns) {
    assert(!pattern.test(source), `${name} reintroduced ${description}.`);
  }
  assert(source.includes('createImageModalGuard'), `${name} is not using the shared image-modal guard.`);
}

const main = read('js/main.js');
const minerals = read('js/minerals-tabs.js');
const stones = read('js/stone-gallery.js');
const guard = read('js/image-modal-guard.js');
const homeCss = read('css/components/home-cinematic-motion.css');
const productsCss = read('css/pages/products.css');

assertSafeModalSection(
  'Product image lightbox',
  `createImageModalGuard${getSection(main, 'function initProductImageLightbox()', 'function initMineCarouselPreload()')}`
);
assertSafeModalSection(
  'Homepage mineral image modal',
  `createImageModalGuard${getSection(minerals, 'function createMediaModal()', 'enhanceMineralCards();')}`
);
assertSafeModalSection('Stone gallery lightbox', stones);

assert(!/window\.scrollTo\s*\(/.test(guard), 'Shared image-modal guard must never move the page.');
assert(!/document\.body\.style\.(?:position|top|left|right|width)/.test(guard), 'Shared guard must never fix-position the body.');
assert(guard.includes('focus({ preventScroll: true })'), 'Shared guard lost scroll-safe focus handling.');
assert(guard.includes("document.addEventListener('touchmove', handleTouchMove, nonPassiveCapture)"), 'Shared guard lost legacy touch-scroll containment.');
assert(guard.includes("document.addEventListener('click', blockResidualClick, nonPassiveCapture)"), 'Shared guard lost residual click-through protection.');

assert((minerals.match(/imageWrap\.addEventListener\('click', handleOpen\)/g) || []).length === 1, 'Homepage image must have exactly one open handler.');
assert(!minerals.includes("image.addEventListener('click', handleOpen)"), 'Nested homepage image handler would open the modal twice.');
assert((minerals.match(/requestAnimationFrame\(closeMediaModal\)/g) || []).length >= 2, 'Homepage touch close must remain click-through safe.');
assert((main.match(/requestAnimationFrame\(closeModal\)/g) || []).length >= 2, 'Product lightbox touch close must remain click-through safe.');
assert((stones.match(/requestAnimationFrame\(closeModal\)/g) || []).length >= 2, 'Stone lightbox touch close must remain click-through safe.');

assert(/\.mineral-media-modal__close\s*\{[^}]*position:\s*absolute;[^}]*z-index:\s*4;/s.test(homeCss), 'Homepage modal X must remain picture-anchored and topmost.');
assert(/\.stone-lightbox__close\s*\{[^}]*position:\s*absolute;[^}]*z-index:\s*4;/s.test(productsCss), 'Product lightbox X must remain picture-anchored and topmost.');
assert(minerals.includes('<button class="mineral-media-modal__close"') && minerals.indexOf('<button class="mineral-media-modal__close"') > minerals.indexOf('<div class="mineral-media-modal__stage">'), 'Homepage modal X must live inside the mineral picture stage.');
assert(main.includes('<div class="stone-lightbox__stage">') && main.indexOf('<button class="stone-lightbox__close"') > main.indexOf('<div class="stone-lightbox__stage">'), 'Product lightbox X must live inside the picture stage.');
assert(stones.includes('<div class="stone-lightbox__stage">') && stones.indexOf('<button class="stone-lightbox__close"') > stones.indexOf('<div class="stone-lightbox__stage">'), 'Stone gallery X must live inside the picture stage.');

function listHtmlFiles(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const fullPath = path.join(directory, entry.name);
    if (entry.isDirectory()) return listHtmlFiles(fullPath);
    return entry.name.endsWith('.html') ? [fullPath] : [];
  });
}

const requiredVersion = 'image-modal-guard-20260713';
for (const htmlFile of listHtmlFiles(root)) {
  const html = fs.readFileSync(htmlFile, 'utf8');
  const mainReferences = html.match(/(?:\.\.\/)?js\/main\.js(?:\?[^"']*)?/g) || [];
  for (const reference of mainReferences) {
    assert(reference.includes(`v=${requiredVersion}`), `${path.relative(root, htmlFile)} has a stale main.js reference.`);
  }
}

const homepage = read('index.html');
assert(homepage.includes(`js/minerals-tabs.js?v=${requiredVersion}`), 'Homepage mineral modal script is not cache-busted.');
assert(
  homepage.includes('home-cinematic-motion.css?v=homepage-native-scroll-20260714'),
  'Homepage modal CSS is not cache-busted.'
);

const stonesPage = read('product-stones.html');
assert(stonesPage.includes(`type="module" src="js/stone-gallery.js?v=${requiredVersion}"`), 'Stone gallery must load as a cache-busted module.');

console.log('Image modal safety checks passed.');
