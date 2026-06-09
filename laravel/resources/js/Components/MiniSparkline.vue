<script setup>
import { computed, ref, onMounted, getCurrentInstance } from 'vue';
import { bezierPath } from '@/data/chartHelpers';

const props = defineProps({
    data: { type: Array, required: true },
    color: { type: String, default: 'var(--sky)' },
    index: { type: Number, default: 0 },
});

const W = 80, H = 34, PAD = 3;

const uid = getCurrentInstance()?.uid ?? Math.random().toString(36).slice(2);
const gid = `spk${uid}`;

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

const lineRef = ref(null);
const areaRef = ref(null);

onMounted(() => {
    const linePath = lineRef.value;
    if (!linePath) return;

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) {
        if (areaRef.value) areaRef.value.style.opacity = '1';
        return;
    }

    const len = linePath.getTotalLength();
    linePath.style.strokeDasharray = len;
    linePath.style.strokeDashoffset = len;

    // Delay: card entrance (280ms + index*60ms) + small buffer
    const delay = props.index * 60 + 320;

    setTimeout(() => {
        linePath.style.transition = `stroke-dashoffset 500ms cubic-bezier(0.16, 1, 0.3, 1)`;
        linePath.style.strokeDashoffset = '0';

        // Fade in the area fill in sync
        if (areaRef.value) {
            areaRef.value.style.transition = `opacity 400ms cubic-bezier(0.25, 1, 0.5, 1) 100ms`;
            requestAnimationFrame(() => {
                areaRef.value.style.opacity = '1';
            });
        }
    }, delay);
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
        <path ref="areaRef" :d="area.area" :fill="`url(#${gid})`" style="opacity: 0" />
        <path ref="lineRef" :d="area.line" fill="none" :stroke="color" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</template>
