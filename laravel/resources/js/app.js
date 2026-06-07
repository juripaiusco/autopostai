import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'FaPer3';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    pages: './Pages', // @inertiajs/vite riscrive questo in un resolve() con glob
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#0369a1', // sky-strong (brand)
    },
});
