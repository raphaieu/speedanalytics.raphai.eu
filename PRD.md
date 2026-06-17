# PRD v0.5 — Speedway Analytics

## 1. Visão Geral

O **Speedway Analytics** é uma plataforma mobile-first de inteligência operacional para o jogo virtual Speedway, focada em coleta automatizada de dados, análise probabilística, criação de setups, simulação em conta demo, backtests, gestão de risco, diário de operações e análise assistida por IA.

A proposta central é transformar uma leitura visual, manual e emocional do Speedway em um ambiente estruturado de estudo, validação e tomada de decisão baseada em dados.

O produto será construído como uma aplicação web/PWA, com backend próprio, banco histórico, engine de estratégias, simulação demo, relatórios estatísticos e camada de IA explicativa.

---

## 2. Objetivo do Produto

Construir uma aplicação capaz de:

* Coletar dados do Speedway 24/7 em produção.
* Armazenar histórico completo de corridas.
* Salvar odds pré-corrida antes do resultado.
* Atualizar corridas com resultado oficial após encerramento.
* Criar setups com regras objetivas.
* Simular entradas em conta demo.
* Executar backtests sobre dados históricos reais.
* Aplicar gestão de risco por setup.
* Registrar racional automático e manual das operações.
* Gerar relatórios estatísticos e análises com IA.
* Ajudar o usuário a validar hipóteses antes de qualquer uso com dinheiro real.

---

## 3. Posicionamento

### Frase principal

**Speedway Analytics é uma plataforma mobile-first de inteligência para Speedway, que coleta odds e resultados, organiza histórico, cria setups, simula entradas em conta demo, executa backtests, aplica gestão de risco, registra diário operacional e usa IA para explicar padrões, alertar riscos e melhorar decisões.**

### Princípio do produto

> Não aposte no escuro. Teste antes.

### O que o produto deve ser

* Laboratório de estratégias.
* Ferramenta de análise probabilística.
* Simulador demo.
* Diário operacional.
* Auditor de setups.
* Copiloto estatístico com IA.
* Plataforma de validação de hipóteses.

### O que o produto não deve ser

* Não deve prometer lucro.
* Não deve vender previsão certeira.
* Não deve incentivar aposta automática.
* Não deve mascarar ruído estatístico como padrão real.
* Não deve usar técnicas agressivas de bypass anti-bot.
* Não deve virar dashboard visualmente poluído estilo cassino.

---

## 4. Problema

A leitura atual do Speedway depende muito de observação manual:

* Cores.
* Odds.
* Tendências.
* Históricos visuais.
* Sequências.
* Favoritos.
* Zebras.
* Previsões do app.
* Sensação de padrão.

O problema é que a leitura visual não responde com precisão:

* Esse padrão funciona mesmo?
* A odd paga o risco?
* O favorito vence acima do esperado?
* A previsão do app tem valor estatístico?
* A sequência atual é sinal ou ruído?
* O setup teria lucro no longo prazo?
* O resultado ruim foi erro de estratégia ou variância?
* O usuário está seguindo regra ou operando por emoção?

O Speedway Analytics resolve isso criando uma base própria de dados e um ambiente de simulação.

---

## 5. Usuário-Alvo

### Perfil inicial

* Usuário que acompanha Speedway.
* Apostador analítico.
* Trader esportivo curioso.
* Pessoa interessada em odds, estatística e probabilidade.
* Usuário que quer testar setups sem arriscar dinheiro real.
* Usuário que quer registrar racional, disciplina e evolução.

### Necessidades

* Entender padrões reais.
* Validar setups.
* Simular entradas.
* Medir ROI, drawdown e expectância.
* Evitar decisões emocionais.
* Separar estratégia boa de ilusão estatística.
* Ter relatórios simples e acionáveis.

---

# 6. Escopo Geral

O produto será dividido em 9 módulos principais:

1. Dashboard mobile.
2. Coleta 24/7.
3. Histórico de corridas.
4. Criador de setups.
5. Simulador demo.
6. Backtest.
7. Gestão de risco.
8. Diário operacional.
9. IA analítica.

---

# 7. Coleta de Dados 24/7

## 7.1 Contexto Técnico

A URL principal de coleta será:

```txt
https://api.bbtips.com.br/api/speedway?filtroExibicao=Odd_Todas&horas=Horas12&futuro=true&dadosAlteracao=
```

Parâmetros usados:

```txt
filtroExibicao=Odd_Todas
horas=Horas12
futuro=true
dadosAlteracao={cursor}
```

O endpoint retorna dois tipos de dados:

### Corridas já encerradas

Contêm:

```txt
Id
Horario
Vencedor
Cor_Vencedor
Nome_Piloto
Odd
Previsao
Odd_Previsao
Previsao_Tricast
Hora
Minutos
Odds_Pilotos
```

### Corridas futuras ou pendentes

Contêm:

```txt
Id
Horario
Hora
Minutos
Odds_Pilotos
```

Ou seja, com `futuro=true`, a API permite capturar odds antes da corrida ser resolvida.

Isso é essencial para evitar `look-ahead bias` nos backtests.

---

## 7.2 Problema de Acesso

Testes com `curl` usando Bearer Token, headers reais, Origin, Referer, User-Agent e `sec-ch-*` ainda retornam a página de challenge da Cloudflare:

```txt
Just a moment...
```

Conclusão:

* Bearer Token sozinho não basta.
* Headers copiados do navegador não bastam.
* A API depende do contexto real do browser.
* A Cloudflare valida ambiente, cookies, sessão, JS/fingerprint e estado do challenge.

Portanto, o coletor server-to-server usando apenas HTTP client não será confiável para produção.

---

## 7.3 Estratégia de Coleta Escolhida

O MVP de produção usará um **Playwright Browser Collector** rodando 24/7 em VPS.

### Decisão técnica

```txt
MVP técnico de coleta 24/7:
Playwright Browser Collector em VPS, usando sessão autenticada persistente, capturando responses da API dentro do contexto real do navegador e enviando os payloads para o backend Laravel.
```

### Fluxo

```txt
VPS
↓
Playwright abre app.bbtips.com.br com sessão autenticada
↓
Aplicação oficial faz as requests normalmente
↓
Collector captura responses de /api/speedway
↓
Collector envia JSON para o backend Laravel
↓
Backend salva payload bruto
↓
Backend processa corridas pending/settled
```

---

## 7.4 Arquitetura do Collector

```txt
VPS
├── Laravel API
├── MySQL
├── Redis
├── Queue Worker
└── Speedway Collector
    └── Playwright Chromium
        ├── sessão persistente
        ├── storageState salvo
        ├── captura responses da API
        ├── detecta falhas/sessão expirada
        └── envia payload para Laravel
```

---

## 7.5 Login e Sessão Persistente

O collector deverá usar sessão autenticada persistente.

### Fluxo inicial

```txt
1. Rodar script de login com Playwright em modo visível.
2. Usuário faz login manualmente.
3. Após login concluído, salvar storageState.
4. Subir collector em modo contínuo usando o storageState salvo.
```

Arquivo gerado:

```txt
collector/storage/bbtips-storage-state.json
```

Esse arquivo conterá cookies e localStorage necessários para manter a sessão.

### Estados possíveis da sessão

```txt
valid
expired
needs_login
cloudflare_challenge
blocked
unknown_error
```

---

## 7.6 Estratégia de Captura

A abordagem preferencial é capturar as responses que o próprio app já faz.

### Regra

O collector deve escutar responses cujo URL contenha:

```txt
api.bbtips.com.br/api/speedway
```

E processar apenas respostas com:

```txt
content-type: application/json
```

Caso receba HTML, deve registrar possível challenge, sessão expirada ou bloqueio.

---

## 7.7 Endpoint Receptor no Backend

O backend Laravel terá um endpoint interno para receber payloads do collector.

```txt
POST /api/collector/speedway
```

Headers:

```txt
x-speedway-collector-token: {secret}
```

Payload:

```json
{
  "source": "bbtips",
  "mode": "odd_todas",
  "source_url": "https://api.bbtips.com.br/api/speedway?...",
  "captured_at": "2026-06-15T16:25:00-03:00",
  "payload": {}
}
```

### Regras

* Validar token interno.
* Validar estrutura básica.
* Salvar payload bruto.
* Enfileirar processamento.
* Responder rapidamente.
* Não processar pesado na request.

---

## 7.8 Segurança do Collector

### Requisitos

* Nunca versionar `storageState`.
* Nunca versionar tokens.
* Nunca versionar cookies.
* Usar `.env` para secrets.
* Proteger endpoint interno com token.
* Opcionalmente restringir IP do collector.
* Registrar logs sem expor Bearer Token.
* Rotacionar sessão se tokens forem expostos.

### Variáveis de ambiente

```env
SPEEDWAY_COLLECTOR_TOKEN=
SPEEDWAY_COLLECTOR_ENDPOINT=
SPEEDWAY_COLLECTOR_INTERVAL_MS=30000
```

---

## 7.9 Frequência de Coleta

As corridas ocorrem aproximadamente a cada 3 minutos.

Coleta recomendada:

```txt
Captura natural das responses do app
Health check a cada 1 minuto
Reload leve a cada 5 minutos se o app parar de atualizar
Sem loop agressivo de 1 segundo
```

O objetivo é manter coleta suficiente sem comportamento abusivo.

---

## 7.10 Ciclo Pending → Settled

O sistema deve tratar a corrida em dois momentos:

### 1. Pré-corrida

A corrida aparece com odds, mas sem vencedor.

Exemplo:

```json
{
  "Id": 909380,
  "Horario": "20",
  "Hora": "20",
  "Minutos": "22",
  "Odds_Pilotos": "3.10|2.75|5.50|5.50"
}
```

Ação:

```txt
Salvar como status = pending
Salvar odds pré-corrida
Salvar raw_pending_payload
Salvar first_seen_at
```

### 2. Pós-corrida

A mesma corrida aparece com resultado.

Exemplo:

```json
{
  "Id": 909380,
  "Vencedor": 2,
  "Cor_Vencedor": "Vermelho",
  "Nome_Piloto": "Nome do Piloto",
  "Odd": "2.75",
  "Previsao": "2-1",
  "Odd_Previsao": 7.50,
  "Previsao_Tricast": "2-1-4",
  "Odds_Pilotos": "3.10|2.75|5.50|5.50"
}
```

Ação:

```txt
Atualizar status = settled
Preencher vencedor
Preencher odd vencedora
Preencher previsão
Salvar raw_result_payload
Salvar settled_at
Liquidar operações demo relacionadas
```

---

## 7.11 Regra Anti Look-Ahead Bias

Backtests e simulações não podem usar dados que só ficaram disponíveis após o resultado.

Para uma entrada ser considerada válida:

* A corrida precisa ter sido capturada como `pending`.
* As odds pré-corrida precisam estar salvas em `raw_pending_payload`.
* O sinal precisa ser gerado antes da corrida ser `settled`.

Isso evita simular uma entrada usando informações que não existiam no momento real.

---

# 8. Observabilidade da Coleta

## 8.1 Collector Status

Criar tabela:

```txt
collector_statuses
```

Campos:

```txt
id
source
status
last_success_at
last_payload_at
last_error_at
last_error_message
last_external_id
last_data_updated_at
needs_login
metadata_json
created_at
updated_at
```

Status possíveis:

```txt
running
ok
failed
needs_login
cloudflare_challenge
expired_session
blocked
stale
```

---

## 8.2 Collector Runs

Criar tabela:

```txt
collector_runs
```

Campos:

```txt
id
source
started_at
finished_at
status
payload_count
race_count
pending_count
settled_count
error_message
metadata_json
created_at
updated_at
```

---

## 8.3 Alertas do Collector

O sistema deve alertar quando:

* Collector parar.
* Não houver payload novo por X minutos.
* API retornar HTML.
* Sessão expirar.
* Cloudflare challenge aparecer.
* Nenhum novo ID for capturado por período anormal.
* Backend rejeitar payload.
* Job de processamento falhar.

Canais futuros:

```txt
dashboard
e-mail
WhatsApp
Telegram
```

---

# 9. Histórico de Corridas

## 9.1 Objetivo

Permitir consulta visual e estatística das corridas coletadas.

## 9.2 Card de Corrida

Cada corrida deve exibir:

```txt
13:58
Status: Settled

Vencedor: Vermelho / Piloto 2
Odd vencedora: 3.10

Odds pré-corrida:
P1 Verde: 3.80
P2 Vermelho: 3.10
P3 Amarelo: 3.40
P4 Roxo: 6.00

Favorito venceu? Sim
Zebra venceu? Não
Previsão bateu? Sim/Não
```

Para corrida pendente:

```txt
20:22
Status: Pending

Odds:
P1 Verde: 3.10
P2 Vermelho: 2.75
P3 Amarelo: 5.50
P4 Roxo: 5.50

Favorito: Vermelho / Piloto 2
Aguardando resultado
```

---

## 9.3 Filtros

* Data.
* Hora.
* Minuto.
* Status.
* Cor vencedora.
* Piloto vencedor.
* Favorito venceu.
* Zebra venceu.
* Faixa de odd vencedora.
* Previsão bateu.
* Tricast bateu.
* Corridas com odds pré-capturadas.
* Corridas sem pré-captura.

---

## 9.4 Gráficos

* Frequência por cor.
* Vitória de favoritos vs zebras.
* Distribuição por faixa de odd.
* Resultados por hora.
* Odds vencedoras.
* Sequências de cor.
* Sequências de favoritos/zebras.
* Taxa de liquidação pending → settled.
* Latência média entre pending e settled.

---

# 10. Dashboard Mobile

## 10.1 Objetivo

Ser a tela inicial do produto, com visão rápida da coleta, setups, operações demo e IA.

## 10.2 Cards principais

* Status do collector.
* Última corrida capturada.
* Última corrida liquidada.
* Total de corridas no histórico.
* Corridas pendentes.
* Corridas resolvidas.
* Setups ativos.
* Operações demo abertas.
* Resultado demo do dia.
* Banca demo atual.
* Melhor setup.
* Pior setup.
* Alerta da IA.

## 10.3 Exemplo de card

```txt
Collector: OK
Último payload: há 22s
Último ID: 909385
Pendentes: 7
Settled hoje: 142
```

## 10.4 Bottom Navigation

```txt
Dashboard
Corridas
Setups
Demo
IA
```

---

# 11. Criador de Setups

## 11.1 Objetivo

Permitir que o usuário crie estratégias com regras objetivas, sem precisar programar.

## 11.2 Campos do setup

* Nome.
* Descrição.
* Hipótese.
* Mercado alvo.
* Condições de entrada.
* Janela estatística.
* Gestão de risco.
* Status.
* Tags.
* Observações.

## 11.3 Status possíveis

```txt
rascunho
em_backtest
em_demo
pausado
validado
descartado
```

## 11.4 Exemplo de setup

```txt
Nome:
Favorito Odd 2.40–3.20 após zebra

Hipótese:
Após vitória de zebra, o favorito tende a recuperar acima da média.

Condições:
- Entrar no favorito.
- Odd do favorito entre 2.40 e 3.20.
- Última corrida foi vencida por não favorito.
- Spread das odds maior que 3.00.
- Máximo 1 entrada a cada 3 corridas.

Gestão:
- Stake fixa de 1u.
- Stop loss diário: -5u.
- Stop win diário: +3u.
```

## 11.5 Modo simples

Interface guiada:

```txt
Entrar em:
[ Favorito | Cor específica | Zebra | Previsão do app ]

Quando:
[ odd entre X e Y ]
[ últimos N resultados ]
[ cor venceu X vezes ]
[ favorito perdeu X vezes ]
```

## 11.6 Modo avançado

Builder lógico:

```txt
IF favorite_odd BETWEEN 2.40 AND 3.20
AND previous_winner_was_underdog = true
AND odds_spread > 3.00
THEN entry = favorite
```

---

# 12. Simulador Demo

## 12.1 Objetivo

Simular entradas fictícias com base nos setups ativos.

## 12.2 Fluxo

```txt
Corrida pending capturada
↓
Métricas pré-corrida calculadas
↓
Setups ativos são avaliados
↓
Setup gera sinal
↓
Gestão de risco aprova ou bloqueia
↓
Sistema cria operação demo
↓
Corrida vira settled
↓
Operação é liquidada
↓
Banca demo é atualizada
↓
Racional é salvo
```

## 12.3 Regra crítica

Operação demo só pode ser criada para corrida `pending`.

Não criar operação demo em corrida já `settled`, pois isso contaminaria a simulação.

---

## 12.4 Card de operação aberta

```txt
Setup: Favorito Odd 2.40–3.20
Corrida: 20:22
Entrada: Piloto 2 / Vermelho
Odd: 2.75
Stake: 1u
Retorno potencial: +1.75u
Status: aguardando resultado
```

## 12.5 Card de operação encerrada

```txt
Resultado: Green
Lucro: +1.75u
Banca: 101.75u
```

ou:

```txt
Resultado: Red
Prejuízo: -1u
Banca: 99u
```

---

# 13. Backtest

## 13.1 Objetivo

Executar setups contra histórico real para medir desempenho.

## 13.2 Regra de validade

O backtest deve poder operar em dois modos:

### Modo histórico puro

Usa dados já coletados para simular hipóteses antigas.

Esse modo deve ser marcado como:

```txt
historical_backtest
```

Risco:

```txt
Pode conter look-ahead se as odds não foram capturadas antes do resultado.
```

### Modo validado

Usa apenas corridas que tiveram estado `pending` salvo antes de serem `settled`.

Esse modo deve ser marcado como:

```txt
validated_backtest
```

Esse é o modo mais confiável.

---

## 13.3 Inputs

* Setup.
* Período.
* Tipo de backtest.
* Banca inicial.
* Stake.
* Stop loss.
* Stop win.
* Limite de entradas por dia.
* Pausa após X reds.
* Pausa após X greens.
* Filtros de horário.

## 13.4 Outputs

* Total de entradas.
* Greens.
* Reds.
* Green rate.
* ROI.
* Lucro/prejuízo em unidades.
* Odd média.
* Maior sequência de greens.
* Maior sequência de reds.
* Drawdown máximo.
* Expectância.
* Melhor horário.
* Pior horário.
* Melhor faixa de odd.
* Pior faixa de odd.
* Curva da banca.
* Distribuição de resultados.
* Validade estatística.
* Risco de overfitting.

---

# 14. Gestão de Risco

## 14.1 Objetivo

Controlar exposição, limitar perdas e evitar simulações irreais.

## 14.2 Configurações iniciais

* Banca demo inicial.
* Stake fixa.
* Stake percentual.
* Stop loss diário.
* Stop win diário.
* Máximo de entradas por dia.
* Pausa após X reds consecutivos.
* Pausa após X greens consecutivos.
* Drawdown máximo.
* Máximo de entradas por hora.

## 14.3 MVP

Implementar:

* Stake fixa.
* Stop loss diário.
* Stop win diário.
* Máximo de entradas por dia.
* Pausa após reds consecutivos.

## 14.4 Fora do MVP

* Martingale.
* Aposta automática real.
* Integração direta com casa de aposta.

---

# 15. IA Analítica

## 15.1 Objetivo

Adicionar análise, explicação, auditoria e geração de hipóteses baseada nos dados coletados.

A IA não deve agir como vidente. Ela deve atuar como:

* Analista quantitativo.
* Auditor de setup.
* Explicador de entradas.
* Detector de overfitting.
* Gerador de relatórios.
* Copiloto de investigação estatística.

---

## 15.2 Funções da IA

### 1. Relatório diário

Gerar resumo com:

* Corridas coletadas.
* Corridas pendentes.
* Corridas liquidadas.
* Status do collector.
* Setups ativos.
* Resultado demo.
* Melhor setup.
* Pior setup.
* Alertas.
* Mudanças de padrão.
* Riscos detectados.
* Sugestões de investigação.

### 2. Auditor de setup

Avaliar se um setup tem:

* Regras objetivas.
* Hipótese clara.
* Amostra suficiente.
* Risco de overfitting.
* Gestão adequada.
* Volume mínimo de operações.

### 3. Explicador de entrada

Gerar racional automático para cada operação demo.

Exemplo:

```txt
Entrada simulada no Piloto 2 / Vermelho.

Motivo:
- Piloto 2 era o favorito com odd 2.75.
- Favoritos nessa faixa venceram 41% nas últimas 1.000 corridas.
- O spread de odds estava acima de 3.00.
- A gestão autorizava entrada.
- Não havia stop loss ativo.
```

### 4. Análise pós-resultado

Exemplo:

```txt
Resultado: Red.

A entrada seguiu as regras. O red está dentro da variância esperada. O setup só deve ser revisado caso ultrapasse 5 reds consecutivos ou drawdown de -5u.
```

### 5. Detector de overfitting

Sinais de alerta:

* Regras demais.
* Poucas entradas.
* Lucro concentrado em poucos eventos.
* Setup funciona apenas em horário específico.
* Setup funciona apenas em um dia.
* Alta variação entre períodos.
* Resultado positivo sem volume estatístico.

### 6. Chat com os dados

Perguntas naturais:

```txt
Qual setup teve melhor ROI nas últimas 2.000 corridas?

O vermelho está realmente mais forte hoje ou é impressão?

Favoritos entre odd 2.40 e 3.20 estão performando bem?

Qual faixa de odd mais deu lucro?

A previsão do app tem edge?

O collector ficou estável nas últimas 24h?
```

---

## 15.3 Regra de segurança da IA

A IA nunca deve responder:

```txt
Aposte agora.
```

Ela deve responder:

```txt
O setup gerou um sinal demo com base nas regras definidas. O risco atual está dentro/fora do perfil configurado. A decisão final permanece do usuário.
```

---

# 16. Diário Operacional

## 16.1 Objetivo

Registrar histórico de operações, racional, disciplina e observações manuais.

## 16.2 Campos

* Operação.
* Setup.
* Resultado.
* Racional automático.
* Observação manual.
* Estado emocional.
* Nível de confiança.
* Score de disciplina.
* Tags.
* Tipo de erro.
* Contexto automático.

## 16.3 Tags iniciais

```txt
entrada válida
entrada fora da regra
setup respeitado
overtrade
FOMO
revanche
ajuste necessário
falso padrão
boa execução
má execução
```

## 16.4 Exemplo de análise do diário

```txt
Você teve 12 sinais hoje. 9 seguiram regra. 3 foram entradas manuais fora do setup. As entradas fora do setup geraram -2.7 unidades. O problema do dia não foi o robô, foi intervenção manual.
```

---

# 17. Indicadores e Métricas

## 17.1 Métricas por corrida

* Favorito.
* Segunda menor odd.
* Zebra.
* Odd vencedora.
* Favorito venceu?
* Zebra venceu?
* Previsão bateu?
* Spread das odds.
* Margem da casa.
* Probabilidade implícita.
* Probabilidade normalizada.
* Entropia do mercado.
* Status pending/settled.
* Tempo entre captura e liquidação.

---

## 17.2 Fórmulas

### Probabilidade implícita

```txt
probabilidade_implícita = 1 / odd
```

### Probabilidade normalizada

```txt
probabilidade_normalizada = probabilidade_implícita / soma_das_probabilidades_implícitas
```

### Margem da casa

```txt
margem = soma(1 / odd_de_cada_piloto) - 1
```

### Spread de odds

```txt
spread = maior_odd - menor_odd
```

### ROI

```txt
ROI = lucro_total / valor_total_apostado
```

### Expectância

```txt
expectância = (taxa_de_acerto × lucro_médio) - (taxa_de_erro × perda_média)
```

---

# 18. Entidades Principais

## 18.1 User

```txt
id
name
email
password
settings
created_at
updated_at
```

---

## 18.2 SpeedwayRace

```txt
id
external_id
race_datetime
race_date
race_hour
race_minute
status

winner_position
winner_color
winner_odd
pilot_name

prediction
prediction_odd
tricast_prediction

pilot_odds_raw
pilot_1_odd
pilot_2_odd
pilot_3_odd
pilot_4_odd

favorite_position
favorite_odd
second_favorite_position
second_favorite_odd
underdog_position
underdog_odd

winner_was_favorite
winner_was_underdog

odds_spread
house_margin
market_entropy

source_updated_at
first_seen_at
settled_at

raw_pending_payload
raw_result_payload

created_at
updated_at
```

---

## 18.3 CollectorStatus

```txt
id
source
status
last_success_at
last_payload_at
last_error_at
last_error_message
last_external_id
last_data_updated_at
needs_login
metadata_json
created_at
updated_at
```

---

## 18.4 CollectorRun

```txt
id
source
started_at
finished_at
status
payload_count
race_count
pending_count
settled_count
error_message
metadata_json
created_at
updated_at
```

---

## 18.5 Strategy

```txt
id
user_id
name
description
hypothesis
market_type
entry_type
status
rules_json
settings_json
created_at
updated_at
```

---

## 18.6 RiskProfile

```txt
id
strategy_id
initial_bankroll
stake_type
fixed_stake
percentage_stake
daily_stop_loss
daily_stop_win
max_daily_entries
max_consecutive_losses
max_consecutive_wins
max_drawdown
created_at
updated_at
```

---

## 18.7 StrategySignal

```txt
id
strategy_id
speedway_race_id
entry_position
entry_color
entry_odd
reason
metadata_json
status
created_at
updated_at
```

---

## 18.8 DemoOperation

```txt
id
user_id
strategy_id
strategy_signal_id
speedway_race_id
entry_position
entry_color
entry_odd
stake
potential_profit
result
profit_loss
bankroll_before
bankroll_after
reason_snapshot
opened_at
settled_at
created_at
updated_at
```

---

## 18.9 BacktestRun

```txt
id
user_id
strategy_id
backtest_type
period_start
period_end
initial_bankroll
final_bankroll
total_entries
greens
reds
green_rate
roi
profit_loss
average_odd
max_drawdown
max_consecutive_wins
max_consecutive_losses
expectancy
look_ahead_risk
settings_json
summary_json
created_at
updated_at
```

---

## 18.10 BacktestOperation

```txt
id
backtest_run_id
speedway_race_id
entry_position
entry_color
entry_odd
stake
result
profit_loss
bankroll_before
bankroll_after
metadata_json
created_at
updated_at
```

---

## 18.11 JournalEntry

```txt
id
user_id
demo_operation_id
note
emotion
confidence_level
discipline_score
tags_json
mistake_type
ai_summary
created_at
updated_at
```

---

## 18.12 AiAnalysis

```txt
id
user_id
analysis_type
related_type
related_id
prompt_context_json
response
metadata_json
created_at
updated_at
```

---

# 19. Arquitetura Técnica

## 19.1 Stack principal

```txt
Backend:
Laravel 13 (PHP 8.3+)

Banco:
MySQL 8.4

Cache/Fila:
Redis

Jobs:
Laravel Queue + Scheduler

Frontend:
Vue 3 SPA em resources/js/ (Vite + vite-plugin-pwa)

UI:
Tailwind CSS + shadcn-vue (componentes progressivos)

Gráficos:
ECharts ou Recharts

IA:
OpenAI / Anthropic / Gemini

Fallback opcional:
Ollama local

Collector:
Node.js 24 + Playwright (serviço separado em collector/)

Deploy:
  Coolify — docker-compose (web, queue, collector, mysql, redis)
  Produção: https://speedanalytics.raphai.eu (desde 2026-06-18)

Formato mobile:
PWA (requisito — RNF006)
```

> **Nota v0.5:** Uma única aplicação Laravel; Vue SPA em `resources/js/` (não pasta `frontend/` separada). Tailwind + shadcn-vue para UI. API REST em `/api` para collector e SPA. Ver `docs/ARCHITECTURE.md`.

---

## 19.2 Estrutura sugerida

```txt
speedway-analytics/
├── app/                        # Laravel 13
│   ├── Http/Controllers/Api/
│   └── Jobs/
├── resources/
│   ├── js/                     # Vue 3 SPA + Tailwind + shadcn-vue
│   │   ├── router/
│   │   ├── views/
│   │   └── components/ui/
│   └── views/app.blade.php
├── routes/
│   ├── api.php
│   └── web.php                 # catch-all → SPA
├── database/migrations/
├── collector/                  # Node 24 + Playwright (Fase 0 ✓)
│   ├── package.json
│   ├── login.js
│   ├── collector.js
│   └── storage/
├── docker/
│   ├── app/Dockerfile
│   ├── collector/Dockerfile
│   ├── nginx/
│   └── supervisor/
├── docker-compose.yml         # Coolify
├── .env.coolify.example
├── vite.config.js
├── package.json
├── CHANGELOG.md
└── README.md
```

---

## 19.3 Serviços internos Laravel

```txt
SpeedwayPayloadService
SpeedwayParserService
RaceMetricsService
CollectorHealthService
StrategyEngineService
DemoOperationService
BacktestService
RiskManagementService
AiAnalysisService
JournalService
```

---

## 19.4 Jobs

```txt
ProcessSpeedwayPayloadJob
NormalizeRaceDataJob
EvaluateStrategiesJob
SettleDemoOperationsJob
RunBacktestJob
GenerateDailyAiReportJob
AuditStrategyWithAiJob
CheckCollectorHealthJob
```

---

## 19.5 Scheduler

```txt
Check collector health: a cada 1 minuto
Process pending races: contínuo via queue
Evaluate strategies: após processamento de pending
Settle demo operations: após corrida virar settled
Generate daily report: 1x por dia
Audit setups: sob demanda
Run backtests: sob demanda
```

---

# 20. Strategy Engine

## 20.1 Fluxo

```txt
Corrida pending capturada
↓
Calcular métricas pré-corrida
↓
Montar RaceContext
↓
Executar setups ativos
↓
Gerar StrategySignal
↓
Aplicar RiskProfile
↓
Criar DemoOperation
↓
Aguardar corrida settled
↓
Liquidar operação
```

## 20.2 Interface conceitual

```php
interface SpeedwayStrategy
{
    public function shouldEnter(RaceContext $context): StrategyDecision;
}
```

## 20.3 Decision object

```php
class StrategyDecision
{
    public bool $shouldEnter;
    public ?int $entryPosition;
    public ?string $entryColor;
    public ?float $entryOdd;
    public string $reason;
    public array $metadata;
}
```

---

# 21. IA — Arquitetura de Uso

## 21.1 Princípio

A estatística deve ser calculada pelo backend.

A IA deve interpretar, explicar e sugerir.

### Backend calcula

```txt
win rate
ROI
expectância
drawdown
sequências
frequência por cor
frequência por odd
probabilidade implícita
desvio contra esperado
distribuição por horário
status do collector
qualidade da amostra
```

### IA interpreta

```txt
o que melhorou
o que piorou
qual setup é promissor
qual setup é cilada
qual hipótese testar
qual regra está subjetiva
qual risco existe
se há baixa amostra
se há overfitting
se a coleta está saudável
```

## 21.2 Tipos de análise

```txt
daily_report
strategy_audit
entry_explanation
backtest_summary
overfitting_alert
hypothesis_generation
journal_summary
collector_health_summary
```

---

# 22. MVP

## MVP 1 — Fundação + Coleta 24/7

### Objetivo

Ter uma base histórica confiável e coleta contínua em produção.

### Escopo

* Criar projeto Laravel.
* Criar banco MySQL.
* Criar tabelas principais.
* Criar Playwright Browser Collector.
* Criar script de login manual.
* Salvar storageState.
* Capturar responses da API.
* Enviar payloads ao backend.
* Processar payloads.
* Salvar corridas pending.
* Atualizar corridas settled.
* Criar tela básica de histórico.
* Criar tela de status do collector.

### Entregáveis

* Collector rodando em VPS.
* Endpoint receptor.
* Processamento assíncrono.
* Tabela de corridas.
* Tabela de status do collector.
* Histórico mobile básico.
* Logs de coleta.
* Alerta visual quando collector parar.

---

## MVP 2 — Métricas e Histórico Visual

### Objetivo

Transformar dados brutos em leitura estatística inicial.

### Escopo

* Calcular favorito.
* Calcular zebra.
* Calcular probabilidade implícita.
* Calcular probabilidade normalizada.
* Calcular spread.
* Calcular margem.
* Criar filtros.
* Criar gráficos básicos.

### Entregáveis

* Cards de corrida.
* Filtros por data, cor, odd e status.
* Gráfico de frequência por cor.
* Gráfico favorito vs zebra.
* Gráfico distribuição de odds vencedoras.

---

## MVP 3 — Setups e Demo

### Objetivo

Permitir criação de setups simples e simulação automática.

### Escopo

* Cadastro de setup simples.
* Engine de sinais.
* Gestão de risco básica.
* Conta demo.
* Operações simuladas.
* Liquidação automática.
* Banca fictícia.

### Entregáveis

* Tela de setups.
* Criador de setup simples.
* Lista de operações demo.
* Resultado green/red.
* Curva básica da banca.
* Racional automático básico.

---

## MVP 4 — Backtest

### Objetivo

Validar setups contra histórico.

### Escopo

* Rodar setup contra período passado.
* Suportar modo histórico e modo validado.
* Calcular métricas.
* Gerar relatório.
* Exibir curva de banca.
* Comparar setups.

### Entregáveis

* Tela de backtest.
* Resultado estatístico.
* Curva de banca.
* ROI.
* Drawdown.
* Expectância.
* Flag de risco de look-ahead.

---

## MVP 5 — IA Analítica

### Objetivo

Adicionar interpretação e auditoria.

### Escopo

* Relatório diário.
* Auditor de setup.
* Explicação de entrada.
* Resumo de backtest.
* Alerta de overfitting.
* Resumo de saúde do collector.

### Entregáveis

* Tela IA.
* Relatórios salvos.
* Análise por setup.
* Explicação automática em operações demo.
* Alertas textuais.

---

## MVP 6 — Diário Operacional

### Objetivo

Registrar disciplina e racional humano.

### Escopo

* Notas manuais.
* Tags.
* Score de disciplina.
* Relatório comportamental.
* IA resumindo comportamento.

### Entregáveis

* Tela de diário.
* Tags.
* Observações.
* Histórico por operação.
* Relatório de disciplina.

---

# 23. Requisitos Funcionais

## RF001 — Coletar dados via Playwright

O sistema deve possuir um collector baseado em Playwright rodando 24/7 em produção.

## RF002 — Manter sessão persistente

O collector deve usar storageState salvo para manter sessão autenticada.

## RF003 — Capturar responses da API

O collector deve capturar responses de `/api/speedway` feitas pelo app oficial.

## RF004 — Enviar payload ao backend

O collector deve enviar o JSON capturado para endpoint interno do Laravel.

## RF005 — Validar token do collector

O backend deve aceitar payloads apenas com token interno válido.

## RF006 — Salvar payload bruto

O sistema deve salvar o payload bruto recebido.

## RF007 — Processar corridas pending

Corridas sem vencedor devem ser salvas como `pending`.

## RF008 — Processar corridas settled

Corridas com vencedor devem atualizar o registro existente para `settled`.

## RF009 — Evitar duplicidade

O sistema deve impedir duplicação usando `external_id`.

## RF010 — Preservar odds pré-corrida

O sistema deve manter as odds capturadas antes do resultado.

## RF011 — Calcular métricas

O sistema deve calcular favorito, zebra, spread, margem, probabilidade implícita e probabilidade normalizada.

## RF012 — Exibir histórico

O sistema deve exibir corridas coletadas com filtros e detalhes.

## RF013 — Monitorar collector

O sistema deve monitorar saúde do collector e registrar falhas.

## RF014 — Criar setup

O usuário deve conseguir criar setup com regras simples.

## RF015 — Executar setup

O sistema deve avaliar setups ativos contra corridas `pending`.

## RF016 — Criar operação demo

Quando um setup gerar sinal aprovado pela gestão, o sistema deve criar operação simulada.

## RF017 — Liquidar operação demo

O sistema deve atualizar green/red e banca quando a corrida virar `settled`.

## RF018 — Rodar backtest

O usuário deve conseguir rodar setup contra histórico.

## RF019 — Calcular métricas de backtest

O sistema deve calcular ROI, green rate, drawdown, expectância e sequências.

## RF020 — Configurar gestão de risco

O usuário deve definir stake, stop loss, stop win e limites de entrada.

## RF021 — Gerar análise com IA

O sistema deve gerar análises textuais baseadas em métricas calculadas.

## RF022 — Registrar diário

O usuário deve adicionar nota manual e tags às operações.

## RF023 — Gerar relatório diário

O sistema deve gerar resumo diário com dados, setups, operações e alertas.

---

# 24. Requisitos Não Funcionais

## RNF001 — Mobile-first

A interface deve ser desenhada primeiro para celular.

## RNF002 — Performance

Histórico e dashboards devem carregar rapidamente, mesmo com grande volume de corridas.

## RNF003 — Escalabilidade

O banco deve suportar crescimento contínuo de dados históricos.

## RNF004 — Auditabilidade

Toda operação simulada deve salvar racional e snapshot do contexto.

## RNF005 — Segurança

Tokens, cookies, storageState e chaves de API não devem ser versionados.

## RNF006 — PWA

O produto deve poder ser instalado no celular como PWA.

## RNF007 — Observabilidade

Jobs de coleta, falhas, payloads e análises devem ter logs.

## RNF008 — Reprocessamento

O sistema deve manter payload bruto para recalcular métricas futuras.

## RNF009 — Resiliência do collector

O collector deve reiniciar automaticamente em caso de falha.

## RNF010 — Detecção de sessão expirada

O sistema deve detectar quando a sessão do collector expirar.

## RNF011 — Baixo comportamento abusivo

A coleta deve ser moderada, sem loops agressivos ou tentativa de bypass furtivo.

---

# 25. Gráficos Essenciais

## MVP

* Frequência por cor.
* Favorito vs zebra.
* Distribuição de odds vencedoras.
* Curva da banca demo.
* ROI por setup.
* Green/red por setup.
* Drawdown.
* Status pending vs settled.
* Saúde do collector.

## Futuro

* Heatmap por hora/minuto.
* Sequências de cores.
* Correlação entre odds e vitória.
* Distribuição de lucro por faixa de odd.
* Comparativo entre setups.
* Evolução da assertividade.
* Performance por período do dia.
* Latência de coleta.
* Replay de mercado.

---

# 26. Alertas

## Alertas iniciais

* Collector parado.
* Sessão expirada.
* Cloudflare challenge detectado.
* Payload HTML recebido.
* Nenhum dado novo por X minutos.
* Setup atingiu stop loss.
* Setup atingiu stop win.
* Setup entrou em drawdown máximo.
* Setup teve X reds consecutivos.
* Setup com possível overfitting.
* Setup com baixa amostra.
* Job de processamento falhou.

## Canais futuros

* Dashboard.
* E-mail.
* WhatsApp.
* Telegram.

---

# 27. Riscos do Produto

## Risco 1 — Dependência de sessão autenticada

A sessão pode expirar ou ser invalidada.

### Mitigação

* Monitorar status.
* Alertar `needs_login`.
* Criar script simples para renovar sessão.
* Não depender de token manual no backend.

---

## Risco 2 — Cloudflare challenge

A Cloudflare pode bloquear o ambiente do collector.

### Mitigação

* Usar browser real com sessão persistente.
* Evitar chamadas agressivas.
* Detectar HTML em vez de JSON.
* Alertar falha de coleta.
* Não tentar bypass furtivo.

---

## Risco 3 — Overfitting

Criar setups que funcionam no passado, mas falham no futuro.

### Mitigação

* Alertas de IA.
* Amostra mínima.
* Forward test em demo.
* Comparação entre períodos.
* Diferenciar backtest histórico e validado.

---

## Risco 4 — Look-ahead bias

Simular entradas com dados disponíveis apenas após o resultado.

### Mitigação

* Status `pending`.
* Salvar `first_seen_at`.
* Salvar `raw_pending_payload`.
* Operações demo apenas em corridas pending.
* Flag de risco em backtests históricos.

---

## Risco 5 — Falsa confiança

Usuário interpretar simulação curta como validação.

### Mitigação

* Exibir volume de entradas.
* Alertar baixa amostra.
* Exibir drawdown.
* Exibir expectância.
* IA auditando setup.

---

## Risco 6 — Incentivo indevido à aposta

Produto pode ser interpretado como promessa de lucro.

### Mitigação

* Posicionamento como ferramenta de estudo.
* Conta demo como padrão.
* Sem aposta automática no MVP.
* Linguagem clara de risco.

---

# 28. Roadmap Técnico

## Fase 1 — Fundação

* ~~Criar projeto **Laravel 13 monólito** na raiz (PHP 8.3+).~~ — concluído
* ~~Vue 3 SPA em `resources/js/` — Vite, Vue Router, `vite-plugin-pwa`.~~ — concluído
* ~~Tailwind CSS + shadcn-vue (componentes UI).~~ — concluído
* ~~Configurar MySQL e Redis.~~ — concluído
* ~~Criar migrations principais (`speedway_payloads`, `speedway_races`, `collector_statuses`).~~ — concluído
* ~~Criar endpoint receptor `POST /api/collector/speedway`.~~ — concluído
* ~~Criar `ProcessSpeedwayPayloadJob`.~~ — concluído
* ~~Conectar Playwright Collector (Node 24) ao endpoint.~~ — concluído
* ~~Rodar collector em VPS 24h.~~ — concluído (Coolify, 2026-06-18)
* ~~Endpoints mínimos de leitura (`GET /api/collector/status`, `GET /api/races`).~~ — concluído
* ~~Telas Vue: status collector + lista de corridas.~~ — concluído
* ~~`docker-compose.yml` (web, mysql, redis, queue, collector — Coolify).~~ — concluído
* ~~Criar Playwright Collector~~ — concluído (Fase 0)
* ~~Criar script de login / storageState~~ — concluído (Fase 0)

## Fase 2 — Corridas e Métricas

* Salvar corridas pending.
* Atualizar corridas settled.
* Normalizar odds.
* Calcular favorito.
* Calcular zebra.
* Calcular spread.
* Calcular margem.
* Criar tela de histórico.

## Fase 3 — Dashboard e Gráficos

* Criar dashboard mobile.
* Criar cards principais.
* Criar gráficos básicos.
* Criar filtros.
* Criar indicadores do collector.

## Fase 4 — Setups

* Criar tabela de strategies.
* Criar criador simples.
* Implementar engine.
* Criar sinais.
* Aplicar gestão de risco.

## Fase 5 — Demo

* Criar operações demo.
* Liquidar operações.
* Criar banca fictícia.
* Criar curva de banca.
* Criar racional automático.

## Fase 6 — Backtest

* Implementar execução histórica.
* Implementar modo validado.
* Gerar métricas.
* Criar tela de relatório.
* Criar curva da banca.

## Fase 7 — IA

* Gerar relatório diário.
* Auditar setup.
* Explicar entrada.
* Resumir backtest.
* Detectar overfitting.
* Resumir saúde do collector.

## Fase 8 — Diário

* Criar notas.
* Criar tags.
* Criar score de disciplina.
* Criar relatório comportamental.

---

# 29. Critérios de Sucesso

## Produto

* Collector roda 24/7 em VPS.
* Sistema captura corridas futuras como pending.
* Sistema atualiza corridas encerradas como settled.
* Usuário visualiza histórico no celular.
* Usuário cria setup sem programar.
* Sistema simula entradas automaticamente.
* Sistema liquida operações demo.
* Usuário roda backtest.
* IA explica setup, entrada e resultado.
* Sistema alerta falha de coleta.

## Métricas

* Corridas coletadas por dia.
* Taxa pending → settled.
* Tempo médio entre pending e settled.
* Tempo desde último payload.
* Falhas do collector por dia.
* Setups criados.
* Backtests executados.
* Operações demo simuladas.
* Alertas gerados.
* Relatórios IA lidos.

---

# 30. Decisões de Escopo

## Dentro do MVP

* Playwright collector 24/7.
* Sessão persistente.
* Endpoint receptor.
* Coleta pending/settled.
* Histórico.
* Métricas básicas.
* Setups simples.
* Simulação demo.
* Backtest básico.
* Gestão de risco básica.
* IA explicativa.
* Diário simples.

## Fora do MVP

* Aposta automática real.
* Martingale.
* Integração direta com casa de aposta.
* App nativo.
* Marketplace de setups.
* Assinatura paga.
* Modelo preditivo avançado.
* Machine learning complexo.
* Tentativas agressivas de bypass Cloudflare.

---

# 31. Possíveis Evoluções Futuras

* PWA instalável.
* Alertas por WhatsApp.
* Chat com dados.
* Ranking de setups.
* Comparador de estratégias.
* Simulador multi-bancas.
* Exportação CSV/PDF.
* IA criando setups experimentais.
* Machine learning para score de probabilidade.
* Detecção de mudança de regime.
* Heatmap avançado.
* Replay de mercado.
* Marketplace privado de setups.
* Modo comunidade.
* Collector multi-sessão.
* API oficial, caso viável.

---

# 32. Resumo Executivo

O Speedway Analytics será uma plataforma de estudo, coleta e validação estatística para Speedway.

O sistema usará um Playwright Browser Collector rodando 24/7 em VPS para capturar, dentro de uma sessão autenticada real, os dados da API do Speedway. As corridas serão salvas primeiro como `pending`, com odds pré-corrida, e posteriormente atualizadas como `settled`, com o resultado oficial.

Essa separação permite criar simulações demo e backtests mais confiáveis, evitando o erro clássico de usar dados conhecidos depois do resultado como se fossem dados disponíveis antes da corrida.

O produto combina:

* Coleta contínua.
* Histórico estruturado.
* Métricas probabilísticas.
* Criação de setups.
* Simulação demo.
* Backtests.
* Gestão de risco.
* Diário operacional.
* IA analítica.

A proposta não é prever magicamente o próximo resultado.

A proposta é responder uma pergunta muito mais séria:

> Existe alguma distorção persistente entre as odds oferecidas, os padrões históricos e os resultados reais?

Se existir, o sistema ajuda a encontrar, testar e monitorar.

Se não existir, o sistema ajuda a descobrir antes que o usuário pague caro pela ilusão.

---
