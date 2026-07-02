<script setup>
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';

defineProps({
    items: { type: Array, required: true }, // [{ id, name, sub }]
    active: { type: String, required: true },
    connStates: { type: Object, required: true }, // id -> 'ok' | 'off' | null
});

defineEmits(['select']);

function channelIconId(id) {
    if (id === 'meta') return 'facebook';
    if (id === 'newsletter') return 'newsletter';
    return id;
}
</script>

<template>
    <div class="acc-imenu">
        <button v-for="intg in items" :key="intg.id" type="button"
            class="acc-imenu-btn" :class="{ active: active === intg.id }"
            @click="$emit('select', intg.id)">
            <div class="acc-imenu-ic">
                <Icon v-if="intg.id === 'ai'" name="sparkles" :size="16" />
                <Icon v-else-if="intg.id === 'openai'" name="key" :size="16" />
                <ChannelIcon v-else :id="channelIconId(intg.id)" :size="16" />
            </div>
            <div class="acc-imenu-grow">
                <span class="acc-imenu-name">{{ intg.name }}</span>
                <span class="acc-imenu-st">
                    {{ connStates[intg.id] === null ? 'Sempre attivo' : connStates[intg.id] === 'ok' ? 'Connesso' : 'Non connesso' }}
                </span>
            </div>
            <span v-if="connStates[intg.id] !== null" class="acc-imenu-dot"
                :style="{ background: connStates[intg.id] === 'ok' ? 'var(--st-pub-bd)' : 'var(--g300)' }" />
        </button>
    </div>
</template>
