import fs from 'node:fs';
import path from 'node:path';
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

console.log(`CSS build is synchronized (${Buffer.byteLength(committedOutput)} bytes).`);
