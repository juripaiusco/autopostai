<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Icon.vue';
import AiWatchBadge from '@/Components/AiWatchBadge.vue';

const STATUS = {
    scheduled: { cls: 'pill-scheduled pill--pulse-warn', label: 'Schedulato' },
    published: { cls: 'pill-published', label: 'Pubblicato' },
    // Pubblicato e monitoraggio commenti chiuso (task_complete=1).
    done: { cls: 'pill-done', label: 'Completato' },
    draft: { cls: 'pill-draft', label: 'Bozza' },
    error: { cls: 'pill-error pill--pulse-error', label: 'Errore' },
    // Post uscito solo su alcuni canali: non più modificabile, gli altri
    // canali sono falliti e il worker li ritenta.
    partial: { cls: 'pill-error pill--pulse-error', label: 'Parziale' },
    // Newsletter smtp_custom ancora in consegna a batch: il post è già
    // "Pubblicato" (published=1) ma non tutti i contatti l'hanno ricevuta.
    sending: { cls: 'pill-scheduled pill--pulse-warn', label: 'In invio' },
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
    // Solo post pubblicati ancora seguiti dal worker: pill a due colori
    // con il segmento AI a destra.
    monitoringUntil: { type: String, default: null },
    // Invio newsletter in corso: variante di visualizzazione di published/done,
    // lo stato del post (Post::status()) non cambia.
    sending: { type: Boolean, default: false },
});

const shown = computed(() => (props.sending && ['published', 'done'].includes(props.status) ? 'sending' : props.status));
const s = computed(() => STATUS[shown.value] ?? STATUS.scheduled);
</script>

<template>
    <span v-if="ERROR_LIKE.includes(shown)" class="pill" :class="s.cls">
        <Icon name="warning" :size="13" /> {{ s.label }}
    </span>
    <span v-else-if="shown === 'done'" class="pill" :class="s.cls">
        <Icon name="check" :size="12" /> {{ s.label }}
    </span>
    <span v-else class="pill" :class="[s.cls, { 'pill--split': monitoringUntil }]">
        <span class="dot"></span>{{ s.label }}
        <AiWatchBadge v-if="monitoringUntil" :until="monitoringUntil" />
    </span>
</template>
