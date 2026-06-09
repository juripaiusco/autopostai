<script setup>
defineProps({
    state: { type: String, default: 'off' }, // 'ok' | 'off' | 'checking'
});
</script>

<template>
    <span v-if="state === 'checking'" class="acc-badge acc-badge--checking">
        <span class="dot" />Verifica in corso…
    </span>
    <span v-else-if="state === 'ok'" class="acc-badge acc-badge--ok">
        <span class="dot" />Connesso
    </span>
    <span v-else class="acc-badge acc-badge--off">
        <span class="dot" />Non connesso
    </span>
</template>
