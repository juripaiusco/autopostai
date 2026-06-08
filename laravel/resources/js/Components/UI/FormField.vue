<script setup>
import Icon from '@/Components/Icon.vue';

// Campo form: label impilata + input + riga errore. Lo slot #trailing ospita un
// adornment a destra dell'input (es. toggle password); quando presente l'input
// guadagna padding-right via .field--adorned.
defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: String, default: '' },
    type: { type: String, default: 'text' },
    error: { type: String, default: null },
    placeholder: { type: String, default: '' },
    autocomplete: { type: String, default: null },
    required: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="field" :class="{ 'has-error': error, 'field--adorned': !!$slots.trailing }">
        <label class="field-label" :for="id">{{ label }}</label>
        <div class="input-wrap">
            <input
                :id="id"
                :name="id"
                :value="modelValue"
                :type="type"
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :required="required"
                @input="$emit('update:modelValue', $event.target.value)"
            />
            <slot name="trailing" />
        </div>
        <div v-if="error" class="input-error">
            <Icon name="warning" :size="14" /> {{ error }}
        </div>
    </div>
</template>
