import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const ignoredDirectories = new Set(['.git', 'node_modules', 'graphify-out', 'backups', '_backups']);
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const normalizedHash = (file) => crypto
  .createHash('sha256')
  .update(read(file).replace(/\r\n?/g, '\n'))
  .digest('hex')
  .slice(0, 12);

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function listHtmlFiles(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const fullPath = path.join(directory, entry.name);
    if (entry.isDirectory()) {
      return ignoredDirectories.has(entry.name) ? [] : listHtmlFiles(fullPath);
    }
    return entry.isFile() && entry.name.endsWith('.html') ? [fullPath] : [];
  });
}

function assertHtmlVersions(assetPattern, expectedVersion, minimumReferences, label) {
  let references = 0;
  for (const htmlFile of listHtmlFiles(root)) {
    const html = fs.readFileSync(htmlFile, 'utf8');
    const matches = html.match(assetPattern) || [];
    for (const reference of matches) {
      references += 1;
      const version = reference.match(/[?&]v=([^&"']+)/)?.[1];
      assert(
        version === expectedVersion,
        `${path.relative(root, htmlFile)} has a stale ${label} reference; expected v=${expectedVersion}.`
      );
    }
  }
  assert(references >= minimumReferences, `Expected at least ${minimumReferences} ${label} references; found ${references}.`);
}

const mainVersion = normalizedHash('js/main.js');
const navbarVersion = normalizedHash('js/navbar.js');
const utilsVersion = normalizedHash('js/utils.js');
const analyticsVersion = normalizedHash('js/analytics-consent.js');
const cookieScriptVersion = normalizedHash('js/cookies-popup.js');
const cookieStylesVersion = normalizedHash('css/cookies-popup.css');
const aboutStylesVersion = normalizedHash('css/pages/about.css');

assertHtmlVersions(/(?:\.\.\/)?js\/main\.js(?:\?[^"']*)?/g, mainVersion, 24, 'main.js');
assertHtmlVersions(/\/js\/analytics-consent\.js(?:\?[^"']*)?/g, analyticsVersion, 34, 'analytics-consent.js');
assertHtmlVersions(/\.\/js\/navbar\.js(?:\?[^"']*)?/g, navbarVersion, 3, 'navbar.js');

assert(read('js/main.js').includes(`./navbar.js?v=${navbarVersion}`), 'main.js has a stale navbar.js import.');
assert(read('js/navbar.js').includes(`./utils.js?v=${utilsVersion}`), 'navbar.js has a stale utils.js import.');
assert(
  read('js/analytics-consent.js').includes(`/js/cookies-popup.js?v=${cookieScriptVersion}`),
  'analytics-consent.js has a stale cookie popup script reference.'
);
assert(
  read('js/analytics-consent.js').includes(`/css/cookies-popup.css?v=${cookieStylesVersion}`),
  'analytics-consent.js has a stale cookie popup stylesheet reference.'
);
assert(
  read('about.html').includes(`css/pages/about.css?v=${aboutStylesVersion}`),
  'about.html has a stale About stylesheet reference.'
);

console.log('Cache-version checks passed for production CSS and JavaScript assets.');
