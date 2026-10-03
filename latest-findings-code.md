now i want youcheck my code thorugly and see if there is any gap or needs fixing, only report no eidt







10:09 AM






Worked for 18m 58s



Audit complete. I made no code changes and pushed nothing. The Git worktree is clean.
High-priority findings
1. Deployments have no quality gate. Every push to main goes directly to FTP after checkout; it does not install dependencies, build CSS, or run checks. See [deploy.yml (line 12)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/.github/workflows/deploy.yml:12). This is especially risky because:
   - output.css is currently out of sync with its source. The generated file is missing the recent text-align-last: center About-page rule from [about.css (line 886)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/css/pages/about.css:886).
   - npm run check:image-modals currently fails because [our-mines.html (line 2430)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/our-mines.html:2430) uses a different main.js version than the checker requires in [check-image-modal-safety.js (line 75)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/scripts/check-image-modal-safety.js:75).
2. The active navigation logic is incompatible with clean URLs. [utils.js (line 1)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/js/utils.js:1) compares about from /about against links such as /about or about.html without normalizing them. Meanwhile, [.htaccess (line 21)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/.htaccess:21) redirects .html URLs to clean URLs. This explains why active orange indicators can disappear on About and potentially every clean-route page.
3. Cookie consent does not actually control analytics.
   - Google Analytics starts immediately on 34 of 35 HTML pages, for example [about.html (line 145)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/about.html:145).
   - The cookie popup appears on only four pages.
   - Analytics and marketing are preselected in [cookies-popup.js (line 91)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/js/cookies-popup.js:91).
   - “Customize Preferences” calls an empty method, and initializeTracking() incorrectly says no tracking is configured at [cookies-popup.js (line 191)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/js/cookies-popup.js:191).
   This creates a meaningful privacy/compliance risk. The Web3Forms contact submission should also be clearly disclosed in the privacy policy.
4. Page CSS is globally bundled and then loaded again separately. [src/styles/index.css (line 35)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/src/styles/index.css:35) imports every page stylesheet. Pages then load their own stylesheet again, such as [about.html (line 88)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/about.html:88) and [products.html (line 174)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/products.html:174).
   This is already causing cross-page bugs: the unscoped .parallax-hero__text rule in [products.css (line 1044)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/css/pages/products.css:1044) applies justified/left-last-line text to unrelated pages. That is the underlying reason the About heading needed increasingly specific overrides.
5. Dependency audit reports four high-severity vulnerabilities. Affected packages include postcss, nanoid, sharp, and svgo. Most are build-time dependencies, which reduces public runtime exposure, but they still affect CI, image processing, and developer machines. sharp requires a major-version upgrade for the available fix.
Medium-priority findings
6. Twenty-four navigation/card links point to a nonexistent anchor: /projects#khuzdar, for example [about.html (line 275)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/about.html:275). The corresponding project section is actually id="industrial-barite" in [projects.html (line 1761)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/projects.html:1761).
7. The policy-page hero background is broken. [policy.css (line 27)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/css/pages/policy.css:27) resolves to a nonexistent css/pages/images/hero/footer-bg-new.webp.
8. Search-modal accessibility is incomplete across 27 pages. The dialog references aria-labelledby="search-title", but that ID does not exist, and the search input has no label or aria-label. Example: [about.html (line 922)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/about.html:922). Three freight-calculator selects also lack properly associated labels.
9. The site checker is producing 2,443 mostly false missing-file errors. [check-site.js (line 110)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/scripts/check-site.js:110) treats /about as a literal file instead of mapping it to about.html. Consequently, the main check command is not currently a trustworthy deployment gate.
10. Third-party script protection could be stronger. Lenis is loaded from unpkg.com on 20 pages without integrity metadata. The CSP in [.htaccess (line 46)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/.htaccess:46) permits 'unsafe-inline' scripts/styles and broadly trusts unpkg.com, weakening its XSS protection.
11. CSS and document payloads are heavy.
    - output.css: approximately 379 KB raw / 59 KB gzip.
    - Products page CSS: approximately 512 KB raw because of duplicate loading.
    - index.html: approximately 250 KB raw.
    - The deployable repository is approximately 109 MB, including large videos and image originals.
Lower-priority inconsistencies
- The website alternates between “Since 2008” and “Founded/Since 2015.” Compare [navbar.js (line 71)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/js/navbar.js:71) with [about.html (line 960)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/about.html:960). The distinction may be intentional, but it currently reads as contradictory.
- Footer Facebook, YouTube, Instagram, and LinkedIn icons link to internal pages rather than social profiles. Example: [about.html (line 1593)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/about.html:1593).
- Two homepage images lack explicit dimensions, increasing layout-shift risk: [index.html (line 4086)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/index.html:4086) and [index.html (line 4118)](C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/index.html:4118).
- There are no automated test files, browser tests, accessibility tests, linting configuration, or README. package.json also contains placeholder metadata such as "name": "x".
Checks that passed
- All JavaScript files passed syntax checking.
- CSS compiled successfully in memory.
- No duplicate HTML IDs were found.
- Every audited page has one H1.
- All images have alt attributes.
- Canonical URLs and sitemap entries match across the 34 indexable pages.
- Structured-data JSON parsed successfully.
- No case-sensitive local asset mismatches were found.
- .htaccess includes HTTPS redirects and useful security headers.
This was a repository-level static/build audit; real-device visual and interaction regression testing is still recommended, particularly for the mobile navbar and dropdown states.