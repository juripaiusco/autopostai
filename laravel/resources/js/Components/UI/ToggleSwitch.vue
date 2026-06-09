<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        class="acc-toggle"
        :class="{ 'acc-toggle--on': modelValue }"
        @click="$emit('update:modelValue', !modelValue)"
    >
        <span class="acc-toggle-thumb" />
    </button>
</template>
