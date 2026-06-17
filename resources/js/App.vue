<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router';
import PwaInstallBanner from '@/components/PwaInstallBanner.vue';
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <PwaInstallBanner />
    <header class="border-b border-border">
      <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
        <div>
          <p class="text-sm font-medium tracking-tight">Speedway Analytics</p>
          <p class="text-xs text-muted-foreground">Coleta · Histórico · Estratégias</p>
        </div>
        <nav class="flex gap-2 text-sm">
          <RouterLink
            class="rounded-md px-3 py-1.5 text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
            active-class="!bg-primary !text-primary-foreground"
            to="/"
          >
            Dashboard
          </RouterLink>
          <RouterLink
            class="rounded-md px-3 py-1.5 text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
            active-class="!bg-primary !text-primary-foreground"
            to="/analytics"
          >
            Análises
          </RouterLink>
          <RouterLink
            class="rounded-md px-3 py-1.5 text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
            active-class="!bg-primary !text-primary-foreground"
            to="/glossario"
          >
            Glossário
          </RouterLink>
          <RouterLink
            class="rounded-md px-3 py-1.5 text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
            active-class="!bg-primary !text-primary-foreground"
            to="/races"
          >
            Corridas
          </RouterLink>
        </nav>
      </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-6">
      <RouterView />
    </main>
  </div>
</template>
