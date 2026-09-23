<script setup>
import FieldRow from '@/Components/UI/FieldRow.vue';
import SecretField from '@/Components/UI/SecretField.vue';
import ConnectionBadge from '@/Components/UI/ConnectionBadge.vue';

defineProps({
    modelValue: { type: Object, required: true }, // { apiKey, apiKeyHint, apiKeyClear, connected }
});

const emit = defineEmits(['update']);
</script>

<template>
    <div>
        <FieldRow id="acc-openai-key" label="API Key" help="La chiave segreta del tuo account OpenAI. Viene usata per generare testi e immagini.">
            <SecretField id="acc-openai-key" :model-value="modelValue.apiKey" placeholder="sk-proj-…"
                :saved-hint="modelValue.apiKeyHint" :cleared="!!modelValue.apiKeyClear"
                @update:model-value="emit('update', 'apiKey', $event)" @clear="emit('update', 'apiKeyClear', $event)" />
        </FieldRow>
        <div class="acc-verify-row">
            <ConnectionBadge :state="modelValue.connected ? 'ok' : 'off'" />
            <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
        </div>
    </div>
</template>
