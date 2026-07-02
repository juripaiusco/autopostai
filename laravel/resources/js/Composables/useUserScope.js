import { router } from '@inertiajs/vue3';

/**
 * Imposta/rimuove lo scope globale "filtra per utente" in sessione lato
 * server (ScopeController@update), così persiste attraverso la normale
 * navigazione (link della sidebar) finché non viene disattivato o cambiato
 * esplicitamente — non basta più portarlo in giro nella query string, dato
 * che i link della sidebar non la preservano.
 */
export function useUserScope() {
    function setScope(id) {
        router.post(route('scope.update'), { user: id ?? undefined }, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    return { setScope };
}
