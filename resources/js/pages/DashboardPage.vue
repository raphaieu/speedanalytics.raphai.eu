<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiGet } from '@/composables/useApi';
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
  metadata_json?: Record<string, unknown>;
};

const loading = ref(true);
const error = ref<string | null>(null);
const status = ref<CollectorStatus | null>(null);

function statusVariant(value?: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  if (value === 'valid' || value === 'running') return 'default';
  if (value === 'stale' || value === 'unknown') return 'secondary';
  return 'destructive';
}

function formatDate(value?: string | null) {
  if (!value) return '—';
  return new Date(value).toLocaleString('pt-BR');
}

onMounted(async () => {
  try {
    status.value = await apiGet<CollectorStatus>('/collector/status');
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
        Status da coleta e visão rápida do sistema.
      </p>
    </div>

    <Card>
      <CardHeader>
        <CardTitle>Collector</CardTitle>
        <CardDescription>
          Lê <code class="text-xs">collector/storage/collector-status.json</code>
        </CardDescription>
      </CardHeader>
      <CardContent>
        <p v-if="loading" class="text-sm text-muted-foreground">Carregando…</p>
        <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>
        <div v-else-if="status" class="space-y-4">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-muted-foreground">Status</span>
            <Badge :variant="statusVariant(status.status)">
              {{ status.status ?? 'desconhecido' }}
            </Badge>
            <Badge v-if="status.needs_login" variant="destructive">needs_login</Badge>
          </div>
          <Separator />
          <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
              <dt class="text-muted-foreground">Último payload</dt>
              <dd class="font-medium">{{ formatDate(status.last_payload_at) }}</dd>
            </div>
            <div>
              <dt class="text-muted-foreground">Último external_id</dt>
              <dd class="font-medium">{{ status.last_external_id ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-muted-foreground">Último sucesso</dt>
              <dd class="font-medium">{{ formatDate(status.last_success_at) }}</dd>
            </div>
            <div>
              <dt class="text-muted-foreground">Último erro</dt>
              <dd class="font-medium">{{ status.last_error_message ?? 'nenhum' }}</dd>
            </div>
          </dl>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
