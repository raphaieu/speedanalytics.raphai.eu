<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { apiGet, apiPost } from '@/composables/useApi';
import { formatCountdown, formatDateTimeBr, formatRelativeBr, formatSecondsAgo, formatUnits, isSameDayBr } from '@/lib/format';
import { formatScheduleSlot, PILOT_POSITION_COLORS } from '@/lib/speedway';
import type {
  DemoAccount,
  DemoBankrollCurve as DemoBankrollCurveData,
  DemoOperation,
  PendingDemoRace,
  PendingDemoRacesResponse,
  PricingStatus,
  QuickEntry,
  QuickPresetId,
} from '@/types/demo';
import DemoBankrollCurve from '@/components/demo/DemoBankrollCurve.vue';
import DemoOperationOutcome from '@/components/demo/DemoOperationOutcome.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';

const JOURNAL_TAG_SUGGESTIONS = [
  'entrada válida',
  'entrada fora da regra',
  'setup respeitado',
  'overtrade',
  'FOMO',
  'revanche',
  'boa execução',
  'má execução',
];

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);
const account = ref<DemoAccount | null>(null);
const bankrollCurve = ref<DemoBankrollCurveData | null>(null);
const openOperations = ref<DemoOperation[]>([]);
const settledOperations = ref<DemoOperation[]>([]);
const activeTab = ref<'open' | 'settled'>('open');
const pendingRaces = ref<PendingDemoRace[]>([]);
const pendingMeta = ref<{ stale_pending?: number; actionable?: number }>({});
const selectedRace = ref<PendingDemoRace | null>(null);
const contextSnapshot = ref<Record<string, unknown> | null>(null);
const collectorStatus = ref<{
  effective_status?: string;
  last_payload_at?: string | null;
  payload_age_seconds?: number | null;
  is_payload_stale?: boolean;
} | null>(null);
const lastRefreshAt = ref<Date | null>(null);

const pricingMeta = reactive({
  pricing_status: null as PricingStatus | null,
  estimated_entry_odd: null as number | null,
  selected_quick_entry_label: null as string | null,
});

const QUICK_PRESETS: Array<{ id: QuickPresetId; label: string }> = [
  { id: 'winner_favorite', label: 'Winner favorito' },
  { id: 'winner_underdog', label: 'Winner zebra' },
  { id: 'forecast_suggested', label: 'Forecast sugerido' },
  { id: 'tricast_suggested', label: 'Tricast sugerido' },
];

const bankrollForm = reactive({
  amount: '',
  description: '',
});

const operationForm = reactive({
  speedway_race_id: '',
  market_type: 'winner' as 'winner' | 'forecast' | 'tricast',
  bet_type: 'single' as 'single' | 'combo',
  stake_amount: '1',
  entry_odd: '',
  entry_position: '',
  entry_color: '',
  order: '',
  risk_enforced: true,
  after_stop: false,
  tags: '',
  note: '',
});

const settleTarget = ref<DemoOperation | null>(null);
const settleForm = reactive({
  result: 'win' as 'win' | 'loss' | 'void',
});

const settlementPreview = computed(() => {
  if (!settleTarget.value) {
    return null;
  }

  const stake = Number.parseFloat(settleTarget.value.stake_amount);
  const odd = Number.parseFloat(settleTarget.value.entry_odd ?? '0');

  if (settleForm.result === 'loss') {
    return {
      stakeDebited: stake,
      settlementCredit: 0,
      finalPl: -stake,
    };
  }

  if (settleForm.result === 'void') {
    return {
      stakeDebited: stake,
      settlementCredit: stake,
      finalPl: 0,
    };
  }

  const grossReturn = stake * odd;
  const netProfit = grossReturn - stake;

  return {
    stakeDebited: stake,
    grossReturn,
    netProfit,
    settlementCredit: grossReturn,
    finalPl: netProfit,
  };
});

const marketLabels: Record<string, string> = {
  winner: 'Winner',
  forecast: 'Forecast',
  tricast: 'Tricast',
};

const resultVariant = (result: DemoOperation['result']) => {
  if (result === 'win') return 'default';
  if (result === 'loss') return 'destructive';
  if (result === 'void') return 'secondary';
  return 'outline';
};

const resultLabel = (result: DemoOperation['result']) => {
  if (result === 'win') return 'Green';
  if (result === 'loss') return 'Red';
  if (result === 'void') return 'Void';
  return 'Aberta';
};

const displayedOperations = computed(() =>
  activeTab.value === 'open' ? openOperations.value : settledOperations.value,
);

const hasAwaitingAutoSettlement = computed(() =>
  openOperations.value.some((operation) => operation.race?.status === 'settled'),
);

const settledSummary = computed(() => {
  const wins = settledOperations.value.filter((operation) => operation.result === 'win').length;
  const losses = settledOperations.value.filter((operation) => operation.result === 'loss').length;
  const totalPl = settledOperations.value.reduce(
    (sum, operation) => sum + Number.parseFloat(operation.profit_loss ?? '0'),
    0,
  );

  return { wins, losses, totalPl, count: settledOperations.value.length };
});

const dailyPl = computed(() =>
  settledOperations.value
    .filter((operation) => isSameDayBr(operation.settled_at))
    .reduce((sum, operation) => sum + Number.parseFloat(operation.profit_loss ?? '0'), 0),
);

const nextActionableRace = computed(() => pendingRaces.value[0] ?? null);
const liveRace = computed(
  () => pendingRaces.value.find((race) => race.timing_status === 'live') ?? null,
);

const entryPreview = computed(() => {
  const stake = Number.parseFloat(operationForm.stake_amount);
  const odd = operationForm.entry_odd ? Number.parseFloat(operationForm.entry_odd) : null;
  const hasValidStake = !Number.isNaN(stake) && stake > 0;
  const grossReturn = odd && !Number.isNaN(odd) ? stake * odd : null;

  return {
    market: marketLabels[operationForm.market_type],
    entry:
      operationForm.market_type === 'winner'
        ? operationForm.entry_position
          ? `P${operationForm.entry_position}`
          : '—'
        : operationForm.order || '—',
    odd: odd && !Number.isNaN(odd) ? odd.toFixed(2) : null,
    stake: hasValidStake ? stake.toFixed(2) : null,
    grossReturn: grossReturn !== null ? grossReturn.toFixed(2) : null,
    missingOddWarning:
      operationForm.market_type !== 'winner' && (!odd || Number.isNaN(odd))
        ? 'Sem odd: resultado não terá P/L confiável'
        : null,
  };
});

const refreshAgeLabel = computed(() => {
  if (!lastRefreshAt.value) return null;
  const seconds = Math.round((Date.now() - lastRefreshAt.value.getTime()) / 1000);
  return formatSecondsAgo(seconds);
});

let operationsPollTimer: ReturnType<typeof setInterval> | null = null;
let pendingPollTimer: ReturnType<typeof setInterval> | null = null;
let collectorPollTimer: ReturnType<typeof setInterval> | null = null;

const oddHint = computed(() => {
  switch (pricingMeta.pricing_status) {
    case 'observed':
      return 'Observada (odd pré-corrida do piloto)';
    case 'estimated':
      return 'Estimativa editável — altere se tiver a odd real da casa';
    case 'manual':
      return 'Manual (odd real informada por você)';
    case 'unavailable':
      return 'Sem odd — potencial não calculado';
    default:
      return null;
  }
});

const selectedQuickEntries = computed(() => selectedRace.value?.quick_entries ?? []);

const primaryQuickEntries = computed(() =>
  selectedQuickEntries.value.filter((entry) => entry.tier === 'primary'),
);

const alternateQuickEntries = computed(() =>
  selectedQuickEntries.value.filter((entry) => entry.tier === 'alternate'),
);

function parseTags(raw: string): string[] {
  return raw
    .split(',')
    .map((tag) => tag.trim())
    .filter(Boolean);
}

function resetPricingMeta() {
  pricingMeta.pricing_status = null;
  pricingMeta.estimated_entry_odd = null;
  pricingMeta.selected_quick_entry_label = null;
}

function selectRace(race: PendingDemoRace) {
  selectedRace.value = race;
  operationForm.speedway_race_id = String(race.id);
  contextSnapshot.value = {
    ...race,
    source: 'demo_manual_pending_picker',
    captured_at: new Date().toISOString(),
  };
  resetPricingMeta();
}

function applyQuickEntry(entry: QuickEntry) {
  operationForm.market_type = entry.market_type;
  operationForm.bet_type = 'single';
  pricingMeta.pricing_status = entry.pricing_status;
  pricingMeta.estimated_entry_odd = entry.pricing_status === 'estimated' ? entry.entry_odd : null;
  pricingMeta.selected_quick_entry_label = entry.label;

  if (entry.market_type === 'winner') {
    operationForm.entry_position = entry.entry_position ? String(entry.entry_position) : '';
    operationForm.entry_color = entry.entry_position
      ? PILOT_POSITION_COLORS[entry.entry_position] ?? ''
      : '';
    operationForm.order = entry.order;
    operationForm.entry_odd = entry.entry_odd !== null ? String(entry.entry_odd) : '';
  } else {
    clearWinnerFields();
    operationForm.order = entry.order;
    operationForm.entry_odd = entry.entry_odd !== null ? String(entry.entry_odd) : '';
  }
}

function clearSelectedRace() {
  selectedRace.value = null;
  operationForm.speedway_race_id = '';
  contextSnapshot.value = null;
  resetPricingMeta();
}

function clearWinnerFields() {
  operationForm.entry_position = '';
  operationForm.entry_color = '';
}

function onEntryOddInput() {
  const parsed = operationForm.entry_odd ? Number.parseFloat(operationForm.entry_odd) : null;

  if (parsed === null || Number.isNaN(parsed)) {
    if (operationForm.market_type === 'winner') {
      pricingMeta.pricing_status = 'unavailable';
    } else if (pricingMeta.estimated_entry_odd !== null) {
      pricingMeta.pricing_status = 'unavailable';
    }
    return;
  }

  if (pricingMeta.pricing_status === 'estimated' && pricingMeta.estimated_entry_odd !== null) {
    pricingMeta.pricing_status = parsed === pricingMeta.estimated_entry_odd ? 'estimated' : 'manual';
    return;
  }

  if (operationForm.market_type === 'winner') {
    pricingMeta.pricing_status = 'observed';
  } else if (pricingMeta.pricing_status !== 'observed') {
    pricingMeta.pricing_status = 'manual';
  }
}

function applyQuickPreset(preset: QuickPresetId) {
  const entry = selectedRace.value?.quick_entries?.find((item) => item.id === preset);
  if (entry) {
    applyQuickEntry(entry);
    return;
  }

  const race = selectedRace.value;
  if (!race) return;

  switch (preset) {
    case 'winner_favorite':
      applyQuickEntry({
        id: preset,
        label: 'Winner favorito',
        tier: 'primary',
        market_type: 'winner',
        bet_type: 'single',
        order: String(race.rank_1_position ?? ''),
        entry_position: race.rank_1_position ?? undefined,
        entry_odd: race.rank_1_odd ? Number.parseFloat(race.rank_1_odd) : null,
        pricing_status: 'observed',
      });
      break;
    case 'winner_underdog':
      applyQuickEntry({
        id: preset,
        label: 'Winner zebra',
        tier: 'primary',
        market_type: 'winner',
        bet_type: 'single',
        order: String(race.rank_4_position ?? ''),
        entry_position: race.rank_4_position ?? undefined,
        entry_odd: race.rank_4_odd ? Number.parseFloat(race.rank_4_odd) : null,
        pricing_status: 'observed',
      });
      break;
    case 'forecast_suggested':
      if (race.market_rank_forecast_order) {
        applyQuickEntry({
          id: preset,
          label: `Forecast ${race.market_rank_forecast_order}`,
          tier: 'primary',
          market_type: 'forecast',
          bet_type: 'single',
          order: race.market_rank_forecast_order,
          entry_odd: null,
          pricing_status: 'unavailable',
        });
      }
      break;
    case 'tricast_suggested':
      if (race.market_rank_tricast_order) {
        applyQuickEntry({
          id: preset,
          label: `Tricast ${race.market_rank_tricast_order}`,
          tier: 'primary',
          market_type: 'tricast',
          bet_type: 'single',
          order: race.market_rank_tricast_order,
          entry_odd: null,
          pricing_status: 'unavailable',
        });
      }
      break;
  }
}

function raceScheduleLabel(race: PendingDemoRace): string {
  return race.schedule_slot
    ?? formatScheduleSlot(
      race.race_hour !== null ? String(race.race_hour) : null,
      race.race_minute !== null ? String(race.race_minute) : null,
    );
}

function buildEntryPayload(): Record<string, unknown> {
  const odd = operationForm.entry_odd ? Number.parseFloat(operationForm.entry_odd) : undefined;
  const pricingStatus: PricingStatus = pricingMeta.pricing_status
    ?? (odd !== undefined && !Number.isNaN(odd) ? 'manual' : 'unavailable');

  const base: Record<string, unknown> = {
    pricing_status: pricingStatus,
  };

  if (pricingMeta.estimated_entry_odd !== null) {
    base.estimated_entry_odd = pricingMeta.estimated_entry_odd;
  }
  if (pricingMeta.selected_quick_entry_label) {
    base.selected_quick_entry_label = pricingMeta.selected_quick_entry_label;
  }

  if (operationForm.market_type === 'winner') {
    const position = operationForm.entry_position
      ? Number.parseInt(operationForm.entry_position, 10)
      : undefined;

    return {
      ...base,
      order: position ? String(position) : operationForm.order || undefined,
      position,
      color: operationForm.entry_color || undefined,
      odd,
    };
  }

  return {
    ...base,
    order: operationForm.order || undefined,
    odd,
  };
}

async function loadBankrollCurve() {
  const curveRes = await apiGet<{ data: DemoBankrollCurveData }>('/demo/account/bankroll-curve');
  bankrollCurve.value = curveRes.data;
}

async function refreshPendingRacesQuietly() {
  try {
    const pendingRes = await apiGet<PendingDemoRacesResponse>('/demo/pending-races?limit=12');
    pendingRaces.value = pendingRes.data;
    pendingMeta.value = pendingRes.meta;

    if (
      selectedRace.value
      && !pendingRaces.value.some((race) => race.id === selectedRace.value?.id)
    ) {
      clearSelectedRace();
    }

    lastRefreshAt.value = new Date();
  } catch {
    // polling silencioso
  }
}

async function refreshCollectorStatusQuietly() {
  try {
    collectorStatus.value = await apiGet('/collector/status');
  } catch {
    // polling silencioso
  }
}

async function refreshOperationsQuietly() {
  try {
    const [accountRes, openRes, settledRes, curveRes] = await Promise.all([
      apiGet<{ data: DemoAccount }>('/demo/account'),
      apiGet<{ data: DemoOperation[] }>('/demo/operations?status=open'),
      apiGet<{ data: DemoOperation[] }>('/demo/operations?status=settled'),
      apiGet<{ data: DemoBankrollCurveData }>('/demo/account/bankroll-curve'),
    ]);

    account.value = accountRes.data;
    openOperations.value = openRes.data;
    settledOperations.value = settledRes.data;
    bankrollCurve.value = curveRes.data;
    lastRefreshAt.value = new Date();
  } catch {
    // polling silencioso
  }
}

function stopPolling() {
  if (operationsPollTimer !== null) {
    clearInterval(operationsPollTimer);
    operationsPollTimer = null;
  }
  if (pendingPollTimer !== null) {
    clearInterval(pendingPollTimer);
    pendingPollTimer = null;
  }
  if (collectorPollTimer !== null) {
    clearInterval(collectorPollTimer);
    collectorPollTimer = null;
  }
}

function startPolling() {
  stopPolling();

  operationsPollTimer = setInterval(() => {
    void refreshOperationsQuietly();
  }, 12_000);

  pendingPollTimer = setInterval(() => {
    void refreshPendingRacesQuietly();
  }, 12_000);

  collectorPollTimer = setInterval(() => {
    void refreshCollectorStatusQuietly();
  }, 20_000);
}

function stopOperationsPolling() {
  stopPolling();
}

function syncOperationsPolling() {
  startPolling();
}

async function loadData() {
  loading.value = true;
  error.value = null;

  try {
    const [accountRes, openRes, settledRes, pendingRes, curveRes] = await Promise.all([
      apiGet<{ data: DemoAccount }>('/demo/account'),
      apiGet<{ data: DemoOperation[] }>('/demo/operations?status=open'),
      apiGet<{ data: DemoOperation[] }>('/demo/operations?status=settled'),
      apiGet<PendingDemoRacesResponse>('/demo/pending-races?limit=12'),
      apiGet<{ data: DemoBankrollCurveData }>('/demo/account/bankroll-curve'),
    ]);

    account.value = accountRes.data;
    openOperations.value = openRes.data;
    settledOperations.value = settledRes.data;
    pendingRaces.value = pendingRes.data;
    pendingMeta.value = pendingRes.meta;
    bankrollCurve.value = curveRes.data;
    await refreshCollectorStatusQuietly();
    lastRefreshAt.value = new Date();
    startPolling();
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Falha ao carregar demo.';
  } finally {
    loading.value = false;
  }
}

async function submitBankrollAdjust() {
  saving.value = true;
  error.value = null;

  try {
    const amount = Number.parseFloat(bankrollForm.amount);
    if (Number.isNaN(amount) || amount === 0) {
      throw new Error('Informe um valor diferente de zero.');
    }

    const res = await apiPost<{ data: { account: DemoAccount } }>('/demo/account/adjust-bankroll', {
      amount,
      description: bankrollForm.description || null,
    });

    account.value = res.data.account;
    bankrollForm.amount = '';
    bankrollForm.description = '';
    await loadBankrollCurve();
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Falha ao ajustar banca.';
  } finally {
    saving.value = false;
  }
}

async function submitOperation() {
  saving.value = true;
  error.value = null;

  try {
    const stake = Number.parseFloat(operationForm.stake_amount);
    if (Number.isNaN(stake) || stake <= 0) {
      throw new Error('Stake inválida.');
    }

    if (operationForm.market_type === 'winner' && !operationForm.entry_odd) {
      throw new Error('Winner exige odd de entrada.');
    }

    const payload: Record<string, unknown> = {
      market_type: operationForm.market_type,
      bet_type: 'single',
      stake_amount: stake,
      entry_payload_json: buildEntryPayload(),
      risk_enforced: operationForm.risk_enforced,
      after_stop: operationForm.after_stop,
      tags: parseTags(operationForm.tags),
    };

    if (operationForm.speedway_race_id) {
      payload.speedway_race_id = Number.parseInt(operationForm.speedway_race_id, 10);
    }
    if (operationForm.entry_odd) {
      payload.entry_odd = Number.parseFloat(operationForm.entry_odd);
    }
    if (operationForm.entry_position) {
      payload.entry_position = Number.parseInt(operationForm.entry_position, 10);
    }
    if (operationForm.entry_color) {
      payload.entry_color = operationForm.entry_color;
    }
    if (operationForm.note.trim()) {
      payload.note = operationForm.note.trim();
      payload.journal_tags = parseTags(operationForm.tags);
    }
    if (contextSnapshot.value) {
      payload.context_snapshot_json = contextSnapshot.value;
    }

    await apiPost('/demo/operations', payload);
    operationForm.note = '';
    operationForm.tags = '';
    resetPricingMeta();
    clearSelectedRace();
    await loadData();
    activeTab.value = 'open';
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Falha ao criar operação.';
  } finally {
    saving.value = false;
  }
}

function openSettleModal(operation: DemoOperation) {
  settleTarget.value = operation;
  settleForm.result = 'win';
}

function closeSettleModal() {
  settleTarget.value = null;
}

async function submitSettlement() {
  if (!settleTarget.value) return;

  saving.value = true;
  error.value = null;

  try {
    await apiPost(`/demo/operations/${settleTarget.value.id}/settle`, {
      result: settleForm.result,
    });
    closeSettleModal();
    await loadData();
    activeTab.value = 'settled';
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Falha ao liquidar operação.';
  } finally {
    saving.value = false;
  }
}

function entrySummary(operation: DemoOperation): string {
  if (operation.market_type === 'winner') {
    const parts = [];
    if (operation.entry_position) parts.push(`P${operation.entry_position}`);
    if (operation.entry_color) parts.push(operation.entry_color);
    if (operation.entry_odd) parts.push(`@${operation.entry_odd}`);
    return parts.join(' · ') || 'Winner';
  }

  const order = operation.entry_payload_json.order;
  const odd = operation.entry_odd ?? operation.entry_payload_json.odd;
  return `${order ?? '—'}${odd ? ` @${odd}` : ''}`;
}

function settlementModeLabel(mode: DemoOperation['settlement_mode']): string | null {
  if (mode === 'auto') return 'Liquidação auto';
  if (mode === 'manual') return 'Liquidação manual';
  return null;
}

function operationAwaitingAutoSettlement(operation: DemoOperation): boolean {
  return operation.status === 'open' && operation.race?.status === 'settled';
}

function timingBadgeVariant(status: PendingDemoRace['timing_status']) {
  if (status === 'live') return 'default';
  if (status === 'upcoming') return 'secondary';
  if (status === 'late') return 'outline';
  return 'destructive';
}

function timingBadgeLabel(race: PendingDemoRace): string {
  if (race.timing_status === 'upcoming') {
    return `começa em ${formatCountdown(race.seconds_to_start)}`;
  }
  if (race.timing_status === 'live') return 'ao vivo';
  if (race.timing_status === 'late') return 'atrasada';
  if (race.timing_status === 'stale') return 'stale';
  return 'pending';
}

function operationAgeLabel(operation: DemoOperation): string | null {
  if (!operation.opened_at) return null;
  const seconds = Math.round((Date.now() - new Date(operation.opened_at).getTime()) / 1000);
  return formatSecondsAgo(seconds);
}

function collectorPayloadLabel(): string {
  if (!collectorStatus.value?.last_payload_at) return 'sem payload';
  if (collectorStatus.value.payload_age_seconds != null) {
    return `há ${formatSecondsAgo(collectorStatus.value.payload_age_seconds)}`;
  }
  return formatRelativeBr(collectorStatus.value.last_payload_at) ?? '—';
}

onMounted(loadData);
onUnmounted(stopPolling);

watch(openOperations, () => {
  syncOperationsPolling();
});
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">Demo manual</h1>
      <p class="mt-1 text-sm text-muted-foreground">
        Registre entradas fictícias, ajuste a banca e liquide operações com nota no diário.
      </p>
    </div>

    <p v-if="error" class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
      {{ error }}
    </p>

    <div v-if="loading" class="text-sm text-muted-foreground">Carregando…</div>

    <template v-else>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <Card>
          <CardContent class="p-4">
            <p class="text-xs text-muted-foreground">Banca atual</p>
            <p class="text-2xl font-semibold tabular-nums">{{ account?.current_balance }}u</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-4">
            <p class="text-xs text-muted-foreground">Operações abertas</p>
            <p class="text-2xl font-semibold tabular-nums">{{ openOperations.length }}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-4">
            <p class="text-xs text-muted-foreground">P/L do dia</p>
            <p class="text-2xl font-semibold tabular-nums">{{ formatUnits(dailyPl) }}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-4">
            <p class="text-xs text-muted-foreground">Último payload</p>
            <p class="text-sm font-medium">{{ collectorPayloadLabel() }}</p>
            <p
              v-if="collectorStatus?.is_payload_stale"
              class="text-[11px] text-amber-700 dark:text-amber-300"
            >
              collector stale
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-4">
            <p class="text-xs text-muted-foreground">
              {{ liveRace ? 'Corrida ao vivo' : 'Próxima corrida' }}
            </p>
            <p class="text-sm font-semibold tabular-nums">
              {{ (liveRace ?? nextActionableRace)?.starts_at_label ?? '—' }}
            </p>
            <p
              v-if="(liveRace ?? nextActionableRace)?.timing_status"
              class="text-[11px] text-muted-foreground"
            >
              {{ timingBadgeLabel((liveRace ?? nextActionableRace)!) }}
            </p>
          </CardContent>
        </Card>
      </div>

      <p v-if="refreshAgeLabel" class="text-[11px] text-muted-foreground">
        Última atualização {{ refreshAgeLabel }}
      </p>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Banca demo</CardTitle>
          <CardDescription>{{ account?.name }}</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="flex items-baseline justify-between gap-4">
            <span class="text-3xl font-semibold tabular-nums">{{ account?.current_balance }}u</span>
            <span class="text-xs text-muted-foreground">inicial {{ account?.initial_balance }}u</span>
          </div>

          <DemoBankrollCurve :curve="bankrollCurve" />

          <Separator />

          <form class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="submitBankrollAdjust">
            <label class="grid gap-1 text-sm">
              <span class="text-muted-foreground">Ajuste (+/-)</span>
              <input
                v-model="bankrollForm.amount"
                type="number"
                step="0.01"
                class="rounded-md border border-input bg-background px-3 py-2"
                placeholder="10 ou -5"
              />
            </label>
            <label class="grid gap-1 text-sm">
              <span class="text-muted-foreground">Descrição</span>
              <input
                v-model="bankrollForm.description"
                type="text"
                class="rounded-md border border-input bg-background px-3 py-2"
                placeholder="Opcional"
              />
            </label>
            <div class="flex items-end">
              <Button type="submit" :disabled="saving" class="w-full sm:w-auto">Ajustar</Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Próximas corridas</CardTitle>
          <CardDescription>
            Apenas corridas acionáveis.
            <span v-if="pendingMeta.stale_pending">
              {{ pendingMeta.stale_pending }} stale ocultas.
            </span>
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p v-if="pendingRaces.length === 0" class="text-sm text-muted-foreground">
            Nenhuma corrida acionável no momento.
          </p>

          <div
            v-else
            class="grid gap-3 sm:grid-cols-[repeat(auto-fit,minmax(190px,1fr))] max-sm:flex max-sm:gap-3 max-sm:overflow-x-auto max-sm:snap-x max-sm:snap-mandatory max-sm:pb-1"
          >
            <article
              v-for="race in pendingRaces"
              :key="race.id"
              class="rounded-lg border p-3 space-y-2 transition max-sm:min-w-[190px] max-sm:snap-center"
              :class="selectedRace?.id === race.id ? 'border-primary bg-primary/5 ring-1 ring-primary/30' : 'border-border'"
            >
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-2xl font-semibold tabular-nums leading-none">
                    {{ race.starts_at_label ?? raceScheduleLabel(race) }}
                  </p>
                  <p class="mt-1 text-[11px] text-muted-foreground">#{{ race.external_id }}</p>
                </div>
                <Badge :variant="timingBadgeVariant(race.timing_status)" class="text-[10px]">
                  {{ timingBadgeLabel(race) }}
                </Badge>
              </div>

              <div class="space-y-1 text-[11px]">
                <p>
                  Favorito:
                  <span class="font-medium tabular-nums">
                    P{{ race.rank_1_position ?? '—' }} @{{ race.rank_1_odd ?? '—' }}
                  </span>
                </p>
                <p>
                  Zebra:
                  <span class="font-medium tabular-nums">
                    P{{ race.rank_4_position ?? '—' }} @{{ race.rank_4_odd ?? '—' }}
                  </span>
                </p>
                <p>
                  Forecast:
                  <span class="font-medium">{{ race.market_rank_forecast_order ?? '—' }}</span>
                </p>
                <p>
                  Tricast:
                  <span class="font-medium">{{ race.market_rank_tricast_order ?? '—' }}</span>
                </p>
              </div>

              <Button
                type="button"
                size="sm"
                class="w-full"
                :variant="selectedRace?.id === race.id ? 'default' : 'outline'"
                @click="selectRace(race)"
              >
                {{ selectedRace?.id === race.id ? 'Selecionada' : 'Selecionar' }}
              </Button>
            </article>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Nova operação manual</CardTitle>
          <CardDescription>Stake debita a banca imediatamente via bankroll_transactions.</CardDescription>
        </CardHeader>
        <CardContent>
          <form class="grid gap-4" @submit.prevent="submitOperation">
            <div
              v-if="selectedRace"
              class="rounded-lg border border-primary/30 bg-primary/5 p-3 space-y-3"
            >
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-sm font-medium">Corrida selecionada</p>
                  <p class="text-xs text-muted-foreground">
                    {{ raceScheduleLabel(selectedRace) }} · #{{ selectedRace.external_id }} · ID {{ selectedRace.id }}
                  </p>
                </div>
                <Button type="button" size="sm" variant="ghost" @click="clearSelectedRace">
                  Remover
                </Button>
              </div>

              <div class="space-y-2">
                <div class="space-y-1">
                  <p class="text-[11px] font-medium text-foreground">Atalhos principais</p>
                  <div class="flex flex-wrap gap-1.5">
                    <template v-if="primaryQuickEntries.length">
                      <Button
                        v-for="entry in primaryQuickEntries"
                        :key="entry.id"
                        type="button"
                        size="sm"
                        variant="default"
                        class="text-xs shadow-sm"
                        @click="applyQuickEntry(entry)"
                      >
                        {{ entry.label }}
                      </Button>
                    </template>
                    <template v-else>
                      <Button
                        v-for="preset in QUICK_PRESETS"
                        :key="preset.id"
                        type="button"
                        size="sm"
                        variant="default"
                        class="text-xs shadow-sm"
                        @click="applyQuickPreset(preset.id)"
                      >
                        {{ preset.label }}
                      </Button>
                    </template>
                  </div>
                </div>
                <div v-if="alternateQuickEntries.length" class="space-y-1">
                  <p class="text-[11px] text-muted-foreground">Outras ordens</p>
                  <div class="flex flex-wrap gap-1.5">
                    <Button
                      v-for="entry in alternateQuickEntries"
                      :key="entry.id"
                      type="button"
                      size="sm"
                      variant="ghost"
                      class="text-xs text-muted-foreground hover:text-foreground"
                      @click="applyQuickEntry(entry)"
                    >
                      {{ entry.label }}
                    </Button>
                  </div>
                </div>
              </div>
            </div>

            <p v-else class="text-xs text-muted-foreground">
              Selecione uma corrida acima ou deixe em branco para operação sem vínculo.
            </p>

            <div
              v-if="operationForm.market_type || operationForm.stake_amount"
              class="rounded-lg border border-border bg-muted/30 p-3 text-sm space-y-1"
            >
              <p class="font-medium">Resumo da entrada</p>
              <p>Mercado: {{ entryPreview.market }}</p>
              <p>Entrada: {{ entryPreview.entry }}</p>
              <p>Stake: {{ entryPreview.stake ?? '—' }}u</p>
              <p>Odd: {{ entryPreview.odd ?? '—' }}</p>
              <p>Retorno potencial: {{ entryPreview.grossReturn ? `${entryPreview.grossReturn}u` : '—' }}</p>
              <p v-if="entryPreview.missingOddWarning" class="text-xs text-amber-700 dark:text-amber-300">
                {{ entryPreview.missingOddWarning }}
              </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
              <label class="grid gap-1 text-sm">
                <span class="text-muted-foreground">Mercado</span>
                <select v-model="operationForm.market_type" class="rounded-md border border-input bg-background px-3 py-2">
                  <option value="winner">Winner</option>
                  <option value="forecast">Forecast</option>
                  <option value="tricast">Tricast</option>
                </select>
              </label>
              <label class="grid gap-1 text-sm">
                <span class="text-muted-foreground">Tipo de aposta</span>
                <input
                  type="text"
                  value="Single (MVP)"
                  readonly
                  class="rounded-md border border-input bg-muted/40 px-3 py-2 text-muted-foreground"
                />
              </label>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
              <label class="grid gap-1 text-sm">
                <span class="text-muted-foreground">Stake (u)</span>
                <input v-model="operationForm.stake_amount" type="number" step="0.01" min="0.01" class="rounded-md border border-input bg-background px-3 py-2" />
              </label>
              <label class="grid gap-1 text-sm">
                <span class="text-muted-foreground">Odd entrada</span>
                <input
                  v-model="operationForm.entry_odd"
                  type="number"
                  step="0.01"
                  min="0.01"
                  class="rounded-md border border-input bg-background px-3 py-2"
                  placeholder="Opcional p/ forecast sem odd"
                  @input="onEntryOddInput"
                />
                <span v-if="oddHint" class="text-[11px] text-muted-foreground">{{ oddHint }}</span>
              </label>
              <label class="grid gap-1 text-sm">
                <span class="text-muted-foreground">ID corrida (manual)</span>
                <input
                  v-model="operationForm.speedway_race_id"
                  type="number"
                  min="1"
                  class="rounded-md border border-input bg-background px-3 py-2"
                  placeholder="Opcional"
                  @input="selectedRace = null; contextSnapshot = null; resetPricingMeta()"
                />
              </label>
            </div>

            <div v-if="operationForm.market_type === 'winner'" class="grid gap-3 sm:grid-cols-2">
              <label class="grid gap-1 text-sm">
                <span class="text-muted-foreground">Posição (1–4)</span>
                <input v-model="operationForm.entry_position" type="number" min="1" max="4" class="rounded-md border border-input bg-background px-3 py-2" />
              </label>
              <label class="grid gap-1 text-sm">
                <span class="text-muted-foreground">Cor</span>
                <input v-model="operationForm.entry_color" type="text" class="rounded-md border border-input bg-background px-3 py-2" placeholder="Vermelho" />
              </label>
            </div>

            <label v-else class="grid gap-1 text-sm">
              <span class="text-muted-foreground">Ordem (ex: 3-1 ou 3-1-4)</span>
              <input v-model="operationForm.order" type="text" class="rounded-md border border-input bg-background px-3 py-2" placeholder="3-1-4" />
            </label>

            <div class="flex flex-wrap gap-4 text-sm">
              <label class="flex items-center gap-2">
                <input v-model="operationForm.risk_enforced" type="checkbox" class="rounded border-input" />
                Risk enforced
              </label>
              <label class="flex items-center gap-2">
                <input v-model="operationForm.after_stop" type="checkbox" class="rounded border-input" />
                After stop
              </label>
            </div>

            <label class="grid gap-1 text-sm">
              <span class="text-muted-foreground">Tags (vírgula)</span>
              <input v-model="operationForm.tags" type="text" class="rounded-md border border-input bg-background px-3 py-2" placeholder="FOMO, entrada manual" list="journal-tags" />
              <datalist id="journal-tags">
                <option v-for="tag in JOURNAL_TAG_SUGGESTIONS" :key="tag" :value="tag" />
              </datalist>
            </label>

            <label class="grid gap-1 text-sm">
              <span class="text-muted-foreground">Nota do diário</span>
              <textarea
                v-model="operationForm.note"
                rows="2"
                class="rounded-md border border-input bg-background px-3 py-2"
                placeholder="Racional, emoção, contexto…"
              />
            </label>

            <Button type="submit" :disabled="saving" class="w-full sm:w-auto" size="lg">
              Registrar entrada
            </Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="space-y-3">
          <div class="flex items-center justify-between gap-3">
            <div>
              <CardTitle class="text-base">Operações</CardTitle>
              <CardDescription v-if="settledSummary.count > 0" class="text-xs">
                {{ settledSummary.wins }}G · {{ settledSummary.losses }}R · P/L acumulado
                {{ formatUnits(settledSummary.totalPl) }}
              </CardDescription>
            </div>
            <div class="flex gap-1 rounded-lg border border-border p-0.5 text-xs">
              <button
                type="button"
                class="rounded-md px-3 py-1 transition"
                :class="activeTab === 'open' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
                @click="activeTab = 'open'"
              >
                Abertas ({{ openOperations.length }})
              </button>
              <button
                type="button"
                class="rounded-md px-3 py-1 transition"
                :class="activeTab === 'settled' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
                @click="activeTab = 'settled'"
              >
                Resolvidas ({{ settledOperations.length }})
              </button>
            </div>
          </div>

          <p
            v-if="hasAwaitingAutoSettlement"
            class="rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-800 dark:text-amber-200"
          >
            Corrida encerrada — liquidação automática em andamento. Atualização a cada 12s.
          </p>
        </CardHeader>
        <CardContent class="space-y-3">
          <p v-if="displayedOperations.length === 0" class="text-sm text-muted-foreground">
            Nenhuma operação {{ activeTab === 'open' ? 'aberta' : 'resolvida' }}.
          </p>

          <article
            v-for="operation in displayedOperations"
            :key="operation.id"
            class="rounded-lg border border-border p-3 space-y-3"
          >
            <DemoOperationOutcome :operation="operation">
              <template #badges>
                <div class="flex flex-wrap gap-1">
                  <Badge :variant="resultVariant(operation.result)">
                    {{ resultLabel(operation.result) }}
                  </Badge>
                  <Badge
                    v-if="settlementModeLabel(operation.settlement_mode)"
                    variant="outline"
                  >
                    {{ settlementModeLabel(operation.settlement_mode) }}
                  </Badge>
                  <Badge
                    v-if="operationAwaitingAutoSettlement(operation)"
                    variant="secondary"
                  >
                    Aguardando auto
                  </Badge>
                  <Badge v-if="operation.after_stop" variant="outline">after stop</Badge>
                  <Badge v-if="!operation.risk_enforced" variant="secondary">sem risco</Badge>
                </div>
              </template>
            </DemoOperationOutcome>

            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs sm:grid-cols-4 lg:grid-cols-6">
              <div>
                <span class="text-muted-foreground">Stake</span>
                <p class="tabular-nums">{{ operation.stake_amount }}u</p>
              </div>
              <div>
                <span class="text-muted-foreground">Odd</span>
                <p class="tabular-nums">{{ operation.entry_odd ?? '—' }}</p>
              </div>
              <div>
                <span class="text-muted-foreground">Retorno pot.</span>
                <p class="tabular-nums">{{ formatUnits(operation.potential_gross_return) }}</p>
              </div>
              <div v-if="operation.status === 'open'">
                <span class="text-muted-foreground">Status</span>
                <p>Aguardando resultado</p>
              </div>
              <div v-if="operation.status === 'open' && operationAgeLabel(operation)">
                <span class="text-muted-foreground">Aberta há</span>
                <p>{{ operationAgeLabel(operation) }}</p>
              </div>
              <div v-if="operation.status === 'settled'">
                <span class="text-muted-foreground">Retorno bruto</span>
                <p class="tabular-nums">{{ formatUnits(operation.actual_gross_return) }}</p>
              </div>
              <div v-if="operation.status === 'settled'">
                <span class="text-muted-foreground">P/L</span>
                <p class="tabular-nums">{{ formatUnits(operation.profit_loss) }}</p>
              </div>
              <div v-if="operation.status === 'settled' && operation.bankroll_after">
                <span class="text-muted-foreground">Banca após</span>
                <p class="tabular-nums">{{ operation.bankroll_after }}u</p>
              </div>
              <div v-if="operation.status === 'settled' && operation.settled_at">
                <span class="text-muted-foreground">Liquidada</span>
                <p>{{ formatDateTimeBr(operation.settled_at) }}</p>
              </div>
            </div>

            <p v-if="operation.journal?.note" class="text-xs text-muted-foreground border-l-2 border-border pl-2">
              {{ operation.journal.note }}
            </p>

            <div v-if="operation.tags.length" class="flex flex-wrap gap-1">
              <Badge v-for="tag in operation.tags" :key="tag" variant="outline" class="text-[10px]">
                {{ tag }}
              </Badge>
            </div>

            <Button
              v-if="operation.status === 'open' && !operationAwaitingAutoSettlement(operation)"
              size="sm"
              variant="outline"
              :disabled="saving"
              @click="openSettleModal(operation)"
            >
              Liquidar manual
            </Button>
            <p
              v-else-if="operationAwaitingAutoSettlement(operation)"
              class="text-xs text-muted-foreground"
            >
              Resultado da corrida disponível — aguardando job de liquidação.
            </p>
          </article>
        </CardContent>
      </Card>
    </template>

    <div
      v-if="settleTarget"
      class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center"
      @click.self="closeSettleModal"
    >
      <Card class="w-full max-w-md">
        <CardHeader>
          <CardTitle class="text-base">Liquidar #{{ settleTarget.id }}</CardTitle>
          <CardDescription>Stake {{ settleTarget.stake_amount }}u · {{ entrySummary(settleTarget) }}</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-3 gap-2">
            <Button
              type="button"
              size="sm"
              :variant="settleForm.result === 'win' ? 'default' : 'outline'"
              @click="settleForm.result = 'win'"
            >
              Green
            </Button>
            <Button
              type="button"
              size="sm"
              :variant="settleForm.result === 'loss' ? 'destructive' : 'outline'"
              @click="settleForm.result = 'loss'"
            >
              Red
            </Button>
            <Button
              type="button"
              size="sm"
              :variant="settleForm.result === 'void' ? 'secondary' : 'outline'"
              @click="settleForm.result = 'void'"
            >
              Void
            </Button>
          </div>

          <div v-if="settlementPreview" class="rounded-md border border-border bg-muted/40 p-3 text-sm space-y-2">
            <div class="flex justify-between gap-4">
              <span class="text-muted-foreground">Stake já debitada</span>
              <span class="tabular-nums font-medium">{{ formatUnits(settlementPreview.stakeDebited) }}</span>
            </div>

            <template v-if="settleForm.result === 'win'">
              <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">Retorno bruto</span>
                <span class="tabular-nums font-medium">{{ formatUnits(settlementPreview.grossReturn ?? 0) }}</span>
              </div>
              <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">Lucro líquido</span>
                <span class="tabular-nums font-medium">{{ formatUnits(settlementPreview.netProfit ?? 0) }}</span>
              </div>
            </template>

            <template v-else-if="settleForm.result === 'loss'">
              <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">Crédito de liquidação</span>
                <span class="tabular-nums font-medium">{{ formatUnits(settlementPreview.settlementCredit) }}</span>
              </div>
            </template>

            <template v-else>
              <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">Stake devolvida</span>
                <span class="tabular-nums font-medium">{{ formatUnits(settlementPreview.settlementCredit) }}</span>
              </div>
            </template>

            <div class="flex justify-between gap-4 border-t border-border pt-2">
              <span class="text-muted-foreground">P/L final</span>
              <span class="tabular-nums font-semibold">{{ formatUnits(settlementPreview.finalPl) }}</span>
            </div>
          </div>

          <p class="text-xs text-muted-foreground">
            O backend calcula os valores financeiros com base no resultado selecionado.
          </p>

          <div class="flex gap-2 justify-end">
            <Button type="button" variant="outline" @click="closeSettleModal">Cancelar</Button>
            <Button type="button" :disabled="saving" @click="submitSettlement">Confirmar</Button>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
