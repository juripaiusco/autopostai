<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :disabled="disabled"
        class="acc-toggle"
        :class="{ 'acc-toggle--on': modelValue }"
        @click="!disabled && $emit('update:modelValue', !modelValue)"
    >
        <span class="acc-toggle-thumb" />
    </button>
</template>
