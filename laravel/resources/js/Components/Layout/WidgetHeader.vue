<script setup>
// Header di card/pannello: titolo + sottotitolo + slot #action (allineato a destra).
// Normalizza il blocco .widget-head ripetuto nella dashboard.
defineProps({
    title: { type: String, required: true },
    sub: { type: String, default: null },
});
</script>

<template>
    <div class="widget-head">
        <div>
            <div class="widget-title">{{ title }}</div>
            <div v-if="sub" class="widget-sub muted">{{ sub }}</div>
        </div>
        <slot name="action" />
    </div>
</template>
