<script setup>
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
</script>

<template>
    <div style="display:flex;flex-direction:column;gap:12px">

        <div class="flex w-full flex-row gap-4">

            <div v-if="mode === 'create' && users.length > 0" class="acc-field w-1/3" style="margin-bottom:0">
                <label class="acc-row-label" for="post-user">Account</label>
                <span class="acc-row-help">Il post verrà pubblicato con i canali collegati a questo account.</span>
                <select id="post-user" class="control" :value="form.user_id"
                    @change="emit('set', 'user_id', Number($event.target.value) || null)">
                    <option disabled value="">Seleziona l'account</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} - {{ u.email }}</option>
                </select>
            </div>
            <div v-else-if="mode === 'edit' && owner" class="acc-field w-1/3" style="margin-bottom:0">
                <label class="acc-row-label">Account</label>
                <span class="acc-row-help">{{ owner.name }} - {{ owner.email }}</span>
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
