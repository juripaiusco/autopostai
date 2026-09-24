<script setup>
import { computed } from 'vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import ChipPicker from '@/Components/UI/ChipPicker.vue';

const props = defineProps({
    modelValue: { type: Object, required: true }, // { list: {provider, id, name} | null, tag_id: number | null }
    lists: { type: Array, default: () => [] }, // candidati appena scaricati (non persistiti finché non scelti)
    provider: { type: String, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null }, // messaggio dell'ultima fetch fallita
    tags: { type: Array, default: () => [] }, // tag dell'account, per smtp_custom
    tagsLoading: { type: Boolean, default: false },
    activeCount: { type: Number, default: null }, // contatti attivi totali, per "Tutti i contatti attivi"
});

const emit = defineEmits(['pick', 'fetch', 'pick-tag', 'fetch-tags']);

// Liste non ancora ricaricate (es. in modifica): si mostra comunque quella già scelta.
const listOptions = computed(() => (props.lists.length ? props.lists : (props.modelValue.list ? [props.modelValue.list] : [])));

function onPickList(id) {
    const list = listOptions.value.find((l) => String(l.id) === String(id));
    if (list) emit('pick', list);
}
</script>

<template>
    <div class="pf-comments-card" v-if="provider === 'smtp_custom'">
        <div class="pf-comments-title" style="display:flex;align-items:center;gap:6px">
            <ChannelIcon id="newsletter" :size="14" />Newsletter
            <span class="pf-check-help" style="margin:0">(SMTP)</span>
        </div>
        <div class="pf-check-help" style="margin-bottom:10px">
            Scegli a chi inviare: tutti i contatti attivi, oppure solo quelli con un tag specifico.
        </div>

        <ChipPicker :options="tags" :model-value="modelValue.tag_id"
            :none-option="{ name: 'Tutti i contatti attivi', count: activeCount }"
            count-label="contatti attivi" aria-label="Destinatari newsletter"
            @update:model-value="emit('pick-tag', $event)" />
        <div v-if="!tags.length" class="pf-check-help" style="margin:10px 0">Nessun tag caricato ancora.</div>

        <button type="button" class="btn btn-secondary btn-sm" style="margin-top:12px" :disabled="tagsLoading" @click="emit('fetch-tags')">
            <span v-if="tagsLoading" class="btn-spinner" aria-hidden="true"></span>
            {{ tagsLoading ? 'Caricamento…' : 'Carica tag' }}
        </button>
        <div v-if="error" class="pf-channel-error pf-fade-in" role="alert">{{ error }}</div>
    </div>

    <div class="pf-comments-card" v-else>
        <div class="pf-comments-title" style="display:flex;align-items:center;gap:6px">
            <ChannelIcon id="newsletter" :size="14" />Newsletter
            <span v-if="provider" class="pf-check-help" style="margin:0">({{ provider === 'mailchimp' ? 'MailChimp' : 'Brevo' }})</span>
        </div>
        <div class="pf-check-help" style="margin-bottom:10px">Scegli la lista a cui inviare questo post.</div>

        <ChipPicker v-if="listOptions.length" :options="listOptions" :model-value="modelValue.list?.id ?? null"
            count-label="iscritti" aria-label="Lista newsletter"
            @update:model-value="onPickList" />
        <div v-else class="pf-check-help" style="margin-bottom:10px">Nessuna lista caricata ancora.</div>

        <button type="button" class="btn btn-secondary btn-sm" style="margin-top:12px" :disabled="loading" @click="emit('fetch')">
            <span v-if="loading" class="btn-spinner" aria-hidden="true"></span>
            {{ loading ? 'Caricamento…' : 'Carica liste' }}
        </button>
        <div v-if="error" class="pf-channel-error pf-fade-in" role="alert">{{ error }}</div>
    </div>
</template>
