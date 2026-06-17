<script setup lang="ts">
import { Separator } from '@/components/ui/separator';

type GlossaryItem = {
  term: string;
  description: string;
  formula?: string;
  example?: string;
};

const raceConcepts: GlossaryItem[] = [
  {
    term: 'Favorito',
    description: 'Piloto com menor odd pré-corrida.',
    formula: 'favorite_position = posição com menor odd',
  },
  {
    term: 'Zebra (Underdog)',
    description: 'Piloto com maior odd pré-corrida.',
    formula: 'underdog_position = posição com maior odd',
  },
  {
    term: 'Segundo favorito',
    description: 'Piloto com a segunda menor odd pré-corrida.',
    formula: 'second_favorite_position = 2ª menor odd',
  },
  {
    term: 'Ranking por odd do vencedor',
    description: 'Posição do vencedor na ordenação de odds (menor para maior).',
    formula: 'winner_odd_rank = rank(vencedor) entre as odds pré-corrida',
    example: 'Se o vencedor era a maior odd, winner_odd_rank = 4.',
  },
];

const forecastConcepts: GlossaryItem[] = [
  {
    term: 'Forecast por odds',
    description: 'Previsão do 1º e 2º colocados com base nos dois pilotos de menor odd pré-corrida, nessa ordem.',
    formula: 'forecast = [1º menor odd, 2º menor odd]',
    example: 'Odds 3.20|8.00|2.45|5.00 → forecast 3-1 (posições 3 e 1 têm as menores odds).',
  },
  {
    term: 'Forecast hit',
    description: 'Acerto quando o resultado real do forecast (1º e 2º colocados) coincide com a previsão por odds, na ordem exata.',
    formula: 'forecast_hit = (real_1º === forecast_1º) e (real_2º === forecast_2º)',
    example: 'Previsão 3-1 e resultado 3-1 → hit. Resultado 3-2 → miss.',
  },
  {
    term: 'Tricast por odds',
    description: 'Previsão do 1º, 2º e 3º colocados com base nos três pilotos de menor odd pré-corrida, nessa ordem.',
    formula: 'tricast = [1º, 2º e 3º menor odd]',
    example: 'Odds 2.45|3.10|6.00|9.00 → tricast 1-2-3.',
  },
  {
    term: 'Tricast hit',
    description: 'Acerto quando o resultado real do tricast (1º, 2º e 3º colocados) coincide com a previsão por odds, na ordem exata.',
    formula: 'tricast_hit = (real_1º === tricast_1º) e (real_2º === tricast_2º) e (real_3º === tricast_3º)',
    example: 'Previsão 1-2-3 e resultado 1-2-3 → hit. Resultado 1-2-4 → miss.',
  },
];

const analyticsConcepts: GlossaryItem[] = [
  {
    term: 'Win rate',
    description: 'Taxa de acerto em percentual.',
    formula: 'win_rate = (wins / total) * 100',
  },
  {
    term: 'Stake',
    description: 'Valor apostado em cada corrida nas simulações de P/L e ROI. O sistema assume stake fixa — por padrão, 1 unidade por aposta.',
    example: 'Stake 1 em 100 corridas → total apostado = 100 unidades.',
  },
  {
    term: 'P/L (Profit and Loss)',
    description: 'Lucro ou prejuízo total da simulação com stake fixa.',
    formula: 'P/L = soma dos lucros e perdas por corrida',
  },
  {
    term: 'ROI teórico',
    description: 'Retorno percentual sobre o total apostado na simulação.',
    formula: 'theoretical_roi = (profit_loss / total_apostado) * 100',
    example: 'Stake 1 por corrida → total_apostado = total de corridas.',
  },
  {
    term: 'Probabilidade implícita',
    description: 'Probabilidade sugerida pela odd de mercado — o quanto a casa “precifica” aquele resultado.',
    formula: 'implied_probability = 1 / odd',
    example: 'Odd 2.00 → probabilidade implícita de 50%.',
  },
  {
    term: 'Edge',
    description: 'Vantagem estatística em relação ao mercado. Edge positivo indica que o resultado observado supera o que a odd implícita sugeriria; edge negativo, o contrário.',
    example: 'Win rate de 45% com odd média 2.00 (50% implícito) → edge de -5 p.p.',
  },
  {
    term: 'Edge vs implied',
    description: 'Diferença numérica entre a taxa de acerto real e a probabilidade implícita da odd.',
    formula: 'edge = win_rate_decimal - implied_probability_decimal',
  },
  {
    term: 'Spread de odds',
    description: 'Diferença entre a maior odd e a menor odd da corrida.',
    formula: 'odds_spread = max_odd - min_odd',
  },
  {
    term: 'Margem da casa (House margin)',
    description: 'Overround do mercado; mostra o excesso sobre 100% implícito.',
    formula: 'house_margin = (1/odd1 + 1/odd2 + 1/odd3 + 1/odd4) - 1',
    example: 'Se no banco está 0.05, isso representa 5%.',
  },
];
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Glossário</h1>
      <p class="text-sm text-muted-foreground">
        Definições e fórmulas das métricas usadas no Speedway Analytics.
      </p>
    </div>

    <section class="space-y-4">
      <div>
        <h2 class="text-base font-medium">Conceitos de corrida</h2>
        <p class="text-sm text-muted-foreground">
          Termos base para leitura do resultado e contexto da corrida.
        </p>
      </div>

      <div class="space-y-3">
        <div
          v-for="item in raceConcepts"
          :key="item.term"
          class="rounded-md border p-3"
        >
          <p class="text-sm font-semibold">{{ item.term }}</p>
          <p class="text-sm text-muted-foreground">{{ item.description }}</p>
          <p v-if="item.formula" class="mt-1 font-mono text-xs text-muted-foreground">{{ item.formula }}</p>
          <p v-if="item.example" class="mt-1 text-xs text-muted-foreground">{{ item.example }}</p>
        </div>
      </div>
    </section>

    <Separator />

    <section class="space-y-4">
      <div>
        <h2 class="text-base font-medium">Forecast e Tricast</h2>
        <p class="text-sm text-muted-foreground">
          Como o sistema calcula e valida os acertos por odds.
        </p>
      </div>

      <div class="space-y-3">
        <div
          v-for="item in forecastConcepts"
          :key="item.term"
          class="rounded-md border p-3"
        >
          <p class="text-sm font-semibold">{{ item.term }}</p>
          <p class="text-sm text-muted-foreground">{{ item.description }}</p>
          <p v-if="item.formula" class="mt-1 font-mono text-xs text-muted-foreground">{{ item.formula }}</p>
          <p v-if="item.example" class="mt-1 text-xs text-muted-foreground">{{ item.example }}</p>
        </div>
      </div>
    </section>

    <Separator />

    <section class="space-y-4">
      <div>
        <h2 class="text-base font-medium">Métricas analíticas</h2>
        <p class="text-sm text-muted-foreground">
          Indicadores de performance e risco usados nas análises.
        </p>
      </div>

      <div class="space-y-3">
        <div
          v-for="item in analyticsConcepts"
          :key="item.term"
          class="rounded-md border p-3"
        >
          <p class="text-sm font-semibold">{{ item.term }}</p>
          <p class="text-sm text-muted-foreground">{{ item.description }}</p>
          <p v-if="item.formula" class="mt-1 font-mono text-xs text-muted-foreground">{{ item.formula }}</p>
          <p v-if="item.example" class="mt-1 text-xs text-muted-foreground">{{ item.example }}</p>
        </div>
      </div>
    </section>
  </div>
</template>
