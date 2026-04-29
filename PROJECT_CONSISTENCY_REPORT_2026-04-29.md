# Project Consistency Report - 2026-04-29

Time window covered: approximately the last 20 hours of work.

## 1) Executive Summary

The project is now much more stable than where we started.

Major progress:
- CSS ownership is clearer (fewer global overrides).
- Mobile typography is now standardized for non-hero H1/H2.
- Critical visual regressions (radius and random override behavior) were reduced by moving rules to the right owner files.
- Investor page received a safe cleanup pass (better class reuse, fewer inline styles, real report links).
- Repeated verification checks (`npm run check`) have been passing.

Current state:
- Strongly improved consistency.
- Not fully prelaunch-secure yet (contact form flow, CSP tightening, dependency/security cleanup still pending).

## 2) What We Completed

### A. CSS architecture and ownership stabilization
- Stopped relying on global reset hacks for visual style control.
- Reduced `css/premium-polish.css` from being an everything-override file.
- Moved ownership-specific rules into component/page owners, including:
  - stats layout + metric strip behavior -> `css/components/stats.css`
  - hero arrow mobile behavior -> `css/hero-cinematic.css`
  - mobile hero layout behavior -> relevant page CSS files
- Documented ownership policy in:
  - `CSS_OWNERSHIP_AND_SECURITY_NOTES_2026-04-29.md`

### B. Mobile typography normalization (non-hero only)
- Your reference size ("Four Commodities. Endless Applications.") was used as the mobile cap.
- Implemented consistent non-hero H1/H2 sizing:
  - `<=768px`: `clamp(1.65rem, 8vw, 2.2rem)`
  - `<=480px`: `clamp(1.45rem, 9vw, 1.85rem)`
- Added final authority layer:
  - `css/pages/mobile-typography.css`
  - imported last in `src/styles/index.css`
- Cleaned remaining live outlier in contact map heading:
  - moved inline heading style to `.map-section__title` in `css/pages/contact.css`

### C. Investor page safe cleanup
- Converted placeholder-ish link behavior to real report destinations.
- Replaced repeated inline style patterns with reusable classes.
- Improved maintainability of KPI/revenue breakdown styling.
- Captured in:
  - `SAFE_CLEANUP_REPORT_2026-04-29.md`

### D. Security/deployment audit documented
- Captured prelaunch risks and priorities in:
  - `DEPLOYMENT_SECURITY_AUDIT_2026-04-29.md`

### E. Backups created before risky edits
- Multiple backups exist under `backups/` for rollback safety during these passes.

## 3) Code Consistency Status (Now)

## What is consistent now
- Clearer separation of tokens/reset/component/page responsibilities.
- Non-hero mobile heading scale is centrally controlled.
- Fewer hidden CSS battles from broad selectors.
- Build and link checks are passing.

## What is still inconsistent or fragile
- `css/premium-polish.css` still contains some synchronization and `!important` safety rules.
- Some inline styles still exist across HTML (not all are harmful, but they reduce predictability).
- Worktree is still noisy due to ongoing cleanup/untracking activity and many uncommitted changes.

## 4) Site Structure Status (Now)

Current structure direction is correct:
- tokens in `css/variables.css`
- reset in `css/reset.css`
- reusable components in `css/components/*`
- page-specific behavior in `css/pages/*`
- centralized import order in `src/styles/index.css`

This structure is now workable for safe edits, provided we keep following ownership rules.

## 5) Security/Launch Readiness Status

## Good
- Security headers and base hardening are already in place (`.htaccess` baseline is strong).
- Local checks are passing.

## Still required before production launch
1. Replace `mailto:` contact flow with a real form handler.
2. Move remaining inline executable scripts (notably in `contact.html` and `index.html`) into external JS.
3. Tighten CSP after inline scripts are removed.
4. Remove unused vulnerable dependency path (noted around `pip`) and re-run audit.
5. Ensure upload package excludes local/internal folders (`backups/`, `graphify-out/`, `.git/`, `node_modules/`, local reports).

## 6) Recommended Next Steps (Order)

### Phase 1 - Final consistency lock (short pass)
1. Commit current typography + CSS ownership stabilization as one checkpoint.
2. Run a controlled residual pass on `css/premium-polish.css` to remove only rules now duplicated by owner files.
3. Keep `mobile-typography.css` as final mobile heading authority until all page-level heading owners are fully normalized.

### Phase 2 - Prelaunch security hardening
1. Contact form productionization (real endpoint).
2. Externalize inline scripts.
3. CSP tightening update in `.htaccess`.
4. Dependency audit cleanup (`npm audit --omit=dev` target: no critical from unused paths).

### Phase 3 - Deployment preparation
1. Create clean deploy package/folder (production-only artifacts).
2. Run final checks:
   - `npm run check`
   - `npm audit --omit=dev`
3. Perform final mobile + desktop visual smoke test on key pages.
4. Upload to Hostinger.

## 7) Risk Register (Short)

- Risk: accidental reintroduction of global overrides.
  - Control: edit owner file only; avoid new broad selectors in `premium-polish.css`.
- Risk: mobile regressions from typography overrides.
  - Control: keep mobile heading caps centralized in `css/pages/mobile-typography.css`.
- Risk: security header looseness due to inline JS.
  - Control: externalize scripts before CSP tightening.
- Risk: noisy git state hides real changes.
  - Control: checkpoint commits in small, themed batches.

## 8) Overall Conclusion

You are in a strong position now. The project has moved from "fragile and override-prone" to "structured and controllable."

The best immediate move is to checkpoint this consistency state, then do one focused prelaunch security sprint. That sequence gives you both stability and a safer Hostinger release.

## 9) Git Reality Check (Important Before Push)

The "4k+" number is not commit count.

Snapshot at time of this report update:
- Repository commit history length: `60` commits.
- Pending working-tree changes: `4353` entries.
- Staged changes: `4319` (dominated by staged `node_modules` deletions from untracking).
- Unstaged changes: `20`.
- Untracked items: `14`.

Interpretation:
- This does not mean your site is broken.
- It means your current git state is very noisy and should be split into controlled commits.

Push hygiene guidance:
1. Keep `node_modules/`, `backups/`, and `graphify-out/` ignored (now enforced in `.gitignore`).
2. Commit in themed batches (consistency CSS, documentation, dependency/index cleanup).
3. Do not deploy from this raw workspace; deploy from a curated production package.
