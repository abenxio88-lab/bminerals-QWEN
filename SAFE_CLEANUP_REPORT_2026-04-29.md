# Safe Cleanup Report - 2026-04-29

Generated: 2026-04-29 01:21:55 +05:00

## Scope

This pass focused only on low-risk investor-page cleanup. Broad global CSS files such as `css/reset.css` and `css/premium-polish.css` were intentionally left unchanged to avoid site-wide visual regressions.

## Backup Created

- `backups/safe-cleanup-20260429-011015/`

Backed up files before editing:

- `investors.html`
- `css/pages/investors.css`
- `css/reset.css`
- `css/premium-polish.css`
- `css/components/navbar.css`

## Files Changed

- `investors.html`
- `css/pages/investors.css`

## Changes Made

### Investor Report Links

- Replaced fake `href="#"` report links with real destinations:
  - `documents/annual-report-2025.html`
  - `documents/q3-2025-financial-results.html`
  - `documents/esg-sustainability-report.html`
  - `compliance.html`
- Replaced visible report meta bullet characters with safe `&bull;` entities.

### Investor Page CSS Cleanup

- Moved repeated inline KPI icon gradient styles into reusable classes:
  - `.q3-metric-icon--gold`
  - `.q3-metric-icon--navy`
  - `.q3-metric-icon--emerald`
  - `.q3-metric-icon--violet`
- Fixed the green KPI icon so it uses explicit emerald colors instead of undefined `--emerald-*` CSS variables.
- Moved investor revenue breakdown inline styles into reusable classes:
  - `.perf-grid--split`
  - `.investor-breakdown`
  - `.investor-breakdown__row`
  - `.investor-breakdown__bar`
  - `.investor-breakdown__fill`
  - width/color modifier classes
- Added a mobile rule so `.perf-grid--split` collapses to one column on small screens.
- Moved remaining investor-page inline presentation styles into named CSS classes for:
  - hero title accent
  - reports heading accent
  - compliance overline
  - inverse compliance heading
  - technical table heading
  - investor CTA button

## Verification

Passed:

- `cmd.exe /c npm run build:css`
- Active HTML local link/source integrity scan
- Active placeholder-link scan for `href="#"`, `src="#"`, and `javascript:void`
- `git diff --check`

Notes:

- `git diff --check` only reported normal CRLF warnings from Git on Windows.
- No missing local `href` or `src` targets were found in active HTML files.

## Current Modified Files After This Pass

- `investors.html`
- `css/pages/investors.css`
- `SAFE_CLEANUP_REPORT_2026-04-29.md`
- `backups/safe-cleanup-20260429-011015/`

## Recommended Next Safe Step

Perform a visual QA pass on `investors.html`, especially:

- Q3 metric icon colors
- revenue breakdown bars
- reports list links
- compliance section heading
- CTA button
- mobile layout around the investor breakdown grid

After visual QA, the next cleanup target should be a narrow, isolated reduction of global override rules in `css/premium-polish.css`, not a broad rewrite.
