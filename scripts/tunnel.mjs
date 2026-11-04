import { spawn, execFile } from 'node:child_process';
import { copyFileSync, existsSync, readFileSync, unlinkSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const LARAVEL_ORIGIN = 'http://127.0.0.1:8000';
const LARAVEL_HEALTH_URL = `${LARAVEL_ORIGIN}/up`;
const ENV_PATH = path.join(ROOT, 'backend', '.env');
const BACKEND_DIR = path.join(ROOT, 'backend');
const PUBLIC_BASE_KEY = 'SEPAY_PUBLIC_BASE_URL';
const IPN_PATH = '/api/v1/payments/webhooks/sepay';
const URL_TIMEOUT_MS = 45_000;

// Payment Gateway REST API covers orders and transactions only.
// IPN is saved in the merchant UI: Cổng thanh toán → Cấu hình → IPN.
// Docs publish https://my.sepay.vn and https://my.sepay.vn/pg/payment-methods,
// which are not the IPN settings page, so nothing is opened or changed remotely.
const VERIFIED_IPN_SETTINGS_URLS = [];

const TUNNEL_URL_RE = /https:\/\/([a-z0-9-]+)\.trycloudflare\.com(?=$|[\s|/'")\]])/i;

export class TunnelError extends Error {
  constructor(message) {
    super(message);
    this.name = 'TunnelError';
  }
}

export function stripAnsi(text) {
  return String(text).replace(/\u001b\[[0-9;?]*[ -/]*[@-~]/g, '');
}

export function extractTunnelUrl(text) {
  const match = TUNNEL_URL_RE.exec(stripAnsi(text));
  if (!match) {
    return null;
  }
  return `https://${match[1]}.trycloudflare.com`;
}

export function extractUserHostnames(metrics) {
  const found = new Set();
  const labeled = /userHostname="(https:\/\/[a-z0-9-]+\.trycloudflare\.com)"/gi;
  for (const match of String(metrics).matchAll(labeled)) {
    try {
      found.add(assertQuickTunnelOrigin(match[1]));
    } catch {
      // Ignore a metrics label that is not a quick-tunnel origin.
    }
  }
  return [...found];
}

export function assertQuickTunnelOrigin(value) {
  let parsed;
  try {
    parsed = new URL(value);
  } catch {
    throw new TunnelError('URL Quick Tunnel không hợp lệ.');
  }
  const origin = `${parsed.protocol}//${parsed.host}`;
  if (extractTunnelUrl(origin) !== origin || parsed.username || parsed.password || parsed.search || parsed.hash || (parsed.pathname && parsed.pathname !== '/')) {
    throw new TunnelError('URL Quick Tunnel không hợp lệ.');
  }
  return origin;
}

export function ipnUrl(origin) {
  return `${assertQuickTunnelOrigin(origin)}${IPN_PATH}`;
}

export function sepayIpnInstructions() {
  return {
    settingsUrl: VERIFIED_IPN_SETTINGS_URLS[0] ?? null,
    navigation: 'https://my.sepay.vn → Cổng thanh toán → Cấu hình → IPN',
    action: 'Dán IPN URL → Lưu → Gửi test.',
  };
}

export function isVerifiedIpnSettingsUrl(url) {
  if (!VERIFIED_IPN_SETTINGS_URLS.includes(url)) {
    return false;
  }
  try {
    const parsed = new URL(url);
    return parsed.protocol === 'https:' && parsed.hostname === 'my.sepay.vn';
  } catch {
    return false;
  }
}

export async function openVerifiedIpnPage(spawnImpl = spawn) {
  const url = sepayIpnInstructions().settingsUrl;
  if (!isVerifiedIpnSettingsUrl(url) || process.platform !== 'win32') {
    return false;
  }
  await new Promise((resolve, reject) => {
    const child = spawnImpl('cmd.exe', ['/c', 'start', '', url], { windowsHide: true, stdio: 'ignore' });
    child.on('error', reject);
    child.on('exit', (code) => (code === 0 ? resolve() : reject(new TunnelError(`Không mở được trang IPN (mã ${code}).`))));
  });
  return true;
}

function parseEnvValue(raw) {
  if (raw.startsWith('"')) {
    const end = raw.indexOf('"', 1);
    if (end > 0) {
      return { quote: '"', value: raw.slice(1, end), suffix: raw.slice(end + 1) };
    }
  }
  if (raw.startsWith("'")) {
    const end = raw.indexOf("'", 1);
    if (end > 0) {
      return { quote: "'", value: raw.slice(1, end), suffix: raw.slice(end + 1) };
    }
  }
  const commentAt = raw.search(/\s+#/);
  if (commentAt === -1) {
    return { quote: '', value: raw.trim(), suffix: raw.slice(raw.trimEnd().length) };
  }
  return { quote: '', value: raw.slice(0, commentAt).trim(), suffix: raw.slice(commentAt) };
}

export function applyPublicBaseUrl(content, origin) {
  const safe = assertQuickTunnelOrigin(origin);
  const lineRe = new RegExp(`^${PUBLIC_BASE_KEY}=([^\\n]*)$`, 'm');
  const match = lineRe.exec(content);
  if (!match) {
    const nl = content.includes('\r\n') ? '\r\n' : '\n';
    const prefix = content.length === 0 || content.endsWith('\n') ? '' : nl;
    return { content: `${content}${prefix}${PUBLIC_BASE_KEY}=${safe}${nl}`, changed: true };
  }
  const parsed = parseEnvValue(match[1]);
  if (parsed.value === safe) {
    return { content, changed: false };
  }
  const encoded = parsed.quote ? `${parsed.quote}${safe}${parsed.quote}` : safe;
  const line = `${PUBLIC_BASE_KEY}=${encoded}${parsed.suffix}`;
  const next = content.slice(0, match.index) + line + content.slice(match.index + match[0].length);
  return { content: next, changed: true };
}

export function updateEnvFile(file, origin) {
  if (!existsSync(file)) {
    throw new TunnelError('Không thấy backend/.env. Không tạo file mới.');
  }
  const before = readFileSync(file, 'utf8');
  const { content, changed } = applyPublicBaseUrl(before, origin);
  if (!changed) {
    return { changed: false };
  }
  const tmp = `${file}.${process.pid}.tmp`;
  writeFileSync(tmp, content, 'utf8');
  try {
    copyFileSync(tmp, file);
  } finally {
    try {
      unlinkSync(tmp);
    } catch {
      // The env file was already replaced.
    }
  }
  return { changed: true };
}

export function ownedProcessKillInvocation(pid) {
  const id = Number(pid);
  if (!Number.isInteger(id) || id <= 0 || id === process.pid) {
    throw new TunnelError('PID không hợp lệ.');
  }
  if (process.platform === 'win32') {
    return { command: 'taskkill', args: ['/PID', String(id), '/T', '/F'] };
  }
  return { command: 'kill', args: ['-TERM', String(id)] };
}

export function stopOwnedProcess(pid) {
  return new Promise((resolve, reject) => {
    let invocation;
    try {
      invocation = ownedProcessKillInvocation(pid);
    } catch (error) {
      reject(error);
      return;
    }
    execFile(invocation.command, invocation.args, { windowsHide: true, timeout: 10_000 }, (error) => {
      if (error && Number(error.code) !== 128) {
        reject(error);
        return;
      }
      resolve();
    });
  });
}

function parseJsonRows(stdout) {
  const text = Buffer.isBuffer(stdout) ? decodeProcessOutput(stdout) : String(stdout ?? '');
  const trimmed = text.replace(/^\uFEFF/, '').trim();
  if (!trimmed) {
    return [];
  }
  const data = JSON.parse(trimmed);
  return Array.isArray(data) ? data : [data];
}

export function parseProcessList(stdout) {
  return parseJsonRows(stdout)
    .filter((row) => row && row.ProcessId)
    .map((row) => ({
      pid: Number(row.ProcessId),
      commandLine: typeof row.CommandLine === 'string' ? row.CommandLine : '',
    }));
}

function decodeProcessOutput(buffer) {
  if (buffer.length >= 2 && buffer[0] === 0xff && buffer[1] === 0xfe) {
    return buffer.toString('utf16le');
  }
  if (buffer.includes(0)) {
    return buffer.toString('utf16le');
  }
  return buffer.toString('utf8');
}

export function isDuplicateQuickTunnel(proc, origin = LARAVEL_ORIGIN) {
  const line = String(proc.commandLine || '').toLowerCase();
  if (!line.includes('tunnel') || !proc.pid) {
    return false;
  }
  const wanted = origin.toLowerCase();
  if (line.includes(wanted)) {
    return true;
  }
  const aliases = ['http://127.0.0.1:8000', 'http://localhost:8000'];
  return aliases.includes(wanted) && aliases.some((alias) => alias !== wanted && line.includes(alias));
}

export function resolveOnPath(command) {
  const finder = process.platform === 'win32' ? 'where.exe' : 'which';
  return new Promise((resolve) => {
    execFile(finder, [command], { windowsHide: true, timeout: 10_000 }, (error, stdout) => {
      if (error) {
        resolve(null);
        return;
      }
      const found = String(stdout)
        .split(/\r?\n/)
        .map((line) => line.trim())
        .find(Boolean);
      resolve(found ?? null);
    });
  });
}

export function listCloudflaredProcesses() {
  if (process.platform !== 'win32') {
    return Promise.reject(new TunnelError('Script này kiểm tra tunnel trùng trên Windows PowerShell.'));
  }
  const script = [
    "$ErrorActionPreference = 'Stop'",
    '$OutputEncoding = [Console]::OutputEncoding = New-Object System.Text.UTF8Encoding $false',
    "$procs = @(Get-CimInstance Win32_Process -Filter \"Name = 'cloudflared.exe'\")",
    'if ($procs.Count -eq 0) { Write-Output "[]"; exit 0 }',
    '$procs | Select-Object ProcessId, CommandLine | ConvertTo-Json -Compress',
  ].join('\n');
  const encoded = Buffer.from(script, 'utf16le').toString('base64');
  return new Promise((resolve, reject) => {
    execFile(
      'powershell.exe',
      ['-NoProfile', '-NonInteractive', '-EncodedCommand', encoded],
      { windowsHide: true, timeout: 15_000, maxBuffer: 1024 * 1024, encoding: 'buffer' },
      (error, stdout) => {
        if (error) {
          reject(new TunnelError('Không kiểm tra được tiến trình cloudflared. Dừng để tránh chạy tunnel trùng.'));
          return;
        }
        try {
          resolve(parseProcessList(stdout));
        } catch {
          reject(new TunnelError('Không đọc được danh sách cloudflared. Dừng để tránh chạy tunnel trùng.'));
        }
      },
    );
  });
}

function rememberPort(ports, value) {
  const port = Number(value);
  if (Number.isInteger(port) && port > 0 && port <= 65535) {
    ports.add(port);
  }
}

function portsFromNetstat(pid) {
  const id = String(pid);
  return new Promise((resolve, reject) => {
    execFile('netstat', ['-ano', '-p', 'TCP'], { windowsHide: true, timeout: 15_000, encoding: 'utf8', maxBuffer: 4 * 1024 * 1024 }, (error, stdout) => {
      if (error) {
        reject(error);
        return;
      }
      const ports = new Set();
      for (const line of String(stdout).split(/\r?\n/)) {
        const parts = line.trim().split(/\s+/);
        if (parts.length < 5 || parts[0] !== 'TCP' || parts.at(-1) !== id) {
          continue;
        }
        const match = /^(?:127\.0\.0\.1|0\.0\.0\.0|\[::1\]|\[::\]):(\d+)$/.exec(parts[1]);
        if (match) {
          rememberPort(ports, match[1]);
        }
      }
      resolve(ports);
    });
  });
}

function portsFromTcpTable(pid) {
  const script = [
    "$ErrorActionPreference = 'SilentlyContinue'",
    '$OutputEncoding = [Console]::OutputEncoding = New-Object System.Text.UTF8Encoding $false',
    `$ports = @(Get-NetTCPConnection -OwningProcess ${pid} | Where-Object { $_.LocalAddress -match '^(127\\.0\\.0\\.1|::1|0\\.0\\.0\\.0|::)$' } | Select-Object -ExpandProperty LocalPort -Unique)`,
    'if ($ports.Count -eq 0) { Write-Output "[]"; exit 0 }',
    '$ports | ForEach-Object { [PSCustomObject]@{ LocalPort = $_ } } | ConvertTo-Json -Compress',
  ].join('\n');
  const encoded = Buffer.from(script, 'utf16le').toString('base64');
  return new Promise((resolve, reject) => {
    execFile(
      'powershell.exe',
      ['-NoProfile', '-NonInteractive', '-EncodedCommand', encoded],
      { windowsHide: true, timeout: 15_000, maxBuffer: 1024 * 1024, encoding: 'buffer' },
      (error, stdout) => {
        if (error) {
          reject(error);
          return;
        }
        try {
          const ports = new Set();
          for (const row of parseJsonRows(stdout)) {
            rememberPort(ports, row?.LocalPort ?? row);
          }
          resolve(ports);
        } catch (parseError) {
          reject(parseError);
        }
      },
    );
  });
}

export async function listOwnedLoopbackPorts(pid) {
  const id = Number(pid);
  if (!Number.isInteger(id) || id <= 0) {
    return [];
  }
  const ports = new Set();
  const [fromNetstat, fromTable] = await Promise.all([
    portsFromNetstat(id).catch(() => new Set()),
    portsFromTcpTable(id).catch(() => new Set()),
  ]);
  for (const port of fromNetstat) {
    ports.add(port);
  }
  for (const port of fromTable) {
    ports.add(port);
  }
  return [...ports];
}

export async function readExistingQuickTunnelUrl(pid, fetchImpl = fetch) {
  let ports = [];
  try {
    ports = await listOwnedLoopbackPorts(pid);
  } catch {
    return null;
  }
  const found = new Set();
  for (const port of ports) {
    try {
      const response = await fetchImpl(`http://127.0.0.1:${port}/metrics`, {
        signal: AbortSignal.timeout(1500),
        redirect: 'manual',
      });
      if (!response.ok) {
        continue;
      }
      for (const host of extractUserHostnames(await response.text())) {
        found.add(host);
      }
    } catch {
      // This listen port is not the cloudflared metrics server.
    }
  }
  return found.size === 1 ? [...found][0] : null;
}

export function checkLaravel(url = LARAVEL_HEALTH_URL, timeoutMs = 4000) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  return fetch(url, { signal: controller.signal, redirect: 'manual' })
    .then((response) => {
      if (response.status < 200 || response.status >= 300) {
        throw new TunnelError(`Laravel tại ${url} trả HTTP ${response.status}.`);
      }
    })
    .catch((error) => {
      if (error instanceof TunnelError) {
        throw error;
      }
      const aborted = error?.name === 'AbortError' || error?.name === 'TimeoutError';
      if (aborted) {
        throw new TunnelError(`Laravel không phản hồi tại ${url}.`);
      }
      throw new TunnelError(
        `Laravel chưa chạy tại ${url}. Chạy "php artisan serve --host=127.0.0.1 --port=8000" trong backend, rồi chạy lại "npm run tunnel".`,
      );
    })
    .finally(() => clearTimeout(timer));
}

export function copyToClipboard(text, command = 'clip', args = []) {
  if (!command || (command === 'clip' && process.platform !== 'win32')) {
    return Promise.resolve(false);
  }
  return new Promise((resolve) => {
    let settled = false;
    const finish = (ok) => {
      if (settled) {
        return;
      }
      settled = true;
      resolve(ok);
    };
    const child = spawn(command, args, { windowsHide: true, stdio: ['pipe', 'ignore', 'ignore'] });
    child.on('error', () => finish(false));
    child.on('exit', (code) => finish(code === 0));
    child.stdin.on('error', () => finish(false));
    child.stdin.end(String(text), 'utf8');
  });
}

export function clearLaravelConfig(backendDir = BACKEND_DIR, command = 'php') {
  return new Promise((resolve, reject) => {
    execFile(command, ['artisan', 'config:clear'], { cwd: backendDir, windowsHide: true, timeout: 60_000 }, (error) => {
      if (error) {
        reject(new TunnelError(`php artisan config:clear thất bại (mã ${error.code ?? 'unknown'}).`));
        return;
      }
      resolve();
    });
  });
}

export function startQuickTunnel({
  command,
  args,
  timeoutMs = URL_TIMEOUT_MS,
  forwardLogs = true,
  onSpawn,
}) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, { windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
    if (child.pid) {
      onSpawn?.(child);
    }
    let buffer = '';
    let settled = false;
    const fail = (error) => {
      if (settled) {
        return;
      }
      settled = true;
      clearTimeout(timer);
      reject(error);
    };
    const timer = setTimeout(() => {
      if (settled) {
        return;
      }
      settled = true;
      const message = new TunnelError(`Không nhận được URL Quick Tunnel sau ${Math.round(timeoutMs / 1000)}s. Đã dừng cloudflared do script tạo.`);
      const finish = () => reject(message);
      if (!child.pid) {
        finish();
        return;
      }
      stopOwnedProcess(child.pid).then(finish, finish);
    }, timeoutMs);

    const onData = (chunk) => {
      const text = chunk.toString('utf8');
      if (forwardLogs) {
        process.stderr.write(text);
      }
      buffer = (buffer + text).slice(-131072);
      if (settled) {
        return;
      }
      const url = extractTunnelUrl(buffer);
      if (!url) {
        return;
      }
      settled = true;
      clearTimeout(timer);
      resolve({ child, url: assertQuickTunnelOrigin(url) });
    };

    child.stdout?.setEncoding('utf8');
    child.stderr?.setEncoding('utf8');
    child.stdout?.on('data', onData);
    child.stderr?.on('data', onData);
    child.on('error', (error) => {
      const missing = error?.code === 'ENOENT';
      fail(new TunnelError(missing
        ? 'Không tìm thấy cloudflared trong PATH. Cài Cloudflare Tunnel rồi chạy lại npm run tunnel.'
        : 'Không khởi động được cloudflared.'));
    });
    child.on('exit', (code) => {
      if (settled) {
        return;
      }
      const tail = stripAnsi(buffer).trim().split(/\r?\n/).filter(Boolean).slice(-6).join('\n');
      fail(new TunnelError(`cloudflared thoát trước khi có URL (mã ${code ?? 'null'}).${tail ? ` ${tail}` : ''}`));
    });
  });
}

export async function runTunnel(options = {}) {
  const origin = options.origin ?? LARAVEL_ORIGIN;
  const healthUrl = options.healthUrl ?? `${origin}/up`;
  const envPath = options.envPath ?? ENV_PATH;
  const backendDir = options.backendDir ?? BACKEND_DIR;
  const log = options.log ?? ((line) => console.log(line));
  const forwardLogs = options.forwardLogs ?? true;
  const ensureLaravel = options.checkLaravel ?? (() => checkLaravel(healthUrl));
  const lookupProcesses = options.listProcesses ?? listCloudflaredProcesses;
  const clearConfig = options.clearConfig ?? (() => clearLaravelConfig(backendDir));
  const copy = options.copyToClipboard ?? copyToClipboard;
  const openPage = options.openPage ?? openVerifiedIpnPage;

  await ensureLaravel();
  log('Laravel: OK');

  const command = options.cloudflaredCommand ?? await resolveOnPath('cloudflared');
  if (!command) {
    throw new TunnelError('Không tìm thấy cloudflared trong PATH. Cài Cloudflare Tunnel rồi chạy lại npm run tunnel.');
  }
  log('cloudflared: OK');

  const processes = await lookupProcesses();
  const duplicate = processes.find((proc) => isDuplicateQuickTunnel(proc, origin));
  if (duplicate) {
    const readExisting = options.readExistingUrl ?? readExistingQuickTunnelUrl;
    const verifyPublic = options.verifyPublicUrl ?? ((url) => checkLaravel(`${url}/up`, 8000));
    const existingUrl = await readExisting(duplicate.pid).catch(() => null);
    if (!existingUrl) {
      throw new TunnelError(`Đã có cloudflared Quick Tunnel tới ${origin} (PID ${duplicate.pid}). Không tạo tunnel trùng. Dừng PID đó rồi chạy lại npm run tunnel nếu cần URL mới.`);
    }
    try {
      await verifyPublic(existingUrl);
    } catch {
      throw new TunnelError(`Quick Tunnel PID ${duplicate.pid} không phản hồi công khai. Dừng PID đó rồi chạy lại npm run tunnel.`);
    }
    log(`Quick Tunnel sẵn có (PID ${duplicate.pid}). Không tạo tunnel mới và không dừng PID này.`);
    return publishTunnel({
      url: existingUrl,
      child: null,
      envPath,
      log,
      clearConfig,
      copy,
      openPage,
    });
  }
  if (processes.some((proc) => proc.pid && !proc.commandLine)) {
    throw new TunnelError('Có cloudflared đang chạy nhưng không đọc được lệnh. Không tạo tunnel trùng.');
  }

  const args = options.cloudflaredArgs ?? ['tunnel', '--url', origin];
  const { child, url } = await startQuickTunnel({
    command,
    args,
    timeoutMs: options.timeoutMs ?? URL_TIMEOUT_MS,
    forwardLogs,
    onSpawn: options.onSpawn,
  });

  return publishTunnel({
    url,
    child,
    envPath,
    log,
    clearConfig,
    copy,
    openPage,
  });
}

async function publishTunnel({ url, child, envPath, log, clearConfig, copy, openPage }) {
  let changed = false;
  try {
    ({ changed } = updateEnvFile(envPath, url));
    log(changed ? 'SEPAY_PUBLIC_BASE_URL: đã cập nhật' : 'SEPAY_PUBLIC_BASE_URL: không đổi');
  } catch (error) {
    if (child?.pid) {
      await stopOwnedProcess(child.pid).catch(() => {});
    }
    throw error;
  }

  let configOk = false;
  try {
    await clearConfig();
    configOk = true;
    log('Laravel config: PASS');
  } catch (error) {
    log(`Laravel config: FAIL`);
    log(error instanceof Error ? error.message : 'php artisan config:clear thất bại.');
  }

  const endpoint = ipnUrl(url);
  const hint = sepayIpnInstructions();
  log(`IPN URL: ${endpoint}`);
  const copied = await copy(endpoint).catch(() => false);
  log(copied ? 'Clipboard: PASS' : 'Clipboard: SKIP');
  log(hint.navigation);
  log(hint.action);
  const opened = await openPage().catch(() => false);
  if (opened) {
    log('Đã mở trang cấu hình IPN.');
  }

  return {
    child,
    url,
    ipn: endpoint,
    changed,
    configOk,
    copied: Boolean(copied),
    stop: () => (child?.pid ? stopOwnedProcess(child.pid) : Promise.resolve()),
  };
}

function samePath(left, right) {
  const a = path.resolve(left);
  const b = path.resolve(right);
  return process.platform === 'win32' ? a.toLowerCase() === b.toLowerCase() : a === b;
}

async function main() {
  let childPid = null;
  let stopping = false;
  const onSignal = () => {
    if (stopping) {
      return;
    }
    stopping = true;
    process.stderr.write('\nĐang dừng Quick Tunnel...\n');
    const done = childPid ? stopOwnedProcess(childPid) : Promise.resolve();
    done.finally(() => process.exit(0));
  };
  process.on('SIGINT', onSignal);
  process.on('SIGTERM', onSignal);

  try {
    const session = await runTunnel({
      onSpawn(child) {
        childPid = child.pid;
      },
    });
    if (!session.child) {
      process.exit(session.configOk ? 0 : 1);
    }
    session.child.on('exit', (code) => {
      if (!stopping) {
        process.exit(code && code !== 0 ? code : 0);
      }
    });
  } catch (error) {
    if (!stopping && childPid) {
      await stopOwnedProcess(childPid);
    }
    if (!stopping) {
      console.error(error instanceof Error ? error.message : String(error));
      process.exit(1);
    }
  }
}

const invokedDirectly = process.argv[1] && samePath(process.argv[1], fileURLToPath(import.meta.url));
if (invokedDirectly) {
  main();
}
