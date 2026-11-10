<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { CH_CONFIG } from '@/data/dashboardMock';
import { htmlToText } from '@/lib/postText.js';

const props = defineProps({
    channelId: { type: String, required: true },
    status: { type: String, required: true },
    publishedAt: { type: String, default: null },
    commentsCount: { type: Number, default: 0 },
    // post.channelEdit[canale]: { editable, content, format (html|text), pending, error }
    edit: { type: Object, default: null },
});

const emit = defineEmits(['edit']);

const def = CH_CONFIG[props.channelId] ?? { label: props.channelId, color: '#6b7280' };

const excerpt = computed(() => {
    const text = props.edit?.format === 'html' ? htmlToText(props.edit.content) : (props.edit?.content ?? '');
    return text.length > 220 ? `${text.slice(0, 220)}…` : text;
});

// Perché il pulsante non c'è: la piattaforma non lo consente (Post::EDITABLE_AFTER_PUBLISH)
// oppure il post su questo canale non è ancora uscito.
const NOT_EDITABLE = {
    instagram: 'Instagram non permette di modificare un post già pubblicato.',
    newsletter: 'Newsletter già inviata: non si può modificare.',
    linkedin: 'Modifica da LinkedIn non ancora disponibile.',
};
const lockedReason = computed(() => (
    props.edit?.editable ? null : (NOT_EDITABLE[props.channelId] ?? 'Non ancora pubblicato su questo canale.')
));

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

        <div class="ps-ch-card__edit">
            <p v-if="lockedReason" class="ps-ch-card__edit-note">{{ lockedReason }}</p>
            <template v-else>
                <p v-if="edit.pending" class="ps-ch-card__edit-note">Aggiornamento in corso…</p>
                <p v-else-if="edit.error" class="ps-ch-card__edit-note is-error" :title="edit.error">
                    Ultimo aggiornamento non riuscito: {{ edit.error }}
                </p>
                <button type="button" class="btn btn-light btn-sm" :disabled="edit.pending" @click="emit('edit')">
                    <Icon name="pencil" :size="14" /> Modifica post
                </button>
            </template>
        </div>

        <div class="ps-ch-card__foot">Andamento non disponibile</div>
    </div>
</template>
