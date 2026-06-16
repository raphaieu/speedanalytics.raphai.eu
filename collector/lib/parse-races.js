import fs from 'node:fs';
import path from 'node:path';
import { config } from './config.js';

function isRaceObject(value) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return false;
  }

  const hasId = value.Id !== undefined || value.id !== undefined;
  if (!hasId) {
    return false;
  }

  // Ignora stubs da grade horária ({ "Minutos": "13" } sem Id)
  const hasOdds = value.Odds_Pilotos !== undefined && value.Odds_Pilotos !== '';
  const hasResult = value.Vencedor !== undefined && value.Vencedor !== null && value.Vencedor !== '';

  return hasOdds || hasResult;
}

function extractRaceArray(payload) {
  if (Array.isArray(payload)) {
    return payload.filter(isRaceObject);
  }

  if (!payload || typeof payload !== 'object') {
    return [];
  }

  if (Array.isArray(payload.Linhas)) {
    const races = [];
    for (const linha of payload.Linhas) {
      if (Array.isArray(linha.Colunas)) {
        races.push(...linha.Colunas.filter(isRaceObject));
      }
    }
    if (races.length > 0) {
      return races;
    }
  }

  const candidateKeys = ['data', 'corridas', 'races', 'items', 'resultado', 'Resultado'];
  for (const key of candidateKeys) {
    if (Array.isArray(payload[key]) && payload[key].some(isRaceObject)) {
      return payload[key].filter(isRaceObject);
    }
  }

  for (const value of Object.values(payload)) {
    if (Array.isArray(value) && value.some(isRaceObject)) {
      return value.filter(isRaceObject);
    }
  }

  if (isRaceObject(payload)) {
    return [payload];
  }

  return [];
}

export function getExternalId(race) {
  return String(race.Id ?? race.id);
}

export function classifyRace(race) {
  const hasWinner =
    race.Vencedor !== undefined &&
    race.Vencedor !== null &&
    race.Vencedor !== '';

  return hasWinner ? 'settled' : 'pending';
}

export function parseRacesFromPayload(payload) {
  const races = extractRaceArray(payload);

  return races.map((race) => ({
    external_id: getExternalId(race),
    status: classifyRace(race),
    horario: race.Horario ?? race.Hora ?? null,
    hora: race.Hora ?? null,
    minutos: race.Minutos ?? null,
    odds_pilotos: race.Odds_Pilotos ?? null,
    vencedor: race.Vencedor ?? null,
    cor_vencedor: race.Cor_Vencedor ?? null,
    odd: race.Odd ?? null,
    raw: race,
  }));
}

export function summarizePayload(payload) {
  const races = parseRacesFromPayload(payload);

  return {
    race_count: races.length,
    pending_count: races.filter((race) => race.status === 'pending').length,
    settled_count: races.filter((race) => race.status === 'settled').length,
    races,
  };
}

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

export function updateRacesIndex(parsedRaces, capturedAt = new Date().toISOString()) {
  const index = readRacesIndex();
  const transitions = [];

  for (const race of parsedRaces) {
    const previous = index[race.external_id];
    const nextEntry = {
      external_id: race.external_id,
      status: race.status,
      horario: race.horario,
      hora: race.hora,
      minutos: race.minutos,
      first_seen_at: previous?.first_seen_at ?? capturedAt,
      last_seen_at: capturedAt,
      settled_at: race.status === 'settled' ? capturedAt : (previous?.settled_at ?? null),
      raw_pending_payload: previous?.raw_pending_payload ?? (race.status === 'pending' ? race.raw : null),
      raw_result_payload: race.status === 'settled' ? race.raw : (previous?.raw_result_payload ?? null),
    };

    if (previous && previous.status === 'pending' && race.status === 'settled') {
      transitions.push({
        external_id: race.external_id,
        from: 'pending',
        to: 'settled',
        at: capturedAt,
      });
    }

    index[race.external_id] = nextEntry;
  }

  fs.mkdirSync(path.dirname(config.racesIndexFile), { recursive: true });
  fs.writeFileSync(config.racesIndexFile, JSON.stringify(index, null, 2), 'utf8');

  return { index, transitions };
}
