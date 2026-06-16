# Validação Fase 0 — Speedway Collector

**Status: concluída** — 2026-06-16

Runtime validado: **Node.js 24** + Playwright Chromium headless.

---

## Validação automatizada

```bash
cd collector
nvm use 24    # ou equivalente
npm run validate
npm run validate:collect   # E2E com sessão real (opcional, requer login)
```

### Resultados

| Teste | Resultado |
|-------|-----------|
| `validate:parser` — ciclo pending → settled (PRD §7.10) | OK |
| `validate:smoke` — persistência de payloads + índice + status | OK |
| `validate:url` — filtro `Odd_Todas` + `Horas48` + `futuro=true` | OK |
| `validate:collect` — E2E com sessão real | OK (960+ corridas no snapshot inicial) |
| Detecção `cloudflare_challenge` em HTML | OK |
| `collector.js` sem storageState | Exit code 1 (esperado) |

### Evidência parser (fixture Id 909380)

```json
{
  "pending_count": 1,
  "settled_count": 1,
  "transitions": [
    {
      "external_id": "909380",
      "from": "pending",
      "to": "settled"
    }
  ]
}
```

### Evidência smoke (pipeline local)

- 2 payloads salvos em `storage/payloads/smoke-*.json`
- `storage/races-index.json` com corrida `909381` em `settled`
- `storage/collector-status.json` com status `valid`

---

## Validação manual — coleta ao vivo

Sessão de referência: **2026-06-16**, collector rodando em produção local (~15+ min).

### Comandos

```bash
cd collector
npm run login          # janela visível — login manual (uma vez)
npm run collect        # coleta contínua
# outro terminal:
npm run status
tail -f storage/collector.log
```

### Critérios de sucesso

- [x] `storage/bbtips-storage-state.json` criado após login
- [x] 3+ arquivos em `storage/payloads/` (não smoke)
- [x] `npm run status` mostra `last_payload_at` recente
- [x] Pelo menos 1 transição `pending → settled` no log ou `races-index.json`
- [x] Nenhum `cloudflare_challenge` persistente nos logs

### Evidência da sessão 2026-06-16

**Inicialização (21:35:10 UTC)**

```
Iniciando collector — filtro: Odd_Todas, horas: Horas48, futuro: true
Filtro de exibição aplicado → Janela de horas aplicada → Toggle futuro aplicado
Payload speedway capturado — race_count: 964, pending: 7, settled: 957
Payload inicial recebido após aplicar filtros
```

**Updates incrementais** (`dadosAlteracao=...`, ~7 corridas por payload):

| Arquivo | Horário (UTC) | race_count | Notas |
|---------|---------------|------------|-------|
| `2026-06-16T21-35-23-210Z.json` | 21:35:23 | 964 | Snapshot inicial completo |
| `2026-06-16T21-35-55-748Z.json` | 21:35:55 | 7 | Incremental + settled `909904` |
| `2026-06-16T21-39-25-670Z.json` | 21:39:25 | 7 | Incremental + settled `909905` |
| `2026-06-16T21-44-21-101Z.json` | 21:44:21 | 7 | Incremental (`dadosAlteracao=909906`) |

**Transições pending → settled detectadas:** `909904`, `909905`, `909906` (e subsequentes na mesma sessão).

**`npm run status` (após ~10 min de coleta)**

```
Status atual:        valid
Precisa login:       não
Último payload:      recente (< 1 min)
Corridas no índice:  965
Pending:             6
Settled:             959
Ciclos pending→settled completos: 3
```

### Comportamento esperado em produção

| Evento | Frequência |
|--------|------------|
| Snapshot inicial ao aplicar filtros | 1× por navegação/reload |
| Payload incremental (`dadosAlteracao`) | Quando o app detecta mudança via `ultimaAtualizacao` |
| Health check | A cada 60s |
| Reload automático se stale | Após 5 min sem payload novo |
| Renovação de sessão | Manual via `npm run login` quando `needs_login` |

---

## Deploy VPS (produção 24h)

Checklist para deixar coletando na VPS antes da Fase 1:

- [ ] Node.js **24** instalado (`node -v` → v24.x)
- [ ] `npx playwright install chromium` + dependências de sistema do Chromium
- [ ] `npm run login` com display (VNC/X11) — sessão não versionada
- [ ] Process manager: `systemd` ou `pm2` para `npm run collect`
- [ ] `.env` e `storage/` fora do git, permissões restritas
- [ ] Monitorar: cron ou alerta se status `stale` / `needs_login` por > 10 min
- [ ] Disco: payloads acumulam; planejar rotação ou envio ao backend (Fase 1)

---

## Bugs corrigidos durante a validação

| Problema | Causa | Correção |
|----------|-------|----------|
| `payload_count_session: 0` por minutos | API completa disparava durante `applySpeedwayView` com `filtersReady=false` | `filtersReady=true` antes dos filtros + `waitForResponse` |
| Página carregava `Podio`/`Horas12` por padrão | UI não aplicava filtros automaticamente | `lib/speedway-ui.js` |
| Estados intermediários capturados | Filtro de URL só checava `filtroExibicao` | `isCaptureTargetUrl` valida `horas` + `futuro` também |

---

## Próximo passo — Fase 1

Fase 0 **concluída**. Seguir para:

1. Collector 24h na VPS (feedback de volume e estabilidade).
2. Backend **Laravel 13** (API REST) — ver [docs/ARCHITECTURE.md](../../docs/ARCHITECTURE.md).
3. Endpoint `POST /api/collector/speedway` + job de processamento.
4. App **Laravel 13 monólito** + Vue SPA em `resources/js/` (Tailwind + shadcn-vue). Ver [docs/ARCHITECTURE.md](../../docs/ARCHITECTURE.md).
