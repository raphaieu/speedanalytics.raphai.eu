<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router';
import PwaInstallBanner from '@/components/PwaInstallBanner.vue';
</script>

<template>
  <div class="min-h-screen overflow-x-hidden bg-background text-foreground">
    <PwaInstallBanner />
    <header class="border-b border-border">
      <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-2.5 sm:py-3">
        <RouterLink to="/" class="shrink-0" aria-label="Speedway Analytics — Dashboard">
          <img
            src="/pwa-source.svg"
            alt=""
            width="32"
            height="32"
            class="h-8 w-8 rounded-md"
          />
        </RouterLink>
        <nav class="flex min-w-0 flex-1 justify-end gap-0.5 text-xs sm:gap-2 sm:text-sm">
          <RouterLink
            class="rounded-md px-2 py-1 text-muted-foreground transition hover:bg-accent hover:text-accent-foreground sm:px-3 sm:py-1.5"
            active-class="!bg-primary !text-primary-foreground"
            to="/analytics"
          >
            Análises
          </RouterLink>
          <RouterLink
            class="rounded-md px-2 py-1 text-muted-foreground transition hover:bg-accent hover:text-accent-foreground sm:px-3 sm:py-1.5"
            active-class="!bg-primary !text-primary-foreground"
            to="/glossario"
          >
            Glossário
          </RouterLink>
          <RouterLink
            class="rounded-md px-2 py-1 text-muted-foreground transition hover:bg-accent hover:text-accent-foreground sm:px-3 sm:py-1.5"
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
