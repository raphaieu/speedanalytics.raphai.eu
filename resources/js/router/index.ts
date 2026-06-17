import { createRouter, createWebHistory } from 'vue-router';
import DashboardPage from '@/pages/DashboardPage.vue';
import RaceDetailPage from '@/pages/RaceDetailPage.vue';
import RacesPage from '@/pages/RacesPage.vue';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'dashboard', component: DashboardPage },
    { path: '/races', name: 'races', component: RacesPage },
    { path: '/races/:externalId', name: 'race-detail', component: RaceDetailPage },
  ],
});

export default router;
