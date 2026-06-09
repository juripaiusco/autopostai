<script setup>
import { computed, ref, onMounted } from 'vue';
import { CH_CONFIG, fmtNum } from '@/data/dashboardMock';
import ChannelIcon from '@/Components/ChannelIcon.vue';

const props = defineProps({ data: { type: Array, required: true } });

const R = 70, CX = 90, CY = 90;
const C = 2 * Math.PI * R;
const SEG_GAP = 2.5;

const sorted = computed(() => [...props.data].sort((a, b) => b.views - a.views));
const total = computed(() => sorted.value.reduce((s, d) => s + d.views, 0));

const ready = ref(false);

const arcs = computed(() => {
    let acc = 0;
    return sorted.value.map((d) => {
        const len = (d.views / total.value) * C;
        const visLen = Math.max(len - SEG_GAP, 0);
        const arc = { id: d.id, color: CH_CONFIG[d.id].color, visLen, offset: -acc };
        acc += len;
        return arc;
    });
});

const legend = computed(() => sorted.value.map((d) => ({
    id: d.id,
    label: CH_CONFIG[d.id].label,
    color: CH_CONFIG[d.id].color,
    pct: ((d.views / total.value) * 100).toFixed(1),
    views: d.views,
})));

onMounted(() => {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) { ready.value = true; return; }
    // Double rAF ensures the browser has painted the initial state before transitioning
    requestAnimationFrame(() => requestAnimationFrame(() => { ready.value = true; }));
});
</script>

<template>
    <div class="donut-layout">
        <div class="donut-svg-wrap">
            <svg viewBox="0 0 180 180" class="donut-svg" aria-label="Distribuzione views per canale">
                <circle :cx="CX" :cy="CY" :r="R" fill="none" stroke="var(--g100)" stroke-width="22" />
                <circle
                    v-for="(arc, i) in arcs"
                    :key="arc.id"
                    :cx="CX" :cy="CY" :r="R"
                    fill="none"
                    :stroke="arc.color"
                    stroke-width="22"
                    :stroke-dasharray="ready ? `${arc.visLen} ${C - arc.visLen}` : `0 ${C}`"
                    :stroke-dashoffset="arc.offset"
                    :transform="`rotate(-90 ${CX} ${CY})`"
                    stroke-linecap="butt"
                    :style="{ transition: `stroke-dasharray 620ms cubic-bezier(0.16, 1, 0.3, 1) ${i * 85}ms` }"
                />
                <text :x="CX" :y="CY - 8" text-anchor="middle" font-size="22" font-weight="700"
                      fill="var(--ink)" font-family="Figtree, sans-serif">{{ fmtNum(total) }}</text>
                <text :x="CX" :y="CY + 11" text-anchor="middle" font-size="10.5"
                      fill="var(--g400)" font-family="Figtree, sans-serif">views totali</text>
            </svg>
        </div>

        <div class="donut-legend">
            <div v-for="(d, i) in legend" :key="d.id" class="donut-row">
                <div class="donut-row__head">
                    <div class="donut-row__badge"
                         :style="{ background: `color-mix(in srgb, ${d.color} 13%, white)` }">
                        <ChannelIcon :id="d.id" :size="15" :style="{ color: d.color }" />
                    </div>
                    <span class="donut-row__name">{{ d.label }}</span>
                    <span class="donut-row__views">{{ d.views.toLocaleString('it-IT') }}</span>
                    <span class="donut-row__pct" :style="{ color: d.color }">{{ d.pct }}%</span>
                </div>
                <div class="donut-row__track">
                    <div class="donut-row__fill"
                         :style="{
                             background: d.color,
                             width: ready ? `${d.pct}%` : '0%',
                             transition: `width 620ms cubic-bezier(0.16, 1, 0.3, 1) ${i * 85 + 160}ms`,
                         }" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.donut-layout {
    display: flex;
    align-items: center;
    gap: 24px;
    flex: 1;
}

.donut-svg-wrap {
    flex-shrink: 0;
}

.donut-svg {
    width: 164px;
    height: 164px;
    display: block;
}

.donut-legend {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 11px;
    min-width: 0;
}

.donut-row {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.donut-row__head {
    display: flex;
    align-items: center;
    gap: 8px;
}

.donut-row__badge {
    width: 26px;
    height: 26px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.donut-row__name {
    font-size: 13px;
    font-weight: 500;
    color: var(--ink);
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.donut-row__views {
    font-size: 12px;
    color: var(--g500);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.donut-row__pct {
    font-size: 13px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    min-width: 42px;
    text-align: right;
    white-space: nowrap;
}

.donut-row__track {
    height: 4px;
    background: var(--g100);
    border-radius: 9999px;
    overflow: hidden;
}

.donut-row__fill {
    height: 100%;
    border-radius: 9999px;
    width: 0;
}

@media (max-width: 520px) {
    .donut-layout {
        flex-direction: column;
        align-items: center;
        gap: 16px;
    }
    .donut-svg {
        width: 140px;
        height: 140px;
    }
    .donut-legend {
        width: 100%;
    }
}
</style>
