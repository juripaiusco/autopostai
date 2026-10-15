<script setup>
import { ref, watch } from 'vue';
import Icon from '@/Components/Icon.vue';

/**
 * Miniatura del post: prima immagine se presente, altrimenti (o se il file
 * non si carica) il gradiente di fallback con l'icona immagine.
 */
const props = defineProps({
    src: { type: String, default: null },
    fallback: { type: String, default: 'var(--g200)' },
    size: { type: Number, default: 40 },
});

const failed = ref(false);
watch(() => props.src, () => { failed.value = false; });
</script>

<template>
    <span class="post-thumb" :style="{ width: `${size}px`, height: `${size}px`, background: src && !failed ? null : fallback }">
        <img v-if="src && !failed" :src="src" alt="" loading="lazy" decoding="async" @error="failed = true" />
        <Icon v-else name="image" :size="Math.round(size * 0.45)" class="post-thumb__icon" />
    </span>
</template>
