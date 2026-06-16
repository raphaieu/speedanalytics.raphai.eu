import { isCaptureTargetUrl, isSpeedwayRaceDataUrl, parseSpeedwayUrl } from './config.js';

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

const oddTodasUrl =
  'https://api.bbtips.com.br/api/speedway?filtroExibicao=Odd_Todas&horas=Horas48&futuro=true&dadosAlteracao=';
const podioUrl =
  'https://api.bbtips.com.br/api/speedway?filtroExibicao=Podio&horas=Horas12&futuro=false&dadosAlteracao=';
const ultimaAtualizacaoUrl = 'https://api.bbtips.com.br/api/speedway/ultimaAtualizacao';

assert(isSpeedwayRaceDataUrl(oddTodasUrl), 'URL Odd_Todas deveria ser speedway data');
assert(!isSpeedwayRaceDataUrl(ultimaAtualizacaoUrl), 'ultimaAtualizacao não deveria passar');

const oddTodasPartialUrl =
  'https://api.bbtips.com.br/api/speedway?filtroExibicao=Odd_Todas&horas=Horas12&futuro=false&dadosAlteracao=909899';

assert(isCaptureTargetUrl(oddTodasUrl), 'Odd_Todas deveria ser alvo de captura');
assert(!isCaptureTargetUrl(podioUrl), 'Podio não deveria ser alvo de captura');
assert(!isCaptureTargetUrl(oddTodasPartialUrl), 'estado intermediário de filtros não deveria ser alvo');

const params = parseSpeedwayUrl(oddTodasUrl);
assert(params.filtro_exibicao === 'Odd_Todas');
assert(params.horas === 'Horas48');
assert(params.futuro === 'true');

console.log('url-filter: OK');
