<script setup>
const props = defineProps({
    modelValue: { type: Number, default: 0 },
    min: { type: Number, default: 0 },
    max: { type: Number, default: 999 },
    step: { type: Number, default: 1 },
});

const emit = defineEmits(['update:modelValue']);

function clamp(n) {
    return Math.max(props.min, Math.min(props.max, n));
}
</script>

<template>
    <div class="acc-step">
        <button type="button" :disabled="modelValue <= min" aria-label="Diminuisci"
            @click="emit('update:modelValue', clamp(modelValue - step))">−</button>
        <input type="number" :value="modelValue" :min="min" :max="max"
            @change="emit('update:modelValue', clamp(parseInt($event.target.value || '0', 10)))" />
        <button type="button" :disabled="modelValue >= max" aria-label="Aumenta"
            @click="emit('update:modelValue', clamp(modelValue + step))">+</button>
    </div>
</template>
