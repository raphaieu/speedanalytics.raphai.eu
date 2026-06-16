import fs from 'node:fs';
import path from 'node:path';
import { config, isCaptureTargetUrl, parseSpeedwayUrl } from './config.js';
import { logger } from './logger.js';
import {
  isCloudflareChallenge,
  SESSION_STATUSES,
  writeCollectorStatus,
} from './session-status.js';
import { summarizePayload, updateRacesIndex } from './parse-races.js';

function buildPayloadFilename(capturedAt) {
  const safe = capturedAt.replace(/[:.]/g, '-');
  return path.join(config.payloadDir, `${safe}.json`);
}

function savePayloadFile(record) {
  fs.mkdirSync(config.payloadDir, { recursive: true });
  const filePath = buildPayloadFilename(record.captured_at);
  fs.writeFileSync(filePath, JSON.stringify(record, null, 2), 'utf8');
  return filePath;
}

export function attachSpeedwayInterceptor(page, state) {
  page.on('response', async (response) => {
    if (state.filtersReady === false) {
      return;
    }

    const url = response.url();
    if (!isCaptureTargetUrl(url)) {
      return;
    }

    const urlParams = parseSpeedwayUrl(url);

    const contentType = response.headers()['content-type'] || '';
    const statusCode = response.status();
    const capturedAt = new Date().toISOString();

    let bodyText = '';
    try {
      bodyText = await response.text();
    } catch (error) {
      logger.error('Falha ao ler body da response speedway', {
        url,
        error: error.message,
      });
      return;
    }

    if (!contentType.includes('application/json')) {
      const challenge = isCloudflareChallenge(contentType, bodyText);
      const sessionStatus = challenge
        ? SESSION_STATUSES.CLOUDFLARE_CHALLENGE
        : SESSION_STATUSES.UNKNOWN_ERROR;

      state.lastErrorAt = capturedAt;
      state.lastErrorMessage = challenge
        ? 'Cloudflare challenge detectado na API speedway'
        : `Resposta não-JSON da API speedway (status ${statusCode})`;

      writeCollectorStatus({
        status: sessionStatus,
        last_error_at: state.lastErrorAt,
        last_error_message: state.lastErrorMessage,
        needs_login: !challenge,
        metadata_json: {
          last_non_json_url: url,
          last_non_json_status: statusCode,
          content_type: contentType,
        },
      });

      logger.warn(state.lastErrorMessage, {
        url,
        status: statusCode,
        content_type: contentType,
        session_status: sessionStatus,
      });
      return;
    }

    let payload;
    try {
      payload = JSON.parse(bodyText);
    } catch (error) {
      state.lastErrorAt = capturedAt;
      state.lastErrorMessage = 'JSON inválido na response speedway';

      writeCollectorStatus({
        status: SESSION_STATUSES.UNKNOWN_ERROR,
        last_error_at: state.lastErrorAt,
        last_error_message: state.lastErrorMessage,
      });

      logger.error(state.lastErrorMessage, { url, error: error.message });
      return;
    }

    const summary = summarizePayload(payload);
    const { transitions } = updateRacesIndex(summary.races, capturedAt);

    const record = {
      source: 'bbtips',
      mode: urlParams.filtro_exibicao?.toLowerCase() ?? 'unknown',
      filtro_exibicao: urlParams.filtro_exibicao,
      horas: urlParams.horas,
      futuro: urlParams.futuro === 'true',
      source_url: url,
      captured_at: capturedAt,
      data_atualizacao: payload.DataAtualizacao ?? null,
      payload,
      summary: {
        race_count: summary.race_count,
        pending_count: summary.pending_count,
        settled_count: summary.settled_count,
      },
    };

    const filePath = savePayloadFile(record);

    state.payloadCount += 1;
    state.lastPayloadAt = capturedAt;
    state.lastSuccessAt = capturedAt;

    const lastRace = summary.races[summary.races.length - 1];
    if (lastRace) {
      state.lastExternalId = lastRace.external_id;
    }

    writeCollectorStatus({
      status: SESSION_STATUSES.VALID,
      last_success_at: state.lastSuccessAt,
      last_payload_at: state.lastPayloadAt,
      last_external_id: state.lastExternalId,
      last_data_updated_at: capturedAt,
      needs_login: false,
      last_error_message: null,
      metadata_json: {
        last_payload_file: filePath,
        last_race_count: summary.race_count,
        last_pending_count: summary.pending_count,
        last_settled_count: summary.settled_count,
      },
    });

    logger.info('Payload speedway capturado', {
      file: filePath,
      filtro_exibicao: urlParams.filtro_exibicao,
      horas: urlParams.horas,
      futuro: urlParams.futuro,
      data_atualizacao: payload.DataAtualizacao ?? null,
      race_count: summary.race_count,
      pending_count: summary.pending_count,
      settled_count: summary.settled_count,
      last_external_id: state.lastExternalId,
    });

    for (const transition of transitions) {
      logger.info('Transição pending → settled detectada', transition);
    }
  });
}
