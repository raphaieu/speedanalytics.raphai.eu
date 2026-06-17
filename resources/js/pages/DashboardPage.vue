<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { apiGet } from '@/composables/useApi';
import { formatDateTimeBr, formatRelativeBr } from '@/lib/format';
import { Badge } from '@/components/ui/badge';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';

type CollectorStatus = {
  status?: string;
  needs_login?: boolean;
  last_payload_at?: string | null;
  last_success_at?: string | null;
  last_external_id?: string | null;
  last_error_message?: string | null;
  metadata_json?: {
    last_race_count?: number;
    last_pending_count?: number;
    last_settled_count?: number;
    backend_sent?: boolean;
    backend_payload_id?: number | null;
  };
};

type RacesMeta = {
  meta: {
    total: number;
    day: {
      counts: { upcoming: number; settled: number; total: number };
      is_today: boolean;
    };
    global: { total: number; upcoming: number };
  };
};

const loading = ref(true);
const error = ref<string | null>(null);
const status = ref<CollectorStatus | null>(null);
const raceStats = ref<RacesMeta['meta'] | null>(null);

const collectorHealthy = computed(
  () => status.value?.status === 'valid' || status.value?.status === 'running',
);

function statusVariant(value?: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  if (value === 'valid' || value === 'running') return 'default';
  if (value === 'stale' || value === 'unknown') return 'secondary';
  return 'destructive';
}

function statusLabel(value?: string): string {
  const labels: Record<string, string> = {
    valid: 'Coletando',
    running: 'Em execução',
    stale: 'Sem dados recentes',
    needs_login: 'Login necessário',
    cloudflare_challenge: 'Cloudflare',
    unknown_error: 'Erro',
  };
  return labels[value ?? ''] ?? value ?? 'desconhecido';
}

onMounted(async () => {
  try {
    const [collectorStatus, races] = await Promise.all([
      apiGet<CollectorStatus>('/collector/status'),
      apiGet<RacesMeta>('/races?per_page=1'),
    ]);
    status.value = collectorStatus;
    raceStats.value = races.meta;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Erro ao carregar status';
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
      <p class="text-sm text-muted-foreground">
        Visão rápida da coleta e do histórico no banco.
      </p>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Carregando…</p>
    <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>

    <template v-else>
      <div class="grid gap-3 sm:grid-cols-3">
        <Card>
          <CardContent class="pt-4">
            <p class="text-xs text-muted-foreground">Corridas no banco</p>
            <p class="text-2xl font-semibold tabular-nums">{{ raceStats?.global.total ?? 0 }}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="pt-4">
            <p class="text-xs text-muted-foreground">Próximas (hoje)</p>
            <p class="text-2xl font-semibold tabular-nums text-amber-600">
              {{ raceStats?.day.counts.upcoming ?? 0 }}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="pt-4">
            <p class="text-xs text-muted-foreground">Encerradas (hoje)</p>
            <p class="text-2xl font-semibold tabular-nums">
              {{ raceStats?.day.counts.settled ?? 0 }}
            </p>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Collector</CardTitle>
          <CardDescription>
            Monitoramento em tempo real da coleta Playwright.
          </CardDescription>
        </CardHeader>
        <CardContent v-if="status" class="space-y-4">
          <div class="flex flex-wrap items-center gap-2">
            <Badge :variant="statusVariant(status.status)">
              {{ statusLabel(status.status) }}
            </Badge>
            <Badge v-if="status.needs_login" variant="destructive">Precisa de login</Badge>
            <Badge v-else-if="collectorHealthy" variant="outline">Sessão OK</Badge>
          </div>

          <Separator />

          <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div>
              <dt class="text-muted-foreground">Último payload</dt>
              <dd class="font-medium">
                {{ formatRelativeBr(status.last_payload_at) ?? '—' }}
              </dd>
              <dd class="text-xs text-muted-foreground tabular-nums">
                {{ formatDateTimeBr(status.last_payload_at) }}
              </dd>
            </div>
            <div>
              <dt class="text-muted-foreground">Última corrida capturada</dt>
              <dd class="font-mono font-medium tabular-nums">
                {{ status.last_external_id ?? '—' }}
              </dd>
            </div>
            <div v-if="status.metadata_json?.last_race_count != null">
              <dt class="text-muted-foreground">Último lote</dt>
              <dd class="font-medium">
                {{ status.metadata_json.last_race_count }} corridas
                <span class="text-muted-foreground">
                  ({{ status.metadata_json.last_pending_count ?? 0 }} pend. /
                  {{ status.metadata_json.last_settled_count ?? 0 }} enc.)
                </span>
              </dd>
            </div>
            <div v-if="status.metadata_json?.backend_sent != null">
              <dt class="text-muted-foreground">Enviado ao backend</dt>
              <dd class="font-medium">
                {{ status.metadata_json.backend_sent ? 'Sim' : 'Não' }}
                <span
                  v-if="status.metadata_json.backend_payload_id"
                  class="font-mono text-xs text-muted-foreground"
                >
                  #{{ status.metadata_json.backend_payload_id }}
                </span>
              </dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-muted-foreground">Último erro</dt>
              <dd :class="status.last_error_message ? 'text-destructive' : 'text-muted-foreground'">
                {{ status.last_error_message ?? 'Nenhum' }}
              </dd>
            </div>
          </dl>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
