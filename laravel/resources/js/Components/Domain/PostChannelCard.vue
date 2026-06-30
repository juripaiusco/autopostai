<script setup>
import ChannelIcon from '@/Components/ChannelIcon.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { CH_CONFIG } from '@/data/dashboardMock';

const props = defineProps({
    channelId: { type: String, required: true },
    status: { type: String, required: true },
    publishedAt: { type: String, default: null },
    excerpt: { type: String, default: '' },
    commentsCount: { type: Number, default: 0 },
});

const def = CH_CONFIG[props.channelId] ?? { label: props.channelId, color: '#6b7280' };

const METRICS = [
    { lab: 'Views', key: 'views' },
    { lab: 'Reach', key: 'reach' },
    { lab: 'Reazioni', key: 'reactions' },
    { lab: 'Commenti', key: 'comments' },
    { lab: 'Condivisioni', key: 'shares' },
    { lab: 'Salvataggi', key: 'saves' },
];

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div class="card ps-ch-card">
        <div class="ps-ch-card__head">
            <span class="ps-ch-card__badge" :style="{ background: `color-mix(in srgb, ${def.color} 12%, white)`, color: def.color }">
                <ChannelIcon :id="channelId" :size="18" />
            </span>
            <div class="ps-ch-card__head-text">
                <div class="ps-ch-card__head-row">
                    <span class="ps-ch-card__label">{{ def.label }}</span>
                    <StatusPill :status="status" />
                </div>
                <div class="ps-ch-card__date">Pubblicato {{ formatDate(publishedAt) }}</div>
            </div>
        </div>

        <div v-if="excerpt" class="ps-ch-card__excerpt" :style="{ borderLeftColor: def.color }">
            {{ excerpt }}
        </div>

        <div class="ps-ch-card__metrics">
            <div v-for="m in METRICS" :key="m.key" class="ps-ch-card__metric">
                <div class="ps-ch-card__metric-lab">{{ m.lab }}</div>
                <div class="ps-ch-card__metric-val" :class="{ 'is-nd': m.key !== 'comments' }">
                    {{ m.key === 'comments' ? commentsCount.toLocaleString('it-IT') : 'N/D' }}
                </div>
            </div>
        </div>

        <div class="ps-ch-card__foot">Andamento non disponibile</div>
    </div>
</template>
