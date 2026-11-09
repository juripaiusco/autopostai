import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Come v1: i non-admin vedono "crediti" (token / 1000), l'admin i token veri.
// showTokens arriva da HandleInertiaRequests: vero solo per l'admin senza
// filtro utente; filtrando un utente anche l'admin vede i crediti.
// Il backend lavora sempre in token: qui solo la conversione a video.
export const TOKENS_PER_CREDIT = 1000;

export function useTokenUnit() {
    const showTokens = computed(() => !!usePage().props.showTokens);

    // Token -> unità mostrata.
    const toUnit = (tokens) => {
        const n = Number(tokens) || 0;
        return showTokens.value ? n : n / TOKENS_PER_CREDIT;
    };

    // Unità mostrata -> token (input del limite mensile).
    const fromUnit = (value) => {
        const n = Number(value) || 0;
        return Math.round(showTokens.value ? n : n * TOKENS_PER_CREDIT);
    };

    const formatTokens = (tokens) => toUnit(tokens).toLocaleString('it-IT', {
        maximumFractionDigits: showTokens.value ? 0 : 2,
        useGrouping: 'always',
    });

    const unitLabel = computed(() => (showTokens.value ? 'token' : 'crediti'));
    const unitTitle = computed(() => (showTokens.value ? 'Token' : 'Crediti'));

    // "1.234 token" / "1,23 crediti" (singolare per esattamente 1 credito).
    const withUnit = (tokens) => {
        const label = !showTokens.value && toUnit(tokens) === 1 ? 'credito' : unitLabel.value;
        return `${formatTokens(tokens)} ${label}`;
    };

    return { showTokens, toUnit, fromUnit, formatTokens, unitLabel, unitTitle, withUnit };
}
