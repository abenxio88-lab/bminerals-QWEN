## Styling Rules

- Keep page-specific CSS in `css/pages/*.css`, not in inline `<style>` blocks inside HTML.
- Keep reusable component CSS in `css/components/*.css` or the shared source stylesheet when appropriate.
- Avoid element-level `style=""` attributes for layout, color, spacing, and typography unless the value is truly dynamic, such as a data-driven CSS variable.
- If a page needs a one-off visual adjustment, create a named class and place the rule in that page's CSS file.
- Before adding new CSS, check whether the page already has a linked stylesheet and extend that file first.
