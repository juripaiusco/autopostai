<script setup>
import Icon from '@/Components/Icon.vue';

const STATUS = {
    scheduled: { cls: 'pill-scheduled pill--pulse-warn', label: 'Schedulato' },
    published: { cls: 'pill-published', label: 'Pubblicato' },
    done: { cls: 'pill-done', label: 'Completato' },
    draft: { cls: 'pill-draft', label: 'Bozza' },
    error: { cls: 'pill-error pill--pulse-error', label: 'Errore' },
    // Stati contatto (stessi token colore: verde=attivo, ambra=in attesa,
    // rosso=errore, grigio=inattivo) — riusati, non duplicati in un pill dedicato.
    active: { cls: 'pill-published', label: 'Attivo' },
    unverified: { cls: 'pill-scheduled pill--pulse-warn', label: 'Da verificare' },
    bounced: { cls: 'pill-error pill--pulse-error', label: 'Bounced' },
    unsubscribed: { cls: 'pill-draft', label: 'Disiscritto' },
};

const ERROR_LIKE = ['error', 'bounced'];

const props = defineProps({
    status: { type: String, required: true },
});

const s = STATUS[props.status] ?? STATUS.scheduled;
</script>

<template>
    <span v-if="ERROR_LIKE.includes(status)" class="pill" :class="s.cls">
        <Icon name="warning" :size="13" /> {{ s.label }}
    </span>
    <span v-else class="pill" :class="s.cls">
        <span class="dot"></span>{{ s.label }}
    </span>
</template>
