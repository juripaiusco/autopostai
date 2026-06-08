<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: Number, default: 36 },
});

const initials = computed(() => props.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase());
const hue = computed(() => (props.name.charCodeAt(0) * 17) % 360);
</script>

<template>
    <div :style="{
        width: size + 'px', height: size + 'px', borderRadius: '50%', flexShrink: 0,
        background: `oklch(0.78 0.12 ${hue})`,
        color: `oklch(0.28 0.08 ${hue})`,
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        fontSize: (size * 0.36) + 'px', fontWeight: 700, userSelect: 'none',
    }">{{ initials }}</div>
</template>
