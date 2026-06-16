import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import { config } from './config.js';

export function ensureStorageDirs() {
  fs.mkdirSync(path.dirname(config.storageStatePath), { recursive: true });
  fs.mkdirSync(config.payloadDir, { recursive: true });
}

export function storageStateExists() {
  return fs.existsSync(config.storageStatePath);
}

export async function launchBrowser(options = {}) {
  const { headless = config.headless, storageState = config.storageStatePath } = options;

  ensureStorageDirs();

  const launchOptions = {
    headless,
    args: ['--disable-blink-features=AutomationControlled'],
  };

  const browser = await chromium.launch(launchOptions);

  const contextOptions = {
    viewport: { width: 1280, height: 800 },
    userAgent:
      'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
  };

  if (storageState && fs.existsSync(storageState)) {
    contextOptions.storageState = storageState;
  }

  const context = await browser.newContext(contextOptions);
  const page = await context.newPage();

  return { browser, context, page };
}

export async function saveStorageState(context) {
  ensureStorageDirs();
  await context.storageState({ path: config.storageStatePath });
}
