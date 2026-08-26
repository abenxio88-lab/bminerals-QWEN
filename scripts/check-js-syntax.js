import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const root = process.cwd();
const ignoredDirectories = new Set(['.git', 'node_modules', 'graphify-out', 'backups', '_backups']);

function listJavaScriptFiles(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
    const absolute = path.join(directory, entry.name);
    if (entry.isDirectory()) {
      return ignoredDirectories.has(entry.name) ? [] : listJavaScriptFiles(absolute);
    }
    return entry.isFile() && entry.name.endsWith('.js') ? [absolute] : [];
  });
}

const files = listJavaScriptFiles(root);
const failures = [];

for (const file of files) {
  const result = spawnSync(process.execPath, ['--check', file], { encoding: 'utf8' });
  if (result.status !== 0) {
    failures.push(`${path.relative(root, file)}\n${result.stderr || result.stdout}`);
  }
}

if (failures.length) {
  console.error(`JavaScript syntax failed in ${failures.length} file(s):\n${failures.join('\n')}`);
  process.exit(1);
}

console.log(`JavaScript syntax checks passed for ${files.length} files.`);
