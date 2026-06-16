import { config, isCaptureTargetUrl } from './lib/config.js';
import { launchBrowser, storageStateExists } from './lib/browser.js';
import { attachSpeedwayInterceptor } from './lib/intercept.js';
import { logger } from './lib/logger.js';
import { applySpeedwayView } from './lib/speedway-ui.js';
import {
  appendCollectorRun,
  detectPageSessionState,
  readCollectorStatus,
  SESSION_STATUSES,
  writeCollectorStatus,
} from './lib/session-status.js';

const state = {
  payloadCount: 0,
  lastPayloadAt: null,
  lastSuccessAt: null,
  lastExternalId: null,
  lastErrorAt: null,
  lastErrorMessage: null,
  reloadCount: 0,
  filtersReady: false,
};

let browser;
let page;
let healthCheckTimer;
let running = true;

function msSince(isoDate, fallbackIsoDate = null) {
  const reference = isoDate ?? fallbackIsoDate;
  if (!reference) {
    return 0;
  }

  return Date.now() - new Date(reference).getTime();
}

function staleForMs() {
  return msSince(state.lastPayloadAt, state.startedAt);
}

async function evaluateSession() {
  if (!page) {
    return SESSION_STATUSES.UNKNOWN_ERROR;
  }

  return detectPageSessionState(page);
}

async function navigateToSpeedway() {
  state.filtersReady = false;
  await page.goto(config.bbtipsSpeedwayUrl, {
    waitUntil: 'domcontentloaded',
    timeout: 120_000,
  });

  try {
    await page.waitForLoadState('networkidle', { timeout: 60_000 });
  } catch {
    logger.warn('networkidle não atingido; continuando com filtros da UI');
  }

  state.filtersReady = true;

  const initialPayloadPromise = page.waitForResponse(
    (response) => isCaptureTargetUrl(response.url()) && response.status() === 200,
    { timeout: 90_000 },
  );

  await applySpeedwayView(page);

  try {
    await initialPayloadPromise;
    logger.info('Payload inicial recebido após aplicar filtros');
  } catch (error) {
    logger.warn('Payload inicial não confirmado após filtros', { error: error.message });
  }
}

async function reloadIfStale() {
  const staleFor = staleForMs();

  if (staleFor < config.staleThresholdMs) {
    return;
  }

  const sessionStatus = await evaluateSession();

  if (
    sessionStatus === SESSION_STATUSES.NEEDS_LOGIN ||
    sessionStatus === SESSION_STATUSES.CLOUDFLARE_CHALLENGE
  ) {
    writeCollectorStatus({
      status: sessionStatus,
      needs_login: sessionStatus === SESSION_STATUSES.NEEDS_LOGIN,
      last_error_at: new Date().toISOString(),
      last_error_message:
        sessionStatus === SESSION_STATUSES.CLOUDFLARE_CHALLENGE
          ? 'Cloudflare challenge na página'
          : 'Sessão expirada — execute npm run login',
    });
    logger.warn('Coleta interrompida por estado de sessão', { session_status: sessionStatus });
    return;
  }

  state.reloadCount += 1;
  logger.warn('Sem payload novo dentro do limite stale. Recarregando página.', {
    stale_for_ms: staleFor,
    reload_count: state.reloadCount,
  });

  await navigateToSpeedway();
}

async function runHealthCheck() {
  if (!running) {
    return;
  }

  const sessionStatus = await evaluateSession();
  const staleFor = staleForMs();
  const currentStatus = readCollectorStatus();

  if (sessionStatus === SESSION_STATUSES.NEEDS_LOGIN) {
    writeCollectorStatus({
      status: SESSION_STATUSES.NEEDS_LOGIN,
      needs_login: true,
      last_error_at: new Date().toISOString(),
      last_error_message: 'Sessão expirada — execute npm run login',
    });
    logger.error('Sessão expirada', { session_status: sessionStatus });
    return;
  }

  if (sessionStatus === SESSION_STATUSES.CLOUDFLARE_CHALLENGE) {
    writeCollectorStatus({
      status: SESSION_STATUSES.CLOUDFLARE_CHALLENGE,
      last_error_at: new Date().toISOString(),
      last_error_message: 'Cloudflare challenge na página',
    });
    logger.error('Cloudflare challenge na página', { session_status: sessionStatus });
    return;
  }

  if (staleFor >= config.staleThresholdMs) {
    if (state.payloadCount === 0) {
      logger.warn('Sem payload capturado dentro do limite stale. Recarregando e reaplicando filtros.', {
        stale_for_ms: staleFor,
        reload_count: state.reloadCount,
      });
    } else if (currentStatus.status !== SESSION_STATUSES.STALE) {
      writeCollectorStatus({
        status: SESSION_STATUSES.STALE,
        last_error_at: new Date().toISOString(),
        last_error_message: `Sem payload novo há ${Math.round(staleFor / 1000)}s`,
        metadata_json: {
          stale_for_ms: staleFor,
        },
      });
    }

    await reloadIfStale();
    return;
  }

  writeCollectorStatus({
    status: SESSION_STATUSES.RUNNING,
    metadata_json: {
      payload_count_session: state.payloadCount,
      stale_for_ms: Number.isFinite(staleFor) ? staleFor : null,
      reload_count: state.reloadCount,
      session_status: sessionStatus,
    },
  });

  logger.info('Health check', {
    payload_count_session: state.payloadCount,
    last_payload_at: state.lastPayloadAt,
    stale_for_ms: Number.isFinite(staleFor) ? staleFor : null,
    session_status: sessionStatus,
  });
}

async function shutdown(exitCode = 0) {
  running = false;

  if (healthCheckTimer) {
    clearInterval(healthCheckTimer);
  }

  appendCollectorRun({
    started_at: state.startedAt,
    finished_at: new Date().toISOString(),
    status: exitCode === 0 ? 'ok' : 'failed',
    payload_count: state.payloadCount,
    error_message: state.lastErrorMessage,
    metadata_json: {
      reload_count: state.reloadCount,
      last_external_id: state.lastExternalId,
    },
  });

  if (browser) {
    await browser.close();
  }

  process.exit(exitCode);
}

async function main() {
  if (!storageStateExists()) {
    console.error('storageState não encontrado. Execute primeiro: npm run login');
    process.exit(1);
  }

  state.startedAt = new Date().toISOString();

  writeCollectorStatus({
    status: SESSION_STATUSES.RUNNING,
    needs_login: false,
    metadata_json: {
      started_at: state.startedAt,
      headless: config.headless,
    },
  });

  logger.info('Iniciando collector', {
    speedway_url: config.bbtipsSpeedwayUrl,
    filtro_exibicao: config.speedwayFiltroExibicao,
    horas: config.speedwayHoras,
    futuro: config.speedwayFuturo,
    headless: config.headless,
    health_check_interval_ms: config.healthCheckIntervalMs,
    stale_threshold_ms: config.staleThresholdMs,
  });

  const launched = await launchBrowser({ headless: config.headless });
  browser = launched.browser;
  page = launched.page;

  attachSpeedwayInterceptor(page, state);

  page.on('framenavigated', async () => {
    const sessionStatus = await evaluateSession();
    if (sessionStatus === SESSION_STATUSES.NEEDS_LOGIN) {
      logger.warn('Navegação detectou tela de login', { url: page.url() });
    }
  });

  await navigateToSpeedway();

  logger.info('Página Speedway carregada. Aguardando responses nativas do app.', {
    url: page.url(),
  });

  healthCheckTimer = setInterval(() => {
    runHealthCheck().catch((error) => {
      logger.error('Erro no health check', { error: error.message });
    });
  }, config.healthCheckIntervalMs);

  process.on('SIGINT', () => {
    logger.info('Encerrando collector (SIGINT)');
    shutdown(0);
  });

  process.on('SIGTERM', () => {
    logger.info('Encerrando collector (SIGTERM)');
    shutdown(0);
  });
}

main().catch(async (error) => {
  state.lastErrorMessage = error.message;
  writeCollectorStatus({
    status: SESSION_STATUSES.UNKNOWN_ERROR,
    last_error_at: new Date().toISOString(),
    last_error_message: error.message,
  });
  logger.error('Collector falhou', { error: error.message });
  await shutdown(1);
});
