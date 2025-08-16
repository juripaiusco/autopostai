import '../css/app.css';
import './bootstrap';
import '@fortawesome/fontawesome-free/css/all.css';
import '@fortawesome/fontawesome-free/js/all.js';
import './scss/style.scss';
import '../../node_modules/bootstrap/dist/js/bootstrap.bundle';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// funzione per aggiornare la classe del body
function updateBodyClass(page) {
    // rimuovi tutte le classi "page-" precedenti
    document.body.className = document.body.className
        .split(' ')
        .filter(c => !c.startsWith('page-'))
        .join(' ');

    // aggiungi la nuova classe
    const componentClass = 'page-' + page.component.replace('/', '-');
    document.body.classList.add(componentClass);
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const vueApp = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);

        // classe iniziale
        updateBodyClass(props.initialPage);

        // aggiorna la classe ad ogni navigazione Inertia
        document.addEventListener('inertia:navigate', (event) => {
            updateBodyClass(event.detail.page);
        });

        return vueApp;
        /*return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);*/
    },
    progress: {
        showSpinner: true,
        delay: 400,
        color: '#0ea5e9',
    },
});
