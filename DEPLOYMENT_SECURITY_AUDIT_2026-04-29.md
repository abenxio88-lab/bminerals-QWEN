# Deployment Security Audit - 2026-04-29

Purpose: record the current prelaunch security and Hostinger deployment findings so future edits do not lose context.

## Current Goal

The site is close to ready. Before uploading to Hostinger, the priority is to make sure the public upload contains only production files, the contact workflow works reliably, security headers match the actual site, and the repo is clean enough that future edits are easy to reason about.

## Findings

### 1. Do not upload local/private project folders

Risk: `backups/`, `graphify-out/`, audit reports, Lighthouse JSON reports, local markdown reports, `.git/`, `node_modules/`, and other build/dev artifacts are currently present in the project workspace.

Why it matters: `.htaccess` has `Options -Indexes`, which helps block directory listing, but direct file paths can still be requested if those files are uploaded. These folders should not go to Hostinger.

Status: must handle before launch.

### 2. Contact form is not production-safe yet

Current state:

- `contact.html` uses `action="mailto:sales@balochistanminerals.com"`.
- `contact.html` also has inline JS that builds a `mailto:` URL and redirects with `window.location.href`.

Why it matters: `mailto:` depends on the visitor having a local email app configured. It often fails, opens the wrong app, or loses the message. For real sales leads, this is not reliable.

Status: fix before launch if inquiries matter.

Recommended path: use a real form endpoint supported by Hostinger/PHP, Formspree, Basin, Netlify Forms equivalent, or a small server-side handler if Hostinger supports it.

### 3. CSP is good but still too loose

Current state:

- `.htaccess` includes a real Content Security Policy.
- CSP still needs `script-src 'unsafe-inline'` because executable inline scripts exist in `contact.html` and `index.html`.

Why it matters: inline scripts weaken CSP. For stronger security, move executable inline JS into external files, then remove `'unsafe-inline'` from `script-src`.

Status: should fix before launch if aiming for strong hardening.

### 4. Critical npm audit path from unused `pip`

Current state:

- `npm audit --omit=dev` reports a critical dependency path through `pip -> optimist -> minimist`.
- `pip` is listed in `package.json`.
- No live site usage of `pip` was found.

Why it matters: the hosted static site is not directly exposed through npm packages, but unused vulnerable packages create avoidable project risk and noisy audits.

Status: remove before launch.

### 5. Leaflet is still loaded from `unpkg`

Current state:

- `projects.html` loads Leaflet CSS and JS from `https://unpkg.com`.
- SRI hashes are present.
- `.htaccess` CSP allows `unpkg`.

Why it matters: it should work, but a local vendored copy is more reliable for Hostinger deployment and avoids dependency on a third-party CDN.

Status: can wait, but local vendoring is recommended.

## What Looks Good

- `.htaccess` already includes Apache/Hostinger hardening: HTTPS redirect, canonical host, HSTS, `nosniff`, `SAMEORIGIN`, CSP, compression, cache headers, and custom 404.
- Local link/source scan passed.
- Windows-to-Linux path case scan found no local `href`/`src` case mismatches.
- GSAP is already local under `vendor/gsap`, which is better than hotlinking CDN files.
- `robots.txt` and `sitemap.xml` are present.

## Do Now Before Hostinger Upload

1. Create a production upload folder or checklist that excludes:
   `backups/`, `_backups/`, `graphify-out/`, `.git/`, `node_modules/`, Lighthouse JSON files, local audit markdown, scratch files, and dev-only scripts/reports.

2. Replace the `mailto:` contact form with a real form handling strategy.

3. Remove unused `pip` dependency and re-run:

```bash
npm audit --omit=dev
```

4. Move executable inline scripts out of:
   `contact.html`
   `index.html`

5. Tighten `.htaccess` CSP after inline scripts are removed.

6. Run:

```bash
npm run check
```

## Can Wait Until Second Pass

- Vendor Leaflet locally instead of loading from `unpkg`.
- Reduce inline `style=""` usage so `style-src 'unsafe-inline'` can eventually be removed.
- Continue controlled flat-radius audit across component/page CSS.
- Commit the `node_modules` untracking cleanup so git status becomes readable again.

## Update Since Initial Audit

- Mobile non-hero typography is now centralized with a final authority layer:
  `css/pages/mobile-typography.css` imported last in `src/styles/index.css`.
- Contact map heading is no longer using inline font-size; it now uses `.map-section__title` in `css/pages/contact.css`.
- `node_modules/` is no longer tracked by git (`git ls-files node_modules` returns `0`), but a large staged deletion set remains and should be committed intentionally.

## CSS Note Connection

Use `CSS_OWNERSHIP_AND_SECURITY_NOTES_2026-04-29.md` together with this file.

During security edits, do not create new CSS fights:

- Do not add broad overrides in `css/premium-polish.css`.
- Do not add more inline styles while trying to tighten CSP.
- Move page-specific styles into `css/pages/*.css`.
- Move component styles into `css/components/*.css`.
- Keep `src/styles/index.css` as import order only.

## Recommended Next Work Session

Next practical cleanup pass:

1. Back up `contact.html`, `index.html`, `.htaccess`, `package.json`, and `package-lock.json`.
2. Remove unused `pip`.
3. Move contact form inline JS into a dedicated JS file.
4. Move homepage inline module script into an external module or existing app init.
5. Re-run `npm run check` and `npm audit --omit=dev`.
6. Update `.htaccess` CSP only after scripts are external.
