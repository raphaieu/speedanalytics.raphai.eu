# Arquitetura — Speedway Analytics

Última atualização: 2026-06-17

## Visão geral

**Uma aplicação Laravel monólito** com Vue 3 SPA embutido em `resources/js`. O collector permanece um serviço Node separado (`collector/`).

```txt
                    ┌──────────────────────────────────────────┐
                    │  Coolify (Traefik/Caddy + SSL)           │
                    │  speedanalytics.raphai.eu → serviço web  │
                    └────────────────────┬─────────────────────┘
                                         │
                    ┌────────────────────▼─────────────────────┐
                    │  docker-compose.yml                      │
                    │                                          │
  BB Tips ◄──►      │  ┌────────┐  ┌───────┐  ┌─────────────┐ │
  Playwright        │  │  web   │  │ queue │  │  collector  │ │
  Collector ────────┼─►│ nginx  │  │ worker│  │  (headless) │ │
  POST /api/...     │  │ + PHP  │  └───┬───┘  └──────┬──────┘ │
                    │  └────┬───┘      │             │        │
                    │       │     ┌────▼────┐  ┌─────▼─────┐  │
                    │       └────►│  mysql  │  │   redis   │  │
                    │             └─────────┘  └───────────┘  │
                    └──────────────────────────────────────────┘

collector/  (Node 24 + Playwright) — serviço irmão, não dentro do PHP
```

- **`routes/api.php`** — JSON para o collector e para o Vue (`/api/...`)
- **`routes/web.php`** — catch-all entrega `app.blade.php` (shell da SPA)
- **`resources/js/`** — Vue 3 + Vue Router + Tailwind + PWA
- Mesma origem → **Sanctum** para auth SPA, sem CORS

---

## Stack fixada

| Camada | Tecnologia |
|--------|------------|
| App principal | **Laravel 13** monólito (PHP 8.4+ em produção) |
| Frontend | **Vue 3** SPA em `resources/js/` |
| Build | **Vite** + `vite-plugin-pwa` ✓ |
| Estilo | **Tailwind CSS** |
| Componentes UI | **shadcn-vue** (adotar progressivamente) |
| Auth SPA | Laravel **Sanctum** |
| Collector | **Node.js 24** + Playwright (`collector/`) |
| Banco | MySQL 8.4 |
| Fila / cache | Redis 7+ |
| Gráficos | ECharts ou Recharts (Fase 3) |
| Deploy | **Coolify** — Docker Compose, um subdomínio (`speedanalytics.raphai.eu`) |

---

## Frontend — decisão: Laravel monólito + Vue SPA ✓

**Padrão adotado:** uma única aplicação Laravel; Vue vive em `resources/js`, não em pasta `frontend/` separada.

### Por que este modelo

- Um `composer.json`, um deploy, um container PHP — **menos camadas**
- API REST explícita (`/api`) para collector **e** para o Vue
- PWA via Vite do próprio Laravel (`vite-plugin-pwa`)
- Tailwind nativo no ecossistema Laravel
- **shadcn-vue** — componentes copiáveis (Button, Card, Table, Sheet…) sem dependência de pacote monolítico; ideal para dashboard mobile

### Estrutura `resources/js/`

```txt
resources/
├── views/
│   └── app.blade.php          # <div id="app"> + @vite
└── js/
    ├── app.ts                 # createApp, plugins
    ├── App.vue
    ├── router/
    │   └── index.ts
    ├── views/                 # Dashboard, Corridas, Collector…
    ├── components/
    │   └── ui/                # shadcn-vue (Button, Card, Dialog…)
    ├── composables/           # useApi, useCollectorStatus…
    ├── lib/
    │   └── utils.ts           # cn() — helper shadcn-vue
    └── assets/
        └── css/
            └── app.css        # Tailwind directives
```

### Rotas

```txt
GET  /api/collector/status   →  CollectorStatusController@show
POST /api/collector/speedway →  CollectorIngestController@store  (token)
GET  /api/races              →  RaceController@index
GET  /api/analytics/summary
GET  /api/analytics/favorite-odds-bands
GET  /api/analytics/underdog-odds-bands
…

GET  /*                      →  view('app')   # Vue Router no client
```

### Dev local

```bash
# Terminal 1 — Laravel
php artisan serve

# Terminal 2 — Vite (HMR)
npm run dev

# Terminal 3 — Collector (opcional)
cd collector && npm run collect
```

### UI — Tailwind + shadcn-vue

| Ferramenta | Uso |
|------------|-----|
| **Tailwind CSS** | Layout, spacing, responsivo mobile-first |
| **shadcn-vue** | Componentes acessíveis prontos; adicionar via CLI conforme necessidade |
| **lucide-vue-next** | Ícones (padrão shadcn-vue) |

shadcn-vue não é instalado de uma vez — componentes são copiados para `resources/js/components/ui/`. Na Fase 1: Tailwind + scaffold; shadcn-vue nos primeiros ecrãs (status collector, tabela de corridas).

Documentação: [shadcn-vue.com](https://www.shadcn-vue.com/)

---

## Opções descartadas (referência)

<details>
<summary>Pasta `frontend/` separada (Opção C anterior)</summary>

Dois projetos (`backend/` + `frontend/`) no monorepo. Descartado em favor do monólito Laravel com Vue em `resources/js`.

</details>

<details>
<summary>Laravel + Inertia.js</summary>

Monólito, mas acoplamento controller→página Inertia em vez de API REST + Vue Router. Menos adequado quando o collector e integrações futuras precisam de API JSON clara.

</details>

<details>
<summary>Blade + Livewire</summary>

Monólito server-driven. Descartado para dashboard interativo com gráficos e PWA estilo app.

</details>

<details>
<summary>Nuxt PWA</summary>

Runtime/deploy Node extra além do collector. Descartado.

</details>

---

## Fase 1 — escopo técnico

### Já pronto (Fase 0)

- [x] `collector/` — login, coleta, parser, índice local, health check
- [x] Validação ao vivo documentada

### Scaffold Laravel (2026-06-17)

- [x] Laravel 13 na raiz + Vue 3 SPA (`resources/js/`)
- [x] Tailwind v4 + shadcn-vue (`components.json`, Button/Card/Badge/Separator)
- [x] `GET /api/collector/status`, `GET /api/races`, `POST /api/collector/speedway`
- [x] Dashboard Vue + página Corridas com dados reais

### Produção (2026-06-18) ✓

- [x] Deploy **Coolify** em `https://speedanalytics.raphai.eu`
- [x] Stack: `web`, `queue`, `collector`, `mysql`, `redis`
- [x] Collector Playwright 24h + POST ao Laravel + queue worker
- [x] Sessão BB Tips via `bbtips-storage-state.json` no volume persistente
- [x] **PWA** — `vite-plugin-pwa`, install prompt, ícones, service worker

### Fase 2+ (próximo)

| # | Entrega | Prioridade |
|---|---------|------------|
| 1 | Métricas, favorito, zebra, spread | Alta |
| 2 | Setups e demo | Média |
| 3 | Auth Sanctum | Quando necessário |

### Analytics e métricas (incremental já em uso)

- Backend:
  - `RaceMetricsService` centraliza cálculo de métricas por corrida
  - comando `speedway:recalculate-metrics` para recálculo histórico em chunks
- Frontend:
  - `/analytics` com cards e bandas de favorito/zebra
  - `/glossario` com conceitos e fórmulas
- Métricas base persistidas em `speedway_races`:
  - `favorite_*`, `underdog_*`, `winner_was_favorite`, `winner_was_underdog`
  - `winner_odd_rank` (rank do vencedor por odd pré-corrida)
  - `odds_spread`, `house_margin`
  - `forecast_hit`, `tricast_winner_hit`, `tricast_exact_hit`

### Semântica de previsão

- Forecast e tricast do sistema são derivados de odds pré-corrida (ordem crescente)
- `forecast_hit` exige acerto exato da ordem 1º+2º
- `tricast_exact_hit` exige acerto exato da ordem 1º+2º+3º
- `tricast_winner_hit` acompanha apenas acerto do primeiro piloto previsto
- Nomenclatura de corrida:
  - zebra só quando o piloto de maior odd vence
  - “favorito não venceu” não implica zebra automaticamente

### Regras de processamento (PRD §7.10–7.11)

- Upsert por `external_id` (payloads incrementais ~7 corridas).
- Primeira captura `pending` → `raw_pending_payload` + `first_seen_at`.
- Transição `settled` → preencher resultado; **nunca** sobrescrever odds pré-corrida.
- Payload bruto sempre em `speedway_payloads`.

---

## Estrutura de repositório

```txt
speedanalytics.raphai.eu/
├── app/
│   ├── Http/Controllers/Api/
│   └── Jobs/ProcessSpeedwayPayloadJob.php
├── bootstrap/
├── config/
├── database/migrations/
├── resources/
│   ├── js/                    # Vue 3 SPA + shadcn-vue
│   └── views/app.blade.php
├── routes/
│   ├── api.php
│   └── web.php                # catch-all → SPA
├── public/                    # build Vite → public/build/
├── collector/                 # Node 24 — Fase 0 ✓
├── docker/
│   ├── app/Dockerfile         # web + worker (produção)
│   ├── collector/Dockerfile   # Playwright
│   ├── nginx/coolify.conf
│   └── supervisor/supervisord.conf
├── docker-compose.yml         # Coolify
├── .env.coolify.example
├── vite.config.js
├── package.json               # Vue, Tailwind, shadcn-vue deps
├── composer.json
├── CHANGELOG.md
└── PRD.md
```

---

## Deploy produção — Coolify

Stack definida em `docker-compose.yml`. O Coolify faz build a partir do Git, injeta variáveis de ambiente e roteia HTTPS para o serviço **`web`**.

| Serviço | Imagem / build | Papel |
|---------|----------------|-------|
| `web` | `docker/app/Dockerfile` → target `web` | nginx + PHP-FPM, Laravel + Vue (build Vite na imagem) |
| `queue` | `docker/app/Dockerfile` → target `worker` | `php artisan queue:work redis` |
| `collector` | `docker/collector/Dockerfile` | Playwright headless 24/7 |
| `mysql` | `mysql:8.4` | Banco persistente (`mysql_data`) |
| `redis` | `redis:7-alpine` | Fila e cache (`redis_data`) |

Volumes nomeados: `app_storage`, `collector_storage` (status + `storageState` BB Tips).

```txt
https://speedanalytics.raphai.eu/          → serviço web → Laravel → Vue SPA
https://speedanalytics.raphai.eu/api/...   → serviço web → Laravel → JSON
http://web/api/collector/speedway           → rede interna (collector → Laravel)
```

### Restrições Coolify

- **Sem bind mounts** de código (`.:/var/www`) — o repositório é efêmero no build; tudo entra na imagem
- **Sem `ports` publicados** — domínio atribuído ao serviço `web` na UI do Coolify
- Variáveis: `.env.coolify.example` → Environment Variables do resource
- Pós-deploy: `php artisan migrate --force` no serviço `web`
- Sessão BB Tips: `npm run login` no PC → copiar `bbtips-storage-state.json` para volume do collector

Guia operacional: [README.md](../README.md#docker--coolify-deploy).

**Status:** em produção desde 2026-06-18 — coleta, API e SPA validados em `speedanalytics.raphai.eu`.

---

## Referências

- [PRD.md](../PRD.md) — requisitos completos
- [CHANGELOG.md](../CHANGELOG.md) — histórico de mudanças
- [collector/docs/VALIDATION.md](../collector/docs/VALIDATION.md) — evidência Fase 0
- [collector/README.md](../collector/README.md) — operação do collector
- [shadcn-vue.com](https://www.shadcn-vue.com/) — componentes UI
