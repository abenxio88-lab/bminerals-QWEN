import fs from "node:fs";
import path from "node:path";

const REPO_ROOT = process.cwd();

const IGNORE_DIRS = new Set([
  ".git",
  "node_modules",
  "backups",
  "_backups",
  "graphify-out",
]);

function isExternalUrl(u) {
  return (
    u.startsWith("http://") ||
    u.startsWith("https://") ||
    u.startsWith("mailto:") ||
    u.startsWith("tel:") ||
    u.startsWith("data:") ||
    u.startsWith("javascript:")
  );
}

function stripHashAndQuery(u) {
  // Keep only the path portion: foo.html?x=1#y -> foo.html
  const q = u.indexOf("?");
  const h = u.indexOf("#");
  const cut = Math.min(q === -1 ? u.length : q, h === -1 ? u.length : h);
  return u.slice(0, cut);
}

function walkHtmlFiles(dirAbs) {
  const out = [];
  const entries = fs.readdirSync(dirAbs, { withFileTypes: true });
  for (const ent of entries) {
    const abs = path.join(dirAbs, ent.name);
    if (ent.isDirectory()) {
      if (IGNORE_DIRS.has(ent.name)) continue;
      out.push(...walkHtmlFiles(abs));
      continue;
    }
    if (ent.isFile() && ent.name.toLowerCase().endsWith(".html")) out.push(abs);
  }
  return out;
}

function fileExists(p) {
  try {
    return fs.statSync(p).isFile();
  } catch {
    return false;
  }
}

function checkHtmlFile(htmlPathAbs) {
  const rel = path.relative(REPO_ROOT, htmlPathAbs);
  const dir = path.dirname(htmlPathAbs);
  const html = fs.readFileSync(htmlPathAbs, "utf8");

  const issues = [];

  // Placeholder links (high-signal + usually accidental).
  if (/\bhref\s*=\s*["']#["']/i.test(html)) {
    issues.push({ type: "placeholder", file: rel, detail: 'Found href="#"' });
  }
  if (/\bhref\s*=\s*["']javascript:void/i.test(html)) {
    issues.push({
      type: "placeholder",
      file: rel,
      detail: "Found javascript:void(...) href",
    });
  }

  if (rel.startsWith(`blog${path.sep}`)) {
    const requiredBlogDetailMarkers = [
      [/<div class="article-progress"/, "Missing article reading progress"],
      [/<nav class="article-breadcrumb"/, "Missing article breadcrumb"],
      [/<article class="article-content" id="article-content">/, "Missing article content anchor"],
      [/<div class="article-intro">/, "Missing article intro block"],
      [/<nav class="article-sidebox article-toc"/, "Missing article table of contents"],
      [/data-copy-link/, "Missing copy-link share action"],
      [/<section class="article-related"/, "Missing related insights section"],
    ];

    for (const [pattern, detail] of requiredBlogDetailMarkers) {
      if (!pattern.test(html)) {
        issues.push({ type: "blog-style", file: rel, detail });
      }
    }

    if (/--article-image:\s*url\('\.\.\/images\//.test(html)) {
      issues.push({
        type: "blog-style",
        file: rel,
        detail: "Hero image custom property must use ../../images/... so external article CSS resolves it",
      });
    }
  }

  // Basic href/src scanning. Intentionally simple; we just want guardrails.
  const attrRe = /\b(?:href|src)\s*=\s*["']([^"']+)["']/gi;
  let m;
  while ((m = attrRe.exec(html)) !== null) {
    const raw = m[1].trim();
    if (!raw) continue;
    if (raw.startsWith("#")) continue;
    if (isExternalUrl(raw)) continue;

    const cleaned = stripHashAndQuery(raw);
    if (!cleaned) continue;

    // If it ends with '/', treat it like a directory and skip (static site, no router).
    if (cleaned.endsWith("/")) continue;

    const targetAbs = cleaned.startsWith("/")
      ? path.join(REPO_ROOT, cleaned.slice(1))
      : path.resolve(dir, cleaned);

    // If the reference points at a directory, we don't know what to expect; skip.
    try {
      const st = fs.statSync(targetAbs);
      if (st.isDirectory()) continue;
    } catch {
      // fallthrough to report as missing file
    }

    if (!fileExists(targetAbs)) {
      issues.push({
        type: "missing",
        file: rel,
        detail: `Missing target: ${raw}`,
      });
    }
  }

  return issues;
}

function main() {
  const htmlFiles = walkHtmlFiles(REPO_ROOT);
  const allIssues = [];
  for (const f of htmlFiles) allIssues.push(...checkHtmlFile(f));

  if (allIssues.length === 0) {
    console.log(`OK: scanned ${htmlFiles.length} HTML files; no issues found.`);
    process.exit(0);
  }

  const byType = new Map();
  for (const it of allIssues) {
    if (!byType.has(it.type)) byType.set(it.type, []);
    byType.get(it.type).push(it);
  }

  console.error(`Found ${allIssues.length} issue(s):`);
  for (const [type, items] of byType.entries()) {
    console.error(`\n[${type}] (${items.length})`);
    for (const it of items.slice(0, 80)) {
      console.error(`- ${it.file}: ${it.detail}`);
    }
    if (items.length > 80) console.error(`- ...and ${items.length - 80} more`);
  }

  process.exit(1);
}

main();
