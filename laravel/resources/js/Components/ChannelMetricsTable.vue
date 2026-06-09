<script setup>
import { computed } from 'vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import { CH_CONFIG, CH_RANKED_COLOR } from '@/data/dashboardMock';

const props = defineProps({
    data: { type: Array, required: true },
});

const maxViews = computed(() => Math.max(...props.data.map((d) => d.views)));

const rows = computed(() => props.data.map((row) => ({
    ...row,
    cfg: CH_CONFIG[row.id],
    barColor: CH_RANKED_COLOR[row.id],
    vpp: row.posts > 0 ? Math.round(row.views / row.posts) : 0,
    barW: (row.views / maxViews.value) * 100,
})));
</script>

<template>
    <div style="overflow-x: auto">
        <table class="table">
            <thead>
                <tr>
                    <th v-for="h in ['Canale', 'Views', 'Utenti unici', 'Commenti', 'Views / post']" :key="h"
                        :style="{ textAlign: h === 'Canale' ? 'left' : 'right', whiteSpace: 'nowrap' }">{{ h }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.id">
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px">
                            <span :style="{ width: '30px', height: '30px', borderRadius: '7px',
                                           background: `color-mix(in srgb, ${row.cfg.color} 12%, white)`,
                                           display: 'flex', alignItems: 'center', justifyContent: 'center' }">
                                <ChannelIcon :id="row.id" :size="14" :style="{ color: row.cfg.color }" />
                            </span>
                            <span style="font-weight: 600; color: var(--ink)">{{ row.cfg.label }}</span>
                        </div>
                    </td>
                    <td style="text-align: right">
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 5px">
                            <span style="font-weight: 700; color: var(--ink)">{{ row.views.toLocaleString('it-IT') }}</span>
                            <div style="width: 80px; height: 4px; border-radius: 9999px; background: var(--g100)">
                                <div :style="{ width: row.barW + '%', height: '100%', borderRadius: '9999px', background: row.barColor }"></div>
                            </div>
                        </div>
                    </td>
                    <td style="text-align: right; color: var(--g700); font-weight: 500">
                        {{ row.unique.toLocaleString('it-IT') }}
                    </td>
                    <td style="text-align: right; color: var(--g700); font-weight: 500">
                        <span v-if="row.comments > 0">{{ row.comments.toLocaleString('it-IT') }}</span>
                        <span v-else style="color: var(--g300)">—</span>
                    </td>
                    <td style="text-align: right; font-weight: 700; color: var(--ink)">
                        {{ row.vpp.toLocaleString('it-IT') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
