<script setup>
import { computed, ref, onMounted } from 'vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import { CH_CONFIG } from '@/data/dashboardMock';

const props = defineProps({ data: { type: Array, required: true } });

const sorted = computed(() => [...props.data].sort((a, b) => b.views - a.views));

const top = computed(() => {
    const ch = sorted.value[0];
    const cfg = CH_CONFIG[ch.id];
    return {
        ...ch,
        cfg,
        vpp: ch.posts > 0 ? Math.round(ch.views / ch.posts) : 0,
        rank: props.data.length,
    };
});

const others = computed(() => sorted.value.slice(1, 4));

const totalViews = computed(() => props.data.reduce((s, d) => s + d.views, 0));

const ready = ref(false);

onMounted(() => {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) { ready.value = true; return; }
    requestAnimationFrame(() => requestAnimationFrame(() => { ready.value = true; }));
});
</script>

<template>
    <div class="top-ch">

        <!-- Hero: canale vincente -->
        <div class="top-ch__hero">
            <div class="top-ch__badge"
                 :style="{ background: `color-mix(in srgb, ${top.cfg.color} 13%, white)` }">
                <ChannelIcon :id="top.id" :size="22" :style="{ color: top.cfg.color }" />
            </div>
            <div>
                <div class="top-ch__name">{{ top.cfg.label }}</div>
                <div class="top-ch__rank-label">1° su {{ top.rank }} canali</div>
            </div>
            <div class="top-ch__views-block">
                <span class="top-ch__views" :style="{ color: top.cfg.color }">
                    {{ top.views.toLocaleString('it-IT') }}
                </span>
                <span class="top-ch__views-sub">views</span>
            </div>
        </div>

        <!-- Barra quota views sul totale -->
        <div class="top-ch__share">
            <div class="top-ch__share-track">
                <div class="top-ch__share-fill"
                     :style="{
                         background: top.cfg.color,
                         width: ready ? `${((top.views / totalViews) * 100).toFixed(1)}%` : '0%',
                         transition: `width 700ms cubic-bezier(0.16, 1, 0.3, 1) 80ms`,
                     }" />
            </div>
            <span class="top-ch__share-pct" :style="{ color: top.cfg.color }">
                {{ ((top.views / totalViews) * 100).toFixed(1) }}% del totale
            </span>
        </div>

        <!-- Metriche -->
        <div class="top-ch__stats">
            <div class="top-ch__stat">
                <span class="top-ch__stat-val">{{ top.vpp.toLocaleString('it-IT') }}</span>
                <span class="top-ch__stat-lbl">views / post</span>
            </div>
            <div class="top-ch__stat-sep"></div>
            <div class="top-ch__stat">
                <span class="top-ch__stat-val">{{ top.comments.toLocaleString('it-IT') }}</span>
                <span class="top-ch__stat-lbl">commenti</span>
            </div>
            <div class="top-ch__stat-sep"></div>
            <div class="top-ch__stat">
                <span class="top-ch__stat-val">{{ top.unique.toLocaleString('it-IT') }}</span>
                <span class="top-ch__stat-lbl">utenti unici</span>
            </div>
        </div>

        <!-- Altri canali: mini ranking -->
        <div class="top-ch__others">
            <div v-for="(ch, i) in others" :key="ch.id" class="top-ch__other-row">
                <div class="top-ch__other-icon"
                     :style="{ background: `color-mix(in srgb, ${CH_CONFIG[ch.id].color} 10%, white)` }">
                    <ChannelIcon :id="ch.id" :size="13" :style="{ color: CH_CONFIG[ch.id].color }" />
                </div>
                <span class="top-ch__other-name">{{ CH_CONFIG[ch.id].label }}</span>
                <div class="top-ch__other-bar-wrap">
                    <div class="top-ch__other-bar"
                         :style="{
                             background: CH_CONFIG[ch.id].color,
                             width: ready ? `${((ch.views / top.views) * 100).toFixed(1)}%` : '0%',
                             transition: `width 550ms cubic-bezier(0.16, 1, 0.3, 1) ${(i + 1) * 70 + 200}ms`,
                             opacity: 0.55,
                         }" />
                </div>
                <span class="top-ch__other-views">{{ ch.views.toLocaleString('it-IT') }}</span>
            </div>
        </div>

    </div>
</template>

<style scoped>
.top-ch {
    display: flex;
    flex-direction: column;
    gap: 16px;
    flex: 1;
    padding-top: 4px;
}

.top-ch__hero {
    display: flex;
    align-items: center;
    gap: 12px;
}

.top-ch__badge {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.top-ch__name {
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
    line-height: 1.2;
}

.top-ch__rank-label {
    font-size: 11.5px;
    color: var(--g500);
    margin-top: 2px;
}

.top-ch__views-block {
    margin-left: auto;
    text-align: right;
}

.top-ch__views {
    display: block;
    font-size: 28px;
    font-weight: 800;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}

.top-ch__views-sub {
    display: block;
    font-size: 11px;
    color: var(--g500);
    margin-top: 3px;
}

/* Share bar */
.top-ch__share {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.top-ch__share-track {
    height: 5px;
    background: var(--g100);
    border-radius: 9999px;
    overflow: hidden;
}

.top-ch__share-fill {
    height: 100%;
    border-radius: 9999px;
    width: 0;
}

.top-ch__share-pct {
    font-size: 11.5px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

/* Stats row */
.top-ch__stats {
    display: flex;
    align-items: center;
    background: var(--g50);
    border-radius: var(--radius);
    padding: 10px 14px;
    gap: 0;
}

.top-ch__stat {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.top-ch__stat-val {
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
    font-variant-numeric: tabular-nums;
}

.top-ch__stat-lbl {
    font-size: 11px;
    color: var(--g500);
    white-space: nowrap;
}

.top-ch__stat-sep {
    width: 1px;
    height: 28px;
    background: var(--g200);
    flex-shrink: 0;
}

/* Others mini-ranking */
.top-ch__others {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.top-ch__other-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.top-ch__other-icon {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.top-ch__other-name {
    font-size: 12px;
    color: var(--g600);
    width: 72px;
    flex-shrink: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.top-ch__other-bar-wrap {
    flex: 1;
    height: 4px;
    background: var(--g100);
    border-radius: 9999px;
    overflow: hidden;
}

.top-ch__other-bar {
    height: 100%;
    border-radius: 9999px;
    width: 0;
}

.top-ch__other-views {
    font-size: 11.5px;
    color: var(--g500);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    min-width: 44px;
    text-align: right;
}

@media (max-width: 520px) {
    .top-ch__views {
        font-size: 22px;
    }
    .top-ch__stat-val {
        font-size: 13px;
    }
    .top-ch__stat-lbl {
        font-size: 10px;
    }
}
</style>
