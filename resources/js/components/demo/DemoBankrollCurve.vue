<script setup lang="ts">
import { computed } from 'vue';
import { formatUnits } from '@/lib/format';
import type { DemoBankrollCurve } from '@/types/demo';

const props = defineProps<{
  curve: DemoBankrollCurve | null;
}>();

const chart = computed(() => {
  const curve = props.curve;
  if (!curve || curve.points.length < 2) {
    return null;
  }

  const balances = curve.points.map((point) => Number.parseFloat(point.balance));
  const minBalance = Math.min(...balances, Number.parseFloat(curve.initial_balance));
  const maxBalance = Math.max(...balances, Number.parseFloat(curve.initial_balance));
  const padding = Math.max((maxBalance - minBalance) * 0.08, 0.5);
  const yMin = minBalance - padding;
  const yMax = maxBalance + padding;
  const yRange = yMax - yMin || 1;

  const width = 320;
  const height = 96;
  const left = 8;
  const right = width - 8;
  const top = 8;
  const bottom = height - 8;

  const coordinates = curve.points.map((point, index) => {
    const x = left + (index / (curve.points.length - 1)) * (right - left);
    const balance = Number.parseFloat(point.balance);
    const y = bottom - ((balance - yMin) / yRange) * (bottom - top);

    return { x, y, balance };
  });

  const linePath = coordinates
    .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`)
    .join(' ');

  const areaPath = `${linePath} L ${coordinates[coordinates.length - 1].x.toFixed(2)} ${bottom} L ${coordinates[0].x.toFixed(2)} ${bottom} Z`;

  const initialBalance = Number.parseFloat(curve.initial_balance);
  const initialY = bottom - ((initialBalance - yMin) / yRange) * (bottom - top);
  const currentBalance = Number.parseFloat(curve.current_balance);
  const delta = currentBalance - initialBalance;

  return {
    width,
    height,
    linePath,
    areaPath,
    initialY,
    delta,
    currentBalance,
    initialBalance,
    isUp: delta >= 0,
  };
});
</script>

<template>
  <div v-if="chart" class="space-y-2">
    <div class="flex items-baseline justify-between gap-3 text-xs">
      <span class="text-muted-foreground">Curva da banca</span>
      <span
        class="tabular-nums font-medium"
        :class="chart.isUp ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'"
      >
        {{ chart.isUp ? '+' : '' }}{{ formatUnits(chart.delta) }} vs inicial
      </span>
    </div>

    <svg
      :viewBox="`0 0 ${chart.width} ${chart.height}`"
      class="h-24 w-full text-primary"
      role="img"
      aria-label="Curva da banca demo"
    >
      <defs>
        <linearGradient id="demo-bankroll-fill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="currentColor" stop-opacity="0.18" />
          <stop offset="100%" stop-color="currentColor" stop-opacity="0.02" />
        </linearGradient>
      </defs>

      <line
        :x1="8"
        :y1="chart.initialY"
        :x2="chart.width - 8"
        :y2="chart.initialY"
        class="stroke-muted-foreground/40"
        stroke-width="1"
        stroke-dasharray="4 3"
      />

      <path :d="chart.areaPath" fill="url(#demo-bankroll-fill)" />
      <path
        :d="chart.linePath"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linejoin="round"
        stroke-linecap="round"
      />
    </svg>

    <div class="flex justify-between text-[11px] text-muted-foreground tabular-nums">
      <span>Inicial {{ formatUnits(chart.initialBalance) }}</span>
      <span>Atual {{ formatUnits(chart.currentBalance) }}</span>
    </div>
  </div>

  <p v-else class="text-xs text-muted-foreground">
    A curva aparece após movimentações na banca.
  </p>
</template>
