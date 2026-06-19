import { createRouter, createWebHistory } from 'vue-router';
import AnalyticsPage from '@/pages/AnalyticsPage.vue';
import DashboardPage from '@/pages/DashboardPage.vue';
import GlossaryPage from '@/pages/GlossaryPage.vue';
import ManualDemoPage from '@/pages/ManualDemoPage.vue';
import RaceDetailPage from '@/pages/RaceDetailPage.vue';
import RacesPage from '@/pages/RacesPage.vue';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'dashboard', component: DashboardPage },
    { path: '/analytics', name: 'analytics', component: AnalyticsPage },
    { path: '/glossario', name: 'glossary', component: GlossaryPage },
    { path: '/races', name: 'races', component: RacesPage },
    { path: '/demo/manual', name: 'demo-manual', component: ManualDemoPage },
    { path: '/races/:externalId', name: 'race-detail', component: RaceDetailPage },
  ],
});

export default router;
