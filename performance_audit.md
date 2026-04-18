# 🚀 Performance Audit: Balochistan Minerals

**Date:** April 16, 2026
**Status:** 🔴 CRITICAL (High Mobile Latency)

This report outlines the technical "choke points" currently slowing down the website, particularly on mobile devices, and provides a priority-ordered roadmap for fixes.

---

## 🔴 1. Massive Image Payloads (9.7 MB Total)
The single biggest cause of slow loading. The browser is struggling to download nearly 10MB of data just to show the homepage.

| File | Size | Impact | Recommendation |
| :--- | :--- | :--- | :--- |
| `hero-6.webp` | **1.85 MB** | 🔴 Critical | Compress to < 250KB |
| `hero-3.webp` | **1.84 MB** | 🔴 Critical | Compress to < 250KB |
| `hero-2.webp` | **1.66 MB** | 🔴 Critical | [Squoosh.app](https://squoosh.app) |
| `hero-4.webp` | **1.25 MB** | 🔴 Critical | Use 1200px width for mobile |
| `iron-ore-new.webp` | **874 KB** | 🔴 High | Convert to WebP 80% quality |

---

## 🟠 2. Raw HTML Bloat (146 KB)
The `index.html` file is nearly **150 KB of raw code**. 
*   **The Issue:** A typical optimized page is 30-50 KB. The browser must finish downloading this huge text file before it even starts fetching images or CSS.
*   **Fix:** Audit the 2,700+ lines for redundant sections or commented-out code.

---

## 🟠 3. Google Font Bloat (4 Families)
Currently loading: `Montserrat`, `Oswald`, `Poppins`, and `Playfair Display`.
*   **The Issue:** Each font family adds specific HTTP requests and blocks the "First Meaningful Paint."
*   **Fix:** Standardize on **2 families max** (e.g., Montserrat for UI, Playfair for headings). Remove the others.

---

## 🟠 4. Blocking JavaScript (GSAP & ScrollTrigger)
```html
<script src=".../gsap.min.js"></script>
<script src=".../ScrollTrigger.min.js"></script>
```
*   **The Issue:** These are external CDN scripts loaded without the `defer` or `async` attribute. The browser pauses all page rendering until these scripts are fully downloaded and executed.
*   **Fix:** Add `defer` to all footer script tags.

---

## 🟡 5. Unpurged Tailwind CSS (109 KB)
The `output.css` file is **109 KB**.
*   **The Issue:** This indicates that Tailwind is including thousands of utility classes that aren't actually used on the site. CSS is a "render-blocking" resource.
*   **Fix:** Ensure `tailwind.config.js` content paths are correct and run a production build.

---

## 🟡 6. Three.js Library Weight (~600 KB)
*   **The Issue:** Three.js is a heavy library used only for a subtle visual effect on the hero section. 
*   **Fix:** If the design allows, remove or use a lightweight SVG alternative.

---

## ✅ Implementation Checklist (Priority Order)

1. [ ] **Image Compression**: Use [Squoosh.app](https://squoosh.app) to get every image under 300KB.
2. [ ] **Add `defer`**: Update all footer scripts in `index.html`.
3. [ ] **Reduce Fonts**: Edit the Google Fonts `<link>` to only include Montserrat and Playfair Display.
4. [ ] **Purge CSS**: Run a proper Tailwind production build to shrink `output.css`.
