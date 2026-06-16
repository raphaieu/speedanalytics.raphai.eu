import fs from 'node:fs';
import { config } from './lib/config.js';
import { readCollectorRuns, readCollectorStatus } from './lib/session-status.js';

function readRacesIndex() {
  if (!fs.existsSync(config.racesIndexFile)) {
    return {};
  }

  try {
    return JSON.parse(fs.readFileSync(config.racesIndexFile, 'utf8'));
  } catch {
    return {};
  }
}

function formatDuration(ms) {
  if (!Number.isFinite(ms)) {
    return 'nunca';
  }

  const seconds = Math.floor(ms / 1000);
  if (seconds < 60) {
    return `${seconds}s`;
  }

  const minutes = Math.floor(seconds / 60);
  const remSeconds = seconds % 60;
  return `${minutes}m ${remSeconds}s`;
}

function countPayloadFiles() {
  if (!fs.existsSync(config.payloadDir)) {
    return 0;
  }

  return fs.readdirSync(config.payloadDir).filter((name) => name.endsWith('.json')).length;
}

function countTransitions(index) {
  return Object.values(index).filter((race) => race.raw_pending_payload && race.raw_result_payload)
    .length;
}

const status = readCollectorStatus();
const index = readRacesIndex();
const runs = readCollectorRuns();
const payloadFiles = countPayloadFiles();
const pending = Object.values(index).filter((race) => race.status === 'pending').length;
const settled = Object.values(index).filter((race) => race.status === 'settled').length;
const transitions = countTransitions(index);
const staleFor = status.last_payload_at
  ? Date.now() - new Date(status.last_payload_at).getTime()
  : Number.POSITIVE_INFINITY;

console.log('Speedway Collector — Status');
console.log('===========================');
console.log(`Status atual:        ${status.status}`);
console.log(`Precisa login:       ${status.needs_login ? 'sim' : 'não'}`);
console.log(`Último payload:      ${status.last_payload_at ?? 'nunca'} (${formatDuration(staleFor)} atrás)`);
console.log(`Último sucesso:      ${status.last_success_at ?? 'nunca'}`);
console.log(`Último external_id:  ${status.last_external_id ?? 'n/a'}`);
console.log(`Último erro:         ${status.last_error_message ?? 'nenhum'}`);
console.log('');
console.log('Coleta');
console.log('------');
console.log(`Arquivos de payload: ${payloadFiles}`);
console.log(`Corridas no índice:  ${Object.keys(index).length}`);
console.log(`Pending:             ${pending}`);
console.log(`Settled:             ${settled}`);
console.log(`Ciclos pending→settled completos: ${transitions}`);
console.log('');
console.log('Runs');
console.log('----');
console.log(`Total de execuções:  ${runs.length}`);
if (runs.length > 0) {
  const lastRun = runs[runs.length - 1];
  console.log(`Última execução:     ${lastRun.started_at} → ${lastRun.finished_at ?? 'em andamento'}`);
  console.log(`Payloads na sessão:  ${lastRun.payload_count ?? 0}`);
}
