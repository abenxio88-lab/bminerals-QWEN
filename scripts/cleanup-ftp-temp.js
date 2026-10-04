import fs from 'node:fs';
import path from 'node:path';
import net from 'node:net';

function getTargets(dir, baseDir = '') {
  let list = [];
  for (const f of fs.readdirSync(dir, { withFileTypes: true })) {
    if (['.git', 'node_modules', '.github', '.vscode', 'graphify-out', 'src'].includes(f.name)) continue;
    const rel = path.join(baseDir, f.name).split(path.sep).join('/');
    if (f.isDirectory()) {
      list = list.concat(getTargets(path.join(dir, f.name), rel));
    } else if (/\.(html|css|js)$/i.test(f.name)) {
      const d = path.posix.dirname(rel);
      const b = path.posix.basename(rel);
      list.push(d === '.' ? `/.in.${b}.` : `/${d}/.in.${b}.`);
    }
  }
  return list;
}

function cleanFtpTempFiles() {
  const host = process.env.FTP_SERVER;
  const user = process.env.FTP_USERNAME;
  const pass = process.env.FTP_PASSWORD;

  if (!host || !user || !pass) {
    console.log('FTP credentials not available, skipping pre-clean');
    process.exit(0);
  }

  const targets = getTargets(process.cwd());
  console.log(`FTP pre-clean: generated ${targets.length} temporary file targets to check.`);

  const socket = net.createConnection(21, host);
  socket.setEncoding('utf8');
  socket.setTimeout(45000);

  let state = 'CONNECTING';
  let targetIndex = 0;
  let deletedCount = 0;
  let buffer = '';

  function sendLine(line) {
    socket.write(line + '\r\n');
  }

  function handleResponse(code, text) {
    if (state === 'CONNECTING') {
      if (code === 220) {
        state = 'USER';
        sendLine(`USER ${user}`);
      }
    } else if (state === 'USER') {
      if (code === 331) {
        state = 'PASS';
        sendLine(`PASS ${pass}`);
      } else if (code === 230) {
        state = 'DELE';
        sendNextDele();
      }
    } else if (state === 'PASS') {
      if (code === 230) {
        console.log('FTP pre-clean: Authenticated successfully. Beginning DELE sweep...');
        state = 'DELE';
        sendNextDele();
      } else {
        console.log(`FTP pre-clean auth notice: ${code} ${text}`);
        socket.destroy();
      }
    } else if (state === 'DELE') {
      if (code === 250) {
        const lastTarget = targets[targetIndex - 1];
        console.log(`[DELE SUCCESS] Removed stranded file: ${lastTarget}`);
        deletedCount++;
      }
      sendNextDele();
    } else if (state === 'QUIT') {
      socket.end();
    }
  }

  function sendNextDele() {
    if (targetIndex < targets.length) {
      const target = targets[targetIndex++];
      sendLine(`DELE ${target}`);
    } else {
      console.log(`FTP pre-clean complete. Total stranded temporary files removed: ${deletedCount}`);
      state = 'QUIT';
      sendLine('QUIT');
    }
  }

  socket.on('data', (chunk) => {
    buffer += chunk;
    const lines = buffer.split('\r\n');
    buffer = lines.pop();

    for (const line of lines) {
      if (!line) continue;
      const match = line.match(/^(\d{3})\s(.*)$/);
      if (match) {
        const code = parseInt(match[1], 10);
        const text = match[2];
        handleResponse(code, text);
      }
    }
  });

  socket.on('error', (err) => {
    console.log('FTP pre-clean socket error (continuing anyway):', err.message);
  });

  socket.on('timeout', () => {
    console.log('FTP pre-clean socket timeout, closing socket.');
    socket.destroy();
  });

  socket.on('close', () => {
    console.log('FTP pre-clean finished.');
    process.exit(0);
  });
}

cleanFtpTempFiles();
