<script setup>
import { computed } from 'vue';
import { CH_CONFIG, CH_RANKED_COLOR, fmtNum } from '@/data/dashboardMock';

const props = defineProps({
    data: { type: Array, required: true },
});

const R = 58, CX = 80, CY = 80;
const C = 2 * Math.PI * R;

const sorted = computed(() => [...props.data].sort((a, b) => b.views - a.views));
const total = computed(() => sorted.value.reduce((s, d) => s + d.views, 0));

const arcs = computed(() => {
    let acc = 0;
    return sorted.value.map((d) => {
        const len = (d.views / total.value) * C;
        const arc = { id: d.id, color: CH_RANKED_COLOR[d.id], len, offset: -acc };
        acc += len;
        return arc;
    });
});

const legend = computed(() => sorted.value.map((d) => ({
    id: d.id,
    label: CH_CONFIG[d.id].label,
    color: CH_RANKED_COLOR[d.id],
    pct: ((d.views / total.value) * 100).toFixed(1),
})));
</script>

<template>
    <div style="display: flex; align-items: center; gap: 20px">
        <svg viewBox="0 0 160 160" style="width: 150px; height: 150px; flex-shrink: 0">
            <circle :cx="CX" :cy="CY" :r="R" fill="none" stroke="var(--g100)" stroke-width="18" />
            <circle v-for="arc in arcs" :key="arc.id"
                :cx="CX" :cy="CY" :r="R" fill="none"
                :stroke="arc.color" stroke-width="18"
                :stroke-dasharray="`${arc.len} ${C - arc.len}`"
                :stroke-dashoffset="arc.offset"
                :transform="`rotate(-90 ${CX} ${CY})`"
                stroke-linecap="butt" />
            <text :x="CX" :y="CY - 7" text-anchor="middle" font-size="22" font-weight="700" fill="var(--ink)">{{ fmtNum(total) }}</text>
            <text :x="CX" :y="CY + 11" text-anchor="middle" font-size="10.5" fill="var(--g400)">views totali</text>
        </svg>
        <div style="display: flex; flex-direction: column; gap: 8px; flex: 1">
            <div v-for="d in legend" :key="d.id" style="display: flex; align-items: center; gap: 8px; font-size: 13px">
                <span :style="{ width: '9px', height: '9px', borderRadius: '2px', background: d.color, flexShrink: 0 }"></span>
                <span style="color: var(--g600); flex: 1">{{ d.label }}</span>
                <span style="font-weight: 700; color: var(--ink)">{{ d.pct }}%</span>
            </div>
        </div>
    </div>
</template>
