import fs from 'node:fs';
import path from 'node:path';
import { config } from './config.js';

export const SESSION_STATUSES = {
  VALID: 'valid',
  EXPIRED: 'expired',
  NEEDS_LOGIN: 'needs_login',
  CLOUDFLARE_CHALLENGE: 'cloudflare_challenge',
  BLOCKED: 'blocked',
  STALE: 'stale',
  UNKNOWN_ERROR: 'unknown_error',
  RUNNING: 'running',
};

const DEFAULT_STATUS = {
  source: 'bbtips',
  status: SESSION_STATUSES.RUNNING,
  last_success_at: null,
  last_payload_at: null,
  last_error_at: null,
  last_error_message: null,
  last_external_id: null,
  last_data_updated_at: null,
  needs_login: false,
  metadata_json: {},
  updated_at: null,
};

export function readCollectorStatus() {
  if (!fs.existsSync(config.statusFile)) {
    return { ...DEFAULT_STATUS };
  }

  try {
    return { ...DEFAULT_STATUS, ...JSON.parse(fs.readFileSync(config.statusFile, 'utf8')) };
  } catch {
    return { ...DEFAULT_STATUS };
  }
}

export function writeCollectorStatus(patch) {
  const current = readCollectorStatus();
  const next = {
    ...current,
    ...patch,
    updated_at: new Date().toISOString(),
  };

  fs.mkdirSync(path.dirname(config.statusFile), { recursive: true });
  fs.writeFileSync(config.statusFile, JSON.stringify(next, null, 2), 'utf8');
  return next;
}

export function isCloudflareChallenge(contentType, bodyText = '', pageTitle = '') {
  const normalizedType = (contentType || '').toLowerCase();
  const text = `${bodyText} ${pageTitle}`.toLowerCase();

  if (normalizedType.includes('text/html') && text.includes('just a moment')) {
    return true;
  }

  return text.includes('checking your browser') || text.includes('cf-browser-verification');
}

export function isLoginUrl(url = '') {
  const lower = url.toLowerCase();
  return lower.includes('/login') || lower.includes('/entrar') || lower.includes('/auth');
}

export async function detectPageSessionState(page) {
  const url = page.url();
  const title = await page.title().catch(() => '');

  if (isCloudflareChallenge('text/html', '', title)) {
    return SESSION_STATUSES.CLOUDFLARE_CHALLENGE;
  }

  if (isLoginUrl(url)) {
    return SESSION_STATUSES.NEEDS_LOGIN;
  }

  const hasAuthToken = await page
    .evaluate(() => {
      const keys = Object.keys(localStorage);
      return keys.some(
        (key) =>
          key.toLowerCase().includes('token') ||
          key.toLowerCase().includes('auth') ||
          key.toLowerCase().includes('user'),
      );
    })
    .catch(() => false);

  if (!hasAuthToken && isLoginUrl(url)) {
    return SESSION_STATUSES.NEEDS_LOGIN;
  }

  return SESSION_STATUSES.VALID;
}

export function appendCollectorRun(entry) {
  const runs = readCollectorRuns();
  runs.push({
    id: runs.length + 1,
    source: 'bbtips',
    created_at: new Date().toISOString(),
    ...entry,
  });

  fs.mkdirSync(path.dirname(config.runsFile), { recursive: true });
  fs.writeFileSync(config.runsFile, JSON.stringify(runs, null, 2), 'utf8');
}

export function readCollectorRuns() {
  if (!fs.existsSync(config.runsFile)) {
    return [];
  }

  try {
    return JSON.parse(fs.readFileSync(config.runsFile, 'utf8'));
  } catch {
    return [];
  }
}
