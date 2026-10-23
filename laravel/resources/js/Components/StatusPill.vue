<script setup>
import Icon from '@/Components/Icon.vue';
import AiWatchBadge from '@/Components/AiWatchBadge.vue';

const STATUS = {
    scheduled: { cls: 'pill-scheduled pill--pulse-warn', label: 'Schedulato' },
    published: { cls: 'pill-published', label: 'Pubblicato' },
    // Pubblicato e monitoraggio commenti chiuso (task_complete=1).
    done: { cls: 'pill-published', label: 'Completato' },
    draft: { cls: 'pill-draft', label: 'Bozza' },
    error: { cls: 'pill-error pill--pulse-error', label: 'Errore' },
    // Post uscito solo su alcuni canali: non più modificabile, gli altri
    // canali sono falliti e il worker li ritenta.
    partial: { cls: 'pill-error pill--pulse-error', label: 'Parziale' },
    // Stati contatto (stessi token colore: verde=attivo, ambra=in attesa,
    // rosso=errore, grigio=inattivo) — riusati, non duplicati in un pill dedicato.
    active: { cls: 'pill-published', label: 'Attivo' },
    unverified: { cls: 'pill-scheduled pill--pulse-warn', label: 'Da verificare' },
    bounced: { cls: 'pill-error pill--pulse-error', label: 'Bounced' },
    unsubscribed: { cls: 'pill-draft', label: 'Disiscritto' },
};

const ERROR_LIKE = ['error', 'bounced', 'partial'];

const props = defineProps({
    status: { type: String, required: true },
    // Solo post pubblicati ancora seguiti dal worker: mostra il badge AI.
    monitoringUntil: { type: String, default: null },
});

const s = STATUS[props.status] ?? STATUS.scheduled;
</script>

<template>
    <span class="status-wrap">
        <span v-if="ERROR_LIKE.includes(status)" class="pill" :class="s.cls">
            <Icon name="warning" :size="13" /> {{ s.label }}
        </span>
        <span v-else-if="status === 'done'" class="pill" :class="s.cls">
            <Icon name="check" :size="13" /> {{ s.label }}
        </span>
        <span v-else class="pill" :class="s.cls">
            <span class="dot"></span>{{ s.label }}
        </span>
        <AiWatchBadge v-if="monitoringUntil" :until="monitoringUntil" />
    </span>
</template>
