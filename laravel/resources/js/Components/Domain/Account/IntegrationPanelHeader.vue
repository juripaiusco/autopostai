<script setup>
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import ConnectionBadge from '@/Components/UI/ConnectionBadge.vue';

defineProps({
    id: { type: String, required: true },
    name: { type: String, required: true },
    sub: { type: String, required: true },
    tint: { type: Object, required: true }, // { bg, fg }
    connState: { type: String, default: null }, // 'ok' | 'off' | null
});

function channelIconId(id) {
    if (id === 'meta') return 'facebook';
    if (id === 'newsletter') return 'newsletter';
    return id;
}
</script>

<template>
    <div class="acc-iphead">
        <div class="acc-iphead-ic" :style="{ background: tint.bg, color: tint.fg }">
            <Icon v-if="id === 'ai'" name="sparkles" :size="20" />
            <Icon v-else-if="id === 'openai'" name="key" :size="20" />
            <ChannelIcon v-else :id="channelIconId(id)" :size="20" />
        </div>
        <div>
            <div class="acc-iphead-t">{{ name }}</div>
            <div class="acc-iphead-s">{{ sub }}</div>
        </div>
        <div class="acc-iphead-r">
            <ConnectionBadge v-if="connState !== null" :state="connState" />
        </div>
    </div>
</template>
