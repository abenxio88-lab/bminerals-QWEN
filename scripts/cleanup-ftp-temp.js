import fs from 'node:fs';
import path from 'node:path';
import { Client } from 'basic-ftp';

function getCandidateFiles(dir, baseDir = '') {
  let results = [];
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    if (['.git', 'node_modules', '.github', '.vscode', 'graphify-out', 'src'].includes(entry.name)) {
      continue;
    }
    const fullPath = path.join(dir, entry.name);
    const relPath = path.join(baseDir, entry.name).split(path.sep).join('/');
    if (entry.isDirectory()) {
      results = results.concat(getCandidateFiles(fullPath, relPath));
    } else {
      // Focus on web pages, stylesheets, scripts, and documents that get updated
      if (/\.(html|css|js|json|txt|xml|md)$/i.test(entry.name)) {
        results.push(relPath);
      }
    }
  }
  return results;
}

async function cleanFtpTempFiles() {
  const host = process.env.FTP_SERVER;
  const user = process.env.FTP_USERNAME;
  const pass = process.env.FTP_PASSWORD;

  if (!host || !user || !pass) {
    console.log('FTP credentials not available, skipping pre-clean');
    process.exit(0);
  }

  const client = new Client();
  client.ftp.timeout = 25000;

  try {
    console.log(`Connecting to FTP server ${host} for cleanup...`);
    await client.access({
      host,
      user,
      password: pass,
      secure: false
    });
    console.log('Connected! Scanning for stranded ProFTPD HiddenStores temp files (.in.*)...');

    let deletedCount = 0;

    // 1. Directory scan: look for any files matching .in.* in key folders
    const dirsToScan = [
      '/',
      '/blog',
      '/css',
      '/css/components',
      '/css/pages',
      '/documents',
      '/js',
      '/vendor'
    ];

    for (const dir of dirsToScan) {
      try {
        const list = await client.list(dir);
        for (const item of list) {
          if (item.name.startsWith('.in.') || item.name.includes('.in.')) {
            const target = dir === '/' ? `/${item.name}` : `${dir}/${item.name}`;
            console.log(`[Listing] Found stranded temp file: ${target}, deleting...`);
            try {
              await client.remove(target);
              console.log(`[Listing] Successfully removed ${target}`);
              deletedCount++;
            } catch (delErr) {
              console.log(`[Listing] Failed to remove ${target}:`, delErr.message);
            }
          }
        }
      } catch (listErr) {
        // Directory may not exist or cannot be listed, that is fine
      }
    }

    // 2. Direct probe: ProFTPD HiddenStores files might be invisible to LIST
    // Probe all candidate repo files and send DELE for their .in. variants
    const candidateFiles = getCandidateFiles(process.cwd());
    console.log(`Probing ${candidateFiles.length} candidate file targets for hidden .in.* files...`);

    for (const rel of candidateFiles) {
      const dirname = path.posix.dirname(rel);
      const basename = path.posix.basename(rel);
      const tempTarget = dirname === '.' ? `/.in.${basename}.` : `/${dirname}/.in.${basename}.`;

      try {
        const res = await client.sendIgnoringError(`DELE ${tempTarget}`);
        if (res && res.code && res.code >= 200 && res.code < 300) {
          console.log(`[Probe] Successfully deleted stranded file: ${tempTarget}`);
          deletedCount++;
        }
      } catch {
        // Ignore errors
      }
    }

    console.log(`FTP pre-clean finished. Total stranded temporary files deleted: ${deletedCount}`);
  } catch (err) {
    console.log('FTP pre-clean notice (continuing to deployment):', err.message);
  } finally {
    try {
      client.close();
    } catch {}
    process.exit(0);
  }
}

cleanFtpTempFiles();
