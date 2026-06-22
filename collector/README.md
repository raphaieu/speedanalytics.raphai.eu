# Speedway Collector (Fase 0)

POC Playwright para coleta passiva de dados do Speedway via `app.bbtips.com.br`, contornando o bloqueio Cloudflare que impede `curl`/HTTP client direto.

## Pré-requisitos

- **Node.js 24** (LTS) — `nvm use 24` ou equivalente
- Conta válida no BB Tips

## Instalação

```bash
cd collector
cp .env.example .env
nvm use          # lê .nvmrc → Node 24
npm install
npx playwright install chromium
npm run validate
```

`npm run validate` executa testes locais do parser e do pipeline (sem login).

## 1. Login manual (gerar sessão)

Abre o Chromium em modo visível. Faça login manualmente e aguarde o script salvar o `storageState`.

```bash
npm run login
```

Arquivo gerado (não versionar):

```txt
storage/bbtips-storage-state.json
```

## 2. Coleta contínua

Intercepta responses de `api.bbtips.com.br/api/speedway?filtroExibicao=Odd_Todas` após configurar a UI automaticamente:

1. **Todas Odds** (`Odd_Todas`) no select de exibição
2. **48 Horas** (`Horas48`) no dropdown de horas
3. **Ver Futuras** (`#switchFuturo`) ligado

Sem isso, a página `/speedway/horarios` carrega só `Podio` + `Horas12` e o collector não captura nada.

### Formato do payload (Odd_Todas)

```txt
{
  "DataAtualizacao": "...",
  "Linhas": [
    {
      "Hora": "22",
      "Colunas": [
        { "Id": 909893, "Odds_Pilotos": "...", ... },  // pending
        { "Minutos": "13" },                            // stub (ignorado)
        { "Id": 909873, "Vencedor": 2, ... }           // settled
      ]
    }
  ]
}
```

- **Pending**: tem `Id` + `Odds_Pilotos`, sem `Vencedor`
- **Settled**: tem `Id` + `Vencedor`
- **Stubs**: só `Minutos` na grade — ignorados pelo parser

```bash
npm run collect
```

Para debug local com browser visível:

```bash
HEADLESS=false npm run collect
```

## 3. Ver status

```bash
npm run status
```

## O que é salvo

| Arquivo | Descrição |
|---------|-----------|
| `storage/payloads/*.json` | Payload bruto + metadados de cada captura |
| `storage/races-index.json` | Índice local `external_id → pending/settled` |
| `storage/collector-status.json` | Status atual do collector |
| `storage/collector-runs.json` | Histórico de execuções |
| `storage/collector.log` | Log estruturado JSON |

## Estados possíveis

- `valid` — JSON recebido com corridas
- `running` — collector ativo
- `stale` — sem payload novo por ~**2 min** (`PAYLOAD_STALE_THRESHOLD_MS=120000`); reload da página após ~**5 min** (`RELOAD_THRESHOLD_MS=300000`)
- `needs_login` / `expired` — sessão expirada; rode `npm run login`
- `cloudflare_challenge` — challenge detectado (HTML em vez de JSON)
- `blocked` / `unknown_error` — falha persistente

## Renovar sessão

Quando `needs_login` aparecer no status ou nos logs:

```bash
npm run login
npm run collect
```

## Segurança

- Nunca commitar `storage/`, `.env` ou `storageState`
- Tokens e cookies ficam apenas no ambiente local/VPS

## Critérios de sucesso (Fase 0)

- [x] `login.js` gera `storageState` reutilizável
- [x] `collector.js` captura payloads JSON por 30–60 min
- [x] Pelo menos 1 ciclo `pending → settled` no `races-index.json`
- [x] Nenhuma chamada direta à API — só interceptação passiva

Evidência completa: [docs/VALIDATION.md](docs/VALIDATION.md)

## Deploy produção (Coolify / Docker Compose)

Em produção o collector roda como serviço **`collector`** no `docker-compose.yml` (Playwright headless). Ver guia completo: [README.md](../README.md#docker--coolify-produção) · [docs/ARCHITECTURE.md](../docs/ARCHITECTURE.md).

**Sessão BB Tips na VPS:** login manual no PC (`npm run login`) → copiar `storage/bbtips-storage-state.json` para `/app/storage/` do container collector. Não há credenciais no `.env`.

Variáveis relevantes (`collector/.env`, `.env.coolify.example` ou Environment Variables do Coolify):

```env
# Backend Laravel (rede interna do compose)
SPEEDWAY_COLLECTOR_ENDPOINT=http://web/api/collector/speedway
SPEEDWAY_COLLECTOR_TOKEN=   # igual ao .env raiz / Coolify

# Freshness / captura (defaults recomendados)
HEALTH_CHECK_INTERVAL_MS=30000
SPEEDWAY_COLLECTOR_INTERVAL_MS=30000
PAYLOAD_STALE_THRESHOLD_MS=120000   # status stale ~2 min
RELOAD_THRESHOLD_MS=300000          # reload após ~5 min stale
# STALE_THRESHOLD_MS=120000         # legado; preferir PAYLOAD_STALE_THRESHOLD_MS

# Filtros BB Tips (defaults no compose)
SPEEDWAY_FILTRO_EXIBICAO=Odd_Todas
SPEEDWAY_HORAS=Horas48
SPEEDWAY_FUTURO=true
```

No Laravel, `SPEEDWAY_COLLECTOR_PAYLOAD_STALE_SECONDS=120` alinha a API `/api/collector/status` com o threshold do collector.

## Status

- **Produção:** [speedanalytics.raphai.eu](https://speedanalytics.raphai.eu) — Coolify, coleta 24/7, PWA ✓
- **Fase 1:** concluída (`1.0.0`)

## Próximo passo

Fase 2 — métricas, setups e demo. Ver [PRD.md](../PRD.md) e [CHANGELOG.md](../CHANGELOG.md).
