<script setup>
import { computed } from 'vue';
import MiniSparkline from '@/Components/MiniSparkline.vue';

// Riga metrica evidenziata (label + valore + sub + sparkline) con accent neutro o sky.
// Il valore va passato già formattato. Il colore della sparkline deriva dall'accent.
const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    sub: { type: String, required: true },
    accent: { type: String, default: 'neutral' }, // neutral | sky
    sparkData: { type: Array, required: true },
});

const sparkColor = computed(() => (props.accent === 'sky' ? 'var(--sky)' : 'var(--g400)'));
</script>

<template>
    <div class="metric-row" :class="`metric-row--${accent}`">
        <div>
            <div class="metric-row__label">{{ label }}</div>
            <div class="metric-row__value">{{ value }}</div>
            <div class="metric-row__sub">{{ sub }}</div>
        </div>
        <MiniSparkline :data="sparkData" :color="sparkColor" />
    </div>
</template>
