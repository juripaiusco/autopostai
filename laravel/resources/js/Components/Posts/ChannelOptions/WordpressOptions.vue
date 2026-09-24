<script setup>
import { computed } from 'vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import ChipPicker from '@/Components/UI/ChipPicker.vue';

const props = defineProps({
    modelValue: { type: Object, required: true }, // { categories: [{id, name, count?, on}] }
    loading: { type: Boolean, default: false },
    error: { type: String, default: null }, // messaggio dell'ultima fetch fallita
});

const emit = defineEmits(['update', 'fetch']);

const selectedIds = computed(() => (props.modelValue.categories ?? []).filter((c) => c.on).map((c) => c.id));

function onPick(ids) {
    const on = ids.map(String);
    const categories = props.modelValue.categories.map((c) => ({ ...c, on: on.includes(String(c.id)) }));
    emit('update', 'categories', categories);
}
</script>

<template>
    <div class="pf-comments-card">
        <div class="pf-comments-title" style="display:flex;align-items:center;gap:6px">
            <ChannelIcon id="wordpress" :size="14" />WordPress
        </div>
        <div class="pf-check-help" style="margin-bottom:10px">Seleziona una o più categorie in cui pubblicare l'articolo.</div>

        <ChipPicker v-if="modelValue.categories?.length" multiple
            :options="modelValue.categories" :model-value="selectedIds"
            count-label="articoli" aria-label="Categorie WordPress"
            @update:model-value="onPick" />
        <div v-else class="pf-check-help" style="margin-bottom:10px">Nessuna categoria caricata ancora.</div>

        <button type="button" class="btn btn-secondary btn-sm" style="margin-top:12px" :disabled="loading" @click="emit('fetch')">
            <span v-if="loading" class="btn-spinner" aria-hidden="true"></span>
            {{ loading ? 'Caricamento…' : (modelValue.categories?.length ? 'Aggiorna categorie' : 'Carica categorie') }}
        </button>
        <div v-if="error" class="pf-channel-error pf-fade-in" role="alert">{{ error }}</div>
    </div>
</template>
