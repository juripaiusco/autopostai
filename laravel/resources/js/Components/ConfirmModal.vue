<script setup>
import Icon from '@/Components/Icon.vue';

defineProps({
    title: { type: String, required: true },
    confirmLabel: { type: String, default: 'Conferma' },
    danger: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <div class="overlay" @click="emit('cancel')">
        <div class="modal" @click.stop>
            <div class="modal-head" style="display: flex; align-items: center; gap: 10px">
                <span v-if="danger" style="color: var(--danger)"><Icon name="warning" :size="20" /></span>
                {{ title }}
            </div>
            <div class="modal-body">
                <slot />
            </div>
            <div class="modal-foot">
                <button class="btn btn-secondary btn-sm" @click="emit('cancel')">Annulla</button>
                <button :class="['btn btn-sm', danger ? 'btn-danger' : 'btn-dark']" @click="emit('confirm')">{{ confirmLabel }}</button>
            </div>
        </div>
    </div>
</template>
