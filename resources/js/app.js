import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { registerSW } from 'virtual:pwa-register';

const appName = import.meta.env.VITE_APP_NAME || 'TrainingApp';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#2563eb',
    },
});

if ('serviceWorker' in navigator) {
    registerSW({
        immediate: true,
        onOfflineReady() {
            console.log('PWA siap digunakan secara cepat.');
        },
    });
}

