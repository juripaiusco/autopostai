<script setup>
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';

const props = defineProps({
    modelValue: { type: Object, required: true }, // { categories: [{id, name, on}] }
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update', 'fetch']);

function toggle(index) {
    const categories = props.modelValue.categories.map((c, i) => (i === index ? { ...c, on: !c.on } : c));
    emit('update', 'categories', categories);
}
</script>

<template>
    <div class="pf-comments-card">
        <div class="pf-comments-title" style="display:flex;align-items:center;gap:6px">
            <ChannelIcon id="wordpress" :size="14" />WordPress
        </div>
        <div class="pf-check-help" style="margin-bottom:10px">Seleziona una o più categorie in cui pubblicare l'articolo.</div>

        <div v-if="modelValue.categories?.length" class="acc-li-pages">
            <button
                v-for="(cat, i) in modelValue.categories"
                :key="cat.id"
                type="button"
                class="acc-li-page"
                :class="{ 'acc-li-page--active': cat.on }"
                @click="toggle(i)"
            >
                <span class="acc-li-page-dot" aria-hidden="true"></span>
                <span class="acc-li-page-name">{{ cat.name }}</span>
                <Icon v-if="cat.on" name="check" :size="16" />
            </button>
        </div>
        <div v-else class="pf-check-help" style="margin-bottom:10px">Nessuna categoria caricata ancora.</div>

        <button type="button" class="btn btn-secondary btn-sm" style="margin-top:12px" :disabled="loading" @click="emit('fetch')">
            <span v-if="loading" class="btn-spinner" aria-hidden="true"></span>
            {{ loading ? 'Caricamento…' : (modelValue.categories?.length ? 'Aggiorna categorie' : 'Carica categorie') }}
        </button>
    </div>
</template>
