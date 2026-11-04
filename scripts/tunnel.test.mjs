import { createHash } from 'node:crypto';
import { spawn } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { after, test } from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';

import {
  applyPublicBaseUrl,
  checkLaravel,
  clearLaravelConfig,
  copyToClipboard,
  extractTunnelUrl,
  extractUserHostnames,
  isDuplicateQuickTunnel,
  isVerifiedIpnSettingsUrl,
  listCloudflaredProcesses,
  openVerifiedIpnPage,
  ownedProcessKillInvocation,
  parseProcessList,
  resolveOnPath,
  runTunnel,
  sepayIpnInstructions,
  stopOwnedProcess,
  TunnelError,
  updateEnvFile,
} from './tunnel.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const REAL_ENV = path.join(ROOT, 'backend', '.env');
const REAL_ENV_HASH = createHash('sha256').update(readFileSync(REAL_ENV)).digest('hex');
const ORIGIN = 'https://sample-tunnel.trycloudflare.com';
const NEXT_ORIGIN = 'https://next-tunnel.trycloudflare.com';

after(() => {
  const afterHash = createHash('sha256').update(readFileSync(REAL_ENV)).digest('hex');
  assert.equal(afterHash, REAL_ENV_HASH);
});

function tempEnv(contents) {
  const dir = mkdtempSync(path.join(tmpdir(), 'bookstore-tunnel-'));
  const file = path.join(dir, '.env');
  writeFileSync(file, contents, 'utf8');
  return {
    file,
    remove() {
      rmSync(dir, { recursive: true, force: true });
    },
  };
}

function isAlive(pid) {
  try {
    process.kill(pid, 0);
    return true;
  } catch {
    return false;
  }
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitUntil(predicate, timeoutMs = 3000) {
  const started = Date.now();
  while (Date.now() - started < timeoutMs) {
    if (predicate()) {
      return true;
    }
    await sleep(50);
  }
  return predicate();
}

function listen(status) {
  return new Promise((resolve) => {
    const server = createServer((req, res) => {
      res.writeHead(status, { 'content-type': 'text/plain' });
      res.end(status === 200 ? 'ok' : 'down');
    });
    server.listen(0, '127.0.0.1', () => resolve(server));
  });
}

test('updates only SEPAY_PUBLIC_BASE_URL and keeps the other lines', () => {
  const input = [
    'APP_NAME=Bookstore',
    'SEPAY_SECRET_KEY=spsk_test_do_not_print',
    'SEPAY_PUBLIC_BASE_URL=https://old-tunnel.trycloudflare.com',
    'DB_PASSWORD=keep-me',
    '',
  ].join('\r\n');
  const { content, changed } = applyPublicBaseUrl(input, NEXT_ORIGIN);
  assert.equal(changed, true);
  assert.equal(content, [
    'APP_NAME=Bookstore',
    'SEPAY_SECRET_KEY=spsk_test_do_not_print',
    `SEPAY_PUBLIC_BASE_URL=${NEXT_ORIGIN}`,
    'DB_PASSWORD=keep-me',
    '',
  ].join('\r\n'));
});

test('leaves the env text untouched when the public URL is unchanged', () => {
  const input = `APP_NAME=Bookstore\r\nSEPAY_PUBLIC_BASE_URL=${ORIGIN}\r\nSEPAY_SECRET_KEY=spsk_test_do_not_print\r\n`;
  const { content, changed } = applyPublicBaseUrl(input, ORIGIN);
  assert.equal(changed, false);
  assert.equal(content, input);
});

test('preserves quotes, inline comments, and ignores a commented assignment', () => {
  const input = [
    '# SEPAY_PUBLIC_BASE_URL=https://commented.trycloudflare.com',
    'SEPAY_PUBLIC_BASE_URL="https://old-tunnel.trycloudflare.com" # public',
    'SEPAY_MERCHANT_ID=SP-TEST',
  ].join('\n');
  const { content, changed } = applyPublicBaseUrl(input, NEXT_ORIGIN);
  assert.equal(changed, true);
  assert.equal(content, [
    '# SEPAY_PUBLIC_BASE_URL=https://commented.trycloudflare.com',
    `SEPAY_PUBLIC_BASE_URL="${NEXT_ORIGIN}" # public`,
    'SEPAY_MERCHANT_ID=SP-TEST',
  ].join('\n'));
});

test('appends the public URL when the active key is missing', () => {
  const { content, changed } = applyPublicBaseUrl('APP_NAME=Bookstore\n', NEXT_ORIGIN);
  assert.equal(changed, true);
  assert.equal(content, `APP_NAME=Bookstore\nSEPAY_PUBLIC_BASE_URL=${NEXT_ORIGIN}\n`);
});

test('rejects a tunnel URL that is not the quick-tunnel origin', () => {
  assert.throws(() => applyPublicBaseUrl('SEPAY_PUBLIC_BASE_URL=http://localhost\n', 'http://127.0.0.1:8000'), TunnelError);
  assert.equal(extractTunnelUrl('see https://not-a-tunnel.example.com'), null);
  assert.equal(
    extractTunnelUrl('INF | https://random-name.trycloudflare.com |'),
    'https://random-name.trycloudflare.com',
  );
});

test('writes a temp env file only when the URL changes', () => {
  const fixture = tempEnv(`APP_NAME=Bookstore\r\nSEPAY_SECRET_KEY=spsk_test_do_not_print\r\nSEPAY_PUBLIC_BASE_URL=${ORIGIN}\r\n`);
  try {
    const same = updateEnvFile(fixture.file, ORIGIN);
    assert.equal(same.changed, false);
    assert.equal(readFileSync(fixture.file, 'utf8').includes('spsk_test_do_not_print'), true);
    const next = updateEnvFile(fixture.file, NEXT_ORIGIN);
    assert.equal(next.changed, true);
    const written = readFileSync(fixture.file, 'utf8');
    assert.equal(written.includes(`SEPAY_PUBLIC_BASE_URL=${NEXT_ORIGIN}`), true);
    assert.equal(written.includes('spsk_test_do_not_print'), true);
    assert.equal(written.includes('APP_NAME=Bookstore'), true);
  } finally {
    fixture.remove();
  }
});

test('duplicate detection matches the local Laravel origin only', () => {
  assert.equal(isDuplicateQuickTunnel({
    pid: 10,
    commandLine: '"C:\\cloudflared.exe" tunnel --url http://127.0.0.1:8000',
  }), true);
  assert.equal(isDuplicateQuickTunnel({
    pid: 11,
    commandLine: 'cloudflared tunnel --url http://localhost:8000',
  }), true);
  assert.equal(isDuplicateQuickTunnel({
    pid: 12,
    commandLine: 'cloudflared tunnel run named-bookstore',
  }), false);
  const parsed = parseProcessList('{"ProcessId":15,"CommandLine":"cloudflared tunnel --url http://127.0.0.1:8000"}');
  assert.deepEqual(parsed, [{ pid: 15, commandLine: 'cloudflared tunnel --url http://127.0.0.1:8000' }]);
  const list = parseProcessList('[{"ProcessId":16,"CommandLine":"cloudflared tunnel run named"}]');
  assert.equal(list.length, 1);
});

test('owned-process stop targets one PID and never an image name', () => {
  const invocation = ownedProcessKillInvocation(4321);
  assert.equal(invocation.args.includes('/IM'), false);
  assert.equal(invocation.args.includes('cloudflared'), false);
  assert.equal(invocation.args.includes('4321'), true);
  const source = readFileSync(new URL('./tunnel.mjs', import.meta.url), 'utf8');
  assert.equal(source.includes('/IM'), false);
  assert.equal(source.includes('SEPAY_SECRET'), false);
  assert.equal(source.includes('webhooks/config'), false);
});

test('stopOwnedProcess kills the requested pid and leaves another process running', async () => {
  const owned = spawn(process.execPath, ['-e', 'setInterval(() => {}, 1000)'], { stdio: 'ignore' });
  const decoy = spawn(process.execPath, ['-e', 'setInterval(() => {}, 1000)'], { stdio: 'ignore' });
  try {
    await waitUntil(() => isAlive(owned.pid) && isAlive(decoy.pid));
    await stopOwnedProcess(owned.pid);
    assert.equal(await waitUntil(() => !isAlive(owned.pid)), true);
    assert.equal(isAlive(decoy.pid), true);
    await assert.rejects(() => stopOwnedProcess(process.pid), TunnelError);
  } finally {
    await stopOwnedProcess(decoy.pid).catch(() => {});
    await stopOwnedProcess(owned.pid).catch(() => {});
  }
});

test('laravel health accepts HTTP 200 and reports a closed port', async () => {
  const up = await listen(200);
  const down = await listen(503);
  try {
    const upPort = up.address().port;
    const downPort = down.address().port;
    await checkLaravel(`http://127.0.0.1:${upPort}/up`);
    await assert.rejects(() => checkLaravel(`http://127.0.0.1:${downPort}/up`), /HTTP 503/);
    const closedPort = upPort;
    await new Promise((resolve) => up.close(resolve));
    await assert.rejects(() => checkLaravel(`http://127.0.0.1:${closedPort}/up`, 1000), /Laravel chưa chạy/);
  } finally {
    up.close();
    down.close();
  }
});

test('cloudflared lookup fails for a missing command and finds node', async () => {
  assert.equal(await resolveOnPath('cloudflared-not-installed-bookstore'), null);
  assert.equal(typeof await resolveOnPath('node'), 'string');
});

test('live cloudflared process query returns a list without killing anything', async () => {
  const decoy = spawn(process.execPath, ['-e', 'setInterval(() => {}, 1000)'], { stdio: 'ignore' });
  try {
    const rows = await listCloudflaredProcesses();
    assert.equal(Array.isArray(rows), true);
    assert.equal(isAlive(decoy.pid), true);
  } finally {
    await stopOwnedProcess(decoy.pid).catch(() => {});
  }
});

test('tunnel failure does not change env or clear config', async () => {
  const fixture = tempEnv(`APP_NAME=Bookstore\r\nSEPAY_SECRET_KEY=spsk_test_do_not_print\r\nSEPAY_PUBLIC_BASE_URL=${ORIGIN}\r\n`);
  const before = readFileSync(fixture.file, 'utf8');
  let cleared = 0;
  try {
    await assert.rejects(() => runTunnel({
      checkLaravel: async () => {},
      listProcesses: async () => [],
      cloudflaredCommand: process.execPath,
      cloudflaredArgs: ['-e', "process.stderr.write('failed to request quick Tunnel\\n'); process.exit(1);"],
      envPath: fixture.file,
      forwardLogs: false,
      clearConfig: async () => {
        cleared += 1;
      },
      copyToClipboard: async () => false,
      log() {},
    }), /thoát trước khi có URL/);
    assert.equal(readFileSync(fixture.file, 'utf8'), before);
    assert.equal(cleared, 0);
  } finally {
    fixture.remove();
  }
});

test('tunnel timeout stops the process it spawned', async () => {
  const fixture = tempEnv(`SEPAY_PUBLIC_BASE_URL=${ORIGIN}\n`);
  let pid = 0;
  try {
    await assert.rejects(() => runTunnel({
      timeoutMs: 400,
      checkLaravel: async () => {},
      listProcesses: async () => [],
      cloudflaredCommand: process.execPath,
      cloudflaredArgs: ['-e', 'setInterval(() => {}, 1000)'],
      envPath: fixture.file,
      forwardLogs: false,
      clearConfig: async () => {},
      copyToClipboard: async () => false,
      log() {},
      onSpawn(child) {
        pid = child.pid;
      },
    }), /Không nhận được URL Quick Tunnel/);
    assert.equal(await waitUntil(() => pid && !isAlive(pid)), true);
    assert.equal(readFileSync(fixture.file, 'utf8'), `SEPAY_PUBLIC_BASE_URL=${ORIGIN}\n`);
  } finally {
    if (pid) {
      await stopOwnedProcess(pid).catch(() => {});
    }
    fixture.remove();
  }
});

test('successful tunnel updates a changed URL and can leave an unchanged URL in place', async () => {
  const changed = tempEnv([
    'APP_NAME=Bookstore',
    'SEPAY_SECRET_KEY=spsk_test_do_not_print',
    'SEPAY_PUBLIC_BASE_URL=https://old-tunnel.trycloudflare.com',
    'DB_PASSWORD=keep-me',
    '',
  ].join('\r\n'));
  const same = tempEnv(`APP_NAME=Bookstore\r\nSEPAY_SECRET_KEY=spsk_test_do_not_print\r\nSEPAY_PUBLIC_BASE_URL=${ORIGIN}\r\n`);
  const sameBefore = readFileSync(same.file);
  const logs = [];
  let cleared = 0;
  const successArgs = ['-e', `process.stderr.write('INF | ${ORIGIN} |\\n'); setInterval(() => {}, 1000);`];
  try {
    const first = await runTunnel({
      checkLaravel: async () => {},
      listProcesses: async () => [],
      cloudflaredCommand: process.execPath,
      cloudflaredArgs: successArgs,
      envPath: changed.file,
      forwardLogs: false,
      clearConfig: async () => {
        cleared += 1;
      },
      copyToClipboard: async () => true,
      log: (line) => logs.push(line),
    });
    const written = readFileSync(changed.file, 'utf8');
    assert.equal(first.changed, true);
    assert.equal(first.configOk, true);
    assert.equal(first.ipn, `${ORIGIN}/api/v1/payments/webhooks/sepay`);
    assert.equal(written.includes('spsk_test_do_not_print'), true);
    assert.equal(written.includes('DB_PASSWORD=keep-me'), true);
    assert.equal(written.includes(`SEPAY_PUBLIC_BASE_URL=${ORIGIN}`), true);
    assert.equal(logs.some((line) => line.includes('spsk_test_do_not_print')), false);
    assert.equal(logs.includes('Laravel config: PASS'), true);
    await first.stop();

    const second = await runTunnel({
      checkLaravel: async () => {},
      listProcesses: async () => [],
      cloudflaredCommand: process.execPath,
      cloudflaredArgs: successArgs,
      envPath: same.file,
      forwardLogs: false,
      clearConfig: async () => {
        cleared += 1;
      },
      copyToClipboard: async () => false,
      log: (line) => logs.push(line),
    });
    assert.equal(second.changed, false);
    assert.equal(Buffer.compare(readFileSync(same.file), sameBefore), 0);
    assert.equal(logs.includes('SEPAY_PUBLIC_BASE_URL: không đổi'), true);
    assert.equal(logs.includes('Clipboard: SKIP'), true);
    await second.stop();
    assert.equal(cleared, 2);
  } finally {
    changed.remove();
    same.remove();
  }
});

test('reads one quick-tunnel hostname from cloudflared metrics', () => {
  const metrics = [
    'cloudflared_tunnel_user_hostnames_counts{userHostname="https://outlined-bibliography-arabia-enlargement.trycloudflare.com"} 1',
    'other_metric{host="https://example.com"} 1',
  ].join('\n');
  assert.deepEqual(extractUserHostnames(metrics), ['https://outlined-bibliography-arabia-enlargement.trycloudflare.com']);
  const two = `${metrics}\ncloudflared_tunnel_user_hostnames_counts{userHostname="https://other-name.trycloudflare.com"} 1`;
  assert.equal(extractUserHostnames(two).length, 2);
});

test('adopts a running tunnel URL without spawning or stopping that process', async () => {
  const fixture = tempEnv([
    'APP_NAME=Bookstore',
    'SEPAY_SECRET_KEY=spsk_test_do_not_print',
    'SEPAY_PUBLIC_BASE_URL=https://old-tunnel.trycloudflare.com',
    'DB_PASSWORD=keep-me',
    '',
  ].join('\r\n'));
  const decoy = spawn(process.execPath, ['-e', 'setInterval(() => {}, 1000)'], { stdio: 'ignore' });
  let spawned = false;
  try {
    const session = await runTunnel({
      checkLaravel: async () => {},
      listProcesses: async () => [{ pid: decoy.pid, commandLine: 'cloudflared tunnel --url http://127.0.0.1:8000' }],
      readExistingUrl: async () => ORIGIN,
      verifyPublicUrl: async () => {},
      cloudflaredCommand: process.execPath,
      cloudflaredArgs: ['-e', 'process.exit(9)'],
      envPath: fixture.file,
      forwardLogs: false,
      clearConfig: async () => {},
      copyToClipboard: async () => true,
      log() {},
      onSpawn() {
        spawned = true;
      },
    });
    assert.equal(spawned, false);
    assert.equal(session.child, null);
    assert.equal(session.changed, true);
    assert.equal(session.ipn, `${ORIGIN}/api/v1/payments/webhooks/sepay`);
    const written = readFileSync(fixture.file, 'utf8');
    assert.equal(written.includes('spsk_test_do_not_print'), true);
    assert.equal(written.includes('DB_PASSWORD=keep-me'), true);
    await session.stop();
    assert.equal(isAlive(decoy.pid), true);
  } finally {
    await stopOwnedProcess(decoy.pid).catch(() => {});
    fixture.remove();
  }
});

test('does not start a second tunnel when the existing URL cannot be read', async () => {
  const fixture = tempEnv(`SEPAY_PUBLIC_BASE_URL=${ORIGIN}\n`);
  const before = readFileSync(fixture.file, 'utf8');
  let spawned = false;
  try {
    await assert.rejects(() => runTunnel({
      checkLaravel: async () => {},
      listProcesses: async () => [{ pid: 4242, commandLine: 'cloudflared.exe tunnel --url http://127.0.0.1:8000' }],
      readExistingUrl: async () => null,
      cloudflaredCommand: process.execPath,
      cloudflaredArgs: ['-e', 'process.exit(9)'],
      envPath: fixture.file,
      forwardLogs: false,
      clearConfig: async () => {},
      log() {},
      onSpawn() {
        spawned = true;
      },
    }), /Không tạo tunnel trùng/);
    assert.equal(spawned, false);
    assert.equal(readFileSync(fixture.file, 'utf8'), before);
  } finally {
    fixture.remove();
  }
});

test('missing cloudflared fails before a tunnel or env write', async () => {
  const fixture = tempEnv(`SEPAY_PUBLIC_BASE_URL=${ORIGIN}\n`);
  const before = readFileSync(fixture.file, 'utf8');
  try {
    await assert.rejects(() => runTunnel({
      checkLaravel: async () => {},
      listProcesses: async () => [],
      cloudflaredCommand: '',
      resolveMissing: true,
      envPath: fixture.file,
      forwardLogs: false,
      log() {},
    }), /Không tìm thấy cloudflared/);
    assert.equal(readFileSync(fixture.file, 'utf8'), before);
  } finally {
    fixture.remove();
  }
});

test('config clear failure keeps the owned tunnel until stop', async () => {
  const fixture = tempEnv(`SEPAY_PUBLIC_BASE_URL=https://old-tunnel.trycloudflare.com\nSEPAY_SECRET_KEY=spsk_test_do_not_print\n`);
  const logs = [];
  let session;
  try {
    session = await runTunnel({
      checkLaravel: async () => {},
      listProcesses: async () => [],
      cloudflaredCommand: process.execPath,
      cloudflaredArgs: ['-e', `process.stderr.write('${ORIGIN}\\n'); setInterval(() => {}, 1000);`],
      envPath: fixture.file,
      forwardLogs: false,
      clearConfig: async () => {
        throw new TunnelError('php artisan config:clear thất bại (mã 1).');
      },
      copyToClipboard: async () => false,
      log: (line) => logs.push(line),
    });
    assert.equal(session.configOk, false);
    assert.equal(isAlive(session.child.pid), true);
    assert.equal(logs.includes('Laravel config: FAIL'), true);
    assert.equal(logs.some((line) => line.includes('spsk_test_do_not_print')), false);
  } finally {
    if (session) {
      await session.stop();
    }
    fixture.remove();
  }
});

test('SePay IPN page is not opened without a verified settings URL', async () => {
  let spawned = 0;
  const opened = await openVerifiedIpnPage(() => {
    spawned += 1;
  });
  assert.equal(opened, false);
  assert.equal(spawned, 0);
  assert.equal(isVerifiedIpnSettingsUrl('https://my.sepay.vn/pg/payment-methods'), false);
  assert.equal(sepayIpnInstructions().action, 'Dán IPN URL → Lưu → Gửi test.');
  assert.equal(sepayIpnInstructions().settingsUrl, null);
});

test('clipboard helper returns false when the command is missing and true when stdin is accepted', async () => {
  assert.equal(await copyToClipboard('https://sample-tunnel.trycloudflare.com/api/v1/payments/webhooks/sepay', ''), false);
  const accepted = await copyToClipboard(
    'https://sample-tunnel.trycloudflare.com/api/v1/payments/webhooks/sepay',
    process.execPath,
    ['-e', "let d=''; process.stdin.on('data', (c) => { d += c; }); process.stdin.on('end', () => { process.exit(d.endsWith('/api/v1/payments/webhooks/sepay') ? 0 : 2); });"],
  );
  assert.equal(accepted, true);
});

test('php artisan config:clear succeeds without printing env values', { timeout: 120_000 }, async () => {
  await clearLaravelConfig(path.join(ROOT, 'backend'));
});

test('cli exits before starting a tunnel when Laravel is down or cloudflared cannot be resolved', async () => {
  let laravelUp = false;
  try {
    await checkLaravel('http://127.0.0.1:8000/up', 1500);
    laravelUp = true;
  } catch {
    laravelUp = false;
  }

  const empty = mkdtempSync(path.join(tmpdir(), 'bookstore-empty-path-'));
  const child = spawn(process.execPath, [path.join(ROOT, 'scripts', 'tunnel.mjs')], {
    cwd: ROOT,
    env: {
      ...process.env,
      PATH: empty,
      Path: empty,
    },
    windowsHide: true,
  });
  let stderr = '';
  child.stderr.setEncoding('utf8');
  child.stderr.on('data', (chunk) => {
    stderr += chunk;
  });
  const code = await new Promise((resolve, reject) => {
    const timer = setTimeout(() => {
      stopOwnedProcess(child.pid).finally(() => reject(new Error('CLI did not exit')));
    }, 8000);
    child.on('exit', (exitCode) => {
      clearTimeout(timer);
      resolve(exitCode);
    });
  });
  rmSync(empty, { recursive: true, force: true });
  assert.equal(code, 1);
  if (laravelUp) {
    assert.match(stderr, /Không tìm thấy cloudflared/);
  } else {
    assert.match(stderr, /Laravel chưa chạy/);
  }
  assert.equal(stderr.includes('SEPAY_SECRET'), false);
});
