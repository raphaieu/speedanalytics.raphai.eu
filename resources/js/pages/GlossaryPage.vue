<script setup lang="ts">
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

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
    description: 'Primeiros 2 pilotos pela menor odd pré-corrida.',
    formula: 'forecast = 1º + 2º menor odd',
    example: 'Odds 3.20|8.00|2.45|5.00 -> forecast 3-1',
  },
  {
    term: 'Tricast por odds',
    description: 'Primeiros 3 pilotos pela menor odd pré-corrida.',
    formula: 'tricast = 1º + 2º + 3º menor odd',
  },
  {
    term: 'Forecast hit',
    description: 'Acerto quando o primeiro do forecast é o vencedor.',
    formula: 'forecast_hit = (winner_position === forecast[0])',
  },
  {
    term: 'Tricast winner hit',
    description: 'Acerto quando o primeiro do tricast é o vencedor.',
    formula: 'tricast_winner_hit = (winner_position === tricast[0])',
  },
  {
    term: 'Tricast exact hit',
    description: 'Acerto apenas quando existe ordem real completa e bate 1º, 2º e 3º.',
    formula: 'tricast_exact_hit = (tricast_previsto === tricast_real_completo)',
  },
];

const analyticsConcepts: GlossaryItem[] = [
  {
    term: 'Win rate',
    description: 'Taxa de acerto em percentual.',
    formula: 'win_rate = (wins / total) * 100',
  },
  {
    term: 'P/L (Profit and Loss)',
    description: 'Lucro/prejuízo total da simulação com stake fixa.',
    formula: 'P/L = soma dos lucros e perdas por corrida',
  },
  {
    term: 'ROI teórico',
    description: 'Retorno percentual sobre o total apostado na simulação.',
    formula: 'theoretical_roi = (profit_loss / total_apostado) * 100',
    example: 'Stake 1 por corrida -> total_apostado = total',
  },
  {
    term: 'Probabilidade implícita',
    description: 'Probabilidade implícita da odd de mercado.',
    formula: 'implied_probability = 1 / odd',
  },
  {
    term: 'Edge vs implied',
    description: 'Diferença entre o acerto real e a probabilidade implícita.',
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
  <div class="space-y-5">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Glossário</h1>
      <p class="text-sm text-muted-foreground">
        Definições e fórmulas das métricas usadas no Speedway Analytics.
      </p>
    </div>

    <Card>
      <CardHeader>
        <CardTitle>Conceitos de corrida</CardTitle>
        <CardDescription>Termos base para leitura do resultado e contexto da corrida.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
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
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Forecast e Tricast</CardTitle>
        <CardDescription>Como o sistema calcula e valida os acertos por odds.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
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
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Métricas analíticas</CardTitle>
        <CardDescription>Indicadores de performance e risco usados nas análises.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
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
      </CardContent>
    </Card>
  </div>
</template>
