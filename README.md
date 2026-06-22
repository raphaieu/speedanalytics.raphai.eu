# Speedway Analytics

Plataforma mobile-first de inteligência operacional para o jogo virtual Speedway — coleta 24/7, histórico de corridas, setups, demo, backtests e IA explicativa.

Documento de produto: [PRD.md](PRD.md) · Arquitetura: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) · Changelog: [CHANGELOG.md](CHANGELOG.md)

## Status do projeto

| Fase | Escopo | Status |
|------|--------|--------|
| **0** | Playwright Collector | Concluída |
| **1** | Laravel 13 + Vue SPA + API + deploy Coolify + PWA | **Concluída** |
| **2** | Métricas, analytics, semântica de corrida | **Em andamento** |
| **3** | Demo manual, setups, risco, backtests, IA | **Parcial** — demo manual ✓ |
| — | Auth Sanctum | Futuro |

## Stack

| Camada | Tecnologia |
|--------|------------|
| App | **Laravel 13** monólito + **Vue 3** SPA (`resources/js/`) |
| UI | Tailwind CSS v4 + **shadcn-vue** |
| Collector | Node.js 24 + Playwright (`collector/`) |
| Banco | MySQL 8.4 (dev: infra local em :3306) |
| Deploy | **Coolify** — Docker Compose (`docker-compose.yml`) |

## Desenvolvimento local

### Pré-requisitos

- PHP 8.3+, Composer, Node 24
- MySQL e Redis da infra local (`infra_mysql` :3306, `infra_redis` :6379)
- Banco: `CREATE DATABASE speedanalytics` (user `dev` / pass `dev`)

### Laravel + Vue

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate

export NVM_DIR="$HOME/.nvm" && . "$NVM_DIR/nvm.sh" && nvm use 24
npm install
npm run dev          # terminal 1 — Vite HMR
php artisan serve --port=9001   # terminal 2 — http://speedanalytics.test
php artisan queue:work redis    # terminal 3 — processa payloads
php artisan schedule:work         # terminal 4 — reconcile + liquidação demo (1/min)
```

### Páginas da SPA

| Rota | Descrição |
|------|-----------|
| `/` | Dashboard — status do collector |
| `/races` | Histórico de corridas |
| `/analytics` | Resumo estatístico e bandas de odd |
| `/glossario` | Conceitos e fórmulas das métricas |
| `/demo/manual` | Demo manual — entradas fictícias, banca e diário |

### Collector

```bash
cd collector
cp .env.example .env   # configure SPEEDWAY_COLLECTOR_TOKEN igual ao .env da raiz
npm run collect
npm run status
```

Ver [collector/README.md](collector/README.md).

## Estrutura

```txt
├── app/
│   ├── Http/Controllers/Api/   # API REST
│   ├── Services/
│   │   ├── Demo/               # Conta demo, operações, quick entries
│   │   └── Speedway/           # Métricas por corrida
│   └── Services/MarketOddEstimatorService.php
├── resources/js/               # Vue 3 SPA + shadcn-vue
├── routes/api.php              # /api/*
├── routes/web.php              # SPA catch-all
├── collector/                  # Coleta BB Tips (Fase 0)
└── components.json             # shadcn-vue
```

## Analytics (Fase 2)

### Endpoints

- `GET /api/analytics/summary`
  - filtros opcionais: `date_from`, `date_to`, `hour_from`, `hour_to`, `only_validated`
- `GET /api/analytics/favorite-odds-bands`
  - análise por faixa de `favorite_odd`
- `GET /api/analytics/underdog-odds-bands`
  - análise por faixa de `underdog_odd` (odds altas)

### Definições importantes

- **Favorito**: piloto com menor odd pré-corrida
- **Zebra**: piloto com maior odd pré-corrida
- **Não favorito** não significa zebra (há rank 2 e rank 3)
- `winner_was_favorite = winner_position === favorite_position`
- `winner_was_underdog = winner_position === underdog_position`
- `winner_odd_rank`: rank do vencedor ao ordenar odds da menor para maior

### Forecast e Tricast do sistema

- **Ordem teórica de mercado** (por odds pré-corrida):
  - `market_rank_forecast_order` — 1º e 2º menores odds
  - `market_rank_tricast_order` — 1º, 2º e 3º menores odds
- **Resultado real** (após `settled`, do `raw_result_payload`):
  - `result_forecast_order`, `result_forecast_odd`, `result_tricast_order`
- `forecast_hit`: `market_rank_forecast_order` === `result_forecast_order`
- `tricast_exact_hit`: `market_rank_tricast_order` === `result_tricast_order`
- `tricast_winner_hit`: 1º do tricast teórico bate com o vencedor
- Campos legados `prediction` / `tricast_prediction` **não** entram no cálculo de hit

### Comandos artisan

```bash
php artisan speedway:recalculate-metrics      # recalcula métricas base
php artisan speedway:backfill-race-ranks        # preenche rank_* e ordens de mercado/resultado
php artisan speedway:reconcile-pending-races    # marca pending obsoletas (também no scheduler 1/min)
```

### Percentuais na API

- Os endpoints de analytics retornam percentuais em **percentage points** (ex.: `39.11` = `39,11%`)
- `house_margin` é salvo em decimal no banco (`0.05`) e exposto em percentual na API (`5.00`)

## Demo manual (MVP 3 parcial)

Simulador de entradas fictícias com banca demo e diário operacional.

**Inclui:** UI `/demo/manual`, API `/api/demo/*`, seleção de corridas pending, atalhos de entrada, odd estimada editável, liquidação automática, curva da banca.

**Não inclui:** Strategy Engine, tickets compostos, captura automática de odds da casa.

### Fluxo

1. Selecionar corrida **pending** (opcional) ou criar operação avulsa
2. Usar atalho (favorito, zebra, forecast/tricast sugerido) ou preencher manualmente
3. Stake debita a banca (`operation_stake`)
4. Operação fica `open` até liquidação **automática** (corrida `settled`) ou **manual** (green/red/void)
5. Nota e tags podem ir ao diário (`journal_entries`)

### Endpoints

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/demo/account` | Conta demo padrão e saldo |
| GET | `/api/demo/account/bankroll-curve` | Série temporal da banca |
| POST | `/api/demo/account/adjust-bankroll` | Ajuste manual (`amount`, `description`) |
| GET | `/api/demo/pending-races?limit=12` | Corridas pending + `quick_entries` |
| GET | `/api/demo/operations?status=open\|settled` | Listar operações manuais |
| POST | `/api/demo/operations` | Criar operação manual |
| POST | `/api/demo/operations/{id}/settle` | Liquidar manual (`result`: `win` \| `loss` \| `void`; backend calcula valores) |
| POST | `/api/demo/operations/{id}/journal` | Entrada de diário avulsa |

### Regras de entrada (MVP)

| Mercado | `bet_type` | Odd | `pricing_status` |
|---------|------------|-----|------------------|
| Winner | `single` | Obrigatória (pré-corrida) | `observed` |
| Forecast | `single` | Estimada ou manual | `estimated` / `manual` / `unavailable` |
| Tricast | `single` | Estimada ou manual | `estimated` / `manual` / `unavailable` |

Odd estimada (heurística provisória, `config/speedway.php`):

```txt
forecast ≈ odd₁ × odd₂ × 0.65
tricast  ≈ odd₁ × odd₂ × odd₃ × 0.35
```

O usuário pode sobrescrever com a odd real observada na casa → `pricing_status: manual`.

### `entry_payload_json` (exemplo forecast manual)

```json
{
  "order": "4-1",
  "pricing_status": "manual",
  "estimated_entry_odd": 5.40,
  "selected_quick_entry_label": "Forecast 4-1"
}
```

### Campos da operação

- `market_type`: `winner` | `forecast` | `tricast`
- `speedway_race_id` (opcional), `context_snapshot_json`, `stake_amount`, `entry_odd`
- `risk_enforced`, `after_stop`, `tags`, `note` (diário)

Conta seed: slug `manual-default`, saldo inicial **100u**.

## Variáveis `.env` (app)

Referência completa para produção: [`.env.coolify.example`](.env.coolify.example).

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=speedanalytics
DB_USERNAME=dev
DB_PASSWORD=dev
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SPEEDWAY_COLLECTOR_TOKEN=       # POST /api/collector/speedway
# COLLECTOR_STATUS_PATH=        # default: collector/storage/collector-status.json

# Timing / corridas (defaults em config/speedway.php)
# SPEEDWAY_TIMEZONE=America/Sao_Paulo
# SPEEDWAY_RACE_SCHEDULE_OFFSET_HOURS=4      # grade BB Tips = BR + 4h
# SPEEDWAY_PENDING_STALE_BUFFER_MINUTES=8
# SPEEDWAY_PENDING_EXTERNAL_ID_LAG=80
# SPEEDWAY_RACE_LIVE_WINDOW_MINUTES=4
# SPEEDWAY_COLLECTOR_PAYLOAD_STALE_SECONDS=120
# SPEEDWAY_RECONCILE_LOOKBACK_HOURS=24
# SPEEDWAY_RECONCILE_SCAN_LIMIT=200
```

### Variáveis collector (Coolify / Docker)

```env
HEALTH_CHECK_INTERVAL_MS=30000
SPEEDWAY_COLLECTOR_INTERVAL_MS=30000
PAYLOAD_STALE_THRESHOLD_MS=120000   # status stale ~2 min sem payload novo
RELOAD_THRESHOLD_MS=300000          # reload da página após ~5 min stale
# STALE_THRESHOLD_MS=120000         # legado; preferir PAYLOAD_STALE_THRESHOLD_MS
```

## Produção

**URL:** [https://speedanalytics.raphai.eu](https://speedanalytics.raphai.eu)

Stack no **Coolify** (Oracle VPS): `web`, `queue`, **`scheduler`**, `collector`, `mysql`, `redis` — coleta 24/7, API, dashboard, histórico de corridas, reconciliação de pending e **PWA instalável** no celular.

> O serviço **`scheduler`** (`php artisan schedule:work`) é obrigatório: roda `speedway:reconcile-pending-races` e catch-up de liquidação demo a cada minuto.

## Docker / Coolify (deploy)

Stack completa para **Coolify**: Laravel (nginx + PHP-FPM), MySQL, Redis, queue worker, **scheduler** e collector Playwright.

### 1. Criar resource no Coolify

1. **Project → Add Resource → Docker Compose** (repositório Git)
2. Compose file: `docker-compose.yml` (raiz)
3. Copie variáveis de [`.env.coolify.example`](.env.coolify.example) para **Environment Variables** (Laravel + collector — ver comentários no arquivo)
4. Gere `APP_KEY`: `php artisan key:generate --show`
5. **Domínio:** atribua `https://speedanalytics.raphai.eu` ao serviço **`web`** (porta 80 do container)
6. **Pós-deploy** (serviço `web`, ou comando one-shot): `php artisan migrate --force`
7. Confirme que o serviço **`scheduler`** está **running** (além de `queue` e `collector`)

> Não publique portas no compose — o Traefik/Caddy do Coolify roteia pelo domínio. Bind mounts (`.:/var/www`) não funcionam no Coolify; o código vai embutido na imagem `docker/app/Dockerfile`.

### 2. Sessão BB Tips (collector)

1. No PC: `cd collector && npm run login`
2. Copie para o volume compartilhado (via container `web`, mais fácil):

```bash
docker cp ~/bbtips-storage-state.json \
  web-<coolify-id>:/var/www/collector/storage/bbtips-storage-state.json
docker restart collector-<coolify-id>
```

3. Ou envie para `/app/storage/` do container **collector** (Terminal Coolify)

Quando a sessão expirar (`needs_login`), repita o processo.

### Dev local

Com `infra_mysql` / `infra_redis` em `:3306` / `:6379`: não use este compose. Prefira `php artisan serve --port=9001` + collector local ([collector/README.md](collector/README.md)).

## PWA

- `vite-plugin-pwa` — manifest, service worker e cache offline do shell da SPA
- Banner **Instalar** no app quando o browser suporta (`beforeinstallprompt`)
- Ícones: `public/pwa-source.svg` → `npm run pwa:assets` para regenerar PNGs

Auth **Sanctum** fica para fase futura (quando houver login de usuários).

## Próximos passos

- Strategy Engine e setups
- Gestão de risco (`RiskSession`, stops, `after_stop` automático)
- Tickets compostos e calibração de odds estimadas com dados reais
- Backtests, IA explicativa, relatório diário

Ver [PRD.md](PRD.md) e [CHANGELOG.md](CHANGELOG.md).
