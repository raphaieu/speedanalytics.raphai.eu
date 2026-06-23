<script setup lang="ts">
import { computed } from 'vue';
import type { RaceSummary } from '@/types/race';
import { parseOrderPositions, parsePilotOdds, pilotPositionColorClass } from '@/lib/speedway';

const props = defineProps<{
  race: RaceSummary;
}>();

const isSettled = computed(() => props.race.status === 'settled');

const favoriteOdd = computed(() => {
  if (props.race.favorite_odd) return props.race.favorite_odd;
  const pos = props.race.favorite_position;
  if (!pos) return null;
  return parsePilotOdds(props.race.pilot_odds_raw).find((p) => p.position === pos)?.odd ?? null;
});

const zebraPosition = computed(() => props.race.underdog_position ?? null);

const zebraOdd = computed(() => {
  if (props.race.underdog_odd) return props.race.underdog_odd;
  const pos = zebraPosition.value;
  if (!pos) return null;
  return parsePilotOdds(props.race.pilot_odds_raw).find((p) => p.position === pos)?.odd ?? null;
});

function pickClass(hit: boolean | null | undefined): string {
  if (!isSettled.value || hit == null) {
    return 'border-border/60 bg-muted/30 text-foreground';
  }
  return hit
    ? 'border-emerald-500/50 bg-emerald-500/10 text-foreground'
    : 'border-border/40 bg-muted/20 text-muted-foreground opacity-70';
}

function positions(order: string | null | undefined): number[] {
  return parseOrderPositions(order);
}
</script>

<template>
  <div class="flex flex-wrap gap-1">
    <span
      v-if="race.favorite_position"
      class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[10px] font-medium"
      :class="pickClass(race.favorite_won)"
    >
      <span class="text-muted-foreground">Fav</span>
      <span
        class="size-2 shrink-0 rounded-full"
        :class="pilotPositionColorClass(race.favorite_position)"
      />
      <span class="font-mono tabular-nums">P{{ race.favorite_position }}</span>
      <span v-if="favoriteOdd" class="font-mono tabular-nums text-muted-foreground">@{{ favoriteOdd }}</span>
    </span>

    <span
      v-if="race.odds_forecast"
      class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[10px] font-medium"
      :class="pickClass(race.forecast_hit)"
    >
      <span class="text-muted-foreground">Fc</span>
      <template v-for="(pos, index) in positions(race.odds_forecast)" :key="`fc-${pos}`">
        <span v-if="index > 0" class="text-muted-foreground/60">-</span>
        <span class="inline-flex items-center gap-0.5">
          <span class="size-2 rounded-full" :class="pilotPositionColorClass(pos)" />
          <span class="font-mono tabular-nums">{{ pos }}</span>
        </span>
      </template>
    </span>

    <span
      v-if="race.odds_tricast"
      class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[10px] font-medium"
      :class="pickClass(race.tricast_exact_hit)"
    >
      <span class="text-muted-foreground">Tc</span>
      <template v-for="(pos, index) in positions(race.odds_tricast)" :key="`tc-${pos}`">
        <span v-if="index > 0" class="text-muted-foreground/60">-</span>
        <span class="inline-flex items-center gap-0.5">
          <span class="size-2 rounded-full" :class="pilotPositionColorClass(pos)" />
          <span class="font-mono tabular-nums">{{ pos }}</span>
        </span>
      </template>
    </span>

    <span
      v-if="zebraPosition"
      class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[10px] font-medium"
      :class="pickClass(race.underdog_won)"
    >
      <span class="text-muted-foreground">Zeb</span>
      <span class="size-2 shrink-0 rounded-full" :class="pilotPositionColorClass(zebraPosition)" />
      <span class="font-mono tabular-nums">P{{ zebraPosition }}</span>
      <span v-if="zebraOdd" class="font-mono tabular-nums text-muted-foreground">@{{ zebraOdd }}</span>
    </span>
  </div>
</template>
