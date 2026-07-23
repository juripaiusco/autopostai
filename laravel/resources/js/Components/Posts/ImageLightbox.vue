<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    images: { type: Array, required: true }, // array di URL
    index: { type: Number, required: true },
});

const emit = defineEmits(['close']);

const current = ref(props.index);
const closeBtn = ref(null);

function prev() {
    current.value = (current.value - 1 + props.images.length) % props.images.length;
}

function next() {
    current.value = (current.value + 1) % props.images.length;
}

function onKeydown(e) {
    if (e.key === 'Escape') emit('close');
    if (e.key === 'ArrowLeft') prev();
    if (e.key === 'ArrowRight') next();
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    closeBtn.value?.focus();
});
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="overlay" role="dialog" aria-modal="true" aria-label="Anteprima immagine" @click="emit('close')">
        <button ref="closeBtn" type="button" class="lightbox-close" aria-label="Chiudi anteprima" @click="emit('close')">
            <Icon name="x" :size="22" />
        </button>
        <button v-if="images.length > 1" type="button" class="lightbox-nav lightbox-nav--prev" aria-label="Immagine precedente" @click.stop="prev">
            <Icon name="chevron" :size="24" />
        </button>
        <img :src="images[current]" alt="Anteprima immagine" class="lightbox-img" @click.stop />
        <button v-if="images.length > 1" type="button" class="lightbox-nav lightbox-nav--next" aria-label="Immagine successiva" @click.stop="next">
            <Icon name="chevron" :size="24" />
        </button>
        <div v-if="images.length > 1" class="lightbox-count">{{ current + 1 }} / {{ images.length }}</div>
    </div>
</template>
