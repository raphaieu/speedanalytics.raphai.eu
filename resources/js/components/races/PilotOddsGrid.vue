<script setup lang="ts">
import type { PilotRow } from '@/types/race';
import { pilotPositionColorClass } from '@/lib/speedway';

defineProps<{
  pilots: PilotRow[];
}>();
</script>

<template>
  <div class="grid grid-cols-4 gap-1.5">
    <div
      v-for="pilot in pilots"
      :key="pilot.position"
      class="rounded-md border px-1.5 py-2 text-center"
      :class="{
        'border-primary bg-primary/5 ring-1 ring-primary/30': pilot.is_winner,
        'border-amber-400/60 bg-amber-50/50 dark:bg-amber-950/20': pilot.is_favorite && !pilot.is_winner,
      }"
    >
      <div class="flex items-center justify-center gap-1">
        <span
          class="size-2 rounded-full"
          :class="pilotPositionColorClass(pilot.position)"
        />
        <span class="text-xs font-medium text-muted-foreground">P{{ pilot.position }}</span>
      </div>
      <p class="mt-1 font-mono text-sm font-semibold tabular-nums">{{ pilot.odd }}</p>
      <p v-if="pilot.is_favorite" class="mt-0.5 text-[10px] text-amber-600">fav</p>
      <p v-if="pilot.is_winner" class="mt-0.5 text-[10px] font-medium text-primary">venceu</p>
    </div>
  </div>
</template>
