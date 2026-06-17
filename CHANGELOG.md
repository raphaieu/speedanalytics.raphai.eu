# Changelog

Todas as mudanças relevantes do **Speedway Analytics** são documentadas neste arquivo.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o versionamento segue [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Unreleased]

### Planejado — Fase 2+

- Métricas, setups, demo, backtests, IA explicativa
- Auth Sanctum (quando houver login de usuários)

---

## [1.0.0] — 2026-06-18 — Fase 1 concluída

MVP fundação em produção: coleta 24/7, backend, histórico, deploy Coolify e PWA.

### Adicionado — PWA

- `vite-plugin-pwa` — manifest, service worker (`autoUpdate`), cache offline do shell
- `usePwaInstall` + `PwaInstallBanner` — prompt de instalação no celular
- Ícones PWA (`public/pwa-*.png`, `maskable-icon`, `apple-touch-icon`, `favicon.ico`)
- `npm run pwa:assets` — regenerar ícones a partir de `public/pwa-source.svg`
- Nginx: header `Service-Worker-Allowed` para `/build/sw.js`

---

## [0.3.0] — 2026-06-18 — Fase 1 em produção

Deploy validado no **Coolify** ([speedanalytics.raphai.eu](https://speedanalytics.raphai.eu)): coleta 24/7, ingestão de payloads, fila, dashboard e histórico de corridas.

### Adicionado — Deploy produção (Coolify)

- `docker-compose.yml` — stack completa: `web`, `queue`, `collector`, `mysql`, `redis`
- `docker/app/Dockerfile` — imagem multi-stage (Vite + Composer + PHP 8.4-FPM + nginx/supervisor)
- `docker/collector/Dockerfile` — Node 24 + Playwright Chromium
- `docker/nginx/coolify.conf`, `docker/supervisor/supervisord.conf`, `docker/app/entrypoint.sh`
- `.env.coolify.example`, `.dockerignore`
- `bootstrap/app.php` — `trustProxies(at: '*')` para proxy do Coolify

### Adicionado — Fase 1 (persistência)

- Migrations: `speedway_payloads`, `speedway_races`, `collector_statuses`, `collector_runs`
- Models `SpeedwayPayload`, `SpeedwayRace`
- `SpeedwayParserService` — port de `collector/lib/parse-races.js`
- `ProcessSpeedwayPayloadJob` — upsert pending/settled por `external_id`
- `POST /api/collector/speedway` — valida, persiste e enfileira job
- `GET /api/races` — paginação do histórico no MySQL
- Collector: POST ao backend após salvar local (`SPEEDWAY_COLLECTOR_ENDPOINT`)
- Página Vue Corridas com tabela real
- `php artisan speedway:import-payloads` — importar JSONs locais
- Stack MySQL 8.4 + Redis 7

### Corrigido — Deploy Coolify

- Container `web` unhealthy — nginx Debian servia `sites-enabled/default` em vez do Laravel (`/up` retornava 404)
- Permissões de `storage/` ao montar volumes nomeados no Coolify

### Alterado — Deploy

- Compose orientado a Coolify: sem bind mounts, sem `ports` publicados, código embutido na imagem
- Serviço público **`web`** (nginx + PHP-FPM em container único)
- Collector → `http://web/api/collector/speedway` na rede interna

### Documentação

- README, ARCHITECTURE, collector — deploy Coolify e status de produção
- Checklist de validação em produção atualizado

---

## [0.2.0] — 2026-06-17 — Scaffold Laravel 13 + Vue SPA

### Adicionado

- **Laravel 13.8** na raiz do monorepo (`composer.json`, `artisan`, migrations base)
- **Vue 3 SPA** em `resources/js/` — Vue Router, TypeScript, `app.blade.php` + catch-all `web.php`
- **Tailwind CSS v4** + **shadcn-vue** (preset `a2kwXzud`, estilo reka-lyra, JetBrains Mono, Phosphor icons)
- Componentes UI: `button`, `card`, `badge`, `separator`
- API stubs: `GET /api/collector/status`, `GET /api/races`, `POST /api/collector/speedway` (token)
- Páginas Vue: Dashboard (status do collector) e Corridas (placeholder)
- `config/speedway.php` — token e caminho do `collector-status.json`

### Documentação

- Arquitetura confirmada: Laravel monólito + Vue em `resources/js/` (não pasta `frontend/` separada)


## [0.1.0] — 2026-06-16 — Fase 0 concluída

Primeira entrega funcional: coleta passiva 24/7 via Playwright.

### Adicionado

- **`collector/`** — pacote Node 24 + Playwright
  - `login.js` — login manual headed, gera `storageState`
  - `collector.js` — coleta contínua, health check, reload automático
  - `status.js` — resumo legível do estado da coleta
  - `lib/intercept.js` — interceptação de responses `/api/speedway`
  - `lib/parse-races.js` — parser `Linhas/Colunas`, ciclo pending → settled
  - `lib/speedway-ui.js` — aplica filtros UI (`Odd_Todas`, `Horas48`, futuro)
  - `lib/config.js` — filtros de URL e configuração via `.env`
  - `lib/session-status.js` — detecção de sessão (valid, needs_login, cloudflare, stale)
- Scripts npm: `login`, `collect`, `status`, `validate`, `validate:parser`, `validate:url`, `validate:collect`, `validate:smoke`
- Persistência local: `storage/payloads/`, `races-index.json`, `collector-status.json`, `collector.log`

### Corrigido

- Página `/speedway/horarios` carregava filtros padrão (`Podio`/`Horas12`) — UI automatizada em `speedway-ui.js`
- Payloads ignorados durante aplicação de filtros (`filtersReady=false`) — captura após `filtersReady=true` + `waitForResponse`
- Filtro de URL incompleto — `isCaptureTargetUrl` valida `filtroExibicao` + `horas` + `futuro`
- Health check não recarregava com zero payloads — reload após `staleThresholdMs` mesmo sem capturas

### Validado

- Coleta ao vivo: snapshot inicial ~964 corridas, updates incrementais via `dadosAlteracao`
- Transições `pending → settled` em produção (ex.: IDs `909904`, `909905`)
- Testes automatizados: parser, smoke, url-filter, E2E `validate:collect`
- Evidência documentada em `collector/docs/VALIDATION.md`

### Documentação (histórico)

- Criados `README.md`, `docs/ARCHITECTURE.md`, `CHANGELOG.md`
- Decisões intermediárias (Opção C com `frontend/` separado) — **substituídas** por monólito Laravel; ver `[0.2.0]`

---

## Convenções de versão

| Versão | Significado |
|--------|-------------|
| `0.x.y` | Pré-MVP / fases do PRD (0 = collector, 1 = Laravel+SPA, 2 = métricas…) |
| `1.0.0` | MVP 1 completo (coleta 24/7 + backend + histórico básico + PWA status) |

### Categorias usadas

- **Adicionado** — funcionalidades novas
- **Alterado** — mudanças em funcionalidades existentes
- **Corrigido** — correções de bugs
- **Removido** — funcionalidades removidas
- **Documentação** — apenas docs, sem mudança de código
- **Planejado** — itens em `Unreleased` ainda não implementados
