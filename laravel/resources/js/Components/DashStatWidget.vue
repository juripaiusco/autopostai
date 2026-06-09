<script setup>
import { computed, ref, onMounted } from 'vue';
import Icon from '@/Components/Icon.vue';
import MiniSparkline from '@/Components/MiniSparkline.vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], required: true },
    sub: { type: String, required: true },
    trend: { type: Number, required: true },
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

onMounted(() => {
    if (typeof props.value !== 'number') return;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) { animatedValue.value = props.value; return; }
    const duration = 650;
    const target = props.value;
    const delay = props.index * 60 + 120;
    setTimeout(() => {
        const start = performance.now();
        const tick = (now) => {
            const t = Math.min((now - start) / duration, 1);
            animatedValue.value = Math.round(target * (1 - Math.pow(1 - t, 4)));
            if (t < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }, delay);
});
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
            <span :style="{ display: 'inline-flex', alignItems: 'center', gap: '3px',
                            fontSize: '12.5px', fontWeight: 600,
                            color: up ? 'var(--st-pub-fg)' : 'var(--g500)' }">
                <span style="font-size: 8.5px">{{ up ? '▲' : '▼' }}</span>{{ Math.abs(trend) }}%
            </span>
            <span class="muted">{{ sub }}</span>
        </div>
    </div>
</template>
