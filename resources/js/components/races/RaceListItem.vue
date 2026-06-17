<script setup lang="ts">
import { RouterLink } from 'vue-router';
import type { RaceSummary } from '@/types/race';
import { formatScheduleSlot, parsePilotOdds, pilotPositionColorClass } from '@/lib/speedway';

defineProps<{
  race: RaceSummary;
}>();

function scheduleLabel(race: RaceSummary) {
  return race.schedule_slot ?? formatScheduleSlot(race.race_hour, race.race_minute);
}
</script>

<template>
  <RouterLink
    :to="{ name: 'race-detail', params: { externalId: race.external_id } }"
    class="flex items-start gap-3 px-3 py-2.5 transition hover:bg-muted/40 active:bg-muted/60"
  >
    <span class="w-11 shrink-0 font-mono text-sm font-semibold tabular-nums">
      {{ scheduleLabel(race) }}
    </span>

    <div class="min-w-0 flex-1 space-y-0.5">
      <div v-if="race.status === 'settled'" class="flex items-center gap-2">
        <span
          class="size-2.5 shrink-0 rounded-full"
          :class="pilotPositionColorClass(race.winner_position)"
        />
        <p class="truncate text-sm font-medium">
          P{{ race.winner_position }}
          <span v-if="race.pilot_name" class="font-normal text-muted-foreground">
            · {{ race.pilot_name }}
          </span>
          <span v-if="race.winner_odd" class="font-mono text-xs text-muted-foreground">
            @ {{ race.winner_odd }}
          </span>
        </p>
      </div>

      <div v-else-if="race.pilot_odds_raw" class="space-y-1">
        <div class="flex flex-wrap gap-x-3 gap-y-1">
          <span
            v-for="pilot in parsePilotOdds(race.pilot_odds_raw)"
            :key="pilot.position"
            class="inline-flex items-center gap-1.5 font-mono text-xs tabular-nums"
            :class="pilot.position === race.favorite_position ? 'font-semibold' : 'text-muted-foreground'"
          >
            <span
              class="size-2.5 shrink-0 rounded-full"
              :class="pilotPositionColorClass(pilot.position)"
            />
            {{ pilot.odd }}
          </span>
        </div>
        <p v-if="race.odds_forecast" class="flex items-center gap-1 text-xs text-muted-foreground">
          <span>Fc</span>
          <template
            v-for="(pos, index) in race.odds_forecast.split('-')"
            :key="`${race.external_id}-fc-${pos}`"
          >
            <span v-if="index > 0">-</span>
            <span class="inline-flex items-center gap-0.5">
              <span
                class="size-2 rounded-full"
                :class="pilotPositionColorClass(Number(pos))"
              />
              {{ pos }}
            </span>
          </template>
        </p>
      </div>

      <p
        v-if="race.status === 'settled' && race.odds_forecast"
        class="truncate text-xs text-muted-foreground"
      >
        Fc {{ race.odds_forecast }}
        <span v-if="race.favorite_won === true">· fav venceu</span>
        <span v-else-if="race.favorite_won === false">· zebra</span>
      </p>
    </div>
  </RouterLink>
</template>
