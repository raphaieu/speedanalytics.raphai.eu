import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const manifestIcons = [
  { src: '/pwa-64x64.png', sizes: '64x64', type: 'image/png' },
  { src: '/pwa-192x192.png', sizes: '192x192', type: 'image/png' },
  { src: '/pwa-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
  {
    src: '/maskable-icon-512x512.png',
    sizes: '512x512',
    type: 'image/png',
    purpose: 'maskable',
  },
];

const publicIcons = [
  { src: '/favicon.ico' },
  { src: '/pwa-source.svg' },
  { src: '/apple-touch-icon-180x180.png' },
];

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.ts'],
      refresh: true,
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
    tailwindcss(),
    VitePWA({
      buildBase: '/build/',
      scope: '/',
      base: '/',
      registerType: 'autoUpdate',
      devOptions: {
        enabled: false,
      },
      includeAssets: [],
      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2}'],
        navigateFallback: '/',
        navigateFallbackDenylist: [/^\/api/, /^\/build/],
        additionalManifestEntries: [
          { url: '/', revision: `${Date.now()}` },
          ...manifestIcons.map((icon) => ({ url: icon.src, revision: `${Date.now()}` })),
          ...publicIcons.map((icon) => ({ url: icon.src, revision: `${Date.now()}` })),
        ],
        maximumFileSizeToCacheInBytes: 3_000_000,
        runtimeCaching: [
          {
            urlPattern: ({ url }) => url.pathname.startsWith('/api/'),
            handler: 'NetworkOnly',
          },
        ],
      },
      manifest: {
        name: 'Speedway Analytics',
        short_name: 'Speedway',
        description: 'Inteligência operacional para Speedway — coleta, histórico e estratégias.',
        theme_color: '#0f172a',
        background_color: '#0f172a',
        display: 'standalone',
        orientation: 'portrait',
        scope: '/',
        start_url: '/',
        id: '/',
        lang: 'pt-BR',
        icons: manifestIcons,
      },
    }),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/js'),
    },
  },
  server: {
    watch: {
      ignored: ['**/storage/framework/views/**', '**/collector/**'],
    },
  },
});
