<script setup>
import { ref, computed } from 'vue';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import ImageLightbox from '@/Components/Posts/ImageLightbox.vue';

const props = defineProps({
    form: { type: Object, required: true },
    channels: { type: Array, required: true }, // [{ id, label, limit }]
    saving: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
});

const emit = defineEmits(['set', 'back', 'save', 'save-and-add']);

const genLoading = ref(false);
const editing = ref(false);

const channelById = computed(() => Object.fromEntries(props.channels.map((c) => [c.id, c])));
const selectedChannelIds = computed(() => Object.keys(props.form.channels));

const allPreviews = computed(() => [
    ...props.form.existingImages.map((i) => i.url),
    ...props.form.newImages.map((i) => i.previewUrl),
]);
const lightboxIndex = ref(null);

const charLimit = computed(() => {
    const limits = selectedChannelIds.value.map((id) => channelById.value[id]?.limit).filter((l) => l != null);
    return limits.length ? Math.min(...limits) : null;
});

const limitChannel = computed(() => {
    if (charLimit.value === null) return null;
    const id = selectedChannelIds.value.find((c) => channelById.value[c]?.limit === charLimit.value);
    return id ? channelById.value[id] : null;
});

const overLimit = computed(() => charLimit.value !== null && (props.form.ai_content || '').length > charLimit.value);
const canSave = computed(() => selectedChannelIds.value.length > 0 && !overLimit.value);

let genTimer = null;
function runPreview() {
    genLoading.value = true;
    editing.value = false;
    emit('set', 'ai_content', '');
    clearTimeout(genTimer);
    genTimer = setTimeout(() => {
        genLoading.value = false;
        emit('set', 'ai_content',
            "Benvenuta estate! ☀️\n\nAbbiamo rinnovato il menù con piatti freschi e profumati per accompagnarti nelle serate più calde dell'anno.\n\nVieni a scoprire le novità — ti aspettiamo con il sorriso e una buona bottiglia fresca. 🍷\n\n#menùestivo #cucinaitaliana #trattoria");
    }, 1700);
}
</script>

<template>
    <div style="display:flex;flex-direction:column;gap:22px">
        <div class="acc-field" style="margin-bottom:0">
            <label class="acc-row-label" for="post-date">Data e ora di pubblicazione</label>
            <span class="acc-row-help">Lascia vuoto per pubblicare subito dopo aver confermato.</span>
            <input id="post-date" class="control" type="datetime-local" :value="form.published_at"
                style="max-width:280px" @input="emit('set', 'published_at', $event.target.value)" />
        </div>

        <!-- Riepilogo -->
        <div class="pf-summary">
            <div class="pf-summary-title">Riepilogo</div>
            <div v-if="allPreviews.length > 0" class="pf-summary-thumbs">
                <div v-for="(url, i) in allPreviews" :key="i" class="pf-summary-thumb">
                    <img :src="url" alt="Anteprima immagine del post" @click="lightboxIndex = i" />
                </div>
            </div>
            <div class="pf-summary-row">
                <div class="pf-summary-grow">
                    <div class="pf-summary-name">
                        <span v-if="form.title">{{ form.title }}</span>
                        <span v-else class="pf-summary-empty">Titolo non impostato</span>
                    </div>
                    <div class="pf-summary-prompt">
                        <span v-if="form.ai_prompt_post">{{ form.ai_prompt_post }}</span>
                        <span v-else class="pf-summary-empty">Nessun prompt inserito</span>
                    </div>
                    <div class="pf-summary-channels">
                        <span v-for="id in selectedChannelIds" :key="id" class="pf-summary-chip">
                            <ChannelIcon :id="id" :size="11" />{{ channelById[id]?.label ?? id }}
                        </span>
                        <span v-if="selectedChannelIds.length === 0" class="pf-summary-warn">⚠ Nessun canale selezionato</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Anteprima AI -->
        <div>
            <div class="pf-ai-head">
                <label class="acc-row-label" style="margin:0">
                    Anteprima AI
                    <span class="acc-row-help" style="margin:0">Testo generato dall'AI — puoi modificarlo prima di pubblicare.</span>
                </label>
                <button type="button" class="btn btn-secondary btn-sm" :disabled="genLoading || !form.ai_prompt_post" @click="runPreview">
                    <Icon name="sparkles" :size="14" />
                    {{ genLoading ? 'Generazione…' : 'Genera anteprima' }}
                </button>
            </div>
            <div class="pf-ai-box">
                <div v-if="genLoading" class="pf-ai-loading">
                    <div class="pf-spinner pf-spinner--sm"></div>
                </div>

                <template v-if="form.ai_content">
                    <textarea v-if="editing" class="control pf-ai-textarea" rows="6" :value="form.ai_content"
                        @input="emit('set', 'ai_content', $event.target.value)" />
                    <div v-else class="pf-ai-text pf-fade-in">{{ form.ai_content }}</div>
                </template>
                <div v-else class="pf-ai-empty">
                    {{ form.ai_prompt_post ? 'Clicca "Genera anteprima" per vedere il testo che verrà pubblicato.' : 'Inserisci un prompt nello step 1 per generare l\'anteprima.' }}
                </div>

                <div v-if="form.ai_content && !genLoading" class="pf-ai-meta">
                    <div class="pf-ai-meta-row">
                        <button type="button" class="pf-ai-edit-btn" @click="editing = !editing">{{ editing ? '✓ Fatto' : 'Modifica' }}</button>
                        <span class="pf-ai-count" :class="{ 'pf-ai-count--over': overLimit }">
                            {{ form.ai_content.length.toLocaleString('it-IT') }}{{ charLimit !== null ? ` / ${charLimit.toLocaleString('it-IT')}` : '' }} caratteri
                            <template v-if="charLimit !== null && limitChannel"> · limite {{ limitChannel.label }}</template>
                        </span>
                    </div>
                    <div v-if="overLimit" class="pf-ai-warn pf-fade-in">
                        <Icon name="warning" :size="14" />
                        <span>Superi il limite di {{ limitChannel.label }} di {{ charLimit.toLocaleString('it-IT') }} caratteri: riduci il testo per poter salvare.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Azioni -->
        <div class="pf-actions">
            <button type="button" class="btn btn-secondary" @click="emit('back')">← Indietro</button>
            <div class="spacer"></div>
            <button v-if="mode !== 'edit'" type="button" class="btn btn-secondary" :disabled="!canSave || saving"
                title="Salva questo post e creane subito una copia, da adattare a un altro canale"
                @click="emit('save-and-add')">
                <span v-if="saving" class="btn-spinner" aria-hidden="true"></span>
                <Icon v-else name="clone" :size="16" />{{ saving ? 'Salvataggio…' : 'Salva e aggiungi' }}
            </button>
            <button type="button" class="btn btn-dark" :disabled="!canSave || saving" @click="emit('save')">
                <span v-if="saving" class="btn-spinner" aria-hidden="true"></span>
                <Icon v-else name="bookmark" :size="16" />{{ saving ? 'Salvataggio…' : (mode === 'edit' ? 'Salva modifiche' : 'Salva') }}
            </button>
        </div>

        <ImageLightbox v-if="lightboxIndex !== null" :images="allPreviews" :index="lightboxIndex" @close="lightboxIndex = null" />
    </div>
</template>
