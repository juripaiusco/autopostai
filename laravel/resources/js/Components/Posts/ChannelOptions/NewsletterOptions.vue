<script setup>
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';

defineProps({
    modelValue: { type: Object, required: true }, // { list: {provider, id, name} | null, tag_id: number | null }
    lists: { type: Array, default: () => [] }, // candidati appena scaricati (non persistiti finché non scelti)
    provider: { type: String, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null }, // messaggio dell'ultima fetch fallita
    tags: { type: Array, default: () => [] }, // tag dell'account, per smtp_custom
    tagsLoading: { type: Boolean, default: false },
});

const emit = defineEmits(['pick', 'fetch', 'pick-tag', 'fetch-tags']);
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

        <div class="acc-li-pages">
            <button type="button" class="acc-li-page" :class="{ 'acc-li-page--active': !modelValue.tag_id }" @click="emit('pick-tag', null)">
                <span class="acc-li-page-dot" aria-hidden="true"></span>
                <span class="acc-li-page-name">Tutti i contatti attivi</span>
                <Icon v-if="!modelValue.tag_id" name="check" :size="16" />
            </button>
            <button
                v-for="tag in tags"
                :key="tag.id"
                type="button"
                class="acc-li-page"
                :class="{ 'acc-li-page--active': modelValue.tag_id === tag.id }"
                @click="emit('pick-tag', tag.id)"
            >
                <span class="acc-li-page-dot" aria-hidden="true"></span>
                <span class="acc-li-page-name">{{ tag.name }}</span>
                <Icon v-if="modelValue.tag_id === tag.id" name="check" :size="16" />
            </button>
        </div>
        <div v-if="!tags.length" class="pf-check-help" style="margin-bottom:10px">Nessun tag caricato ancora.</div>

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

        <div v-if="lists.length" class="acc-li-pages">
            <button
                v-for="list in lists"
                :key="list.id"
                type="button"
                class="acc-li-page"
                :class="{ 'acc-li-page--active': modelValue.list?.id === list.id }"
                @click="emit('pick', list)"
            >
                <span class="acc-li-page-dot" aria-hidden="true"></span>
                <span class="acc-li-page-name">{{ list.name }}</span>
                <Icon v-if="modelValue.list?.id === list.id" name="check" :size="16" />
            </button>
        </div>
        <div v-else-if="modelValue.list" class="acc-li-pages">
            <div class="acc-li-page acc-li-page--active">
                <span class="acc-li-page-dot" aria-hidden="true"></span>
                <span class="acc-li-page-name">{{ modelValue.list.name }}</span>
                <Icon name="check" :size="16" />
            </div>
        </div>
        <div v-else class="pf-check-help" style="margin-bottom:10px">Nessuna lista caricata ancora.</div>

        <button type="button" class="btn btn-secondary btn-sm" style="margin-top:12px" :disabled="loading" @click="emit('fetch')">
            <span v-if="loading" class="btn-spinner" aria-hidden="true"></span>
            {{ loading ? 'Caricamento…' : 'Carica liste' }}
        </button>
        <div v-if="error" class="pf-channel-error pf-fade-in" role="alert">{{ error }}</div>
    </div>
</template>
