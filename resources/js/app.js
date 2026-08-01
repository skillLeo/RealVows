import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import reveal from './directives/reveal';

const appName = import.meta.env.VITE_APP_NAME || 'RealVows';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),

    // Code-split per page, so the profile builder's JS never loads on the dashboard.
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('reveal', reveal)
            .mount(el);
    },

    progress: {
        color: '#B99154',
        showSpinner: false,
    },
});
