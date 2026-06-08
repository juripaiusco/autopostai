<script setup>
import Icon from '@/Components/Icon.vue';

// Input di ricerca controllato con icona e tasto clear. Nessun debounce qui:
// chi lo usa decide (es. useTableFilters fa watch debounced sul v-model).
defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Cerca…' },
});

defineEmits(['update:modelValue', 'clear']);
</script>

<template>
    <div class="search-box">
        <Icon name="search" :size="16" />
        <input
            :value="modelValue"
            type="text"
            :placeholder="placeholder"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <button
            v-if="modelValue"
            type="button"
            class="search-box__clear"
            @click="$emit('clear')"
        >
            <Icon name="x" :size="14" />
        </button>
    </div>
</template>
