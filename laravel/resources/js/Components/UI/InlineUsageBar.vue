<script setup>
const props = defineProps({
    label: { type: String, required: true },
    current: { type: Number, required: true },
    max: { type: Number, required: true },
    color: { type: String, default: 'var(--sky)' },
    valueColor: { type: String, default: 'var(--sky-strong)' },
    formatter: { type: Function, default: (n) => String(n) },
});

const pct = () => Math.min(Math.round((props.current / (props.max || 1)) * 100), 100);
</script>

<template>
    <div class="acc-inline-usage">
        <div class="acc-inline-usage-bar">
            <div class="acc-inline-usage-fill" :style="{ width: pct() + '%', background: color }" />
        </div>
        <div class="acc-inline-usage-row">
            <span>{{ label }}</span>
            <span class="acc-inline-usage-val" :style="{ color: valueColor }">
                {{ formatter(current) }} / {{ formatter(max) }}
            </span>
        </div>
    </div>
</template>
