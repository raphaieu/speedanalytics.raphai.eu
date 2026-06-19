# Changelog

Todas as mudanças relevantes do **Speedway Analytics** são documentadas neste arquivo.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o versionamento segue [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Unreleased]

### Adicionado — Liquidação automática e contabilidade corrigida no demo manual

- **`SettleDemoOperationsJob`** — liquida operações `open` vinculadas a corridas `settled` (winner / forecast / tricast); idempotente com `lockForUpdate`
- Disparo automático em **`ProcessSpeedwayPayloadJob`** quando corrida transita `pending` → `settled`
- Fallback no **scheduler** (a cada minuto) para operações pendentes em corridas já encerradas
- **`settleOperationExplicitly`** calcula valores no backend — loss **não** debita stake novamente; win credita retorno bruto; void devolve stake
- Modal de liquidação envia apenas `result`; preview read-only na UI
- Campo **`settlement_mode`** (`auto` | `manual`) exposto na API via `context_snapshot_json.settlement`
- Testes: `DemoSettlementTest` (loss sem double-debit, win, void, forecast/tricast auto, idempotência)

### Adicionado — Curva da banca demo

- **`GET /api/demo/account/bankroll-curve`** — série temporal a partir de `bankroll_transactions` + saldo inicial
- Componente **`DemoBankrollCurve`** na página `/demo/manual` (SVG, delta vs saldo inicial)
- Polling silencioso (30s) enquanto houver operações abertas; banner quando corrida já `settled` aguarda liquidação auto
- Badges **Liquidação auto/manual** e resumo P/L acumulado nas operações resolvidas

### Adicionado — Demo manual e diário operacional (MVP 3 parcial)

Primeira fatia do simulador demo: operações manuais, banca fictícia e diário — **sem** Strategy Engine, automação, tickets compostos (`demo_operation_legs`) nem captura automática de odds forecast/tricast da casa.

- **Domínio e persistência**
  - Tabelas `demo_accounts`, `demo_operations`, `bankroll_transactions`, `journal_entries`
  - Seed da conta padrão `manual-default` (100u iniciais)
  - Models `DemoAccount`, `DemoOperation`, `BankrollTransaction`, `JournalEntry`
  - Enums: `DemoMarketType`, `DemoBetType`, `DemoOperationOrigin`, `DemoOperationStatus`, `DemoOperationResult`, `BankrollTransactionType`, `RuleCompliance`
- **Serviços**
  - `DemoAccountService` — conta manual padrão e ajuste de banca (`manual_adjustment`)
  - `DemoManualOperationService` — criar operação manual, listar, liquidar por corrida (`settleManualOperation`) ou liquidação explícita green/red/void (`settleOperationExplicitly`), journal vinculado
  - `MarketOddEstimatorService` — odd estimada heurística para forecast/tricast (produto das odds pré-corrida × multiplicador configurável)
  - `DemoQuickEntryBuilder` — atalhos `quick_entries` por corrida pending (favorito, zebra, forecast/tricast sugeridos)
  - Stake debita banca na abertura; liquidação credita retorno (green/void) via `bankroll_transactions`
  - Campo `after_stop` persistido (flag manual até existir `RiskSession`)
- **API** (`/api/demo/*`)
  - `GET /api/demo/account` — conta demo e saldo atual
  - `POST /api/demo/account/adjust-bankroll` — ajuste manual de banca
  - `GET /api/demo/pending-races` — próximas corridas `pending` com odds, ranks e `quick_entries`
  - `GET /api/demo/operations?status=open|settled` — listar operações manuais
  - `POST /api/demo/operations` — criar operação (journal opcional via `note`, `context_snapshot_json`)
  - `POST /api/demo/operations/{id}/settle` — liquidar como `win` | `loss` | `void`
  - `POST /api/demo/operations/{id}/journal` — entrada de diário avulsa
  - `DemoPresenter` e `SpeedwayRacePresenter::pendingForDemo()` para serialização JSON
- **UI** (`/demo/manual`)
  - Banca demo + ajuste manual
  - Carrossel de **próximas corridas pending** (odds P1–P4, rank 1, forecast/tricast teóricos)
  - Seleção de corrida → preenche `speedway_race_id` e `context_snapshot_json`
  - Painel **Corrida selecionada** com atalhos principais:
    - Winner favorito (rank 1, odd observada)
    - Winner zebra (rank 4, odd observada)
    - Forecast sugerido (`market_rank_forecast_order`, ex.: `Forecast 4-1`)
    - Tricast sugerido (`market_rank_tricast_order`, ex.: `Tricast 4-1-3`)
  - Seção **Outras ordens** com forecasts alternativos (quando diferentes do sugerido)
  - Formulário de nova operação, abas abertas/resolvidas, modal de liquidação green/red/void
  - Link **Demo** no header da SPA
  - `apiPost()` em `useApi.ts`; tipos em `resources/js/types/demo.ts`
- **Testes**
  - `DemoManualOperationFlowTest` — fluxo de serviço
  - `DemoManualApiTest` — contratos da API demo, pending races, `quick_entries`, `pricing_status`
  - `MarketOddEstimatorServiceTest` — estimativa forecast/tricast e casos inválidos

### Adicionado — Pricing de entrada no demo manual (`pricing_status`)

Decisão de produto: no MVP, Winner, Forecast e Tricast são operações **`single` lógicas** (não combo/ticket composto).

- **`pricing_status`** em `entry_payload_json`:
  - `observed` — winner com odd pré-corrida do piloto
  - `estimated` — forecast/tricast com odd heurística do `MarketOddEstimatorService`
  - `manual` — usuário sobrescreveu a odd (ex.: odd real vista na Bet365)
  - `unavailable` — sem odd informada (potencial não calculado; criação ainda permitida)
- Fórmulas heurísticas provisórias (`config/speedway.php` → `market_odd_estimation`):
  - Forecast: `odd₁ × odd₂ × 0.65`
  - Tricast: `odd₁ × odd₂ × odd₃ × 0.35`
- Winner **exige** `entry_odd` na API; forecast/tricast aceitam odd vazia
- Payload inclui `estimated_entry_odd` e `selected_quick_entry_label` quando aplicável
- UI exibe hint sob o campo de odd: Observada / Estimativa editável / Manual / Sem odd

### Alterado — Demo manual: UX de seleção e atalhos

- Forecast e tricast **não** mudam mais automaticamente para `bet_type=combo`
- Atalhos rápidos usam rótulos alinhados ao card da corrida (`Forecast 4-1`, não “rank 1-2”)
- Tipo de aposta fixo como **Single (MVP)** no formulário
- Zebra nos atalhos usa **rank 4** (maior odd pré-corrida), não `underdog_position` legado

### Adicionado — Ranking por odds e ordens de mercado em SpeedwayRace

- Colunas persistidas em `speedway_races`:
  - `rank_1_position` … `rank_4_position` e `rank_1_odd` … `rank_4_odd` (ordem crescente de odd pré-corrida)
  - `market_rank_forecast_order`, `market_rank_tricast_order` (previsão teórica por odds)
  - `result_forecast_order`, `result_forecast_odd`, `result_tricast_order` (extraídos de `raw_result_payload` após `settled`)
- `php artisan speedway:backfill-race-ranks` — preenche ranking e ordens em corridas históricas

### Adicionado — Analytics e métricas por corrida

- `RaceMetricsService` para cálculo centralizado de métricas base por corrida:
  - favorito, segundo favorito, zebra, spread, margem da casa
  - `winner_was_favorite`, `winner_was_underdog`, `winner_odd_rank`
  - ranking por odds (`rank_*`) e ordens teóricas de mercado
  - `forecast_hit` — acerto da ordem 1º+2º (`market_rank_forecast_order` vs `result_forecast_order`)
  - `tricast_winner_hit` e `tricast_exact_hit` (`market_rank_tricast_order` vs `result_tricast_order`)
- `php artisan speedway:recalculate-metrics` para recálculo em chunks de corridas históricas
- Endpoint `GET /api/analytics/summary` com filtros (`date_from/date_to`, `hour_from/hour_to`, `only_validated`) e bloco `metadata` de diagnóstico
- Endpoint `GET /api/analytics/favorite-odds-bands` com análise por faixa de odd do favorito (ROI, edge, P/L)
- Endpoint `GET /api/analytics/underdog-odds-bands` com análise por faixa de odd da zebra (odds altas)
- Página `/analytics` com:
  - cards de resumo
  - tabela de favorito por faixa
  - tabela de zebra por faixa
  - filtros globais e estados de loading/vazio
- Página `/glossario` com definições e fórmulas de métricas e termos de corrida

### Alterado — Semântica de forecast/tricast e zebra

- Forecast e tricast **do sistema** passam a ser derivados de odds pré-corrida (menor → maior), não da leitura literal de campos de previsão do provedor
- `forecast_hit` compara ordem teórica de mercado com `result_forecast_order` do payload de resultado — não usa mais `prediction` legado
- `tricast_exact_hit` compara `market_rank_tricast_order` com `result_tricast_order`
- `tricast_hit` (compatibilidade) representa acerto exato 1º+2º+3º (`tricast_exact_hit`)
- Nomenclatura de UI ajustada:
  - “zebra” só quando o piloto de maior odd vence
  - “favorito não venceu” quando não houve vitória do favorito sem caracterizar zebra
  - bloco “Como ler esta corrida” atualizado com definição explícita de zebra

### Documentação

- README, ARCHITECTURE, CHANGELOG e PRD atualizados com:
  - demo manual completo (`/demo/manual`, `/api/demo/*`)
  - seleção de corridas pending e `quick_entries`
  - `pricing_status`, `MarketOddEstimatorService` e regra single no MVP
  - colunas de ranking em `speedway_races`
- Glossário (`/glossario`) alinhado à semântica `market_rank_*` vs `result_*`

### Planejado — Fase 2+ (restante)

- Strategy Engine e setups
- Gestão de risco (`RiskSession`, stop loss/win, `after_stop` automático)
- Tickets compostos / `demo_operation_legs` e captura automática de odds da casa
- Calibração empírica dos multiplicadores de odd estimada
- Backtests, IA explicativa, relatório diário
- Auth Sanctum (quando houver login de usuários)
- Racional automático das operações

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
