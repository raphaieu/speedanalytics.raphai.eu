# Speedway Analytics

Plataforma mobile-first de inteligência operacional para o jogo virtual Speedway — coleta 24/7, histórico de corridas, setups, demo, backtests e IA explicativa.

Documento de produto: [PRD.md](PRD.md) · Arquitetura: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) · Changelog: [CHANGELOG.md](CHANGELOG.md)

## Status do projeto

| Fase | Escopo | Status |
|------|--------|--------|
| **0** | Playwright Collector | Concluída |
| **1** | Laravel 13 + Vue SPA + API collector + deploy Coolify | Em andamento |
| **2+** | Métricas, PWA, setups, demo, IA | Futuro |

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

## Docker / Coolify (produção)

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
2. Envie `collector/storage/bbtips-storage-state.json` para o volume persistente do collector (`/app/storage/` — use o Terminal do Coolify ou SCP no host)
3. Reinicie o serviço **collector**

Quando a sessão expirar (`needs_login`), repita o processo.

### Dev local

Com `infra_mysql` / `infra_redis` em `:3306` / `:6379`: não use este compose. Prefira `php artisan serve --port=9001` + collector local ([collector/README.md](collector/README.md)).

## Próximos passos (Fase 1)

1. ~~Migrations + `ProcessSpeedwayPayloadJob`~~ ✓
2. ~~Collector → POST payloads ao Laravel~~ ✓
3. ~~`docker-compose.yml` (Coolify: web, queue, collector, mysql, redis)~~ ✓
4. ~~`php artisan speedway:import-payloads`~~ ✓
5. Deploy inicial no Coolify + sessão BB Tips no collector
6. `vite-plugin-pwa` — PWA install prompt
