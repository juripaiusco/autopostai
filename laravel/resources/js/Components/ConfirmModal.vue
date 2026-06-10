<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    title: { type: String, required: true },
    confirmLabel: { type: String, default: 'Conferma' },
    pendingLabel: { type: String, default: 'Attendere…' },
    danger: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
});

const emit = defineEmits(['confirm', 'cancel']);

const cancelBtn = ref(null);

function onKeydown(e) {
    if (e.key === 'Escape' && !props.loading) emit('cancel');
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    cancelBtn.value?.focus();
});
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="overlay" @click="!loading && emit('cancel')">
        <div class="modal" role="alertdialog" aria-modal="true" :aria-label="title" @click.stop>
            <div class="modal-head" style="display: flex; align-items: center; gap: 10px">
                <span v-if="danger" style="color: var(--danger)"><Icon name="warning" :size="20" /></span>
                {{ title }}
            </div>
            <div class="modal-body">
                <slot />
                <p v-if="error" class="modal-error" role="alert">{{ error }}</p>
            </div>
            <div class="modal-foot">
                <button ref="cancelBtn" class="btn btn-secondary btn-sm" :disabled="loading" @click="emit('cancel')">Annulla</button>
                <button :class="['btn btn-sm', danger ? 'btn-danger' : 'btn-dark']" :disabled="loading" @click="emit('confirm')">
                    <span v-if="loading" class="btn-spinner" aria-hidden="true"></span>
                    {{ loading ? pendingLabel : confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
