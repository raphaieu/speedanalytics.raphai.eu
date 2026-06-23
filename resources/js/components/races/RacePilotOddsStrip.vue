<script setup lang="ts">
import { computed } from 'vue';
import { parsePilotOdds, pilotPositionColorClass } from '@/lib/speedway';

const props = defineProps<{
  oddsRaw: string | null;
  winnerPosition?: number | null;
}>();

const pilots = computed(() => parsePilotOdds(props.oddsRaw));
</script>

<template>
  <div v-if="pilots.length" class="grid grid-cols-4 gap-1">
    <div
      v-for="pilot in pilots"
      :key="pilot.position"
      class="flex items-center justify-center gap-1 rounded border px-1 py-0.5 font-mono text-[11px] tabular-nums"
      :class="
        winnerPosition === pilot.position
          ? 'border-primary bg-primary/10 font-semibold ring-1 ring-primary/40'
          : 'border-border/60 bg-muted/20 text-muted-foreground'
      "
    >
      <span
        class="size-2 shrink-0 rounded-full"
        :class="pilotPositionColorClass(pilot.position)"
      />
      <span>{{ pilot.odd }}</span>
    </div>
  </div>
</template>
