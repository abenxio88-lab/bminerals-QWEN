Build steps (CSS)

1. Install dev dependencies:

```bash
npm install --save-dev postcss postcss-cli autoprefixer cssnano tailwindcss
```

2. Build once:

```bash
npm run build:css
```

3. Watch during development:

```bash
npm run watch:css
```

Notes:
- `src/styles/index.css` is the new entry; it imports files from `css/`.
- `output.css` is the built artifact — do not edit directly.
