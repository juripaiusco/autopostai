<script setup>
// Tabs segmentate con badge count. Stato attivo guidato dall'esterno via
// model-value (la sorgente di verità è il filtro lato server).
defineProps({
    modelValue: { type: [String, Number], required: true },
    // [{ id, label, count }]
    tabs: { type: Array, required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="filter-tabs" role="tablist">
        <button
            v-for="t in tabs"
            :key="t.id"
            type="button"
            role="tab"
            class="filter-tab"
            :class="{ 'filter-tab--active': modelValue === t.id }"
            :aria-selected="modelValue === t.id"
            @click="$emit('update:modelValue', t.id)"
        >
            {{ t.label }}
            <span class="filter-tab__count">{{ t.count }}</span>
        </button>
    </div>
</template>
