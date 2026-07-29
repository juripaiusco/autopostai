import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Logica condivisa per liste paginate con tabs-filtro, ricerca debounced e
 * ordinamento colonne (Account oggi, Posts in futuro). Naviga via Inertia
 * preservando stato e scroll.
 *
 * @param {Object}   opts
 * @param {string}   opts.routeName  nome rotta Ziggy (es. 'account')
 * @param {Function} opts.filters    getter ai filtri correnti dai props Inertia,
 *                                   es. () => props.filters ({ filter, search, sort, dir }).
 *                                   Getter (non oggetto) per leggere sempre il valore
 *                                   aggiornato dopo ogni visita Inertia.
 * @param {number}  [opts.debounce]  ms di debounce sulla ricerca (default 350)
 */
export function useTableFilters({ routeName, filters, debounce = 350 }) {
    const get = typeof filters === 'function' ? filters : () => filters;

    const search = ref(get().search);

    function reload(extra = {}) {
        // Spread di tutti i filtri correnti come base (non solo filter/search/
        // sort/dir): cosi' una pagina con filtri extra (es. Contatti: tag)
        // li porta avanti automaticamente ad ogni reload senza doverli
        // ripetere in ogni chiamata.
        router.get(route(routeName), {
            ...get(),
            search: search.value,
            ...extra,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function setFilter(id) {
        reload({ filter: id, page: undefined });
    }

    function handleSort(col) {
        const f = get();
        if (f.sort === col) {
            reload({ dir: f.dir === 'asc' ? 'desc' : 'asc' });
        } else {
            reload({ sort: col, dir: 'asc' });
        }
    }

    function clearSearch() {
        search.value = '';
        reload({ search: '', page: undefined });
    }

    let timer = null;
    watch(search, (value) => {
        clearTimeout(timer);
        timer = setTimeout(() => reload({ search: value, page: undefined }), debounce);
    });

    return { search, reload, setFilter, handleSort, clearSearch };
}
