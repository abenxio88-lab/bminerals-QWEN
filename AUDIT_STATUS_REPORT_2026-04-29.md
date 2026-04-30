# Audit Status Report - 2026-04-29

## Scope

This report captures where the project stands after JS/CSS hardening work and full local verification checks.

## Current Standing

### Build and structural checks

- `npm run check` passes.
- Static integrity check passes:
  - `OK: scanned 31 HTML files; no issues found.`
- CSS output is bundled and valid for deployment use:
  - `output.css` size: ~161 KB
  - runtime `@import` count in `output.css`: `0`

### Security and resilience progress

- Completed:
  - Replaced modal `innerHTML` creation with safe DOM API construction in `js/intelligence-hub.js`.
  - Added hidden-tab pause/resume behavior for continuous animation loops in:
    - `js/border-beam.js`
    - `js/earth-tech-core.js`
  - Reduced CSS specificity debt in hotspot:
    - `css/components/cta.css` now has `0` `!important` usages.
  - Added z-index tokens in `css/variables.css` and moved key layers to tokenized values in navbar/loader/modal/global utilities.
- Current CSS-wide `!important` count:
  - now `208` (down from `241` during earlier audit).

### Worktree status

- Local changes are intentionally in progress across CSS/JS hardening files.
- Untracked helper script exists:
  - `scripts/graphify_full_build.py`

## Remaining Gaps (Open)

1. Contact form is still `mailto`-based (intentionally deferred).
2. CSP can be tightened later when inline script paths are finalized.
3. Some debug logs remain in `js/main.js`.
4. Residual global CSS debt still exists outside CTA and should be cleaned in a staged pass.

## Graphify Impact (Up To Now)

## Did Graphify help?

Yes, but **partially** and mostly as an analysis accelerator, not as a direct code fixer.

### Where Graphify helped

- It surfaced structural clusters and weakly connected assets/modules quickly.
- It highlighted likely dead/isolated CSS areas (`cards.css`, `mines-logistics.css`) that aligned with later manual checks.
- It gave a high-level map of cross-file relationships that helped triage audit focus areas faster.

### Where Graphify did not materially help yet

- It did not directly implement hardening changes.
- It produced some noisy/inferred relationships from minified vendor code that required manual filtering.
- Its outputs are not yet integrated into CI, linting, or automated regression gating.

## Practical verdict

Graphify has provided **useful discovery value** for a large static codebase, but it has **not yet become a core enforcement tool** in this project lifecycle. The decisive quality and security improvements still came from targeted manual engineering passes plus build/check validation.

## Recommendation for next use

Use Graphify as a periodic architecture/audit companion (for drift detection and dead-module discovery), not as a replacement for manual code review and release checks.

