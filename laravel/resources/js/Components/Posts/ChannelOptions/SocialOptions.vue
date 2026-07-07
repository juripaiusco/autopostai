<script setup>
import ToggleSwitch from '@/Components/UI/ToggleSwitch.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';

const props = defineProps({
    channelId: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: Object, required: true }, // { comments_enabled, auto_reply_enabled }
    replyOn: { type: Boolean, default: false },
});

const emit = defineEmits(['update']);
</script>

<template>
    <div class="pf-comments-card">
        <div class="pf-comments-title" style="display:flex;align-items:center;gap:6px">
            <ChannelIcon :id="channelId" :size="14" />{{ label }}
        </div>

        <div class="acc-ch-opt-row pf-check-row">
            <div class="acc-ch-opt-txt">
                <div class="pf-check-title">Abilita i commenti</div>
                <div class="pf-check-help">Gli utenti potranno commentare il post su {{ label }}.</div>
            </div>
            <ToggleSwitch :model-value="modelValue.comments_enabled" @update:model-value="emit('update', 'comments_enabled', $event)" />
        </div>

        <div class="acc-ch-opt-row pf-check-row" :class="{ 'pf-check-row--disabled': !modelValue.comments_enabled || !replyOn }">
            <div class="acc-ch-opt-txt">
                <div class="pf-check-title">Abilita risposte automatiche</div>
                <div class="pf-check-help">
                    <template v-if="!replyOn">Non attivo per questo account su {{ label }} — si configura in Account.</template>
                    <template v-else>L'AI risponderà ai commenti seguendo le istruzioni qui sotto.</template>
                </div>
            </div>
            <ToggleSwitch :model-value="modelValue.auto_reply_enabled" :disabled="!modelValue.comments_enabled || !replyOn"
                @update:model-value="emit('update', 'auto_reply_enabled', $event)" />
        </div>
    </div>
</template>
