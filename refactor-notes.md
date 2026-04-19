Refactor notes & aliasing plan

Goal: allow safe modularization while avoiding breakage. Keep old selectors as thin wrappers when renaming.

1. Variables
- Keep `css/variables.css` unchanged. If renaming, add aliases and map both names.

2. Navbar
- Keep `.navbar`, `.navbar__container`, `.navbar__mobile-menu`, `.navbar__hamburger` intact.
- If moving to `.nav-` prefix, add wrappers:
  .navbar { }
  .nav { }
  (Temporary) keep both until QA passes.

3. Hero
- Extract only layout/typography to `critical.css`.
- Keep image classes `.hero__background-image` and overlay selectors unchanged.

4. Global improvements
- Keep `*:focus-visible` and `.skip-link` unchanged.

5. Heavy selectors
- For `:nth-child()` animation delays in testimonials, preserve DOM order or replace with JS-driven indexing.

6. Data-URI backgrounds
- Replace with external SVG files in `/images/` and reference with `background-image: url('/images/noise.svg')`.

7. Pages
- Load page CSS after components. Where a page overrides component styles, add `.page-about .component-class {}` to scope.

8. Rollout strategy
- Phase 1: Introduce `src/styles/index.css` and `critical.css`, build to `output.css` with PostCSS. Deploy to staging.
- Phase 2: Add alias wrappers and remove duplicates after QA.
- Phase 3: Purge unused CSS and enable content-hash filenames.

Testing checklist
- Navbar: scroll + mobile open/close + ARIA `aria-expanded` toggling.
- Hero: LCP and CLS (no jumps when CSS loads).
- Footer: responsive grid across breakpoints.
- Focus states: keyboard navigation across pages.

