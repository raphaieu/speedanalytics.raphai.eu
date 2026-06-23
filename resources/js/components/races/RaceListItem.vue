<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import type { RaceSummary } from '@/types/race';
import { formatCountdown } from '@/lib/format';
import { formatScheduleSlot } from '@/lib/speedway';
import RaceMarketPicks from '@/components/races/RaceMarketPicks.vue';
import RacePilotOddsStrip from '@/components/races/RacePilotOddsStrip.vue';

const props = defineProps<{
  race: RaceSummary;
}>();

const scheduleLabel = computed(
  () =>
    props.race.timing?.starts_at_label
    ?? props.race.schedule_slot
    ?? formatScheduleSlot(props.race.race_hour, props.race.race_minute),
);

const timingHint = computed((): string | null => {
  if (props.race.status !== 'pending' || !props.race.timing) return null;
  if (props.race.timing.timing_status === 'upcoming') {
    return formatCountdown(props.race.timing.seconds_to_start);
  }
  if (props.race.timing.timing_status === 'live') return 'Ao vivo';
  if (props.race.timing.timing_status === 'late') return 'Atrasada';
  return null;
});

const hasOdds = computed(() => Boolean(props.race.pilot_odds_raw));
</script>

<template>
  <RouterLink
    :to="{ name: 'race-detail', params: { externalId: race.external_id } }"
    class="flex items-start gap-2.5 px-3 py-2 transition hover:bg-muted/40 active:bg-muted/60"
  >
    <div class="w-11 shrink-0 pt-0.5">
      <p class="font-mono text-sm font-semibold tabular-nums leading-none">
        {{ scheduleLabel }}
      </p>
      <p
        v-if="timingHint"
        class="mt-1 font-mono text-[10px] tabular-nums text-muted-foreground"
      >
        {{ timingHint }}
      </p>
    </div>

    <div v-if="hasOdds" class="min-w-0 flex-1 space-y-1">
      <RacePilotOddsStrip
        :odds-raw="race.pilot_odds_raw"
        :winner-position="race.status === 'settled' ? race.winner_position : null"
      />
      <RaceMarketPicks :race="race" />
    </div>

    <p v-else class="py-1 text-xs text-muted-foreground">Sem odds</p>
  </RouterLink>
</template>
