<script setup>
import { computed } from 'vue';
import { bezierPath } from '@/data/chartHelpers';

const props = defineProps({
    data: { type: Array, required: true },
    color: { type: String, default: 'var(--sky)' },
});

const W = 80, H = 34, PAD = 3;

const gid = computed(() => {
    const max = Math.max(...props.data);
    const min = Math.min(...props.data);
    return 'spk' + Math.round(min * 7 + max * 13 + props.data.length);
});

const area = computed(() => {
    const max = Math.max(...props.data);
    const min = Math.min(...props.data);
    const range = max - min || 1;
    const n = props.data.length;
    const xOf = (i) => PAD + (i / (n - 1)) * (W - 2 * PAD);
    const yOf = (v) => PAD + (1 - (v - min) / range) * (H - 2 * PAD);
    const pts = props.data.map((v, i) => [xOf(i), yOf(v)]);
    const line = bezierPath(pts);
    return {
        line,
        area: `${line} L${xOf(n - 1)},${H - PAD} L${PAD},${H - PAD} Z`,
    };
});
</script>

<template>
    <svg :width="W" :height="H" :viewBox="`0 0 ${W} ${H}`" style="flex-shrink: 0; display: block">
        <defs>
            <linearGradient :id="gid" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" :stop-color="color" stop-opacity="0.16" />
                <stop offset="100%" :stop-color="color" stop-opacity="0" />
            </linearGradient>
        </defs>
        <path :d="area.area" :fill="`url(#${gid})`" />
        <path :d="area.line" fill="none" :stroke="color" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</template>
