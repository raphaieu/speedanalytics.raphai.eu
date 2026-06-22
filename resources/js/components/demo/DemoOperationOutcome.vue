<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import PilotOrderChips from '@/components/demo/PilotOrderChips.vue';
import { Badge } from '@/components/ui/badge';
import { formatScheduleSlot, parseOrderPositions } from '@/lib/speedway';
import type { DemoOperation } from '@/types/demo';

const props = defineProps<{
  operation: DemoOperation;
}>();

const marketLabels: Record<DemoOperation['market_type'], string> = {
  winner: 'Winner',
  forecast: 'Forecast',
  tricast: 'Tricast',
};

const entryPositions = computed(() => {
  if (props.operation.market_type === 'winner') {
    return props.operation.entry_position ? [props.operation.entry_position] : [];
  }

  const order = props.operation.entry_payload_json.order;
  return typeof order === 'string' ? parseOrderPositions(order) : [];
});

const resultPositions = computed(() => {
  const race = props.operation.race;
  if (!race || race.status !== 'settled') {
    return [];
  }

  if (race.result_tricast_order) {
    return parseOrderPositions(race.result_tricast_order);
  }

  if (race.result_forecast_order) {
    return parseOrderPositions(race.result_forecast_order);
  }

  return race.winner_position ? [race.winner_position] : [];
});

const isZebraBet = computed(() => {
  if (props.operation.market_type !== 'winner') {
    return false;
  }

  const entryPosition = props.operation.entry_position;
  const underdogPosition = props.operation.race?.underdog_position;

  return entryPosition !== null
    && entryPosition !== undefined
    && underdogPosition !== null
    && underdogPosition !== undefined
    && entryPosition === underdogPosition;
});

const scheduleLabel = computed(() => {
  const race = props.operation.race;
  if (!race) {
    return null;
  }

  return formatScheduleSlot(
    race.race_hour !== null ? String(race.race_hour) : null,
    race.race_minute !== null ? String(race.race_minute) : null,
  );
});

const resultScopeLabel = computed(() => {
  const race = props.operation.race;
  if (!race || race.status !== 'settled') {
    return null;
  }

  if (race.result_tricast_order) {
    return 'Top 3';
  }

  if (race.result_forecast_order) {
    return 'Top 2';
  }

  return 'Vencedor';
});

const awaitingResult = computed(
  () => props.operation.status === 'open' && props.operation.race?.status !== 'settled',
);

const resultPendingSettlement = computed(
  () => props.operation.status === 'open' && props.operation.race?.status === 'settled',
);
</script>

<template>
  <div class="space-y-2">
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div class="space-y-1">
        <div class="flex flex-wrap items-center gap-1.5">
          <span class="text-sm font-medium">#{{ operation.id }}</span>
          <span class="text-sm text-muted-foreground">·</span>
          <span class="text-sm font-medium">{{ marketLabels[operation.market_type] }}</span>
          <Badge v-if="isZebraBet" variant="outline" class="text-[10px]">Zebra</Badge>
        </div>

        <p v-if="operation.race" class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
          <span class="tabular-nums">{{ scheduleLabel }}</span>
          <span>·</span>
          <RouterLink
            :to="{ name: 'race-detail', params: { externalId: operation.race.external_id } }"
            class="font-mono text-primary underline-offset-2 hover:underline"
          >
            {{ operation.race.external_id }}
          </RouterLink>
        </p>
        <p v-else class="text-xs text-muted-foreground">Sem corrida vinculada</p>
      </div>

      <slot name="badges" />
    </div>

    <div class="grid gap-2 sm:grid-cols-2">
      <div class="rounded-md border border-border/70 bg-muted/20 px-2.5 py-2">
        <p class="mb-1.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
          Meu palpite
        </p>
        <PilotOrderChips
          v-if="entryPositions.length > 0"
          :positions="entryPositions"
          :odd="operation.entry_odd"
        />
        <p v-else class="text-xs text-muted-foreground">Entrada não registrada</p>
      </div>

      <div class="rounded-md border border-border/70 bg-muted/20 px-2.5 py-2">
        <p class="mb-1.5 flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
          Resultado real
          <span v-if="resultScopeLabel" class="normal-case tracking-normal">({{ resultScopeLabel }})</span>
        </p>
        <PilotOrderChips
          v-if="resultPositions.length > 0"
          :positions="resultPositions"
        />
        <p v-else-if="awaitingResult" class="text-xs text-muted-foreground">
          Aguardando corrida
        </p>
        <p v-else-if="resultPendingSettlement" class="text-xs text-amber-700 dark:text-amber-300">
          Corrida encerrada — liquidação pendente
        </p>
        <p v-else class="text-xs text-muted-foreground">Resultado indisponível</p>
      </div>
    </div>
  </div>
</template>
