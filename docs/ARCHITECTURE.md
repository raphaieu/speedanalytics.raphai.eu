# Arquitetura — Speedway Analytics

Última atualização: 2026-06-17

## Visão geral

**Uma aplicação Laravel monólito** com Vue 3 SPA embutido em `resources/js`. O collector permanece um serviço Node separado (`collector/`).

```txt
                    ┌──────────────────────────────────────────┐
                    │  speedanalytics.raphai.eu                │
                    │  docker-compose.yml                      │
                    │                                          │
  BB Tips ◄──►      │  ┌────────┐      ┌─────────────────────┐ │
  Playwright        │  │ nginx  │ ───► │ Laravel 13 (único)  │ │
  Collector ────────┼─►│        │      │  /api/*  → API JSON │ │
  POST /api/...     │  └────────┘      │  /*      → Vue SPA  │ │
                    │                  │  PostgreSQL + Redis │ │
                    │                  └─────────────────────┘ │
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
| App principal | **Laravel 13** monólito (PHP 8.3+) |
| Frontend | **Vue 3** SPA em `resources/js/` |
| Build | **Vite** (integrado ao Laravel) + `vite-plugin-pwa` |
| Estilo | **Tailwind CSS** |
| Componentes UI | **shadcn-vue** (adotar progressivamente) |
| Auth SPA | Laravel **Sanctum** |
| Collector | **Node.js 24** + Playwright (`collector/`) |
| Banco | PostgreSQL 16+ |
| Fila / cache | Redis 7+ |
| Gráficos | ECharts ou Recharts (Fase 3) |
| Deploy | VPS — `docker-compose`, um subdomínio |

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
GET  /api/collector/status   →  CollectorController@status
POST /api/collector/speedway →  CollectorController@store  (token)
GET  /api/races              →  RaceController@index
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
- [x] `GET /api/collector/status`, `GET /api/races`, `POST /api/collector/speedway` (stub)
- [x] Dashboard Vue lendo `collector-status.json`

### A construir

| # | Entrega | Prioridade |
|---|---------|------------|
| 1 | Migrations: `speedway_payloads`, `speedway_races`, `collector_statuses`, `collector_runs` | Alta |
| 2 | `ProcessSpeedwayPayloadJob` — portar `collector/lib/parse-races.js` | Alta |
| 3 | Collector: POST ao backend após salvar local | Alta |
| 4 | `docker-compose.yml` — nginx, app, postgres, redis, queue | Alta |
| 5 | `vite-plugin-pwa` — PWA install prompt | Média |
| 6 | `php artisan speedway:import-payloads` | Média |
| 7 | Auth Sanctum (quando necessário) | Baixa |

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
│   └── nginx/default.conf
├── docker-compose.yml
├── vite.config.js
├── package.json               # Vue, Tailwind, shadcn-vue deps
├── composer.json
├── CHANGELOG.md
└── PRD.md
```

---

## Deploy VPS — docker-compose

```yaml
# Serviços previstos (resumo)
services:
  nginx:        # :443 → app:9000 (PHP-FPM) ou unix socket
  app:          # Laravel + assets buildados (public/build)
  queue:        # php artisan queue:work
  scheduler:    # php artisan schedule:work (opcional)
  postgres:
  redis:
  collector:    # Node 24 + Playwright (ou systemd no host)
```

```txt
https://speedanalytics.raphai.eu/          → Laravel → Vue SPA
https://speedanalytics.raphai.eu/api/...   → Laravel → JSON
```

Build de produção: `npm run build` + `php artisan config:cache` na imagem ou CI.

O collector pode rodar no compose ou via **systemd** no host (login manual com display).

---

## Referências

- [PRD.md](../PRD.md) — requisitos completos
- [CHANGELOG.md](../CHANGELOG.md) — histórico de mudanças
- [collector/docs/VALIDATION.md](../collector/docs/VALIDATION.md) — evidência Fase 0
- [collector/README.md](../collector/README.md) — operação do collector
- [shadcn-vue.com](https://www.shadcn-vue.com/) — componentes UI
