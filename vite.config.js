import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
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
        VitePWA({
            // KRUSIAL: Taruh sw.js & manifest di public/ (bukan public/build/)
            outDir: 'public',
            buildBase: '/',
            scope: '/',
            registerType: 'autoUpdate',
            injectRegister: false, // Diregister manual di resources/js/app.js

            manifestFilename: 'manifest.webmanifest',
            includeAssets: ['favicon.ico', 'pwa-192x192.png', 'pwa-512x512.png'],

            manifest: {
                name: 'Portal Pelatihan & Kompetensi',
                short_name: 'TrainingApp',
                description: 'Aplikasi Absensi QR & Rapor Skill Matrix Peserta',
                theme_color: '#0f172a',
                background_color: '#f8fafc',
                display: 'standalone',
                orientation: 'portrait',
                start_url: '/participant/dashboard',
                scope: '/',
                icons: [
                    {
                        src: '/pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any maskable',
                    },
                    {
                        src: '/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any maskable',
                    },
                ],
            },

            workbox: {
                // Hanya pre-cache aset statis hasil build Vite
                globDirectory: 'public/build',
                globPatterns: ['**/*.{js,css,ico,png,svg,woff2}'],
                modifyURLPrefix: {
                    '': '/build/',
                },
                cleanupOutdatedCaches: true,
                // JANGAN gunakan navigateFallback di Laravel Inertia agar route server tidak dibajak SW
                navigateFallback: null,

                runtimeCaching: [
                    {
                        // Cache gambar/ikon statis saja
                        urlPattern: /\.(?:png|jpg|jpeg|svg|gif|webp|woff2)$/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'static-assets-cache',
                            expiration: {
                                maxEntries: 60,
                                maxAgeSeconds: 30 * 24 * 60 * 60, // 30 Hari
                            },
                        },
                    },
                    {
                        // Untuk halaman Rapor Peserta (Bisa dilihat saat sinyal lemah, tapi selalu utamakan jaringan & jangan cache JSON X-Inertia)
                        urlPattern: ({ url, request }) =>
                            url.pathname.startsWith('/participant/dashboard') &&
                            request.method === 'GET' &&
                            !request.headers.get('X-Inertia'),
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'participant-pages-cache',
                            networkTimeoutSeconds: 5,
                            expiration: {
                                maxEntries: 10,
                                maxAgeSeconds: 24 * 60 * 60,
                            },
                        },
                    },
                ],
            },
        }),
    ],
});
