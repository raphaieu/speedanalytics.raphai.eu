<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { usePwaInstall } from '@/composables/usePwaInstall';

const { canInstall, isInstalled, isDismissed, install, dismiss } = usePwaInstall();

const visible = computed(
  () => canInstall.value && !isInstalled.value && !isDismissed.value,
);
</script>

<template>
  <div
    v-if="visible"
    class="border-b border-amber-500/30 bg-amber-500/10 px-4 py-3"
    role="region"
    aria-label="Instalar aplicativo"
  >
    <div class="mx-auto flex max-w-5xl items-start gap-3 sm:items-center">
      <div class="min-w-0 flex-1">
        <p class="text-sm font-medium">Instale no celular</p>
        <p class="text-xs text-muted-foreground">
          Acesso rápido ao dashboard e corridas, como um app nativo.
        </p>
      </div>
      <div class="flex shrink-0 items-center gap-2">
        <Button size="sm" @click="install">Instalar</Button>
        <button
          type="button"
          class="rounded-md px-2 py-1 text-xs text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
          @click="dismiss"
        >
          Agora não
        </button>
      </div>
    </div>
  </div>
</template>
