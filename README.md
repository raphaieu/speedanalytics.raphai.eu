# Speedway Analytics

Plataforma mobile-first de inteligência operacional para o jogo virtual Speedway — coleta 24/7, histórico de corridas, setups, demo, backtests e IA explicativa.

Documento de produto: [PRD.md](PRD.md) · Arquitetura: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) · Changelog: [CHANGELOG.md](CHANGELOG.md)

## Status do projeto

| Fase | Escopo | Status |
|------|--------|--------|
| **0** | Playwright Collector | Concluída |
| **1** | Laravel 13 + Vue SPA + API + deploy Coolify + PWA | **Concluída** |
| **2+** | Métricas, setups, demo, IA, auth Sanctum | Futuro |

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
```

- Dashboard: `/` — lê status do collector via `GET /api/collector/status`
- Corridas: `/races` — histórico via `GET /api/races`
- Análises: `/analytics` — resumo e bandas de favorito/zebra
- Glossário: `/glossario` — conceitos e fórmulas das métricas

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
├── app/Http/Controllers/Api/   # API REST
├── resources/js/               # Vue 3 SPA + shadcn-vue
├── routes/api.php              # /api/*
├── routes/web.php              # SPA catch-all
├── collector/                  # Coleta BB Tips (Fase 0)
└── components.json             # shadcn-vue
```

## Analytics (Fase 2 em andamento)

### Páginas

- `/analytics` — cards de resumo + tabelas por faixa de odd
  - Favorito por faixa de odd
  - Zebra por faixa de odd
- `/glossario` — documentação funcional dos termos e fórmulas

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

- Forecast previsto: 1º e 2º menores odds
- Tricast previsto: 1º, 2º e 3º menores odds
- `forecast_hit`: acerto exato de 1º+2º
- `tricast_exact_hit`: acerto exato de 1º+2º+3º quando houver ordem real completa
- `tricast_winner_hit`: apenas o 1º do tricast bate com o vencedor

### Percentuais na API

- Os endpoints de analytics retornam percentuais em **percentage points** (ex.: `39.11` = `39,11%`)
- `house_margin` é salvo em decimal no banco (`0.05`) e exposto em percentual na API (`5.00`)

## Variáveis `.env` (app)

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
```

## Produção

**URL:** [https://speedanalytics.raphai.eu](https://speedanalytics.raphai.eu)

Stack no **Coolify** (Oracle VPS): `web`, `queue`, `collector`, `mysql`, `redis` — coleta 24/7, API, dashboard, histórico de corridas e **PWA instalável** no celular.

## Docker / Coolify (deploy)

Stack completa para **Coolify**: Laravel (nginx + PHP-FPM), MySQL, Redis, queue worker e collector Playwright.

### 1. Criar resource no Coolify

1. **Project → Add Resource → Docker Compose** (repositório Git)
2. Compose file: `docker-compose.yml` (raiz)
3. Copie variáveis de [`.env.coolify.example`](.env.coolify.example) para **Environment Variables**
4. Gere `APP_KEY`: `php artisan key:generate --show`
5. **Domínio:** atribua `https://speedanalytics.raphai.eu` ao serviço **`web`** (porta 80 do container)
6. **Pós-deploy** (serviço `web`, ou comando one-shot): `php artisan migrate --force`

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

## Próximos passos (Fase 2)

Métricas, setups, demo, backtests e IA — ver [PRD.md](PRD.md).
