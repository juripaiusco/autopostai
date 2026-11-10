<script setup>
// Pulsante emoji per gli editor. Il selettore (emoji-picker-element, in
// italiano) si carica solo al primo clic; i dati delle emoji li serve l'app
// (asset Vite), nessuna CDN esterna.
import { ref, nextTick, onBeforeUnmount } from 'vue';

defineProps({
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['pick']);

const open = ref(false);
const host = ref(null);
const root = ref(null);
let picker = null;

async function createPicker() {
    const [{ default: Picker }, { default: it }, { default: dataSource }] = await Promise.all([
        import('emoji-picker-element/picker.js'),
        import('emoji-picker-element/i18n/it.js'),
        import('emoji-picker-element-data/it/cldr/data.json?url'),
    ]);
    picker = new Picker({ locale: 'it', dataSource, i18n: it });
    picker.classList.add('light');
    picker.addEventListener('emoji-click', (event) => {
        emit('pick', event.detail.unicode);
        close();
    });
    return picker;
}

function onDocumentClick(event) {
    if (root.value && !event.composedPath().includes(root.value)) close();
}

function onKeydown(event) {
    if (event.key === 'Escape') close();
}

async function toggle() {
    if (open.value) {
        close();
        return;
    }
    open.value = true;
    await nextTick();
    host.value?.appendChild(picker ?? await createPicker());
    document.addEventListener('mousedown', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
}

function close() {
    open.value = false;
    document.removeEventListener('mousedown', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
}

onBeforeUnmount(close);
</script>

<template>
    <div ref="root" class="rte-pop-wrap">
        <button type="button" class="rte-btn rte-btn--emoji" :class="{ 'is-active': open }" title="Emoji"
            aria-label="Inserisci emoji" :aria-expanded="open" :disabled="disabled" @click="toggle">😊</button>
        <div v-if="open" ref="host" class="rte-pop rte-pop--emoji" />
    </div>
</template>
