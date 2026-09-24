<script setup>
import { computed, ref, onMounted, watch } from 'vue';
import Icon from '@/Components/Icon.vue';
import MiniSparkline from '@/Components/MiniSparkline.vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], required: true },
    sub: { type: String, required: true },
    // null = periodo precedente vuoto: nessuna percentuale da mostrare.
    trend: { type: Number, default: null },
    color: { type: String, default: 'var(--sky)' },
    sparkData: { type: Array, default: null },
    index: { type: Number, default: 0 },
    icon: { type: String, default: 'dashboard' },
});

const up = computed(() => props.trend >= 0);

const animatedValue = ref(typeof props.value === 'number' ? 0 : props.value);

const displayValue = computed(() => {
    const v = animatedValue.value;
    return typeof v === 'number' ? v.toLocaleString('it-IT') : v;
});

let animTimer = null;

// Conteggio animato da 0 al valore; rilanciato anche quando il valore cambia
// senza rimontare il componente (reload parziali della pagina).
function animate(delay) {
    clearTimeout(animTimer);
    if (typeof props.value !== 'number') { animatedValue.value = props.value; return; }
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) { animatedValue.value = props.value; return; }
    const duration = 650;
    const target = props.value;
    animTimer = setTimeout(() => {
        const start = performance.now();
        const tick = (now) => {
            if (target !== props.value) return; // superato da un valore più recente
            const t = Math.min((now - start) / duration, 1);
            animatedValue.value = Math.round(target * (1 - Math.pow(1 - t, 4)));
            if (t < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }, delay);
}

onMounted(() => animate(props.index * 60 + 120));
watch(() => props.value, () => animate(0));
</script>

<template>
    <div class="card stat">
        <div class="stat-header">
            <div class="stat-icon-badge"
                 :style="{ background: `color-mix(in srgb, ${color} 14%, white)` }">
                <Icon :name="icon" :size="16" :style="{ color }" />
            </div>
            <span class="stat-label">{{ label }}</span>
        </div>
        <div class="stat-body">
            <div class="stat-value">{{ displayValue }}</div>
            <MiniSparkline v-if="sparkData" :data="sparkData" :color="color" :index="index" />
        </div>
        <div class="stat-sub">
            <span v-if="trend !== null" :style="{ display: 'inline-flex', alignItems: 'center', gap: '3px',
                            fontSize: '12.5px', fontWeight: 600,
                            color: up ? 'var(--st-pub-fg)' : 'var(--g500)' }">
                <span style="font-size: 8.5px">{{ up ? '▲' : '▼' }}</span>{{ Math.abs(trend) }}%
            </span>
            <span class="muted">{{ sub }}</span>
        </div>
    </div>
</template>
