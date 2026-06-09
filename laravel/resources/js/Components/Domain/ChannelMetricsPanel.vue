<script setup>
import { computed } from 'vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import { CH_CONFIG } from '@/data/dashboardMock';

// Pannello "Metriche per canale": barre views + tabella dettaglio, data-driven da
// CHANNEL_METRICS. Icone e colori brand da ChannelIcon + CH_CONFIG.
const props = defineProps({ data: { type: Array, required: true } });

const fmt = (n) => n.toLocaleString('it-IT');

const maxViews = computed(() => Math.max(...props.data.map((c) => c.views)));

const rows = computed(() => props.data.map((c) => ({
    ...c,
    cfg: CH_CONFIG[c.id],
    pct: maxViews.value > 0 ? (c.views / maxViews.value) * 100 : 0,
    vpp: c.posts > 0 ? Math.round(c.views / c.posts) : 0,
})));
</script>

<template>
    <div class="card dash-card card-clip channel-metrics">
        <div class="channel-metrics__head">
            <div class="channel-metrics__title">Metriche per canale</div>
            <div class="channel-metrics__sub">Views · utenti unici · commenti · ritorno medio — ultimi 90 giorni</div>
        </div>

        <div class="channel-metrics__body">
            <!-- Barre views -->
            <div class="cm-bars">
                <div class="cm-bars__head">Views</div>
                <div v-for="ch in rows" :key="ch.id" class="cm-bar-row">
                    <span
                        class="cm-bar-icon"
                        :style="{ background: `color-mix(in srgb, ${ch.cfg.color} 12%, white)`, color: ch.cfg.color }"
                    >
                        <ChannelIcon :id="ch.id" :size="14" />
                    </span>
                    <span class="cm-bar-label">{{ ch.cfg.label }}</span>
                    <div class="cm-bar-track">
                        <div class="cm-bar-fill" :style="{ width: `${ch.pct.toFixed(2)}%`, background: ch.cfg.color }" />
                    </div>
                    <span class="cm-bar-value">{{ fmt(ch.views) }}</span>
                </div>
            </div>

            <!-- Tabella dettaglio -->
            <div class="cm-detail">
                <table>
                    <thead>
                        <tr>
                            <th>Canale</th>
                            <th>Utenti unici</th>
                            <th>Commenti</th>
                            <th>Views / post</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ch in rows" :key="ch.id">
                            <td>{{ ch.cfg.label }}</td>
                            <td>{{ fmt(ch.unique) }}</td>
                            <td>
                                <span v-if="ch.comments > 0">{{ fmt(ch.comments) }}</span>
                                <span v-else class="cm-empty">—</span>
                            </td>
                            <td class="cm-vpp">{{ fmt(ch.vpp) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.channel-metrics__head {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
}
.channel-metrics__title {
    font: 700 16px/1.3 var(--font-ui);
    color: var(--ink);
}
.channel-metrics__sub {
    font: 400 12px/1.4 var(--font-ui);
    color: var(--g500);
    margin-top: 2px;
}
.channel-metrics__body {
    display: grid;
    grid-template-columns: 1fr 1fr;
}

/* Barre views */
.cm-bars {
    padding: 0 20px 14px;
    border-right: 1px solid var(--g100);
}
.cm-bars__head {
    font: 700 11px/1.3 var(--font-ui);
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--g500);
    background: var(--g50);
    padding: 10px 20px;
    margin: 0 -20px;
}
.cm-bar-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
}
.cm-bar-row:not(:last-child) {
    border-bottom: 1px solid var(--g100);
}
.cm-bar-icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.cm-bar-label {
    font: 600 13px/1.3 var(--font-ui);
    color: var(--ink);
    width: 84px;
    flex-shrink: 0;
}
.cm-bar-track {
    flex: 1;
    height: 6px;
    background: var(--g100);
    border-radius: 9999px;
    overflow: hidden;
}
.cm-bar-fill {
    height: 100%;
    border-radius: 9999px;
}
.cm-bar-value {
    font: 700 13px/1.3 var(--font-ui);
    color: var(--ink);
    width: 50px;
    text-align: right;
    flex-shrink: 0;
}

/* Tabella dettaglio */
.cm-detail {
    overflow-x: auto;
}
.cm-detail table {
    width: 100%;
    border-collapse: collapse;
}
.cm-detail thead th {
    font: 700 11px/1.3 var(--font-ui);
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--g500);
    background: var(--g50);
    padding: 10px 14px;
    text-align: right;
    white-space: nowrap;
}
.cm-detail thead th:first-child {
    text-align: left;
    padding-left: 20px;
}
.cm-detail tbody tr {
    border-top: 1px solid var(--g100);
    transition: background .12s;
}
.cm-detail tbody tr:hover {
    background: var(--g50);
}
.cm-detail td {
    padding: 13px 14px;
    font: 500 13px/1.4 var(--font-ui);
    color: var(--g600);
    text-align: right;
    white-space: nowrap;
}
.cm-detail td:first-child {
    text-align: left;
    padding-left: 20px;
    font: 600 13px/1.4 var(--font-ui);
    color: var(--ink);
}
.cm-vpp {
    font-weight: 700;
    color: var(--ink);
}
.cm-empty {
    color: var(--g300);
}
</style>
