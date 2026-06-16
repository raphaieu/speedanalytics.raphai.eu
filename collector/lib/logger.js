import fs from 'node:fs';
import path from 'node:path';
import { config } from './config.js';

function ensureLogDir() {
  fs.mkdirSync(path.dirname(config.logFile), { recursive: true });
}

function formatEntry(level, message, meta = {}) {
  return JSON.stringify({
    timestamp: new Date().toISOString(),
    level,
    message,
    ...meta,
  });
}

export function log(level, message, meta = {}) {
  ensureLogDir();
  const line = formatEntry(level, message, meta);
  console.log(line);
  fs.appendFileSync(config.logFile, `${line}\n`, 'utf8');
}

export const logger = {
  info: (message, meta) => log('info', message, meta),
  warn: (message, meta) => log('warn', message, meta),
  error: (message, meta) => log('error', message, meta),
  debug: (message, meta) => log('debug', message, meta),
};
