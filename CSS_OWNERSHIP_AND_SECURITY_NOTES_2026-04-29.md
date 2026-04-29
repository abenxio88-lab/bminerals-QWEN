# CSS Ownership and Security Notes - 2026-04-29

Purpose: keep future edits calm. This file records the current CSS ownership rules and the security-edit cautions so we do not reintroduce three files fighting each other.

## Current Principle

Do not rely on global reset hacks to control component design.

The previous `css/reset.css` rule `border-radius: 0 !important` made the site look flatter, but it did that by overriding every component everywhere. That caused hidden dependency: when the reset was corrected, hardcoded rounded values in specific components became visible again.

The safer pattern is:

- Design tokens live in `css/variables.css`.
- Resets live in `css/reset.css` and should only normalize browser defaults.
- Component styles live in `css/components/*.css`.
- Page-only styles live in `css/pages/*.css`.
- `css/premium-polish.css` must not act as a global override layer.

Two files handling the same feature is acceptable when the relationship is clear:

- `css/components/*.css` can define the reusable/base component.
- `css/pages/*.css` can adjust that component for one specific page.
- The page stylesheet loads later, so it is allowed to win intentionally.
- The problem is not "two files"; the problem is hidden third-layer overrides from global files, broad selectors, inline styles, or unnecessary `!important`.

## File Ownership

- `css/variables.css`: owns colors, typography scale, spacing, radius tokens, shadows, and shared timing variables.
- `css/reset.css`: owns box sizing, base element reset, body defaults, focus/selection defaults. It must not contain broad visual overrides like global `border-radius: 0 !important`.
- `css/components/navbar.css`: owns all navbar, dropdown, hamburger, mobile drawer, and logo sizing behavior.
- `css/components/footer.css`: owns all footer layout and footer color styling.
- `css/components/global-improvements.css`: owns skip link, focus-visible improvements, and `data-freshness`.
- `css/components/hero.css`: legacy/base hero defaults.
- `css/hero-cinematic.css`: owns homepage cinematic hero, hero image slider, slider arrows, slider dots, hero CTAs, hero stats panel, and trust strip.
- `css/pages/contact.css`: owns contact hero, contact form, contact cards, map section, and emergency banner.
- `css/pages/investors.css`: owns investor page hero, financial dashboard, reports, compliance, investor tables, and investor CTA styling.
- `css/pages/mobile-typography.css`: final mobile authority for non-hero H1/H2 caps across pages.
- `src/styles/index.css`: owns import order only. Do not put design rules here.
- `output.css`: built artifact. Do not edit directly.

## Rules for Future CSS Edits

1. Before editing CSS, identify the owner file.
2. If a component already has an owner file, edit that file instead of adding an override in `premium-polish.css`.
3. Page files may override component files, but keep the selector clearly page-scoped.
4. Avoid new `!important` rules unless fixing a proven third-party or inline-style conflict.
5. Avoid broad selectors like `section`, `h1`, `h2`, `p`, `.footer`, `.navbar`, or `.hero` inside global polish files.
6. Prefer tokens such as `var(--radius-md)`, `var(--h1-size)`, and `var(--navbar-height)` over hardcoded values.
7. Hardcoded `border-radius: 50%` is allowed only for genuinely circular UI, such as timeline dots or icon dots.
8. If the desired site direction is flat corners, use the radius tokens instead of `0 !important` reset rules.
9. Do not add inline `style=""` unless it is temporary and has a follow-up note.
10. After any CSS edit, run `npm run check`.

## Recent Stability Fixes

- Removed global `border-radius: 0 !important` from `css/reset.css`.
- Trimmed `css/premium-polish.css` so it no longer owns footer styling, contact form mobile styling, generic `h2`/`h3`/`p` sizing, or broad section padding.
- Moved hero slider arrows/dots and hero rounded pieces in `css/hero-cinematic.css` to shared radius tokens.
- Kept `data-freshness` ownership in `css/components/global-improvements.css`.
- Moved stats section layout and metric-strip/glass-bento fixes from `css/premium-polish.css` into `css/components/stats.css`.
- Removed navbar height token overrides from `css/premium-polish.css`; navbar height should be owned by `css/variables.css` and `css/components/navbar.css`.
- Moved the mobile homepage hero arrow hiding rule into `css/hero-cinematic.css`.
- Moved mobile hero layout rules out of `css/premium-polish.css` and into the relevant page owners:
  `css/pages/about.css`, `css/pages/products.css`, `css/pages/projects.css`, `css/pages/contact.css`, `css/pages/investors.css`, `css/pages/our-mines.css`, `css/pages/logistics.css`, `css/pages/sustainability.css`, and the inline blog index styles in `blogs.html`.
- Mobile typography normalized for non-hero headings in `css/premium-polish.css` using a shared cap based on Products page heading scale:
  `clamp(1.65rem, 8vw, 2.2rem)` for `<=768px` and `clamp(1.45rem, 9vw, 1.85rem)` for `<=480px`.
- Hero titles remain excluded from this normalization; hero sizing still belongs to hero/page owner styles.
- Added `css/pages/mobile-typography.css` and imported it last in `src/styles/index.css` so non-hero mobile heading caps stay deterministic even when page CSS loads later.
- Replaced contact map heading inline font-size usage with `.map-section__title` in `css/pages/contact.css`.

## Known Remaining CSS Risk

`css/premium-polish.css` still contains mobile typography synchronization rules and `!important` safeguards. These should be reduced carefully in later passes, one text group at a time, with visual QA.

Current priority targets:

- Hardcoded radii in component/page CSS where they are not intentionally circular.
- Remaining typography synchronization in `css/premium-polish.css` should eventually move into page/component owners once each section is verified.

## Security Edit Memory

When doing the next security/deployment pass, keep the CSS ownership rules above in mind.

Do not fix CSP by adding more inline scripts or inline styles. The target should be the opposite:

- Move inline script from `contact.html` into a dedicated JS file.
- Move inline module script from `index.html` into an existing or dedicated JS module.
- Reduce inline `style=""` usage before tightening `style-src`.
- Keep `.htaccess` CSP aligned with actual assets in use.
- Do not upload `backups/`, `graphify-out/`, `.git/`, `node_modules/`, Lighthouse JSON reports, or local audit markdown files to Hostinger.

## Prelaunch Commands

Run these before upload:

```bash
npm run check
npm audit --omit=dev
```

Expected current state:

- `npm run check` should pass.
- `npm audit --omit=dev` currently reports a critical path from unused `pip`; remove unused dependencies during the security pass.
