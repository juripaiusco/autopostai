<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Icon.vue';

// Segmento destro della pill "Pubblicato": il worker sta ancora seguendo i
// commenti del post (task_complete=0). until = fine stimata della finestra.
const props = defineProps({
    until: { type: String, required: true },
});

const DAY_MS = 24 * 60 * 60 * 1000;

const daysLeft = computed(() => Math.max(0, Math.ceil((new Date(props.until) - Date.now()) / DAY_MS)));

const label = computed(() => {
    if (daysLeft.value === 0) return 'oggi';
    return daysLeft.value === 1 ? '1 g' : `${daysLeft.value} gg`;
});

const hint = computed(() => `L'AI segue i commenti fino al ${new Date(props.until).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' })}`);
</script>

<template>
    <span class="pill__ai" :title="hint" :aria-label="hint">
        <Icon name="sparkles" :size="12" /> {{ label }}
    </span>
</template>
