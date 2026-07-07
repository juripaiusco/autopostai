<script setup>
import { ref, computed } from 'vue';
import { Combobox, ComboboxInput, ComboboxButton, ComboboxOptions, ComboboxOption } from '@headlessui/vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
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

const userQuery = ref('');
const selectedUser = computed(() => props.users.find((u) => u.id === props.form.user_id) ?? null);
const filteredUsers = computed(() => {
    const q = userQuery.value.trim().toLowerCase();
    if (!q) return props.users;
    return props.users.filter((u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q));
});
function userLabel(u) {
    return u ? `${u.name} - ${u.email}` : '';
}
function onUserSelect(u) {
    emit('set', 'user_id', u?.id ?? null);
}

/* ------------------------------------------------------------------ */
/* Fetch live: categorie WordPress / liste Newsletter                    */
/* ------------------------------------------------------------------ */

const wpLoading = ref(false);
async function fetchWordpressCategories() {
    if (!props.targetUserId) return;
    wpLoading.value = true;
    try {
        const res = await fetch(route('posts.wordpress-categories', props.targetUserId), { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        const existing = props.form.channels.wordpress?.categories ?? [];
        const merged = (data.categories ?? []).map((c) => ({
            ...c,
            on: existing.find((e) => e.id === c.id)?.on ?? false,
        }));
        emit('set-channel-option', 'wordpress', 'categories', merged);
    } finally {
        wpLoading.value = false;
    }
}

const nlLoading = ref(false);
const nlLists = ref([]);
const nlProvider = ref(null);
async function fetchNewsletterLists() {
    if (!props.targetUserId) return;
    nlLoading.value = true;
    try {
        const res = await fetch(route('posts.newsletter-lists', props.targetUserId), { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        nlLists.value = data.lists ?? [];
        nlProvider.value = data.provider ?? null;
    } finally {
        nlLoading.value = false;
    }
}
function pickNewsletterList(list) {
    emit('set-channel-option', 'newsletter', 'list', { provider: nlProvider.value, id: list.id, name: list.name });
}
</script>

<template>
    <div style="display:flex;flex-direction:column;gap:12px">

        <div class="flex w-full flex-row gap-4">

            <div class="acc-field w-1/3 pf-combobox" style="margin-bottom:0">
                <label class="acc-row-label" for="post-user">Account</label>
                <span class="acc-row-help">Il post verrà pubblicato con i canali collegati a questo account.</span>
                <Combobox v-if="mode === 'create' && users.length > 0" :model-value="selectedUser" @update:model-value="onUserSelect">
                    <div class="pf-combobox-wrap">
                        <ComboboxInput id="post-user" class="control"
                            :display-value="userLabel"
                            placeholder="Seleziona l'account"
                            @change="userQuery = $event.target.value" />
                        <ComboboxButton class="pf-combobox-btn" aria-label="Apri lista account">▾</ComboboxButton>
                        <ComboboxOptions class="pf-combobox-options">
                            <div v-if="filteredUsers.length === 0" class="pf-combobox-empty">Nessun account trovato</div>
                            <ComboboxOption v-for="u in filteredUsers" :key="u.id" :value="u" v-slot="{ active, selected }">
                                <div class="pf-combobox-option" :class="{ 'pf-combobox-option--active': active, 'pf-combobox-option--selected': selected }">
                                    {{ u.name }} - {{ u.email }}
                                </div>
                            </ComboboxOption>
                        </ComboboxOptions>
                    </div>
                </Combobox>
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
                :model-value="form.channels[ch.id]" :loading="wpLoading"
                @update="(field, value) => emit('set-channel-option', 'wordpress', field, value)"
                @fetch="fetchWordpressCategories" />

            <NewsletterOptions v-else-if="form.channels[ch.id] && ch.id === 'newsletter'"
                :model-value="form.channels[ch.id]" :lists="nlLists" :provider="nlProvider" :loading="nlLoading"
                @fetch="fetchNewsletterLists" @pick="pickNewsletterList" />
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

<style scoped>
.pf-combobox-wrap {
    position: relative;
}
.pf-combobox-btn {
    position: absolute;
    top: 0;
    right: 0;
    height: 100%;
    width: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--g500);
    background: transparent;
    border: none;
    cursor: pointer;
}
.pf-combobox-options {
    position: absolute;
    z-index: 20;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    max-height: 240px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid var(--g300);
    border-radius: var(--radius);
    box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
    padding: 4px;
}
.pf-combobox-empty {
    padding: 8px 10px;
    font-size: 13px;
    color: var(--g500);
}
.pf-combobox-option {
    padding: 8px 10px;
    font-size: 13.5px;
    color: var(--g700);
    border-radius: 6px;
    cursor: pointer;
}
.pf-combobox-option--active {
    background: var(--sky);
    color: #fff;
}
.pf-combobox-option--selected {
    font-weight: 600;
}
</style>
