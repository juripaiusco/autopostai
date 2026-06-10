<script setup>
import Icon from '@/Components/Icon.vue';

const STATUS = {
    scheduled: { cls: 'pill-scheduled', label: 'Schedulato' },
    published: { cls: 'pill-published', label: 'Pubblicato' },
    done: { cls: 'pill-done', label: 'Completato' },
    draft: { cls: 'pill-draft', label: 'Bozza' },
    error: { cls: 'pill-error', label: 'Errore' },
};

const props = defineProps({
    status: { type: String, required: true },
});

const s = STATUS[props.status] ?? STATUS.scheduled;
</script>

<template>
    <span v-if="status === 'error'" class="pill pill-error">
        <Icon name="warning" :size="13" /> {{ s.label }}
    </span>
    <span v-else class="pill" :class="s.cls">
        <span class="dot"></span>{{ s.label }}
    </span>
</template>
