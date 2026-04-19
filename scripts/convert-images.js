#!/usr/bin/env node
// Simple image conversion script using sharp
// Converts images in images/hero to WebP and AVIF

import fs from 'fs';
import path from 'path';
import sharp from 'sharp';

const srcDir = path.resolve('./images/hero');
const outDir = path.resolve('./images/hero/optimized');

if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });

async function convertFile(file) {
  const ext = path.extname(file).toLowerCase();
  const base = path.basename(file, ext);
  const input = path.join(srcDir, file);

  try {
    await sharp(input).webp({ quality: 80 }).toFile(path.join(outDir, `${base}.webp`));
    await sharp(input).avif({ quality: 50 }).toFile(path.join(outDir, `${base}.avif`));
    console.log(`Converted ${file} → ${base}.webp, ${base}.avif`);
  } catch (err) {
    console.error(`Failed ${file}:`, err.message);
  }
}

fs.readdir(srcDir, (err, files) => {
  if (err) return console.error(err);
  files.filter(f => /\.(jpe?g|png|webp)$/i.test(f)).forEach(f => convertFile(f));
});
