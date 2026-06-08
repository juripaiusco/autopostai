<script setup>
import { computed } from 'vue';
import MiniSparkline from '@/Components/MiniSparkline.vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], required: true },
    sub: { type: String, required: true },
    trend: { type: Number, required: true },
    color: { type: String, default: 'var(--sky)' },
    sparkData: { type: Array, default: null },
});

const up = computed(() => props.trend >= 0);
const displayValue = computed(() => (typeof props.value === 'number' ? props.value.toLocaleString('it-IT') : props.value));
</script>

<template>
    <div class="card stat">
        <div class="stat-head">
            <div>
                <div class="stat-value" style="font-weight: 700">{{ displayValue }}</div>
                <div class="stat-label">{{ label }}</div>
            </div>
            <MiniSparkline v-if="sparkData" :data="sparkData" :color="color" />
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
