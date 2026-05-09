# Balochistan Minerals - Technical Mapping & Design Standards

This document serves as a reference for the directory structure, style standards, and technical mappings to ensure future edits maintain consistency across the platform.

## 1. Hero Section Standardization (Institutional Benchmark)

The **Products** page serves as the visual benchmark for hero sections. All subpages have been standardized to match its typography and layout.

### Typography Standards
All hero descriptions must use the following standard:
- **Font Size**: `var(--text-base)` (equivalent to `1rem`)
- **Font Weight**: Standard weight (overriding any bold/thin presets)
- **Text Wrap**: `text-wrap: pretty` (for improved readability and orphans prevention)
- **Override Usage**: Use `!important` on the `font-size` declaration to ensure it wins over bundled legacy styles in `output.css`.

### Layout Standards
- **Standard Height**: `45vh`
- **Minimum Height**: `320px` (ensures content fits on small mobile screens)
- **Background Image**: `height: 120%` (for subtle parallax depth)

---

## 2. File Mapping (HTML to CSS)

Since the build pipeline (`npm run build:css`) currently has environment compatibility issues, we are linking page-specific CSS files **directly** in the `<head>` of HTML files, immediately after the main `output.css`.

| Page | HTML File | Specific CSS File |
| :--- | :--- | :--- |
| **About Us** | [about.html](about.html) | [css/pages/about.css](css/pages/about.css) |
| **Our Products** | [products.html](products.html) | [css/pages/products.css](css/pages/products.css) |
| **Mining Projects** | [projects.html](projects.html) | [css/pages/projects.css](css/pages/projects.css) |
| **Logistics** | [logistics.html](logistics.html) | [css/pages/logistics.css](css/pages/logistics.css) |
| **Investors** | [investors.html](investors.html) | [css/pages/investors.css](css/pages/investors.css) |
| **Sustainability** | [sustainability.html](sustainability.html) | [css/pages/sustainability.css](css/pages/sustainability.css) |
| **Contact** | [contact.html](contact.html) | [css/pages/contact.css](css/pages/contact.css) |
| **Our Mines** | [our-mines.html](our-mines.html) | [css/pages/our-mines.css](css/pages/our-mines.css) |
| **Blog Listing** | [blogs.html](blogs.html) | (Internal Styles + [output.css](output.css)) |

---

## 3. CSS Architecture

The project follows a modular CSS architecture defined in `src/styles/index.css`.

### Import Order
1.  **Variables**: `css/variables.css` (Colors, Spacing, Typography tokens)
2.  **Reset**: `css/reset.css`
3.  **Global**: `css/components/global-improvements.css`
4.  **Components**: Navigation, Footer, Buttons, Cards.
5.  **Pages**: Page-specific layouts (About, Projects, etc.)

> [!IMPORTANT]
> Always edit the files in `css/pages/` or `css/components/`. **NEVER** edit `output.css` directly, as it is a minified bundle that will be overwritten if the build pipeline is fixed.

---

## 4. Known Issues & Maintenance

### Build Pipeline
- **Status**: Currently Failing
- **Cause**: Environment PATH missing `powershell` executable for PostCSS execution.
- **Current Workaround**: Linking individual CSS files in HTML as noted in Section 2. If the build system is restored, these extra links should be removed and the `output.css` will handle everything via the `@import` rules in `index.css`.

### Mobile Overrides
If text alignment looks wrong on mobile, check `css/pages/mobile-typography.css` which contains global responsive overrides for all subpages.
