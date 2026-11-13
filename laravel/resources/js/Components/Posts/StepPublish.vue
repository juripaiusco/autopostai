<script setup>
import { ref, computed, watch } from 'vue';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import ImageLightbox from '@/Components/Posts/ImageLightbox.vue';
import ToggleSwitch from '@/Components/UI/ToggleSwitch.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import RichEditor from '@/Components/Editor/RichEditor.vue';
import SocialEditor from '@/Components/Editor/SocialEditor.vue';
import { hasArticleChannel, splitArticle, joinArticle, htmlToText, normalizeContent } from '@/lib/postText.js';
import NewsletterPreview from '@/Components/Posts/NewsletterPreview.vue';
import { hasCustomTemplate, needsCommonText, usesContent, renderNewsletterPreview } from '@/lib/newsletterTemplate.js';

const props = defineProps({
    form: { type: Object, required: true },
    channels: { type: Array, required: true }, // [{ id, label, limit }]
    saving: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    targetUserId: { type: [Number, String], default: null },
});

const emit = defineEmits(['set', 'back', 'save', 'save-and-add', 'publish-now']);

const genLoading = ref(false); // anteprima (articolo o testo social unico)
const socialLoading = ref(false); // "Genera versione social"
const genError = ref(null);
// Avvisi della generazione (link del prompt non leggibili, immagini inventate tolte).
const genWarnings = ref([]);

function csrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

const channelById = computed(() => Object.fromEntries(props.channels.map((c) => [c.id, c])));
const selectedChannelIds = computed(() => Object.keys(props.form.channels));

const allPreviews = computed(() => [
    ...props.form.existingImages.map((i) => i.url),
    ...props.form.newImages.map((i) => i.previewUrl),
]);
const lightboxIndex = ref(null);

// Solo WordPress e Newsletter hanno un formato di prompt differenziato
// (Markdown, vedi publisher/publishing/{wordpress,newsletter}.py::build_prompt);
// gli altri canali condividono lo stesso ai_prompt_post cosi' com'e'.
const primaryChannel = computed(() => {
    if (selectedChannelIds.value.includes('wordpress')) return 'wordpress';
    if (selectedChannelIds.value.includes('newsletter')) return 'newsletter';
    return null;
});

// Due famiglie (docs/editor-anteprima.md): "articolo" (WordPress/Newsletter,
// editor ricco + titolo, HTML) e "social" (Facebook/Instagram/LinkedIn, testo
// semplice + emoji). Con entrambe: schede, social derivato o personalizzato.
const isArticle = computed(() => hasArticleChannel(selectedChannelIds.value));
const socialChannels = computed(() => selectedChannelIds.value
    .filter((id) => channelById.value[id]?.limit != null)
    .map((id) => channelById.value[id]));
const hasSocial = computed(() => socialChannels.value.length > 0);
const socialLabels = computed(() => socialChannels.value.map((c) => c.label).join(', '));
const showTabs = computed(() => isArticle.value && hasSocial.value);
const tab = ref('article');

const titleLabel = computed(() => {
    const wp = selectedChannelIds.value.includes('wordpress');
    const nl = selectedChannelIds.value.includes('newsletter');
    if (wp && nl) return "Titolo dell'articolo e oggetto dell'email";
    return wp ? "Titolo dell'articolo" : "Oggetto dell'email";
});

const articleLabel = computed(() => {
    const wp = selectedChannelIds.value.includes('wordpress');
    const nl = selectedChannelIds.value.includes('newsletter');
    if (wp && nl) return 'WordPress e Newsletter';
    return wp ? 'WordPress' : 'la Newsletter';
});

// ---------- Articolo ----------
// Titolo e corpo tenuti a parte mentre si scrive (il titolo non va
// "ripulito" a ogni tasto); si riallineano quando il testo cambia da fuori
// (nuova anteprima, cambio canali).
const articleTitle = ref('');
const articleBody = ref('');

function syncArticle() {
    if (joinArticle(articleTitle.value, articleBody.value) === (props.form.ai_content || '')) return;
    const { title, body } = splitArticle(props.form.ai_content);
    articleTitle.value = title;
    articleBody.value = body;
}

function setArticle(title, body) {
    articleTitle.value = title;
    articleBody.value = body;
    const content = articleBody.value || articleTitle.value.trim() ? joinArticle(title, body) : '';
    emit('set', 'ai_content', content);
    emit('set', 'ai_content_format', 'html');
}

const articleText = computed(() => (isArticle.value ? htmlToText(props.form.ai_content) : ''));

// ---------- Social ----------
// Personalizzato = ai_content_social non null (anche vuoto mentre si scrive).
const customSocial = computed(() => props.form.ai_content_social !== null && props.form.ai_content_social !== undefined);
const confirmDropSocial = ref(false);

function setCustomSocial(on) {
    if (on) {
        // Si parte dal testo derivato: spesso basta accorciarlo.
        emit('set', 'ai_content_social', articleText.value);
        return;
    }
    const text = (props.form.ai_content_social ?? '').trim();
    if (text && text !== articleText.value.trim()) {
        confirmDropSocial.value = true;
        return;
    }
    emit('set', 'ai_content_social', null);
}

function dropCustomSocial() {
    confirmDropSocial.value = false;
    emit('set', 'ai_content_social', null);
}

function setSocialOnly(text) {
    emit('set', 'ai_content', text);
    emit('set', 'ai_content_format', 'text');
}

// Testo che uscirà sui social: unico (solo social), personalizzato o ricavato
// dall'articolo (stesse regole del worker, publisher/domain/channel_text.py).
const socialText = computed(() => {
    if (!isArticle.value) return props.form.ai_content || '';
    return customSocial.value ? (props.form.ai_content_social || '') : articleText.value;
});

const charLimit = computed(() => {
    const limits = socialChannels.value.map((c) => c.limit);
    return limits.length ? Math.min(...limits) : null;
});
const limitChannel = computed(() => socialChannels.value.find((c) => c.limit === charLimit.value) ?? null);
const overLimit = computed(() => charLimit.value !== null && socialText.value.length > charLimit.value);
// ---------- Newsletter con HTML personalizzato ----------
const customTemplate = computed(() => hasCustomTemplate(props.form));
// Newsletter da sola senza [content]: niente testo del post, l'oggetto è il titolo.
const templateOnly = computed(() => !needsCommonText(props.form));
const missingSubject = computed(() => templateOnly.value && !(props.form.title || '').trim());
const nlPreviewHtml = computed(() => (customTemplate.value
    ? renderNewsletterPreview(props.form.nl_template_custom, usesContent(props.form.nl_template_custom) ? articleBody.value : '', allPreviews.value)
    : ''));

const canSave = computed(() => selectedChannelIds.value.length > 0 && !overLimit.value && !missingSubject.value);

// Canali cambiati dopo aver scritto: testo e formato si adeguano alla famiglia.
watch(isArticle, () => {
    const { content, format } = normalizeContent(props.form.ai_content, props.form.ai_content_format, selectedChannelIds.value);
    if (content !== props.form.ai_content) emit('set', 'ai_content', content);
    if (format !== props.form.ai_content_format) emit('set', 'ai_content_format', format);
}, { immediate: true });

watch(() => props.form.ai_content, syncArticle, { immediate: true });

// ---------- AI ----------
async function generate(body) {
    const res = await fetch(route('posts.generate-text', props.targetUserId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        body: JSON.stringify({ ...body, channels: selectedChannelIds.value }),
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.message ?? 'Generazione non riuscita, riprova.');
    if (json.token_log_id) {
        emit('set', 'preview_token_log_ids', [...(props.form.preview_token_log_ids ?? []), json.token_log_id]);
    }
    return json;
}

async function runPreview() {
    genLoading.value = true;
    genError.value = null;
    genWarnings.value = [];
    try {
        const json = await generate({ ai_prompt_post: props.form.ai_prompt_post, channel: primaryChannel.value });
        genWarnings.value = json.warnings ?? [];
        if (isArticle.value) {
            emit('set', 'ai_content', json.html ?? '');
            emit('set', 'ai_content_format', 'html');
        } else {
            setSocialOnly(json.content ?? '');
        }
    } catch (e) {
        genError.value = e.message || 'Generazione non riuscita, riprova.';
    } finally {
        genLoading.value = false;
    }
}

// Post social riscritto dall'articolo (più breve, tono social): una chiamata
// AI solo su richiesta.
async function runSocialVersion() {
    socialLoading.value = true;
    genError.value = null;
    try {
        const json = await generate({ ai_prompt_post: props.form.ai_prompt_post, source_text: articleText.value });
        emit('set', 'ai_content_social', json.content ?? '');
    } catch (e) {
        genError.value = e.message || 'Generazione non riuscita, riprova.';
    } finally {
        socialLoading.value = false;
    }
}
</script>

<template>
    <div style="display:flex;flex-direction:column;gap:22px">
        <div class="acc-field" style="margin-bottom:0">
            <label class="acc-row-label" for="post-date">Data e ora di pubblicazione</label>
            <span class="acc-row-help">Lascia vuoto per salvare come bozza: il post non esce finché non imposti una data. Per farlo uscire adesso usa "Pubblica subito".</span>
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

        <!-- Anteprima AI (non serve se la newsletter è tutta nell'HTML personalizzato) -->
        <div v-if="!templateOnly">
            <div class="pf-ai-head">
                <label class="acc-row-label" style="margin:0">
                    Anteprima AI
                    <span v-if="isArticle" class="acc-row-help" style="margin:0">
                        Testo per {{ articleLabel }}: scrivilo tu o generalo con l'AI, poi formattalo con la barra.
                    </span>
                    <span v-else class="acc-row-help" style="margin:0">Testo per {{ socialLabels }}: scrivilo tu o generalo con l'AI, poi modificalo come vuoi.</span>
                </label>
                <button type="button" class="btn btn-secondary btn-sm" :disabled="genLoading || !form.ai_prompt_post"
                    :title="form.ai_prompt_post ? '' : 'Inserisci un prompt nel passo 1'" @click="runPreview">
                    <Icon name="sparkles" :size="14" />
                    {{ genLoading ? 'Generazione…' : 'Genera anteprima' }}
                </button>
            </div>
            <div v-if="genError" class="pf-ai-warn pf-fade-in" style="margin-bottom:8px">
                <Icon name="warning" :size="14" />
                <span>{{ genError }}</span>
            </div>
            <div v-if="genWarnings.length" class="pf-ai-notice pf-fade-in" role="status">
                <Icon name="warning" :size="14" />
                <ul><li v-for="(w, i) in genWarnings" :key="i">{{ w }}</li></ul>
            </div>

            <div v-if="showTabs" class="pf-tabs" role="tablist">
                <button type="button" role="tab" class="pf-tab" :class="{ 'is-active': tab === 'article' }" :aria-selected="tab === 'article'"
                    @click="tab = 'article'">Articolo</button>
                <button type="button" role="tab" class="pf-tab" :class="{ 'is-active': tab === 'social' }" :aria-selected="tab === 'social'"
                    @click="tab = 'social'">
                    Social<span v-if="overLimit" class="pf-tab-dot" title="Testo social oltre il limite" />
                </button>
            </div>

            <!-- Articolo: WordPress / Newsletter -->
            <div v-if="isArticle" v-show="!showTabs || tab === 'article'">
                <label class="sr-only" for="article-title">{{ titleLabel }}</label>
                <input id="article-title" class="control pf-article-title" type="text" :value="articleTitle" :placeholder="titleLabel"
                    :disabled="genLoading" @input="setArticle($event.target.value, articleBody)" />
                <div style="position:relative">
                    <div v-if="genLoading" class="pf-ai-loading">
                        <div class="pf-spinner pf-spinner--sm"></div>
                    </div>
                    <RichEditor :model-value="articleBody" :disabled="genLoading" :user-id="targetUserId"
                        :allow-cta="selectedChannelIds.includes('newsletter')"
                        placeholder="Scrivi l'articolo, oppure usa “Genera anteprima” per farlo scrivere all'AI."
                        @update:model-value="setArticle(articleTitle, $event)" />
                </div>
            </div>

            <!-- Social con articolo: derivato o personalizzato -->
            <div v-if="showTabs" v-show="tab === 'social'">
                <div class="pf-social-head">
                    <label class="acc-row-label" style="margin:0;display:flex;align-items:center;gap:10px;cursor:pointer">
                        <ToggleSwitch :model-value="customSocial" @update:model-value="setCustomSocial" />
                        Personalizza testo social
                    </label>
                    <button v-if="customSocial" type="button" class="btn btn-secondary btn-sm" :disabled="socialLoading || !articleText"
                        :title="articleText ? 'Riscrive l\'articolo come post per i social' : 'Scrivi o genera prima l\'articolo'" @click="runSocialVersion">
                        <Icon name="sparkles" :size="14" />
                        {{ socialLoading ? 'Generazione…' : 'Genera versione social' }}
                    </button>
                </div>
                <template v-if="customSocial">
                    <p class="pf-social-note">Su {{ socialLabels }} esce questo testo al posto dell'articolo.</p>
                    <div style="position:relative">
                        <div v-if="socialLoading" class="pf-ai-loading">
                            <div class="pf-spinner pf-spinner--sm"></div>
                        </div>
                        <SocialEditor :model-value="form.ai_content_social ?? ''" :disabled="socialLoading" :user-id="targetUserId"
                            placeholder="Scrivi il post per i social, oppure usa “Genera versione social”."
                            @update:model-value="emit('set', 'ai_content_social', $event)" />
                    </div>
                </template>
                <template v-else>
                    <p class="pf-social-note">Su {{ socialLabels }} esce il testo dell'articolo senza formattazione. Per un post più breve o con un tono diverso, attiva "Personalizza testo social".</p>
                    <div v-if="articleText" class="pf-social-derived">{{ articleText }}</div>
                    <div v-else class="pf-ai-empty">Scrivi o genera prima l'articolo.</div>
                </template>
            </div>

            <!-- Solo social -->
            <div v-if="!isArticle" style="position:relative">
                <div v-if="genLoading" class="pf-ai-loading">
                    <div class="pf-spinner pf-spinner--sm"></div>
                </div>
                <SocialEditor :model-value="form.ai_content || ''" :disabled="genLoading" :user-id="targetUserId"
                    placeholder="Scrivi il post, oppure usa “Genera anteprima” per farlo scrivere all'AI."
                    @update:model-value="setSocialOnly" />
            </div>

            <div v-if="charLimit !== null && socialText" class="pf-ai-meta">
                <span class="pf-ai-count" :class="{ 'pf-ai-count--over': overLimit }">
                    <template v-if="isArticle">Testo social: </template>{{ socialText.length.toLocaleString('it-IT') }} / {{ charLimit.toLocaleString('it-IT') }} caratteri
                    <template v-if="limitChannel"> · limite {{ limitChannel.label }}</template>
                </span>
                <div v-if="overLimit" class="pf-ai-warn pf-fade-in">
                    <Icon name="warning" :size="14" />
                    <span v-if="isArticle && !customSocial">L'articolo è troppo lungo per {{ limitChannel.label }} ({{ charLimit.toLocaleString('it-IT') }} caratteri): nella scheda Social attiva "Personalizza testo social" e scrivi un testo più breve.</span>
                    <span v-else>Il testo supera il limite di {{ limitChannel.label }} ({{ charLimit.toLocaleString('it-IT') }} caratteri): accorcialo per poter salvare.</span>
                </div>
            </div>
        </div>

        <!-- Newsletter: HTML personalizzato -->
        <div v-if="customTemplate">
            <label class="acc-row-label" for="publish-nl-template" style="margin:0">
                Email newsletter
                <span class="acc-row-help" style="margin:0">
                    <template v-if="templateOnly">L'email è il tuo HTML: ultime modifiche qui sotto, l'anteprima si aggiorna mentre scrivi.</template>
                    <template v-else>Il testo dell'articolo entra al posto di <code>[content]</code>; l'oggetto è il titolo dell'articolo.</template>
                </span>
            </label>
            <div v-if="templateOnly" class="acc-field" style="margin:10px 0 0">
                <label class="acc-row-label" for="publish-nl-subject">Oggetto dell'email</label>
                <input id="publish-nl-subject" class="control" type="text" :value="form.title"
                    @input="emit('set', 'title', $event.target.value)" />
                <div v-if="missingSubject" class="pf-ai-warn pf-fade-in" style="margin-top:6px">
                    <Icon name="warning" :size="14" />
                    <span>Inserisci l'oggetto dell'email per poter salvare.</span>
                </div>
            </div>
            <textarea id="publish-nl-template" class="control pf-nl-code" rows="10" spellcheck="false" style="margin-top:10px"
                :value="form.nl_template_custom" @input="emit('set', 'nl_template_custom', $event.target.value)" />
            <div class="pf-check-help" style="margin:12px 0 6px">
                Anteprima — i pulsanti <code>[cta]</code> e il link di disiscrizione vengono completati all'invio.
            </div>
            <NewsletterPreview :html="nlPreviewHtml" />
        </div>

        <ConfirmModal v-if="confirmDropSocial" title="Tornare al testo dell'articolo?" confirm-label="Sì, usa l'articolo" danger
            @confirm="dropCustomSocial" @cancel="confirmDropSocial = false">
            Il testo social che hai scritto verrà eliminato e su {{ socialLabels }} uscirà l'articolo senza formattazione.
        </ConfirmModal>

        <!-- Azioni -->
        <div class="pf-actions">
            <button type="button" class="btn btn-secondary" @click="emit('back')">← Indietro</button>
            <div class="spacer"></div>
            <button type="button" class="btn btn-secondary" :disabled="!canSave || saving"
                title="Salva e crea una copia programmata 1 minuto dopo, da adattare a un altro canale"
                @click="emit('save-and-add')">
                <span v-if="saving" class="btn-spinner" aria-hidden="true"></span>
                <Icon v-else name="clone" :size="16" />{{ saving ? 'Salvataggio…' : 'Salva e aggiungi' }}
            </button>
            <button type="button" class="btn btn-secondary" :disabled="!canSave || saving"
                title="Salva e pubblica adesso, ignorando la data impostata: il post esce entro un minuto"
                @click="emit('publish-now')">
                <span v-if="saving" class="btn-spinner" aria-hidden="true"></span>
                <Icon v-else name="send" :size="16" />{{ saving ? 'Salvataggio…' : 'Pubblica subito' }}
            </button>
            <button type="button" class="btn btn-dark" :disabled="!canSave || saving" @click="emit('save')">
                <span v-if="saving" class="btn-spinner" aria-hidden="true"></span>
                <Icon v-else name="bookmark" :size="16" />{{ saving ? 'Salvataggio…' : (mode === 'edit' ? 'Salva modifiche' : 'Salva') }}
            </button>
        </div>

        <ImageLightbox v-if="lightboxIndex !== null" :images="allPreviews" :index="lightboxIndex" @close="lightboxIndex = null" />
    </div>
</template>
