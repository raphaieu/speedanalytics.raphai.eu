<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { apiGet } from '@/composables/useApi';
import { formatDateTimeBr, formatRelativeBr } from '@/lib/format';
import { formatScheduleSlot, pilotPositionColorClass } from '@/lib/speedway';
import type { RaceDetail } from '@/types/race';
import PilotOddsGrid from '@/components/races/PilotOddsGrid.vue';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

const route = useRoute();
const loading = ref(true);
const error = ref<string | null>(null);
const race = ref<RaceDetail | null>(null);
const showRaw = ref(false);
const showGuide = ref(false);
const showPendingOdds = ref(false);
const showSettledOdds = ref(false);

const externalId = computed(() => route.params.externalId as string);
const scheduleLabel = computed(() =>
  race.value?.schedule_slot
    ?? formatScheduleSlot(race.value?.race_hour ?? null, race.value?.race_minute ?? null),
);

const oddsDiffer = computed(() => {
  if (!race.value?.pending_pilots?.length || !race.value.pilots.length) return false;
  return race.value.pending_pilots.some((p, i) => p.odd !== race.value?.pilots[i]?.odd);
});

const gridPilots = computed(() => race.value?.pending_pilots ?? race.value?.pilots ?? []);

onMounted(async () => {
  try {
    const res = await apiGet<{ data: RaceDetail }>(`/races/${externalId.value}`);
    race.value = res.data;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Corrida não encontrada';
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="space-y-4">
    <RouterLink
      to="/races"
      class="inline-block text-sm text-muted-foreground hover:text-foreground"
    >
      ← Corridas
    </RouterLink>

    <p v-if="loading" class="text-sm text-muted-foreground">Carregando…</p>
    <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>

    <template v-else-if="race">
      <div>
        <h1 class="text-xl font-semibold tabular-nums">{{ scheduleLabel }}</h1>
        <p class="text-xs text-muted-foreground">ID {{ race.external_id }}</p>
      </div>

      <button
        type="button"
        class="w-full rounded-lg border px-3 py-2 text-left text-sm text-muted-foreground"
        @click="showGuide = !showGuide"
      >
        Como ler esta corrida {{ showGuide ? '▴' : '▾' }}
      </button>
      <div v-if="showGuide" class="rounded-lg border bg-muted/30 px-3 py-2 text-xs leading-relaxed text-muted-foreground">
        <p><strong class="text-foreground">Forecast</strong> — 1º e 2º por menor odd (ex.: 1-4).</p>
        <p class="mt-1"><strong class="text-foreground">Tricast</strong> — 1º, 2º e 3º por menor odd.</p>
        <p class="mt-1"><strong class="text-foreground">Favorito</strong> — piloto com menor odd pré-corrida.</p>
        <p class="mt-1"><strong class="text-foreground">Zebra</strong> — piloto com maior odd pré-corrida (zebra vence quando esse piloto ganha).</p>
        <p class="mt-1"><strong class="text-foreground">Cores</strong> — P1 verde, P2 vermelho, P3 amarelo, P4 roxo.</p>
      </div>

      <Card v-if="race.odds_forecast">
        <CardHeader class="pb-2">
          <CardTitle class="text-base">Forecast por odds</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
          <div class="flex justify-between gap-2">
            <span class="text-muted-foreground">Forecast</span>
            <span class="font-mono font-medium">{{ race.odds_forecast }}</span>
          </div>
          <div v-if="race.odds_tricast" class="flex justify-between gap-2">
            <span class="text-muted-foreground">Tricast</span>
            <span class="font-mono font-medium">{{ race.odds_tricast }}</span>
          </div>
          <div v-if="race.favorite_position" class="flex justify-between gap-2">
            <span class="text-muted-foreground">Favorito</span>
            <span class="font-medium">P{{ race.favorite_position }}</span>
          </div>
          <p v-if="race.status === 'settled' && race.favorite_won === true" class="text-xs text-muted-foreground">
            Favorito venceu a corrida.
          </p>
          <p v-else-if="race.status === 'settled' && race.underdog_won === true" class="text-xs text-muted-foreground">
            Zebra venceu a corrida.
          </p>
          <p v-else-if="race.status === 'settled' && race.favorite_won === false" class="text-xs text-muted-foreground">
            Favorito não venceu.
          </p>
        </CardContent>
      </Card>

      <Card v-if="race.status === 'settled'">
        <CardHeader class="pb-2">
          <CardTitle class="text-base">Resultado</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="flex items-center gap-3">
            <span
              class="size-4 rounded-full"
              :class="pilotPositionColorClass(race.winner_position)"
            />
            <div>
              <p class="font-medium">
                P{{ race.winner_position }}
                <span v-if="race.winner_odd" class="font-mono text-muted-foreground">
                  @ {{ race.winner_odd }}
                </span>
              </p>
              <p v-if="race.pilot_name" class="text-sm text-muted-foreground">{{ race.pilot_name }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <div v-if="gridPilots.length" class="rounded-lg border">
        <button
          type="button"
          class="flex w-full items-center justify-between px-3 py-2.5 text-left text-sm"
          @click="showPendingOdds = !showPendingOdds"
        >
          <span class="font-medium">Odds pré-corrida</span>
          <span class="text-muted-foreground">{{ showPendingOdds ? '▴' : '▾' }}</span>
        </button>
        <div v-if="showPendingOdds" class="border-t px-3 py-3">
          <PilotOddsGrid :pilots="gridPilots" />
        </div>
      </div>

      <div
        v-if="race.pending_pilots && race.pilots.length"
        class="rounded-lg border"
      >
        <button
          type="button"
          class="flex w-full items-center justify-between px-3 py-2.5 text-left text-sm"
          @click="showSettledOdds = !showSettledOdds"
        >
          <span class="font-medium">
            Odds no encerramento
            <span v-if="!oddsDiffer" class="ml-1 text-xs font-normal text-muted-foreground">(iguais)</span>
          </span>
          <span class="text-muted-foreground">{{ showSettledOdds ? '▴' : '▾' }}</span>
        </button>
        <div v-if="showSettledOdds" class="border-t px-3 py-3">
          <PilotOddsGrid :pilots="race.pilots" />
        </div>
      </div>

      <div class="rounded-lg border px-3 py-3 text-sm">
        <p class="text-muted-foreground">Primeira captura</p>
        <p class="font-medium">{{ formatRelativeBr(race.timeline.first_seen_at) }}</p>
        <p class="text-xs text-muted-foreground tabular-nums">
          {{ formatDateTimeBr(race.timeline.first_seen_at) }}
        </p>
        <template v-if="race.timeline.settled_at">
          <p class="mt-2 text-muted-foreground">Encerrada</p>
          <p class="font-medium">{{ formatRelativeBr(race.timeline.settled_at) }}</p>
        </template>
      </div>

      <div class="rounded-lg border">
        <button
          type="button"
          class="flex w-full items-center justify-between px-3 py-2.5 text-left text-sm"
          @click="showRaw = !showRaw"
        >
          <span class="font-medium">Dados brutos (API)</span>
          <span class="text-muted-foreground">{{ showRaw ? '▴' : '▾' }}</span>
        </button>
        <div v-if="showRaw" class="space-y-2 border-t p-3">
          <div v-if="race.raw_pending_payload">
            <p class="mb-1 text-xs font-medium text-muted-foreground">raw_pending_payload</p>
            <pre class="overflow-x-auto rounded bg-muted p-2 text-[10px] leading-relaxed">{{ JSON.stringify(race.raw_pending_payload, null, 2) }}</pre>
          </div>
          <div v-if="race.raw_result_payload">
            <p class="mb-1 text-xs font-medium text-muted-foreground">raw_result_payload</p>
            <pre class="overflow-x-auto rounded bg-muted p-2 text-[10px] leading-relaxed">{{ JSON.stringify(race.raw_result_payload, null, 2) }}</pre>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
