import net from 'node:net';

function cleanFtpTempFiles() {
  const host = process.env.FTP_SERVER;
  const user = process.env.FTP_USERNAME;
  const pass = process.env.FTP_PASSWORD;

  if (!host || !user || !pass) {
    console.log('FTP credentials not available, skipping pre-clean');
    process.exit(0);
  }

  const targets = [
    '/blog/.in.barite-modern-drilling.html.',
    '/blog/.in.ai-automation-deep-pit-mining.html.',
    '/.in.about.html.',
    '/.in.404.html.'
  ];

  const socket = net.createConnection(21, host);
  socket.setEncoding('utf8');
  socket.setTimeout(25000);

  const commands = [
    `USER ${user}\r\n`,
    `PASS ${pass}\r\n`,
    ...targets.map(t => `DELE ${t}\r\n`),
    `QUIT\r\n`
  ];

  let cmdIndex = 0;

  socket.on('data', (data) => {
    // Only log first line of data to keep output tidy
    const line = data.trim().split('\n')[0];
    console.log('FTP <', line);
    if (cmdIndex < commands.length) {
      const nextCmd = commands[cmdIndex++];
      console.log('FTP >', nextCmd.startsWith('PASS') ? 'PASS [HIDDEN]' : nextCmd.trim());
      socket.write(nextCmd);
    }
  });

  socket.on('error', (err) => {
    console.log('FTP pre-clean notice (ignoring):', err.message);
  });

  socket.on('timeout', () => {
    console.log('FTP pre-clean socket timeout, closing');
    socket.destroy();
  });

  socket.on('close', () => {
    console.log('FTP pre-clean finished');
    process.exit(0);
  });
}

cleanFtpTempFiles();
