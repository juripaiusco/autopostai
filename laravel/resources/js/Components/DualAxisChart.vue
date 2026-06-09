<script setup>
import { computed, ref, onMounted } from 'vue';
import { bezierPath } from '@/data/chartHelpers';
import { fmtNum } from '@/data/dashboardMock';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    data: { type: Array, required: true },
});

const W = 680, H = 230;
const PL = 58, PR = 52, PT = 18, PB = 32;
const IW = W - PL - PR;
const IH = H - PT - PB;

const svgEl = ref(null);
const clipRect = ref(null);
const hover = ref(null);

const n = computed(() => props.data.length);
const maxV = computed(() => Math.max(...props.data.map((d) => d.views)) * 1.12);
const maxC = computed(() => Math.max(...props.data.map((d) => d.comments)) * 1.18);

const xOf = (i) => PL + (i / (n.value - 1)) * IW;
const yV = (v) => PT + IH - (v / maxV.value) * IH;
const yC = (c) => PT + IH - (c / maxC.value) * IH;

const viewsPts = computed(() => props.data.map((d, i) => [xOf(i), yV(d.views)]));
const commentsPts = computed(() => props.data.map((d, i) => [xOf(i), yC(d.comments)]));

const viewsLine = computed(() => bezierPath(viewsPts.value));
const commentsLine = computed(() => bezierPath(commentsPts.value));
const areaPath = computed(() => `${viewsLine.value} L${xOf(n.value - 1)},${PT + IH} L${PL},${PT + IH} Z`);

const vTicks = computed(() => [0, 0.25, 0.5, 0.75, 1].map((f) => ({
    y: PT + IH * (1 - f),
    labelV: fmtNum(Math.round(maxV.value * f)),
    labelC: Math.round(maxC.value * f),
})));

onMounted(() => {
    const el = clipRect.value;
    if (!el) return;

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) { el.setAttribute('width', IW); return; }

    const duration = 920;
    const delay = 280;

    setTimeout(() => {
        const start = performance.now();
        const tick = (now) => {
            const t = Math.min((now - start) / duration, 1);
            const eased = t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
            el.setAttribute('width', IW * eased);
            if (t < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }, delay);
});

function handleMouseMove(e) {
    const rect = svgEl.value.getBoundingClientRect();
    const mx = (e.clientX - rect.left) * (W / rect.width);
    const nearest = props.data.reduce((best, d, i) => {
        const dist = Math.abs(xOf(i) - mx);
        return dist < best.dist ? { dist, i } : best;
    }, { dist: Infinity, i: 0 });
    hover.value = nearest.i;
}

const hd = computed(() => (hover.value !== null ? props.data[hover.value] : null));
const tooltipLeft = computed(() => Math.min(Math.max((xOf(hover.value) / W) * 100, 10), 80) + '%');
</script>

<template>
    <div style="position: relative">

        <!-- Legend: icon-badge language consistent with stat cards -->
        <div class="chart-legend">
            <div class="chart-legend-item">
                <div class="stat-icon-badge" style="background: color-mix(in srgb, var(--sky) 14%, white); width: 26px; height: 26px">
                    <Icon name="eye" :size="14" style="color: var(--sky)" />
                </div>
                <span class="chart-legend-label">Views</span>
                <span class="chart-legend-axis">asse sx</span>
            </div>
            <div class="chart-legend-item">
                <div class="stat-icon-badge" style="background: color-mix(in srgb, var(--primary) 14%, white); width: 26px; height: 26px">
                    <Icon name="chat" :size="14" style="color: var(--primary)" />
                </div>
                <span class="chart-legend-label">Commenti</span>
                <span class="chart-legend-axis">asse dx</span>
            </div>
        </div>

        <svg ref="svgEl" :viewBox="`0 0 ${W} ${H}`"
             style="width: 100%; height: auto; display: block; cursor: crosshair"
             @mousemove="handleMouseMove" @mouseleave="hover = null">
            <defs>
                <linearGradient id="dualFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="var(--sky)" stop-opacity="0.22" />
                    <stop offset="100%" stop-color="var(--sky)" stop-opacity="0.02" />
                </linearGradient>
                <clipPath id="chartReveal">
                    <rect ref="clipRect" :x="PL" y="0" width="0" :height="H" />
                </clipPath>
            </defs>

            <!-- Grid + axis labels: always visible -->
            <line v-for="(t, i) in vTicks" :key="'grid' + i"
                  :x1="PL" :x2="W - PR" :y1="t.y" :y2="t.y"
                  stroke="var(--g100)" stroke-width="1" />
            <text v-for="(t, i) in vTicks" :key="'lv' + i"
                  :x="PL - 8" :y="t.y + 4" text-anchor="end"
                  font-size="10.5" fill="var(--g400)">{{ t.labelV }}</text>
            <text v-for="(t, i) in vTicks" :key="'lc' + i"
                  :x="W - PR + 8" :y="t.y + 4" text-anchor="start"
                  font-size="10.5" fill="color-mix(in srgb, var(--primary) 60%, var(--g400))">{{ t.labelC }}</text>
            <text :x="PL - 8" :y="PT - 6" text-anchor="end"
                  font-size="9.5" fill="var(--sky-strong)" font-weight="600">views</text>
            <text :x="W - PR + 8" :y="PT - 6" text-anchor="start"
                  font-size="9.5" fill="var(--primary)" font-weight="600">comm.</text>
            <text v-for="(d, i) in data" :key="'m' + i"
                  :x="xOf(i)" :y="H - 8" text-anchor="middle"
                  font-size="11" fill="var(--g400)">{{ d.month }}</text>

            <!-- Chart data: clip-revealed left to right -->
            <path :d="areaPath" fill="url(#dualFill)" clip-path="url(#chartReveal)" />
            <path :d="viewsLine" fill="none" stroke="var(--sky)" stroke-width="2.5"
                  stroke-linecap="round" stroke-linejoin="round" clip-path="url(#chartReveal)" />
            <path :d="commentsLine" fill="none" stroke="var(--primary)" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round"
                  stroke-dasharray="5 3" clip-path="url(#chartReveal)" />

            <template v-if="hover !== null">
                <line :x1="xOf(hover)" :x2="xOf(hover)" :y1="PT" :y2="PT + IH"
                      stroke="var(--g200)" stroke-width="1" stroke-dasharray="4 3" />
                <circle :cx="viewsPts[hover][0]" :cy="viewsPts[hover][1]"
                        r="4.5" fill="#fff" stroke="var(--sky)" stroke-width="2.5" />
                <circle :cx="commentsPts[hover][0]" :cy="commentsPts[hover][1]"
                        r="4" fill="#fff" stroke="var(--primary)" stroke-width="2" />
            </template>
        </svg>

        <div v-if="hd" class="dual-tooltip" :style="{ left: tooltipLeft }">
            <div class="dual-tooltip__month">{{ hd.month }} 2025–26</div>
            <div class="dual-tooltip__row">
                <Icon name="eye" :size="12" style="color: #7dd3fc; flex-shrink: 0" />
                <span style="color: #94a3b8">Views</span>
                <b style="color: #fff; margin-left: auto">{{ hd.views.toLocaleString('it-IT') }}</b>
            </div>
            <div class="dual-tooltip__row">
                <Icon name="chat" :size="12" style="color: #93c5fd; flex-shrink: 0" />
                <span style="color: #94a3b8">Commenti</span>
                <b style="color: #fff; margin-left: auto">{{ hd.comments }}</b>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Legend */
.chart-legend {
    display: flex;
    gap: 20px;
    margin-bottom: 14px;
}

.chart-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-legend-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink);
}

.chart-legend-axis {
    font-size: 11px;
    color: var(--g400);
}

/* Tooltip */
.dual-tooltip {
    position: absolute;
    top: 36px;
    transform: translateX(-50%);
    background: var(--ink);
    color: #fff;
    border-radius: 8px;
    padding: 9px 13px;
    font-size: 12px;
    line-height: 1.5;
    pointer-events: none;
    white-space: nowrap;
    z-index: 5;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
}

.dual-tooltip__month {
    font-weight: 700;
    margin-bottom: 6px;
    color: #fff;
}

.dual-tooltip__row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.dual-tooltip__row + .dual-tooltip__row {
    margin-top: 3px;
}
</style>
