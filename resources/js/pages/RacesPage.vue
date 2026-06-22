<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { apiGet } from '@/composables/useApi';
import {
  formatDayPill,
  formatYear,
  parseYearMonth,
  todayBr,
} from '@/lib/format';
import {
  RACES_PER_DAY,
} from '@/lib/speedway';
import type { RaceSummary } from '@/types/race';
import RaceListItem from '@/components/races/RaceListItem.vue';

type CalendarDay = { date: string; total: number };
type CalendarMonth = { value: string; label: string };

type RacesResponse = {
  data: RaceSummary[];
  meta: {
    last_page: number;
    day: {
      date: string;
      is_today: boolean;
      counts: { upcoming: number; stale_pending?: number; settled: number };
    };
    calendar: {
      month: string;
      years: string[];
      months: CalendarMonth[];
      days: CalendarDay[];
    };
  };
};

const loading = ref(true);
const error = ref<string | null>(null);
const races = ref<RacesResponse | null>(null);
const selectedDate = ref(todayBr());
const selectedYear = ref(formatYear(todayBr()));
const selectedMonth = ref(parseYearMonth(todayBr()));
const showUpcoming = ref(false);

const calendar = computed(() => races.value?.meta.calendar);
const calendarDays = computed(() => calendar.value?.days ?? []);
const calendarMonths = computed(() => calendar.value?.months ?? []);
const calendarYears = computed(() => calendar.value?.years ?? []);
const isToday = computed(() => races.value?.meta.day.is_today ?? false);
const upcomingCount = computed(() => races.value?.meta.day.counts.upcoming ?? 0);
const stalePendingCount = computed(() => races.value?.meta.day.counts.stale_pending ?? 0);

const upcomingRaces = computed(() =>
  (races.value?.data ?? [])
    .filter((race) => race.status === 'pending' && !race.timing?.is_stale)
    .reverse(),
);

const stalePendingRaces = computed(() =>
  (races.value?.data ?? [])
    .filter((race) => race.status === 'pending' && race.timing?.is_stale)
    .reverse(),
);

const settledRaces = computed(() =>
  (races.value?.data ?? []).filter((race) => race.status === 'settled'),
);

function onYearChange(event: Event) {
  const year = (event.target as HTMLSelectElement).value;
  selectedYear.value = year;
  const monthNum = selectedMonth.value.split('-')[1] ?? '01';
  selectedMonth.value = `${year}-${monthNum}`;
}

function selectDay(date: string) {
  selectedDate.value = date;
  loadRaces();
}

async function loadRaces() {
  loading.value = true;
  error.value = null;

  try {
    const allData: RaceSummary[] = [];
    let page = 1;
    let lastPage = 1;
    let response: RacesResponse | null = null;

    do {
      const params = new URLSearchParams({
        date: selectedDate.value,
        month: selectedMonth.value,
        per_page: String(RACES_PER_DAY),
        page: String(page),
      });
      response = await apiGet<RacesResponse>(`/races?${params}`);
      allData.push(...response.data);
      lastPage = response.meta.last_page;
      page += 1;
    } while (page <= lastPage);

    if (response) {
      races.value = { data: allData, meta: response.meta };
      selectedDate.value = response.meta.day.date;
      selectedMonth.value = response.meta.calendar.month;
      selectedYear.value = formatYear(response.meta.calendar.month);
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Erro ao carregar corridas';
  } finally {
    loading.value = false;
  }
}

watch(selectedMonth, loadRaces);

onMounted(loadRaces);
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-xl font-semibold tracking-tight">Corridas</h1>

    <div class="flex gap-2">
      <select
        :value="selectedYear"
        class="h-9 flex-1 rounded-md border border-input bg-background px-2 text-sm"
        @change="onYearChange"
      >
        <option v-for="year in calendarYears" :key="year" :value="year">
          {{ year }}
        </option>
      </select>
      <select
        v-model="selectedMonth"
        class="h-9 flex-1 rounded-md border border-input bg-background px-2 text-sm"
      >
        <option v-for="month in calendarMonths" :key="month.value" :value="month.value">
          {{ month.label }}
        </option>
      </select>
    </div>

    <div class="flex gap-1.5 overflow-x-auto pb-0.5 -mx-1 px-1">
      <button
        v-for="day in calendarDays"
        :key="day.date"
        type="button"
        class="shrink-0 rounded-full px-3 py-1.5 text-sm font-medium transition"
        :class="
          selectedDate === day.date
            ? 'bg-primary text-primary-foreground'
            : 'bg-muted text-muted-foreground'
        "
        @click="selectDay(day.date)"
      >
        {{ formatDayPill(day.date) }}
      </button>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Carregando…</p>
    <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>

    <template v-else>
      <div v-if="isToday && upcomingCount > 0" class="rounded-lg border">
        <button
          type="button"
          class="flex w-full items-center justify-between px-3 py-2.5 text-left text-sm"
          @click="showUpcoming = !showUpcoming"
        >
          <span class="font-medium">Próximas</span>
          <span class="text-muted-foreground">
            {{ upcomingCount }}
            <span class="ml-1">{{ showUpcoming ? '▴' : '▾' }}</span>
          </span>
        </button>
        <ul v-if="showUpcoming" class="divide-y border-t">
          <RaceListItem
            v-for="race in upcomingRaces"
            :key="race.id"
            :race="race"
          />
        </ul>
      </div>

      <div
        v-if="isToday && stalePendingCount > 0"
        class="rounded-lg border border-amber-500/30 bg-amber-500/5 px-3 py-2.5 text-sm"
      >
        <p class="font-medium text-amber-900 dark:text-amber-100">
          Pendências antigas detectadas
        </p>
        <p class="mt-1 text-xs text-muted-foreground">
          {{ stalePendingCount }} corrida(s) pending stale preservada(s) para auditoria.
        </p>
      </div>

      <div v-if="settledRaces.length === 0" class="py-8 text-center text-sm text-muted-foreground">
        Nenhuma corrida encerrada neste dia.
      </div>
      <ul v-else class="divide-y rounded-lg border">
        <RaceListItem
          v-for="race in settledRaces"
          :key="race.id"
          :race="race"
        />
      </ul>
    </template>
  </div>
</template>
