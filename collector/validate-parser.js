import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { config } from './lib/config.js';
import { parseRacesFromPayload, summarizePayload, updateRacesIndex } from './lib/parse-races.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const testIndexFile = path.join(config.rootDir, 'storage', 'races-index.test.json');

const originalRacesIndexFile = config.racesIndexFile;
config.racesIndexFile = testIndexFile;

const pendingPayload = [
  {
    Id: 909380,
    Horario: '20',
    Hora: '20',
    Minutos: '22',
    Odds_Pilotos: '3.10|2.75|5.50|5.50',
  },
];

const settledPayload = [
  {
    Id: 909380,
    Horario: '20',
    Hora: '20',
    Minutos: '22',
    Vencedor: 2,
    Cor_Vencedor: 'Vermelho',
    Nome_Piloto: 'Nome do Piloto',
    Odd: '2.75',
    Previsao: '2-1',
    Odd_Previsao: 7.5,
    Previsao_Tricast: '2-1-4',
    Odds_Pilotos: '3.10|2.75|5.50|5.50',
  },
];

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

try {
  if (fs.existsSync(testIndexFile)) {
    fs.unlinkSync(testIndexFile);
  }

  const pendingSummary = summarizePayload(pendingPayload);
  assert(pendingSummary.pending_count === 1, 'Esperava 1 pending no payload inicial');
  assert(pendingSummary.settled_count === 0, 'Esperava 0 settled no payload inicial');

  const firstUpdate = updateRacesIndex(pendingSummary.races, '2026-06-15T20:20:00-03:00');
  assert(firstUpdate.transitions.length === 0, 'Não deveria haver transição no primeiro payload');

  const settledSummary = summarizePayload(settledPayload);
  assert(settledSummary.settled_count === 1, 'Esperava 1 settled no payload final');

  const secondUpdate = updateRacesIndex(settledSummary.races, '2026-06-15T20:23:00-03:00');
  assert(secondUpdate.transitions.length === 1, 'Esperava transição pending → settled');
  assert(secondUpdate.transitions[0].external_id === '909380', 'external_id incorreto na transição');

  const index = JSON.parse(fs.readFileSync(testIndexFile, 'utf8'));
  assert(index['909380'].status === 'settled', 'Status final deveria ser settled');
  assert(index['909380'].raw_pending_payload !== null, 'raw_pending_payload deveria estar salvo');
  assert(index['909380'].raw_result_payload !== null, 'raw_result_payload deveria estar salvo');

  const parsed = parseRacesFromPayload(settledPayload);
  assert(parsed[0].status === 'settled', 'classifyRace deveria retornar settled');

  const linhasPayload = {
    Linhas: [
      {
        Hora: '21',
        Colunas: [
          {
            Id: 909873,
            Horario: '21',
            Vencedor: 2,
            Hora: '21',
            Minutos: '01',
            Odds_Pilotos: '6.00|3.40|3.50|3.25',
          },
        ],
      },
    ],
  };
  const linhasSummary = summarizePayload(linhasPayload);
  assert(linhasSummary.race_count === 1, 'Parser deveria extrair corridas de Linhas/Colunas');
  assert(linhasSummary.settled_count === 1, 'Corrida em Linhas/Colunas deveria ser settled');

  const oddTodasPayload = {
    DataAtualizacao: '2026-06-16T17:53:37.233',
    Linhas: [
      {
        Hora: '22',
        Colunas: [
          {
            Id: 909893,
            Horario: '22',
            Hora: '22',
            Minutos: '01',
            Odds_Pilotos: '2.37|6.50|5.25|3.50',
          },
          { Minutos: '13' },
          { Minutos: '16' },
        ],
      },
      {
        Hora: '21',
        Colunas: [
          {
            Id: 909873,
            Horario: '21',
            Vencedor: 2,
            Cor_Vencedor: 'Vermelho',
            Odd: '3.40',
            Hora: '21',
            Minutos: '01',
            Odds_Pilotos: '6.00|3.40|3.50|3.25',
          },
        ],
      },
    ],
  };
  const oddTodasSummary = summarizePayload(oddTodasPayload);
  assert(oddTodasSummary.race_count === 2, 'Deveria ignorar stubs sem Id e contar 2 corridas');
  assert(oddTodasSummary.pending_count === 1, 'Id 909893 deveria ser pending');
  assert(oddTodasSummary.settled_count === 1, 'Id 909873 deveria ser settled');

  console.log('validate-parser: OK');
  console.log(JSON.stringify({
    pending_count: pendingSummary.pending_count,
    settled_count: settledSummary.settled_count,
    transitions: secondUpdate.transitions,
    index_status: index['909380'].status,
  }, null, 2));
} finally {
  config.racesIndexFile = originalRacesIndexFile;
  if (fs.existsSync(testIndexFile)) {
    fs.unlinkSync(testIndexFile);
  }
}
