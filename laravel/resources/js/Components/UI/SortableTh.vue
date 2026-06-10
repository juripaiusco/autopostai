<script setup>
import { computed } from 'vue';

// <th> ordinabile: incapsula l'indicatore freccia (↕ idle, ↑/↓ attivo) e
// emette 'sort' con la colonna al click. Stato sort/dir guidato dall'esterno.
const props = defineProps({
    label: { type: String, required: true },
    column: { type: String, required: true },
    sort: { type: String, default: null },
    dir: { type: String, default: 'asc' },
    align: { type: String, default: 'left' }, // left | center | right
});

defineEmits(['sort']);

const active = computed(() => props.sort === props.column);
const icon = computed(() => (!active.value ? '↕' : props.dir === 'asc' ? '↑' : '↓'));
const ariaSort = computed(() => (!active.value ? 'none' : props.dir === 'asc' ? 'ascending' : 'descending'));
</script>

<template>
    <th
        class="th-sort"
        :class="align !== 'left' ? `th-sort--${align}` : null"
        role="button"
        tabindex="0"
        :aria-sort="ariaSort"
        @click="$emit('sort', column)"
        @keydown.enter.prevent="$emit('sort', column)"
        @keydown.space.prevent="$emit('sort', column)"
    >
        {{ label }}
        <span class="th-sort__icon" :class="{ 'th-sort__icon--active': active }" aria-hidden="true">{{ icon }}</span>
    </th>
</template>
