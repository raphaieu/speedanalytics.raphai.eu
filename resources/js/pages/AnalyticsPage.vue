<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { apiGet } from '@/composables/useApi';
import { todayBr } from '@/lib/format';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

type AnalyticsSummary = {
  totals: {
    races: number;
    validated_races: number;
  };
  favorite: {
    win_rate: number;
    theoretical_roi: number;
  };
  underdog: {
    win_rate: number;
  };
  forecast: {
    hit_rate: number;
  };
  tricast: {
    hit_rate: number;
  };
  odds: {
    average_winner_odd: number;
    average_spread: number;
    average_house_margin: number;
  };
  metadata?: {
    percentage_format?: 'percentage_points_in_api' | 'decimal_fraction_in_api';
    house_margin_format?: string;
  };
};

type FavoriteOddsBand = {
  band: string;
  min: number;
  max: number | null;
  total: number;
  wins: number;
  losses: number;
  win_rate: number;
  average_favorite_odd: number;
  implied_probability: number;
  edge_vs_implied: number;
  profit_loss: number;
  theoretical_roi: number;
};

type FavoriteOddsBandsResponse = {
  bands: FavoriteOddsBand[];
  summary: {
    total_races: number;
    profitable_bands: number;
    best_band: FavoriteOddsBand | null;
    worst_band: FavoriteOddsBand | null;
  };
};

type UnderdogOddsBand = {
  band: string;
  min: number;
  max: number | null;
  total: number;
  wins: number;
  losses: number;
  win_rate: number;
  average_underdog_odd: number;
  implied_probability: number;
  edge_vs_implied: number;
  profit_loss: number;
  theoretical_roi: number;
};

type UnderdogOddsBandsResponse = {
  bands: UnderdogOddsBand[];
  summary: {
    total_races: number;
    profitable_bands: number;
    best_band: UnderdogOddsBand | null;
    worst_band: UnderdogOddsBand | null;
  };
};

type PeriodKey = 'today' | 'last24h' | 'last7d' | 'all';

const period = ref<PeriodKey>('today');
const onlyValidated = ref(false);
const loading = ref(true);
const error = ref<string | null>(null);
const summary = ref<AnalyticsSummary | null>(null);
const favoriteBands = ref<FavoriteOddsBandsResponse | null>(null);
const underdogBands = ref<UnderdogOddsBandsResponse | null>(null);

const periodButtons: Array<{ key: PeriodKey; label: string }> = [
  { key: 'today', label: 'Hoje' },
  { key: 'last24h', label: 'Últimas 24h' },
  { key: 'last7d', label: 'Últimos 7 dias' },
  { key: 'all', label: 'Tudo' },
];

const cards = computed(() => {
  const data = summary.value;

  return [
    { label: 'Total de corridas analisadas', value: `${data?.totals.races ?? 0}` },
    { label: 'Corridas validadas', value: `${data?.totals.validated_races ?? 0}` },
    { label: 'Favorito venceu %', value: formatPercent(data?.favorite.win_rate) },
    { label: 'Zebra venceu %', value: formatPercent(data?.underdog.win_rate) },
    { label: 'Forecast hit rate', value: formatPercent(data?.forecast.hit_rate) },
    { label: 'Tricast hit rate', value: formatPercent(data?.tricast.hit_rate) },
    { label: 'ROI teórico do favorito', value: formatPercent(data?.favorite.theoretical_roi) },
    { label: 'Odd média vencedora', value: formatDecimal(data?.odds.average_winner_odd, 2) },
    { label: 'Spread médio', value: formatDecimal(data?.odds.average_spread, 2) },
    { label: 'Margem média da casa', value: formatPercent(data?.odds.average_house_margin) },
  ];
});

const isEmpty = computed(() => (summary.value?.totals.races ?? 0) === 0);
const bandsAreEmpty = computed(() => (favoriteBands.value?.summary.total_races ?? 0) === 0);
const underdogBandsAreEmpty = computed(() => (underdogBands.value?.summary.total_races ?? 0) === 0);

function formatPercent(value?: number): string {
  return `${formatDecimal(normalizePercentValue(value), 2)}%`;
}

function normalizePercentValue(value?: number): number {
  const safe = Number.isFinite(value) ? Number(value) : 0;
  const percentageFormat = summary.value?.metadata?.percentage_format;

  if (percentageFormat === 'decimal_fraction_in_api') {
    return safe * 100;
  }

  if (percentageFormat === 'percentage_points_in_api') {
    return safe;
  }

  // Fallback de segurança para contratos antigos sem metadata.
  return safe > -1 && safe < 1 ? safe * 100 : safe;
}

function formatDecimal(value?: number, digits = 2): string {
  const safe = Number.isFinite(value) ? Number(value) : 0;

  return safe.toLocaleString('pt-BR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: digits,
  });
}

function dateWithOffset(days: number): string {
  const base = new Date(`${todayBr()}T12:00:00`);
  base.setDate(base.getDate() + days);
  return base.toISOString().slice(0, 10);
}

function buildQuery(): string {
  const params = new URLSearchParams();

  if (onlyValidated.value) {
    params.set('only_validated', '1');
  }

  if (period.value === 'today') {
    const today = todayBr();
    params.set('date_from', today);
    params.set('date_to', today);
  } else if (period.value === 'last24h') {
    params.set('date_from', dateWithOffset(-1));
    params.set('date_to', todayBr());
  } else if (period.value === 'last7d') {
    params.set('date_from', dateWithOffset(-6));
    params.set('date_to', todayBr());
  }

  return params.toString();
}

async function loadSummary() {
  loading.value = true;
  error.value = null;

  try {
    const query = buildQuery();
    const [summaryResponse, bandsResponse, underdogBandsResponse] = await Promise.all([
      apiGet<AnalyticsSummary>(query ? `/analytics/summary?${query}` : '/analytics/summary'),
      apiGet<FavoriteOddsBandsResponse>(
        query ? `/analytics/favorite-odds-bands?${query}` : '/analytics/favorite-odds-bands',
      ),
      apiGet<UnderdogOddsBandsResponse>(
        query ? `/analytics/underdog-odds-bands?${query}` : '/analytics/underdog-odds-bands',
      ),
    ]);

    summary.value = summaryResponse;
    favoriteBands.value = bandsResponse;
    underdogBands.value = underdogBandsResponse;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Erro ao carregar analytics';
  } finally {
    loading.value = false;
  }
}

watch([period, onlyValidated], loadSummary);

onMounted(loadSummary);
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Análises</h1>
      <p class="text-sm text-muted-foreground">
        Resumo estatístico das corridas coletadas. Use como ponto de partida para investigar
        padrões, não como previsão de resultado.
      </p>
    </div>

    <Card>
      <CardHeader class="pb-3">
        <CardTitle class="text-base">Filtros</CardTitle>
        <CardDescription>Refine o recorte das corridas analisadas.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <div class="flex flex-wrap gap-2">
          <button
            v-for="item in periodButtons"
            :key="item.key"
            type="button"
            class="rounded-md border px-3 py-1.5 text-sm transition"
            :class="
              period === item.key
                ? 'border-primary bg-primary text-primary-foreground'
                : 'border-input bg-background text-foreground hover:bg-accent'
            "
            @click="period = item.key"
          >
            {{ item.label }}
          </button>
        </div>

        <label class="inline-flex items-center gap-2 text-sm">
          <input
            v-model="onlyValidated"
            type="checkbox"
            class="h-4 w-4 rounded border-input"
          />
          Somente corridas validadas
        </label>
      </CardContent>
    </Card>

    <p v-if="loading" class="text-sm text-muted-foreground">Carregando resumo analítico…</p>
    <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>
    <p v-else-if="isEmpty" class="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
      Nenhuma corrida encontrada para os filtros atuais.
    </p>

    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <Card v-for="card in cards" :key="card.label">
        <CardContent class="pt-4">
          <p class="text-xs text-muted-foreground">{{ card.label }}</p>
          <p class="text-2xl font-semibold tabular-nums">{{ card.value }}</p>
        </CardContent>
      </Card>
    </div>

    <Card v-if="!loading && !error">
      <CardHeader>
        <CardTitle>Favorito por faixa de odd</CardTitle>
        <CardDescription>
          Ajuda a identificar em quais faixas o favorito tende a performar melhor ou pior.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <div v-if="bandsAreEmpty" class="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
          Sem corridas suficientes para análise por faixa de odd nos filtros atuais.
        </div>

        <div v-else class="space-y-3">
          <div class="grid gap-2 sm:grid-cols-3">
            <div class="rounded-md border p-3">
              <p class="text-xs text-muted-foreground">Corridas na análise</p>
              <p class="text-lg font-semibold tabular-nums">
                {{ favoriteBands?.summary.total_races ?? 0 }}
              </p>
            </div>
            <div class="rounded-md border p-3">
              <p class="text-xs text-muted-foreground">Faixas lucrativas</p>
              <p class="text-lg font-semibold tabular-nums">
                {{ favoriteBands?.summary.profitable_bands ?? 0 }}
              </p>
            </div>
            <div class="rounded-md border p-3">
              <p class="text-xs text-muted-foreground">Melhor faixa (ROI)</p>
              <p class="text-sm font-semibold">
                {{ favoriteBands?.summary.best_band?.band ?? '—' }}
                <span v-if="favoriteBands?.summary.best_band" class="tabular-nums">
                  ({{ formatPercent(favoriteBands.summary.best_band.theoretical_roi) }})
                </span>
              </p>
            </div>
          </div>

          <div class="overflow-x-auto rounded-md border">
            <table class="min-w-full text-sm">
              <thead class="bg-muted/50">
                <tr class="text-left text-xs uppercase tracking-wide text-muted-foreground">
                  <th class="px-3 py-2">Faixa</th>
                  <th class="px-3 py-2">Total</th>
                  <th class="px-3 py-2">Vitórias</th>
                  <th class="px-3 py-2">Derrotas</th>
                  <th class="px-3 py-2">Win rate</th>
                  <th class="px-3 py-2">Prob. implícita</th>
                  <th class="px-3 py-2">Edge</th>
                  <th class="px-3 py-2">ROI</th>
                  <th class="px-3 py-2">P/L</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="band in favoriteBands?.bands ?? []"
                  :key="band.band"
                  class="border-t"
                >
                  <td class="px-3 py-2 font-medium">{{ band.band }}</td>
                  <td class="px-3 py-2 tabular-nums">{{ band.total }}</td>
                  <td class="px-3 py-2 tabular-nums">{{ band.wins }}</td>
                  <td class="px-3 py-2 tabular-nums">{{ band.losses }}</td>
                  <td class="px-3 py-2 tabular-nums">{{ formatPercent(band.win_rate) }}</td>
                  <td class="px-3 py-2 tabular-nums">{{ formatPercent(band.implied_probability) }}</td>
                  <td
                    class="px-3 py-2 tabular-nums"
                    :class="band.edge_vs_implied >= 0 ? 'text-emerald-600' : 'text-destructive'"
                  >
                    {{ formatPercent(band.edge_vs_implied) }}
                  </td>
                  <td
                    class="px-3 py-2 tabular-nums"
                    :class="band.theoretical_roi >= 0 ? 'text-emerald-600' : 'text-destructive'"
                  >
                    {{ formatPercent(band.theoretical_roi) }}
                  </td>
                  <td
                    class="px-3 py-2 tabular-nums"
                    :class="band.profit_loss >= 0 ? 'text-emerald-600' : 'text-destructive'"
                  >
                    {{ formatDecimal(band.profit_loss, 2) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </CardContent>
    </Card>

    <Card v-if="!loading && !error">
      <CardHeader>
        <CardTitle>Zebra por faixa de odd</CardTitle>
        <CardDescription>
          Mostra a performance do piloto com maior odd pré-corrida. Aqui aparecem as odds altas,
          como 5.00, 8.00 e 10.00+.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <div
          v-if="underdogBandsAreEmpty"
          class="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground"
        >
          Sem corridas suficientes para análise da zebra por faixa de odd nos filtros atuais.
        </div>

        <div v-else class="space-y-3">
          <div class="grid gap-2 sm:grid-cols-3">
            <div class="rounded-md border p-3">
              <p class="text-xs text-muted-foreground">Corridas na análise</p>
              <p class="text-lg font-semibold tabular-nums">
                {{ underdogBands?.summary.total_races ?? 0 }}
              </p>
            </div>
            <div class="rounded-md border p-3">
              <p class="text-xs text-muted-foreground">Faixas lucrativas</p>
              <p class="text-lg font-semibold tabular-nums">
                {{ underdogBands?.summary.profitable_bands ?? 0 }}
              </p>
            </div>
            <div class="rounded-md border p-3">
              <p class="text-xs text-muted-foreground">Melhor faixa (ROI)</p>
              <p class="text-sm font-semibold">
                {{ underdogBands?.summary.best_band?.band ?? '—' }}
                <span v-if="underdogBands?.summary.best_band" class="tabular-nums">
                  ({{ formatPercent(underdogBands.summary.best_band.theoretical_roi) }})
                </span>
              </p>
            </div>
          </div>

          <div class="overflow-x-auto rounded-md border">
            <table class="min-w-full text-sm">
              <thead class="bg-muted/50">
                <tr class="text-left text-xs uppercase tracking-wide text-muted-foreground">
                  <th class="px-3 py-2">Faixa</th>
                  <th class="px-3 py-2">Total</th>
                  <th class="px-3 py-2">Vitórias</th>
                  <th class="px-3 py-2">Derrotas</th>
                  <th class="px-3 py-2">Win rate</th>
                  <th class="px-3 py-2">Prob. implícita</th>
                  <th class="px-3 py-2">Edge</th>
                  <th class="px-3 py-2">ROI</th>
                  <th class="px-3 py-2">P/L</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="band in underdogBands?.bands ?? []"
                  :key="`ud-${band.band}`"
                  class="border-t"
                >
                  <td class="px-3 py-2 font-medium">{{ band.band }}</td>
                  <td class="px-3 py-2 tabular-nums">{{ band.total }}</td>
                  <td class="px-3 py-2 tabular-nums">{{ band.wins }}</td>
                  <td class="px-3 py-2 tabular-nums">{{ band.losses }}</td>
                  <td class="px-3 py-2 tabular-nums">{{ formatPercent(band.win_rate) }}</td>
                  <td class="px-3 py-2 tabular-nums">{{ formatPercent(band.implied_probability) }}</td>
                  <td
                    class="px-3 py-2 tabular-nums"
                    :class="band.edge_vs_implied >= 0 ? 'text-emerald-600' : 'text-destructive'"
                  >
                    {{ formatPercent(band.edge_vs_implied) }}
                  </td>
                  <td
                    class="px-3 py-2 tabular-nums"
                    :class="band.theoretical_roi >= 0 ? 'text-emerald-600' : 'text-destructive'"
                  >
                    {{ formatPercent(band.theoretical_roi) }}
                  </td>
                  <td
                    class="px-3 py-2 tabular-nums"
                    :class="band.profit_loss >= 0 ? 'text-emerald-600' : 'text-destructive'"
                  >
                    {{ formatDecimal(band.profit_loss, 2) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
