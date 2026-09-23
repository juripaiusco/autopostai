/* Configurazione canali condivisa (etichette, colori brand) e formattazione.
   I dati mock della dashboard sono stati rimossi: le metriche arrivano da
   App\Services\DashboardMetrics. Il nome del file è storico (rinominarlo
   tocca ~10 import: refactor separato). */

export const CH_CONFIG = {
    facebook: { label: 'Facebook', color: '#1877f2' },
    instagram: { label: 'Instagram', color: '#e4405f' },
    linkedin: { label: 'LinkedIn', color: '#0a66c2' },
    wordpress: { label: 'WordPress', color: '#21759b' },
    newsletter: { label: 'Newsletter', color: '#6b7280' },
};

export function fmtNum(n) {
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000) return (n / 1000).toFixed(1) + 'k';
    return String(n);
}
