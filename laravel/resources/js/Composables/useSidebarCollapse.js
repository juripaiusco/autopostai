import { ref } from 'vue';

// Sidebar desktop compressa a sole icone. Stato singleton a livello di modulo:
// condiviso da Sidebar e AppLayout e conservato tra le navigazioni Inertia.
// Preferenza per-browser (localStorage); se l'utente non ha mai scelto, parte
// compressa sugli schermi stretti (tablet) ed espansa sopra.
const KEY = 'faper3.sidebarCollapsed';

function initial() {
    try {
        const saved = localStorage.getItem(KEY);
        if (saved !== null) return saved === '1';
    } catch { /* storage non disponibile: si usa il default */ }
    return typeof window !== 'undefined' && window.matchMedia('(max-width: 1200px)').matches;
}

const collapsed = ref(initial());

export function useSidebarCollapse() {
    function toggle() {
        collapsed.value = !collapsed.value;
        try { localStorage.setItem(KEY, collapsed.value ? '1' : '0'); } catch { /* ignora */ }
    }

    return { collapsed, toggle };
}
