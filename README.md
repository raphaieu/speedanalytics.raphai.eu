# Speedway Analytics

Plataforma mobile-first de inteligência operacional para o jogo virtual Speedway — coleta 24/7, histórico de corridas, setups, demo, backtests e IA explicativa.

Documento de produto: [PRD.md](PRD.md) · Arquitetura: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) · Changelog: [CHANGELOG.md](CHANGELOG.md)

## Status do projeto

| Fase | Escopo | Status |
|------|--------|--------|
| **0** | Playwright Collector | Concluída |
| **1** | Laravel 13 + Vue SPA + API collector | Em andamento (scaffold ✓) |
| **2+** | Métricas, PWA, setups, demo, IA | Futuro |

## Stack

| Camada | Tecnologia |
|--------|------------|
| App | **Laravel 13** monólito + **Vue 3** SPA (`resources/js/`) |
| UI | Tailwind CSS v4 + **shadcn-vue** |
| Collector | Node.js 24 + Playwright (`collector/`) |
| Banco | SQLite (dev) / PostgreSQL (produção planejada) |

## Desenvolvimento local

### Laravel + Vue

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate

export NVM_DIR="$HOME/.nvm" && . "$NVM_DIR/nvm.sh" && nvm use 24
npm install
npm run dev          # terminal 1 — Vite HMR
php artisan serve    # terminal 2 — http://127.0.0.1:8000
```

- Dashboard: `/` — lê status do collector via `GET /api/collector/status`
- Corridas: `/races` — placeholder `GET /api/races`

### Collector

```bash
cd collector
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
SPEEDWAY_COLLECTOR_TOKEN=       # POST /api/collector/speedway
# COLLECTOR_STATUS_PATH=        # default: collector/storage/collector-status.json
```

## Próximos passos (Fase 1)

1. Migrations + `ProcessSpeedwayPayloadJob`
2. Collector → POST payloads ao Laravel
3. `docker-compose.yml` para VPS
