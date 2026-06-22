<script setup lang="ts">
import { computed } from 'vue';
import { PILOT_POSITION_COLORS, parseOrderPositions, pilotPositionColorClass } from '@/lib/speedway';

const props = defineProps<{
  positions: number[];
  odd?: string | null;
  label?: string | null;
}>();

const chips = computed(() =>
  props.positions.map((position) => ({
    position,
    colorName: PILOT_POSITION_COLORS[position] ?? null,
  })),
);
</script>

<template>
  <span class="inline-flex flex-wrap items-center gap-1">
    <template v-for="(chip, index) in chips" :key="`${chip.position}-${index}`">
      <span v-if="index > 0" class="text-muted-foreground/70">—</span>
      <span
        class="inline-flex items-center gap-1 rounded-md border border-border/60 bg-muted/40 px-1.5 py-0.5 font-mono text-xs tabular-nums"
        :title="chip.colorName ?? undefined"
      >
        <span
          class="size-2.5 shrink-0 rounded-full"
          :class="pilotPositionColorClass(chip.position)"
        />
        P{{ chip.position }}
      </span>
    </template>
    <span v-if="odd" class="text-muted-foreground tabular-nums">@{{ odd }}</span>
  </span>
</template>
