<script setup>
import { computed } from 'vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import { CH_CONFIG } from '@/data/dashboardMock';

const props = defineProps({
    data: { type: Array, required: true },
});

const top = computed(() => {
    const sorted = [...props.data].sort((a, b) => b.views - a.views);
    const ch = sorted[0];
    return {
        ...ch,
        cfg: CH_CONFIG[ch.id],
        vpp: ch.posts > 0 ? Math.round(ch.views / ch.posts) : 0,
        rank: props.data.length,
    };
});
</script>

<template>
    <div class="top-ch">
        <div class="top-ch__hero">
            <div class="top-ch__badge" :style="{ background: `color-mix(in srgb, ${top.cfg.color} 12%, white)` }">
                <ChannelIcon :id="top.id" :size="20" :style="{ color: top.cfg.color }" />
            </div>
            <span class="top-ch__name">{{ top.cfg.label }}</span>
        </div>

        <div>
            <span class="top-ch__views">{{ top.views.toLocaleString('it-IT') }}</span>
            <span class="top-ch__views-label">views totali</span>
        </div>

        <div class="top-ch__stats">
            <div class="top-ch__stat-row">
                <b>{{ top.vpp.toLocaleString('it-IT') }}</b>
                <span class="muted">views / post</span>
            </div>
            <div class="top-ch__stat-row">
                <b>{{ top.comments.toLocaleString('it-IT') }}</b>
                <span class="muted">commenti</span>
            </div>
            <div class="top-ch__stat-row">
                <b>{{ top.unique.toLocaleString('it-IT') }}</b>
                <span class="muted">utenti unici</span>
            </div>
        </div>

        <div class="top-ch__rank">1° su {{ top.rank }} canali</div>
    </div>
</template>
