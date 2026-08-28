import '../css/app.css';
import './pwa';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

const appName = import.meta.env.VITE_APP_NAME || 'FinACourt';
const pages = import.meta.glob('./Pages/**/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    progress: { color: '#17895a', showSpinner: false },
    resolve: (name) => pages[`./Pages/${name}.vue`](),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
});
