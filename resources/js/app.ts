import { createApp } from 'vue';
import { registerSW } from 'virtual:pwa-register';
import App from './App.vue';
import router from './router';
import '../css/app.css';

registerSW({
  immediate: true,
});

createApp(App).use(router).mount('#app');
