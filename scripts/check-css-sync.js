import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import postcss from 'postcss';
import postcssConfig from '../postcss.config.js';

const root = process.cwd();
const inputPath = path.join(root, 'src', 'styles', 'index.css');
const outputPath = path.join(root, 'output.css');
const input = fs.readFileSync(inputPath, 'utf8');
const committedOutput = fs.readFileSync(outputPath, 'utf8');

const result = await postcss(postcssConfig.plugins).process(input, {
  from: inputPath,
  to: outputPath,
  map: false
});

if (result.css !== committedOutput) {
  console.error('output.css is not synchronized with src/styles/index.css. Run npm run build:css and commit the result.');
  process.exit(1);
}

function listHtmlFiles(directory) {
  const excludedDirectories = new Set(['.git', 'graphify-out', 'node_modules']);
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    if (entry.isDirectory() && excludedDirectories.has(entry.name)) return [];
    const fullPath = path.join(directory, entry.name);
    if (entry.isDirectory()) return listHtmlFiles(fullPath);
    return entry.name.endsWith('.html') ? [fullPath] : [];
  });
}

const normalizedOutput = committedOutput.replace(/\r\n?/g, '\n');
const requiredVersion = crypto.createHash('sha256').update(normalizedOutput).digest('hex').slice(0, 12);
const staleReferences = [];

for (const htmlFile of listHtmlFiles(root)) {
  const html = fs.readFileSync(htmlFile, 'utf8');
  const references = html.match(/(?:\.\.\/|\/)?output\.css(?:\?[^"']*)?/g) || [];
  for (const reference of references) {
    const version = reference.match(/[?&]v=([^&"']+)/)?.[1];
    if (version !== requiredVersion) {
      staleReferences.push(`${path.relative(root, htmlFile)}: ${reference}`);
    }
  }
}

if (staleReferences.length) {
  console.error(`Found ${staleReferences.length} stale output.css reference(s); expected v=${requiredVersion}:`);
  for (const reference of staleReferences) console.error(`- ${reference}`);
  process.exit(1);
}

console.log(`CSS build is synchronized (${Buffer.byteLength(committedOutput)} bytes, v=${requiredVersion}).`);
