#!/usr/bin/env node
/**
 * Balochistan Minerals - Automated Production Database Synchronization
 * 
 * Fetches the latest live database dump from https://balochistanminerals.com
 * and updates your local XAMPP MySQL database in ~2 seconds.
 * 
 * Usage:
 *   npm run pull                     # Auto-pull from production and update local DB
 *   npm run pull:db
 *   npm run db:pull
 *   npm run pull -- --file <path>    # Import a specific .sql dump manually
 */

import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { spawnSync, spawn } from 'node:child_process';

const DEFAULT_PROD_URL = 'https://balochistanminerals.com';
const DEFAULT_SYNC_KEY = 'bm_sync_8c4f2a9e1d7b309f7a6c5e2d14b89320';

const DB_CONFIG = {
  host: process.env.DB_HOST || '127.0.0.1',
  name: process.env.DB_NAME || 'balochistan_minerals',
  user: process.env.DB_USER || 'root',
  pass: process.env.DB_PASS || '',
};

function parseArgs() {
  const args = process.argv.slice(2);
  const options = {
    file: null,
    key: null,
    url: process.env.BM_PROD_URL || DEFAULT_PROD_URL,
  };

  for (let i = 0; i < args.length; i++) {
    if (args[i] === '--file' && args[i + 1]) {
      options.file = path.resolve(process.cwd(), args[++i]);
    } else if (args[i] === '--key' && args[i + 1]) {
      options.key = args[++i];
    } else if (args[i] === '--url' && args[i + 1]) {
      options.url = args[++i].replace(/\/+$/, '');
    }
  }
  return options;
}

function resolveSyncKey(overrideKey) {
  if (overrideKey) return overrideKey;
  if (process.env.BM_SYNC_KEY) return process.env.BM_SYNC_KEY;

  const syncConfigPath = path.join(process.cwd(), 'admin', 'sync-config.php');
  if (fs.existsSync(syncConfigPath)) {
    try {
      const content = fs.readFileSync(syncConfigPath, 'utf8');
      const match = content.match(/define\(\s*['"]BM_SYNC_KEY['"]\s*,\s*['"]([^'"]+)['"]\s*\)/);
      if (match && match[1]) return match[1];
    } catch {
      // ignore
    }
  }

  return DEFAULT_SYNC_KEY;
}

function findMysqlBinary() {
  if (process.env.MYSQL_BIN && fs.existsSync(process.env.MYSQL_BIN)) {
    return process.env.MYSQL_BIN;
  }

  const commonPaths = [
    'C:\\xampp\\mysql\\bin\\mysql.exe',
    'D:\\xampp\\mysql\\bin\\mysql.exe',
    'E:\\xampp\\mysql\\bin\\mysql.exe',
    'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe',
    'C:\\Program Files\\MariaDB 10.5\\bin\\mysql.exe',
    '/opt/lampp/bin/mysql',
    '/usr/local/mysql/bin/mysql',
    '/usr/bin/mysql',
  ];

  for (const p of commonPaths) {
    if (fs.existsSync(p)) return p;
  }

  // Check PATH
  const isWindows = process.platform === 'win32';
  const checkCmd = isWindows ? 'where.exe' : 'which';
  try {
    const res = spawnSync(checkCmd, ['mysql'], { encoding: 'utf8' });
    if (res.status === 0 && res.stdout.trim()) {
      const firstLine = res.stdout.trim().split(/\r?\n/)[0];
      if (fs.existsSync(firstLine)) return firstLine;
    }
  } catch {
    // ignore
  }

  return 'mysql';
}

function checkMysqlConnection(mysqlBin) {
  const args = ['-h', DB_CONFIG.host, '-u', DB_CONFIG.user];
  if (DB_CONFIG.pass) args.push(`-p${DB_CONFIG.pass}`);
  args.push('-e', 'SELECT 1;');

  const res = spawnSync(mysqlBin, args, { encoding: 'utf8' });
  return res.status === 0;
}

function ensureDatabaseExists(mysqlBin) {
  const args = ['-h', DB_CONFIG.host, '-u', DB_CONFIG.user];
  if (DB_CONFIG.pass) args.push(`-p${DB_CONFIG.pass}`);
  args.push('-e', `CREATE DATABASE IF NOT EXISTS \`${DB_CONFIG.name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`);

  const res = spawnSync(mysqlBin, args, { encoding: 'utf8' });
  if (res.status !== 0) {
    throw new Error(`Failed to ensure database exists: ${res.stderr || res.stdout}`);
  }
}

function findRecentDownloadedBackup() {
  const searchDirs = [
    process.cwd(),
    path.join(os.homedir(), 'Downloads'),
  ];

  const found = [];
  for (const dir of searchDirs) {
    if (!fs.existsSync(dir)) continue;
    try {
      const files = fs.readdirSync(dir);
      for (const f of files) {
        if (/^BM_DB_BACKUP_.*\.sql$/i.test(f)) {
          const abs = path.join(dir, f);
          const stat = fs.statSync(abs);
          found.push({ path: abs, name: f, mtime: stat.mtimeMs });
        }
      }
    } catch {
      // ignore unreadable dirs
    }
  }

  if (found.length === 0) return null;
  found.sort((a, b) => b.mtime - a.mtime);
  return found[0];
}

async function fetchProductionSql(baseUrl, syncKey) {
  const endpoint = `${baseUrl}/admin/api-backup.php?mode=sql`;
  console.log(`🌐 Connecting to: ${endpoint}`);

  try {
    const res = await fetch(endpoint, {
      method: 'GET',
      headers: {
        'X-BM-SYNC-KEY': syncKey,
        'Authorization': `Bearer ${syncKey}`,
        'User-Agent': 'BalochistanMinerals-SyncCLI/1.0',
      },
    });

    const text = await res.text();

    if (!res.ok) {
      return { success: false, status: res.status, error: text };
    }

    // Verify content looks like a valid MySQL dump
    if (text.includes('-- Balochistan Minerals Full MySQL Backup') || text.includes('CREATE TABLE')) {
      return { success: true, sql: text };
    }

    return { success: false, status: res.status, error: text };
  } catch (err) {
    return { success: false, error: err.message };
  }
}

function importSql(mysqlBin, sqlText) {
  return new Promise((resolve, reject) => {
    const args = ['-h', DB_CONFIG.host, '-u', DB_CONFIG.user];
    if (DB_CONFIG.pass) args.push(`-p${DB_CONFIG.pass}`);
    args.push(DB_CONFIG.name);

    const child = spawn(mysqlBin, args, { stdio: ['pipe', 'inherit', 'inherit'] });

    child.on('error', (err) => reject(err));
    child.on('close', (code) => {
      if (code === 0) resolve();
      else reject(new Error(`MySQL import failed with exit code ${code}`));
    });

    child.stdin.write(sqlText);
    child.stdin.end();
  });
}

function getDatabaseStats(mysqlBin) {
  const query = [
    "SELECT 'Inquiries' as tbl, COUNT(*) as cnt FROM inquiries",
    "UNION ALL SELECT 'Email Messages', COUNT(*) FROM email_messages",
    "UNION ALL SELECT 'Visitor Radar', COUNT(*) FROM visitor_radar",
    "UNION ALL SELECT 'Export Deals', COUNT(*) FROM deals",
    "UNION ALL SELECT 'Mineral Prices', COUNT(*) FROM mineral_prices",
    "UNION ALL SELECT 'Stockpiles', COUNT(*) FROM stockpiles",
    "UNION ALL SELECT 'Admin Staff', COUNT(*) FROM admin_users;",
  ].join(' ');

  const args = ['-h', DB_CONFIG.host, '-u', DB_CONFIG.user];
  if (DB_CONFIG.pass) args.push(`-p${DB_CONFIG.pass}`);
  args.push('-sN', DB_CONFIG.name, '-e', query);

  const res = spawnSync(mysqlBin, args, { encoding: 'utf8' });
  if (res.status !== 0) return null;

  const rows = [];
  const lines = res.stdout.trim().split(/\r?\n/);
  for (const line of lines) {
    const parts = line.split('\t');
    if (parts.length >= 2) {
      rows.push({ label: parts[0], count: parts[1] });
    }
  }
  return rows;
}

async function main() {
  console.log('\n======================================================');
  console.log('  BALOCHISTAN MINERALS — PRODUCTION DATABASE SYNC');
  console.log('======================================================\n');

  const options = parseArgs();
  const syncKey = resolveSyncKey(options.key);
  const mysqlBin = findMysqlBinary();

  // 1. Check local MySQL
  process.stdout.write(`🔍 Verifying local MySQL (${DB_CONFIG.host}:${DB_CONFIG.name})... `);
  if (!checkMysqlConnection(mysqlBin)) {
    console.log('❌ FAILED\n');
    console.error('Error: Could not connect to local MySQL.');
    console.error('Please ensure XAMPP MySQL is running (Start MySQL in XAMPP Control Panel).\n');
    process.exit(1);
  }
  console.log('✅ Connected');

  ensureDatabaseExists(mysqlBin);

  let sqlContent = null;
  let sourceLabel = '';

  // 2. Obtain SQL content
  if (options.file) {
    if (!fs.existsSync(options.file)) {
      console.error(`\n❌ Specified file not found: ${options.file}\n`);
      process.exit(1);
    }
    console.log(`📁 Using manual file: ${options.file}`);
    sqlContent = fs.readFileSync(options.file, 'utf8');
    sourceLabel = `Manual File (${path.basename(options.file)})`;
  } else {
    process.stdout.write('📡 Fetching latest snapshot from live production... ');
    const fetchResult = await fetchProductionSql(options.url, syncKey);

    if (fetchResult.success) {
      console.log('✅ Done');
      sqlContent = fetchResult.sql;
      sourceLabel = `${options.url} (Live Production)`;
    } else {
      console.log('⚠️  Remote API unavailable');
      console.log(`   Notice: ${fetchResult.error ? fetchResult.error.slice(0, 150).replace(/\r?\n/g, ' ') : 'Unauthorized or unreachable'}`);

      // Try fallback to recent downloaded file
      const recentBackup = findRecentDownloadedBackup();
      if (recentBackup) {
        console.log(`\n💡 Auto-detected recent backup in Downloads:`);
        console.log(`   👉 ${recentBackup.path}`);
        console.log('   Using this backup to update your local database now...');
        sqlContent = fs.readFileSync(recentBackup.path, 'utf8');
        sourceLabel = `Recent Backup (${recentBackup.name})`;
      } else {
        console.error('\n❌ Could not pull database from production, and no local backup file was found.');
        console.error('\nTo enable 100% automatic 1-click pulls:');
        console.error('1. Push the latest commit to GitHub (which updates production api-backup.php).');
        console.error('2. Or download a snapshot manually once via:');
        console.error(`   ${options.url}/admin/api-backup.php?mode=sql`);
        console.error('   Then rerun: npm run pull\n');
        process.exit(1);
      }
    }
  }

  // 3. Import SQL into local MySQL
  process.stdout.write(`⚡ Importing into local database (${DB_CONFIG.name})... `);
  const startTime = Date.now();
  try {
    await importSql(mysqlBin, sqlContent);
    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`✅ Completed (${duration}s)\n`);
  } catch (err) {
    console.log('❌ FAILED');
    console.error(`\nImport Error: ${err.message}\n`);
    process.exit(1);
  }

  // 4. Verification and table stats
  const stats = getDatabaseStats(mysqlBin);

  console.log('======================================================');
  console.log('  DATABASE SYNCHRONIZATION SUMMARY');
  console.log('======================================================');
  console.log(`  Source:      ${sourceLabel}`);
  console.log(`  Target:      ${DB_CONFIG.host} / ${DB_CONFIG.name}`);
  console.log('------------------------------------------------------');
  if (stats && stats.length > 0) {
    for (const stat of stats) {
      console.log(`  • ${stat.label.padEnd(16)} : ${stat.count.padStart(6)} records`);
    }
  }
  console.log('======================================================');
  console.log('  🎉 Local database is now 100% in sync with Production!');
  console.log('  👉 Admin Dashboard: http://localhost:8000/admin/dashboard.php');
  console.log('======================================================\n');
}

main().catch((err) => {
  console.error('\nUnexpected Error:', err);
  process.exit(1);
});
