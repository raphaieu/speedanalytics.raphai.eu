import { config } from './lib/config.js';
import { launchBrowser, saveStorageState } from './lib/browser.js';
import { logger } from './lib/logger.js';
import {
  detectPageSessionState,
  isLoginUrl,
  SESSION_STATUSES,
  writeCollectorStatus,
} from './lib/session-status.js';

async function waitForAuthenticatedSession(page) {
  const startedAt = Date.now();

  while (Date.now() - startedAt < config.loginTimeoutMs) {
    const sessionStatus = await detectPageSessionState(page);

    if (sessionStatus === SESSION_STATUSES.CLOUDFLARE_CHALLENGE) {
      logger.warn('Cloudflare challenge detectado durante login. Aguarde a verificação no browser.');
    } else if (!isLoginUrl(page.url()) && sessionStatus === SESSION_STATUSES.VALID) {
      return true;
    }

    await page.waitForTimeout(2000);
  }

  return false;
}

async function main() {
  logger.info('Iniciando login manual', {
    app_url: config.bbtipsAppUrl,
    storage_state_path: config.storageStatePath,
  });

  const { browser, context, page } = await launchBrowser({ headless: false, storageState: null });

  try {
    await page.goto(config.bbtipsAppUrl, { waitUntil: 'domcontentloaded', timeout: 120_000 });

    logger.info('Browser aberto. Faça login manualmente na janela do Chromium.');
    logger.info(`Aguardando até ${config.loginTimeoutMs / 1000}s por sessão autenticada...`);

    const authenticated = await waitForAuthenticatedSession(page);

    if (!authenticated) {
      writeCollectorStatus({
        status: SESSION_STATUSES.NEEDS_LOGIN,
        needs_login: true,
        last_error_at: new Date().toISOString(),
        last_error_message: 'Timeout aguardando login manual',
      });
      throw new Error('Timeout aguardando login manual. Execute novamente: npm run login');
    }

    await saveStorageState(context);

    writeCollectorStatus({
      status: SESSION_STATUSES.VALID,
      needs_login: false,
      last_success_at: new Date().toISOString(),
      last_error_message: null,
    });

    logger.info('Sessão salva com sucesso', {
      storage_state_path: config.storageStatePath,
    });
    console.log('\nLogin concluído. storageState salvo em:', config.storageStatePath);
    console.log('Próximo passo: npm run collect\n');
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  logger.error('Falha no login', { error: error.message });
  console.error(error.message);
  process.exit(1);
});
