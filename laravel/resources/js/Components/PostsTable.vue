<script setup>
import { ref } from 'vue';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { CH_CONFIG } from '@/data/dashboardMock';

defineProps({
    posts: { type: Array, required: true },
});

const deleting = ref(null);

const HEADERS = ['', 'Titolo', 'Canali', 'Data', 'Views', 'Commenti', ''];
function alignFor(h) {
    if (h === 'Views' || h === 'Commenti') return 'right';
    if (h === '') return 'center';
    return 'left';
}
</script>

<template>
    <div style="overflow-x: auto">
        <table class="table" style="min-width: 680px">
            <thead>
                <tr>
                    <th v-for="(h, i) in HEADERS" :key="i" :style="{ textAlign: alignFor(h) }">{{ h }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="p in posts" :key="p.id" :style="{ opacity: deleting === p.id ? 0.4 : 1, transition: 'opacity .2s' }">
                    <td style="width: 56px; padding-left: 16px; padding-right: 8px">
                        <div :style="{ width: '40px', height: '40px', borderRadius: '8px', background: p.thumb,
                                       flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center' }"></div>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: var(--ink); margin-bottom: 4px;
                                    max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">
                            {{ p.title }}
                        </div>
                        <StatusPill :status="p.status" />
                    </td>
                    <td>
                        <div style="display: flex; gap: 6px; align-items: center">
                            <span v-for="c in p.channels" :key="c" class="ch-chip" :title="CH_CONFIG[c].label" :style="{ '--ch-hue': CH_CONFIG[c].color }">
                                <ChannelIcon :id="c" :size="13" />
                            </span>
                        </div>
                    </td>
                    <td style="color: var(--g500); font-size: 13px; white-space: nowrap">{{ p.date }}</td>
                    <td style="text-align: right; font-weight: 600; color: var(--ink)">
                        <span v-if="p.views > 0">{{ p.views.toLocaleString('it-IT') }}</span>
                        <span v-else style="color: var(--g300)">—</span>
                    </td>
                    <td style="text-align: right; color: var(--g600)">
                        <span v-if="p.comments > 0">{{ p.comments }}</span>
                        <span v-else style="color: var(--g300)">—</span>
                    </td>
                    <td style="padding-right: 16px">
                        <div class="row-actions">
                            <button class="icon-btn icon-btn--ghost" title="Modifica" @click="() => {}">
                                <Icon name="pencil" :size="16" />
                            </button>
                            <button class="icon-btn icon-btn--ghost icon-btn--danger" title="Elimina" @click="deleting = p.id">
                                <Icon name="trash" :size="16" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
