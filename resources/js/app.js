import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { applyDocumentLocale, createAppI18n } from './i18n';
import { applyTheme, readStoredTheme } from './theme';
import { bootFlowbite } from './flowbite';

import 'bootstrap';

const appName = import.meta.env.VITE_APP_NAME || 'Shipping ERP';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const initialLocale = props.initialPage?.props?.appSettings?.locale || 'ar';
        const i18n = createAppI18n(initialLocale);
        applyDocumentLocale(initialLocale);
        applyTheme(readStoredTheme());

        const vueApp = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .use(i18n)
            .use(ZiggyVue)
            .mount(el);

        bootFlowbite();
        router.on('navigate', () => {
            requestAnimationFrame(() => bootFlowbite());
        });

        return vueApp;
    },
    progress: {
        color: '#0f766e',
    },
});
