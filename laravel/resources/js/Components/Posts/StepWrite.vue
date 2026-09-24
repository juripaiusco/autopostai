<script setup>
import { ref, computed } from 'vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import ComboboxSelect from '@/Components/UI/ComboboxSelect.vue';
import SocialOptions from '@/Components/Posts/ChannelOptions/SocialOptions.vue';
import WordpressOptions from '@/Components/Posts/ChannelOptions/WordpressOptions.vue';
import NewsletterOptions from '@/Components/Posts/ChannelOptions/NewsletterOptions.vue';

const props = defineProps({
    form: { type: Object, required: true },
    channels: { type: Array, required: true }, // [{ id, label, limit, available, replyOn }]
    users: { type: Array, default: () => [] },
    mode: { type: String, default: 'create' },
    owner: { type: Object, default: null },
    targetUserId: { type: [Number, String], default: null },
});

const emit = defineEmits(['set', 'toggle-channel', 'set-channel-option']);

const SOCIAL_IDS = ['facebook', 'instagram', 'linkedin'];

function toggleChannel(ch) {
    if (!ch.available) return;
    emit('toggle-channel', ch.id);
}

const selectedSocialWithAutoReply = computed(() => SOCIAL_IDS.some((id) => props.form.channels[id]?.auto_reply_enabled));

/* ------------------------------------------------------------------ */
/* Fetch live: categorie WordPress / liste Newsletter                    */
/* ------------------------------------------------------------------ */

/**
 * GET JSON verso gli endpoint live del form. Ritorna i dati, oppure lancia
 * un Error con il messaggio del server (abort 422/502) da mostrare inline.
 */
async function getJson(url) {
    let res;
    try {
        res = await fetch(url, { headers: { Accept: 'application/json' } });
    } catch {
        throw new Error('Connessione non riuscita. Riprova.');
    }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || `Errore durante il caricamento (HTTP ${res.status}).`);
    return data;
}

const wpLoading = ref(false);
const wpError = ref(null);
async function fetchWordpressCategories() {
    if (!props.targetUserId) return;
    wpLoading.value = true;
    wpError.value = null;
    try {
        const data = await getJson(route('posts.wordpress-categories', props.targetUserId));
        const existing = props.form.channels.wordpress?.categories ?? [];
        const merged = (data.categories ?? []).map((c) => ({
            ...c,
            on: existing.find((e) => e.id === c.id)?.on ?? false,
        }));
        emit('set-channel-option', 'wordpress', 'categories', merged);
    } catch (e) {
        wpError.value = e.message;
    } finally {
        wpLoading.value = false;
    }
}

const nlLoading = ref(false);
const nlError = ref(null);
const nlLists = ref([]);
const nlProvider = computed(() => props.channels.find((c) => c.id === 'newsletter')?.provider ?? null);
async function fetchNewsletterLists() {
    if (!props.targetUserId) return;
    nlLoading.value = true;
    nlError.value = null;
    try {
        const data = await getJson(route('posts.newsletter-lists', props.targetUserId));
        nlLists.value = data.lists ?? [];
    } catch (e) {
        nlError.value = e.message;
    } finally {
        nlLoading.value = false;
    }
}
function pickNewsletterList(list) {
    emit('set-channel-option', 'newsletter', 'list', { provider: nlProvider.value, id: list.id, name: list.name });
}

const nlTagsLoading = ref(false);
const nlTags = ref([]);
const nlActiveCount = ref(null);
async function fetchNewsletterTags() {
    if (!props.targetUserId) return;
    nlTagsLoading.value = true;
    nlError.value = null;
    try {
        const data = await getJson(route('posts.newsletter-tags', props.targetUserId));
        nlTags.value = data.tags ?? [];
        nlActiveCount.value = data.activeCount ?? null;
    } catch (e) {
        nlError.value = e.message;
    } finally {
        nlTagsLoading.value = false;
    }
}
function pickNewsletterTag(tagId) {
    emit('set-channel-option', 'newsletter', 'tag_id', tagId);
}
</script>

<template>
    <div style="display:flex;flex-direction:column;gap:12px">

        <div class="flex w-full flex-row gap-4">

            <div class="acc-field w-1/3" style="margin-bottom:0">
                <label class="acc-row-label" for="post-user">Account</label>
                <span class="acc-row-help">Il post verrà pubblicato con i canali collegati a questo account.</span>
                <ComboboxSelect v-if="mode === 'create' && users.length > 0" id="post-user"
                    :model-value="form.user_id" :options="users" placeholder="Seleziona l'account"
                    empty-text="Nessun account trovato"
                    @update:model-value="emit('set', 'user_id', $event)" />
                <div v-else-if="mode === 'edit' && owner" class="acc-field" style="margin-bottom:0">
                    <input class="control readonly" readonly type="text" :value="owner.name + ' - ' + owner.email" />
                </div>
            </div>

            <div class="acc-field w-2/3" style="margin-bottom:0">
                <label class="acc-row-label" for="post-title">Titolo</label>
                <span class="acc-row-help">Solo ad uso interno, non viene pubblicato.</span>
                <input id="post-title" class="control" type="text" :value="form.title"
                    placeholder="Es. Promo del venerdì"
                    @input="emit('set', 'title', $event.target.value)" />
            </div>

        </div>

        <div class="acc-field" style="margin-bottom:0">
            <label class="acc-row-label" for="post-prompt">Prompt</label>
            <span class="acc-row-help">L'AI genera il testo del post in base a queste indicazioni.</span>
            <textarea id="post-prompt" class="control" rows="5" :value="form.ai_prompt_post"
                placeholder="Esempio:&#10;Crea un post per annunciare il nuovo menù estivo. Tono caldo e invitante, max 400 caratteri."
                @input="emit('set', 'ai_prompt_post', $event.target.value)" />
            <div class="pf-counter">{{ (form.ai_prompt_post || '').length }} caratteri</div>
        </div>

        <div>
            <label class="acc-row-label">Canali</label>
            <span class="acc-row-help">Scegli dove pubblicare questo post. FaPer3 gestisce tutti questi canali — quelli disattivati non sono ancora collegati a questo account.</span>
            <div class="pf-channel-grid">
                <button v-for="ch in channels" :key="ch.id" type="button"
                    class="pf-channel-btn" :class="{ 'pf-channel-btn--on': !!form.channels[ch.id], 'pf-channel-btn--disabled': !ch.available }"
                    :aria-pressed="!!form.channels[ch.id]"
                    :disabled="!ch.available"
                    :title="ch.available ? '' : 'Non collegato per questo account'"
                    @click="toggleChannel(ch)">
                    <ChannelIcon :id="ch.id" :size="15" />
                    {{ ch.label }}
                </button>
            </div>
            <div v-if="Object.keys(form.channels).length === 0" class="pf-channel-error pf-fade-in">
                Seleziona almeno un canale per continuare.
            </div>
        </div>

        <template v-for="ch in channels" :key="'opt-' + ch.id">
            <SocialOptions v-if="form.channels[ch.id] && SOCIAL_IDS.includes(ch.id)"
                :channel-id="ch.id" :label="ch.label" :model-value="form.channels[ch.id]" :reply-on="ch.replyOn"
                @update="(field, value) => emit('set-channel-option', ch.id, field, value)" />

            <WordpressOptions v-else-if="form.channels[ch.id] && ch.id === 'wordpress'"
                :model-value="form.channels[ch.id]" :loading="wpLoading" :error="wpError"
                @update="(field, value) => emit('set-channel-option', 'wordpress', field, value)"
                @fetch="fetchWordpressCategories" />

            <NewsletterOptions v-else-if="form.channels[ch.id] && ch.id === 'newsletter'"
                :model-value="form.channels[ch.id]" :lists="nlLists" :provider="nlProvider" :loading="nlLoading" :error="nlError"
                :tags="nlTags" :tags-loading="nlTagsLoading" :active-count="nlActiveCount"
                @fetch="fetchNewsletterLists" @pick="pickNewsletterList"
                @fetch-tags="fetchNewsletterTags" @pick-tag="pickNewsletterTag" />
        </template>

        <div v-if="selectedSocialWithAutoReply" class="acc-field pf-fade-in" style="margin-bottom:0">
            <label class="acc-row-label" for="post-comment-prompt">Prompt per le risposte</label>
            <span class="acc-row-help">Istruzioni per l'AI, usate su tutti i canali con risposta automatica attiva. Facoltativo.</span>
            <textarea id="post-comment-prompt" class="control" rows="3" :value="form.ai_prompt_comment"
                placeholder="Es. Rispondi in modo cordiale, ringrazia sempre chi commenta"
                @input="emit('set', 'ai_prompt_comment', $event.target.value)" />
        </div>
    </div>
</template>
