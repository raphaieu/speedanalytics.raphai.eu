<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { apiGet, apiPost } from '@/composables/useApi';
import { formatDateTimeBr, formatUnits } from '@/lib/format';
import { formatScheduleSlot, PILOT_POSITION_COLORS } from '@/lib/speedway';
import type {
  DemoAccount,
  DemoOperation,
  PendingDemoRace,
  PricingStatus,
  QuickEntry,
  QuickPresetId,
} from '@/types/demo';
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
const openOperations = ref<DemoOperation[]>([]);
const settledOperations = ref<DemoOperation[]>([]);
const activeTab = ref<'open' | 'settled'>('open');
const pendingRaces = ref<PendingDemoRace[]>([]);
const selectedRace = ref<PendingDemoRace | null>(null);
const contextSnapshot = ref<Record<string, unknown> | null>(null);

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
  actual_gross_return: '',
  profit_loss: '',
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

async function loadData() {
  loading.value = true;
  error.value = null;

  try {
    const [accountRes, openRes, settledRes, pendingRes] = await Promise.all([
      apiGet<{ data: DemoAccount }>('/demo/account'),
      apiGet<{ data: DemoOperation[] }>('/demo/operations?status=open'),
      apiGet<{ data: DemoOperation[] }>('/demo/operations?status=settled'),
      apiGet<{ data: PendingDemoRace[] }>('/demo/pending-races?limit=12'),
    ]);

    account.value = accountRes.data;
    openOperations.value = openRes.data;
    settledOperations.value = settledRes.data;
    pendingRaces.value = pendingRes.data;
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
  settleForm.actual_gross_return = operation.potential_gross_return;
  settleForm.profit_loss = operation.potential_net_profit;
}

function closeSettleModal() {
  settleTarget.value = null;
}

async function submitSettlement() {
  if (!settleTarget.value) return;

  saving.value = true;
  error.value = null;

  try {
    const payload: Record<string, unknown> = { result: settleForm.result };

    if (settleForm.result === 'win') {
      if (settleForm.actual_gross_return) {
        payload.actual_gross_return = Number.parseFloat(settleForm.actual_gross_return);
      } else if (settleForm.profit_loss) {
        payload.profit_loss = Number.parseFloat(settleForm.profit_loss);
      }
    } else if (settleForm.result === 'loss' && settleForm.profit_loss) {
      payload.profit_loss = Number.parseFloat(settleForm.profit_loss);
    }

    await apiPost(`/demo/operations/${settleTarget.value.id}/settle`, payload);
    closeSettleModal();
    await loadData();
    activeTab.value = 'settled';
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Falha ao liquidar operação.';
  } finally {
    saving.value = false;
  }
}

function raceLabel(operation: DemoOperation): string {
  if (!operation.race) {
    return operation.speedway_race_id ? `Corrida #${operation.speedway_race_id}` : 'Sem corrida';
  }

  const hh = String(operation.race.race_hour ?? 0).padStart(2, '0');
  const mm = String(operation.race.race_minute ?? 0).padStart(2, '0');
  return `${hh}:${mm} · ${operation.race.external_id}`;
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

onMounted(loadData);
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
          <CardDescription>Corridas pending disponíveis para vincular à operação.</CardDescription>
        </CardHeader>
        <CardContent>
          <p v-if="pendingRaces.length === 0" class="text-sm text-muted-foreground">
            Nenhuma corrida pending no momento.
          </p>

          <div v-else class="-mx-1 flex gap-3 overflow-x-auto pb-1">
            <article
              v-for="race in pendingRaces"
              :key="race.id"
              class="min-w-[220px] shrink-0 rounded-lg border p-3 space-y-2 transition"
              :class="selectedRace?.id === race.id ? 'border-primary bg-primary/5' : 'border-border'"
            >
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-sm font-medium tabular-nums">{{ raceScheduleLabel(race) }}</p>
                  <p class="text-[11px] text-muted-foreground">#{{ race.external_id }}</p>
                </div>
                <Badge variant="outline" class="text-[10px]">pending</Badge>
              </div>

              <div class="grid grid-cols-4 gap-1 text-center text-[10px]">
                <div
                  v-for="pilot in race.pilot_odds"
                  :key="pilot.position"
                  class="rounded bg-muted/60 px-1 py-1"
                >
                  <p class="text-muted-foreground">P{{ pilot.position }}</p>
                  <p class="font-medium tabular-nums">{{ pilot.odd }}</p>
                </div>
              </div>

              <div class="space-y-0.5 text-[11px] text-muted-foreground">
                <p>
                  Rank 1:
                  <span class="text-foreground tabular-nums">
                    P{{ race.rank_1_position ?? '—' }} @{{ race.rank_1_odd ?? '—' }}
                  </span>
                </p>
                <p>
                  Forecast:
                  <span class="text-foreground">{{ race.market_rank_forecast_order ?? '—' }}</span>
                </p>
                <p>
                  Tricast:
                  <span class="text-foreground">{{ race.market_rank_tricast_order ?? '—' }}</span>
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
                <div class="flex flex-wrap gap-1.5">
                  <template v-if="primaryQuickEntries.length">
                    <Button
                      v-for="entry in primaryQuickEntries"
                      :key="entry.id"
                      type="button"
                      size="sm"
                      variant="secondary"
                      class="text-xs"
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
                      variant="secondary"
                      class="text-xs"
                      @click="applyQuickPreset(preset.id)"
                    >
                      {{ preset.label }}
                    </Button>
                  </template>
                </div>
                <div v-if="alternateQuickEntries.length" class="space-y-1">
                  <p class="text-[11px] text-muted-foreground">Outras ordens</p>
                  <div class="flex flex-wrap gap-1.5">
                    <Button
                      v-for="entry in alternateQuickEntries"
                      :key="entry.id"
                      type="button"
                      size="sm"
                      variant="outline"
                      class="text-xs"
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

            <Button type="submit" :disabled="saving" class="w-full sm:w-auto">Registrar entrada</Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="space-y-3">
          <div class="flex items-center justify-between gap-3">
            <CardTitle class="text-base">Operações</CardTitle>
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
        </CardHeader>
        <CardContent class="space-y-3">
          <p v-if="displayedOperations.length === 0" class="text-sm text-muted-foreground">
            Nenhuma operação {{ activeTab === 'open' ? 'aberta' : 'resolvida' }}.
          </p>

          <article
            v-for="operation in displayedOperations"
            :key="operation.id"
            class="rounded-lg border border-border p-3 space-y-2"
          >
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <p class="font-medium text-sm">
                  #{{ operation.id }} · {{ marketLabels[operation.market_type] }} · {{ entrySummary(operation) }}
                </p>
                <p class="text-xs text-muted-foreground">{{ raceLabel(operation) }}</p>
              </div>
              <div class="flex flex-wrap gap-1">
                <Badge :variant="resultVariant(operation.result)">
                  {{ resultLabel(operation.result) }}
                </Badge>
                <Badge v-if="operation.after_stop" variant="outline">after stop</Badge>
                <Badge v-if="!operation.risk_enforced" variant="secondary">sem risco</Badge>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs sm:grid-cols-4">
              <div>
                <span class="text-muted-foreground">Stake</span>
                <p class="tabular-nums">{{ operation.stake_amount }}u</p>
              </div>
              <div>
                <span class="text-muted-foreground">Potencial</span>
                <p class="tabular-nums">{{ formatUnits(operation.potential_net_profit) }}</p>
              </div>
              <div v-if="operation.status === 'settled'">
                <span class="text-muted-foreground">P/L</span>
                <p class="tabular-nums">{{ formatUnits(operation.profit_loss) }}</p>
              </div>
              <div>
                <span class="text-muted-foreground">Aberta</span>
                <p>{{ formatDateTimeBr(operation.opened_at) }}</p>
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
              v-if="operation.status === 'open'"
              size="sm"
              variant="outline"
              :disabled="saving"
              @click="openSettleModal(operation)"
            >
              Liquidar
            </Button>
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

          <div v-if="settleForm.result === 'win'" class="grid gap-3 sm:grid-cols-2">
            <label class="grid gap-1 text-sm">
              <span class="text-muted-foreground">Retorno bruto</span>
              <input v-model="settleForm.actual_gross_return" type="number" step="0.01" min="0" class="rounded-md border border-input bg-background px-3 py-2" />
            </label>
            <label class="grid gap-1 text-sm">
              <span class="text-muted-foreground">Lucro líquido</span>
              <input v-model="settleForm.profit_loss" type="number" step="0.01" class="rounded-md border border-input bg-background px-3 py-2" />
            </label>
          </div>

          <label v-else-if="settleForm.result === 'loss'" class="grid gap-1 text-sm">
            <span class="text-muted-foreground">Prejuízo (opcional)</span>
            <input v-model="settleForm.profit_loss" type="number" step="0.01" max="0" class="rounded-md border border-input bg-background px-3 py-2" :placeholder="`-${settleTarget.stake_amount}`" />
          </label>

          <p v-else class="text-xs text-muted-foreground">Void devolve o stake à banca.</p>

          <div class="flex gap-2 justify-end">
            <Button type="button" variant="outline" @click="closeSettleModal">Cancelar</Button>
            <Button type="button" :disabled="saving" @click="submitSettlement">Confirmar</Button>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
