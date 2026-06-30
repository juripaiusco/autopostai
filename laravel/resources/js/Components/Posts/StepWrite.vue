<script setup>
import { ref, computed } from 'vue';
import { Combobox, ComboboxInput, ComboboxButton, ComboboxOptions, ComboboxOption } from '@headlessui/vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import ToggleSwitch from '@/Components/UI/ToggleSwitch.vue';

const props = defineProps({
    form: { type: Object, required: true },
    channels: { type: Array, required: true }, // [{ id, label }]
    users: { type: Array, default: () => [] },
    mode: { type: String, default: 'create' },
    owner: { type: Object, default: null },
});

const emit = defineEmits(['set']);

function toggleChannel(id) {
    const selected = props.form.channels.includes(id);
    emit('set', 'channels', selected
        ? props.form.channels.filter((c) => c !== id)
        : [...props.form.channels, id]);
}

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

        <div>
            <label class="acc-row-label">Canali</label>
            <span class="acc-row-help">Scegli dove pubblicare questo post.</span>
            <div class="pf-channel-grid">
                <button v-for="ch in channels" :key="ch.id" type="button"
                    class="pf-channel-btn" :class="{ 'pf-channel-btn--on': form.channels.includes(ch.id) }"
                    :aria-pressed="form.channels.includes(ch.id)"
                    @click="toggleChannel(ch.id)">
                    <ChannelIcon :id="ch.id" :size="15" />
                    {{ ch.label }}
                </button>
            </div>
            <div v-if="form.channels.length === 0" class="pf-channel-error pf-fade-in">
                Seleziona almeno un canale per continuare.
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

        <div class="pf-comments-card">
            <div class="pf-comments-title">Commenti</div>

            <div class="acc-ch-opt-row pf-check-row">
                <div class="acc-ch-opt-txt">
                    <div class="pf-check-title">Abilita i commenti</div>
                    <div class="pf-check-help">Gli utenti potranno commentare il post sui canali che lo supportano.</div>
                </div>
                <ToggleSwitch :model-value="form.comments_enabled" @update:model-value="emit('set', 'comments_enabled', $event)" />
            </div>

            <div class="acc-ch-opt-row pf-check-row" :class="{ 'pf-check-row--disabled': !form.comments_enabled }">
                <div class="acc-ch-opt-txt">
                    <div class="pf-check-title">Abilita risposte automatiche</div>
                    <div class="pf-check-help">L'AI risponderà ai commenti seguendo le istruzioni qui sotto. Sempre revisionabili prima dell'invio.</div>
                </div>
                <ToggleSwitch :model-value="form.auto_reply_enabled" :disabled="!form.comments_enabled"
                    @update:model-value="emit('set', 'auto_reply_enabled', $event)" />
            </div>

            <div v-if="form.comments_enabled && form.auto_reply_enabled" class="acc-field" style="margin-top:10px;margin-bottom:0">
                <label class="acc-row-label" for="post-comment-prompt">Prompt per le risposte</label>
                <span class="acc-row-help">Istruzioni per l'AI. Facoltativo.</span>
                <textarea id="post-comment-prompt" class="control" rows="3" :value="form.ai_prompt_comment"
                    placeholder="Es. Rispondi in modo cordiale, ringrazia sempre chi commenta"
                    @input="emit('set', 'ai_prompt_comment', $event.target.value)" />
            </div>
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
