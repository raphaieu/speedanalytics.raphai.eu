<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiGet } from '@/composables/useApi';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

type RacesResponse = {
  data: unknown[];
  meta: { total: number; message?: string };
};

const loading = ref(true);
const error = ref<string | null>(null);
const races = ref<RacesResponse | null>(null);

onMounted(async () => {
  try {
    races.value = await apiGet<RacesResponse>('/races');
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Erro ao carregar corridas';
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Corridas</h1>
      <p class="text-sm text-muted-foreground">Histórico e filtros (em breve).</p>
    </div>

    <Card>
      <CardHeader>
        <CardTitle>Histórico</CardTitle>
        <CardDescription>Endpoint <code class="text-xs">GET /api/races</code></CardDescription>
      </CardHeader>
      <CardContent>
        <p v-if="loading" class="text-sm text-muted-foreground">Carregando…</p>
        <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>
        <p v-else class="text-sm text-muted-foreground">
          {{ races?.meta.message ?? 'Nenhuma corrida no banco ainda.' }}
          (total: {{ races?.meta.total ?? 0 }})
        </p>
      </CardContent>
    </Card>
  </div>
</template>
