<script setup>
// Card di sezione con titolo/sottotitolo. Lo slot #aside ospita un chip a
// destra dell'intestazione; withAside allinea l'header su due colonne anche
// quando lo slot #aside è assente (vedi Account/Form.vue).
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    withAside: { type: Boolean, default: false },
});
</script>

<template>
    <div class="acc-card">
        <div class="acc-sec-head" :class="{ 'with-aside': withAside || $slots.aside }">
            <div>
                <div class="acc-sec-title">{{ title }}</div>
                <div v-if="subtitle" class="acc-sec-sub">{{ subtitle }}</div>
            </div>
            <slot name="aside" />
        </div>
        <slot />
    </div>
</template>
