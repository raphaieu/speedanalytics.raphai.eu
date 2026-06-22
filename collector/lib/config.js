import path from 'node:path';
import { fileURLToPath } from 'node:url';
import dotenv from 'dotenv';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');

dotenv.config({ path: path.join(rootDir, '.env') });

function resolvePath(relativePath, fallback) {
  const value = process.env[relativePath] ?? fallback;
  return path.isAbsolute(value) ? value : path.resolve(rootDir, value);
}

export const config = {
  rootDir,
  bbtipsAppUrl: process.env.BBTIPS_APP_URL ?? 'https://app.bbtips.com.br',
  bbtipsSpeedwayUrl:
    process.env.BBTIPS_SPEEDWAY_URL ?? 'https://app.bbtips.com.br/speedway/horarios',
  storageStatePath: resolvePath('STORAGE_STATE_PATH', './storage/bbtips-storage-state.json'),
  payloadDir: resolvePath('PAYLOAD_DIR', './storage/payloads'),
  statusFile: resolvePath('STATUS_FILE', './storage/collector-status.json'),
  racesIndexFile: resolvePath('RACES_INDEX_FILE', './storage/races-index.json'),
  runsFile: resolvePath('RUNS_FILE', './storage/collector-runs.json'),
  logFile: resolvePath('LOG_FILE', './storage/collector.log'),
  healthCheckIntervalMs: Number(process.env.HEALTH_CHECK_INTERVAL_MS ?? 30_000),
  collectorIntervalMs: Number(process.env.SPEEDWAY_COLLECTOR_INTERVAL_MS ?? 30_000),
  payloadStaleThresholdMs: Number(process.env.PAYLOAD_STALE_THRESHOLD_MS ?? 120_000),
  reloadThresholdMs: Number(process.env.RELOAD_THRESHOLD_MS ?? 300_000),
  staleThresholdMs: Number(process.env.STALE_THRESHOLD_MS ?? 120_000),
  headless: (process.env.HEADLESS ?? 'true').toLowerCase() !== 'false',
  loginTimeoutMs: Number(process.env.LOGIN_TIMEOUT_MS ?? 300_000),
  speedwayApiPattern: 'api.bbtips.com.br/api/speedway',
  speedwayFiltroExibicao: process.env.SPEEDWAY_FILTRO_EXIBICAO ?? 'Odd_Todas',
  speedwayHoras: process.env.SPEEDWAY_HORAS ?? 'Horas48',
  speedwayFuturo: (process.env.SPEEDWAY_FUTURO ?? 'true').toLowerCase() === 'true',
  collectorEndpoint: process.env.SPEEDWAY_COLLECTOR_ENDPOINT ?? null,
  collectorToken: process.env.SPEEDWAY_COLLECTOR_TOKEN ?? null,
};

export function parseSpeedwayUrl(url) {
  try {
    const parsed = new URL(url);
    return {
      filtro_exibicao: parsed.searchParams.get('filtroExibicao'),
      horas: parsed.searchParams.get('horas'),
      futuro: parsed.searchParams.get('futuro'),
      dados_alteracao: parsed.searchParams.get('dadosAlteracao'),
    };
  } catch {
    return {
      filtro_exibicao: null,
      horas: null,
      futuro: null,
      dados_alteracao: null,
    };
  }
}

export function isSpeedwayRaceDataUrl(url) {
  try {
    const parsed = new URL(url);
    return parsed.hostname === 'api.bbtips.com.br' && parsed.pathname === '/api/speedway' && parsed.search.length > 0;
  } catch {
    return false;
  }
}

export function isCaptureTargetUrl(url) {
  if (!isSpeedwayRaceDataUrl(url)) {
    return false;
  }

  const params = parseSpeedwayUrl(url);
  const expectedFuturo = config.speedwayFuturo ? 'true' : 'false';

  return (
    params.filtro_exibicao === config.speedwayFiltroExibicao &&
    params.horas === config.speedwayHoras &&
    params.futuro === expectedFuturo
  );
}
