<script setup>
defineProps({
    id: { type: String, default: null },
    label: { type: String, default: null },
    help: { type: String, default: null },
});
</script>

<template>
    <div class="acc-field">
        <label v-if="label" class="acc-row-label" :for="id">{{ label }}</label>
        <span v-if="help" class="acc-row-help">{{ help }}</span>
        <slot />
    </div>
</template>
