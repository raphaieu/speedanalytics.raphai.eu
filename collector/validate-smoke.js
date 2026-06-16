import fs from 'node:fs';
import path from 'node:path';
import { config } from './lib/config.js';
import { summarizePayload, updateRacesIndex } from './lib/parse-races.js';
import { SESSION_STATUSES, isCloudflareChallenge, writeCollectorStatus } from './lib/session-status.js';

const capturedAt = new Date().toISOString();

const pendingPayload = [
  {
    Id: 909381,
    Horario: '20',
    Hora: '20',
    Minutos: '25',
    Odds_Pilotos: '3.80|3.10|3.40|6.00',
  },
];

const settledPayload = [
  {
    Id: 909381,
    Horario: '20',
    Hora: '20',
    Minutos: '25',
    Vencedor: 2,
    Cor_Vencedor: 'Vermelho',
    Nome_Piloto: 'Piloto Teste',
    Odd: '3.10',
    Odds_Pilotos: '3.80|3.10|3.40|6.00',
  },
];

function saveSimulatedPayload(payload, sourceUrl) {
  const summary = summarizePayload(payload);
  const record = {
    source: 'bbtips',
    mode: 'odd_todas',
    source_url: sourceUrl,
    captured_at: new Date().toISOString(),
    payload,
    summary: {
      race_count: summary.race_count,
      pending_count: summary.pending_count,
      settled_count: summary.settled_count,
    },
  };

  fs.mkdirSync(config.payloadDir, { recursive: true });
  const filename = path.join(
    config.payloadDir,
    `smoke-${Date.now()}-${summary.pending_count ? 'pending' : 'settled'}.json`,
  );
  fs.writeFileSync(filename, JSON.stringify(record, null, 2), 'utf8');
  return { filename, summary };
}

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

// Limpa artefatos de smoke anteriores
for (const file of [config.racesIndexFile, config.statusFile, config.runsFile, config.logFile]) {
  if (fs.existsSync(file)) {
    fs.unlinkSync(file);
  }
}

if (fs.existsSync(config.payloadDir)) {
  for (const name of fs.readdirSync(config.payloadDir)) {
    if (name.startsWith('smoke-')) {
      fs.unlinkSync(path.join(config.payloadDir, name));
    }
  }
}

assert(
  isCloudflareChallenge('text/html', '<html>Just a moment...</html>'),
  'Detecção Cloudflare deveria ser true para HTML challenge',
);

assert(
  !isCloudflareChallenge('application/json', '{"ok":true}'),
  'Detecção Cloudflare deveria ser false para JSON',
);

const pending = saveSimulatedPayload(
  pendingPayload,
  'https://api.bbtips.com.br/api/speedway?futuro=true&smoke=1',
);
const pendingIndex = updateRacesIndex(pending.summary.races, capturedAt);

assert(pendingIndex.transitions.length === 0, 'Smoke: sem transição no pending');

writeCollectorStatus({
  status: SESSION_STATUSES.VALID,
  last_success_at: new Date().toISOString(),
  last_payload_at: new Date().toISOString(),
  last_external_id: '909381',
});

const settled = saveSimulatedPayload(
  settledPayload,
  'https://api.bbtips.com.br/api/speedway?futuro=true&smoke=2',
);
const settledIndex = updateRacesIndex(settled.summary.races, new Date().toISOString());

assert(settledIndex.transitions.length === 1, 'Smoke: transição pending → settled esperada');

const payloadFiles = fs
  .readdirSync(config.payloadDir)
  .filter((name) => name.startsWith('smoke-') && name.endsWith('.json'));

assert(payloadFiles.length >= 2, 'Smoke: deveriam existir 2+ payloads simulados');

const index = JSON.parse(fs.readFileSync(config.racesIndexFile, 'utf8'));
assert(index['909381'].status === 'settled', 'Smoke: índice final settled');

console.log('validate-smoke: OK');
console.log(
  JSON.stringify(
    {
      payloads_saved: payloadFiles.length,
      transitions: settledIndex.transitions,
      collector_status: JSON.parse(fs.readFileSync(config.statusFile, 'utf8')).status,
    },
    null,
    2,
  ),
);
