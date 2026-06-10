<script setup>
import { ref } from 'vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    form: { type: Object, required: true },
});

const emit = defineEmits(['set']);

const tab = ref('carica');
const fileInput = ref(null);

const TABS = [
    ['carica', 'Carica'],
    ['genera', 'Genera con AI'],
    ['archivio', 'Archivio'],
];

/* ---------------- Carica ---------------- */
function onFile(e) {
    const file = e.dataTransfer?.files?.[0] || e.target.files?.[0];
    if (!file) return;
    setImage(file, 'upload');
}

function setImage(file, source) {
    if (props.form.imagePreviewUrl) URL.revokeObjectURL(props.form.imagePreviewUrl);
    emit('set', 'image', file);
    emit('set', 'imagePreviewUrl', URL.createObjectURL(file));
    emit('set', 'img_source', source);
}

function removeImage() {
    if (props.form.imagePreviewUrl) URL.revokeObjectURL(props.form.imagePreviewUrl);
    emit('set', 'image', null);
    emit('set', 'imagePreviewUrl', null);
    emit('set', 'img_source', null);
}

/* ---------------- Genera con AI (mock) ---------------- */
const genStep = ref(0); // 0=prompt 1=loading 2=result
const genPrompt = ref('');
const genResultUrl = ref(null);
let genTimer = null;

const PALETTES = [
    ['#b07a4a', '#7a4a1a'],
    ['#6a8aaa', '#3a5a7a'],
    ['#6a9a6a', '#3a6a3a'],
    ['#7a6a9a', '#4a3a6a'],
    ['#8a6a5a', '#5a3a2a'],
];

function handleGenerate() {
    if (!genPrompt.value.trim()) return;
    genStep.value = 1;
    clearTimeout(genTimer);
    genTimer = setTimeout(() => {
        const [c1, c2] = PALETTES[Math.floor(Math.random() * PALETTES.length)];
        genResultUrl.value = gradientDataUrl(c1, c2);
        genStep.value = 2;
    }, 1700);
}

function gradientDataUrl(c1, c2) {
    const canvas = document.createElement('canvas');
    canvas.width = 800;
    canvas.height = 600;
    const ctx = canvas.getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
    grad.addColorStop(0, c1);
    grad.addColorStop(1, c2);
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/png');
}

function dataUrlToFile(dataUrl, filename) {
    const [meta, base64] = dataUrl.split(',');
    const mime = meta.match(/:(.*?);/)[1];
    const bytes = atob(base64);
    const arr = new Uint8Array(bytes.length);
    for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
    return new File([arr], filename, { type: mime });
}

function saveAndUse() {
    if (!genResultUrl.value) return;
    const file = dataUrlToFile(genResultUrl.value, 'generata-ai.png');
    setImage(file, 'generated');
    genStep.value = 0;
    genPrompt.value = '';
    genResultUrl.value = null;
    tab.value = 'archivio';
}

function regenerate() {
    genStep.value = 0;
    genResultUrl.value = null;
}
</script>

<template>
    <div>
        <div class="pf-tabs">
            <button v-for="[id, label] in TABS" :key="id" type="button"
                class="pf-tab" :class="{ 'pf-tab--active': tab === id }"
                @click="tab = id">{{ label }}</button>
        </div>

        <!-- Carica -->
        <div v-if="tab === 'carica'">
            <div v-if="form.imagePreviewUrl && form.img_source === 'upload'" class="pf-preview-frame">
                <img :src="form.imagePreviewUrl" alt="Immagine caricata" />
                <button type="button" class="pf-preview-remove" @click="removeImage">Rimuovi</button>
            </div>
            <div v-else class="pf-dropzone" role="button" tabindex="0" aria-label="Carica immagine"
                @dragover.prevent @drop.prevent="onFile" @click="fileInput?.click()"
                @keydown.enter.prevent="fileInput?.click()" @keydown.space.prevent="fileInput?.click()">
                <Icon name="image" :size="46" />
                <div class="pf-dropzone-title">Trascina qui l'immagine</div>
                <div class="pf-dropzone-sub">oppure <b>sfoglia i file</b></div>
                <div class="pf-dropzone-hint">JPG, PNG, WebP — max 10 MB</div>
                <input ref="fileInput" type="file" accept="image/*" style="display:none" @change="onFile" tabindex="-1" />
            </div>
            <div class="pf-note">
                <Icon name="clock" :size="15" />
                Il supporto <strong>video</strong> (Reels, Stories) è in arrivo.
            </div>
        </div>

        <!-- Genera con AI -->
        <div v-else-if="tab === 'genera'">
            <div class="pf-genstep">
                <template v-for="(label, i) in ['Descrivi', 'Genera', 'Risultato']" :key="i">
                    <div class="pf-genstep-item">
                        <span class="pf-genstep-dot" :class="{ 'pf-genstep-dot--done': i < genStep, 'pf-genstep-dot--active': i === genStep }">
                            {{ i < genStep ? '✓' : i + 1 }}
                        </span>
                        <span class="pf-genstep-label" :class="{ 'pf-genstep-label--active': i === genStep }">{{ label }}</span>
                    </div>
                    <div v-if="i < 2" class="pf-genstep-line" :class="{ 'pf-genstep-line--done': i < genStep }" />
                </template>
            </div>

            <div v-if="genStep === 0">
                <div class="acc-field" style="margin-bottom:0">
                    <label class="acc-row-label" for="post-img-prompt">Prompt immagine</label>
                    <span class="acc-row-help">Descrivi l'immagine che vuoi generare. Più sei specifico, meglio sarà il risultato.</span>
                    <textarea id="post-img-prompt" class="control" rows="4" v-model="genPrompt"
                        placeholder="Es. Una pizza margherita appena sfornata, luce calda e rustica, fotografia professionale" />
                </div>
                <div class="pf-gen-meta">
                    <div class="pf-gen-meta-info">
                        <Icon name="info" :size="14" />
                        Modello: <strong class="pf-gen-meta-strong">DALL·E 3</strong>
                        <span class="pf-gen-meta-soon">· scelta modello in arrivo</span>
                    </div>
                    <button type="button" class="btn btn-dark" :disabled="!genPrompt.trim()" @click="handleGenerate">
                        <Icon name="sparkles" :size="16" />Genera immagine
                    </button>
                </div>
            </div>

            <div v-else-if="genStep === 1" class="pf-gen-loading">
                <div class="pf-spinner"></div>
                <div class="pf-gen-loading-title">Generazione in corso…</div>
                <div class="pf-gen-loading-sub">"{{ genPrompt }}"</div>
            </div>

            <div v-else-if="genStep === 2 && genResultUrl" class="pf-fade-in">
                <img :src="genResultUrl" alt="Immagine generata dall'AI" class="pf-gen-result-img" />
                <div class="pf-gen-result-prompt">"{{ genPrompt }}"</div>
                <div class="pf-gen-actions">
                    <button type="button" class="btn btn-dark" style="flex:1" @click="saveAndUse">
                        <Icon name="bookmark" :size="16" />Salva e usa nel post
                    </button>
                    <button type="button" class="btn btn-secondary" @click="regenerate">Rifai</button>
                </div>
            </div>
        </div>

        <!-- Archivio -->
        <div v-else-if="tab === 'archivio'">
            <div v-if="!(form.img_source === 'generated' && form.imagePreviewUrl)" class="pf-archive-empty">
                <Icon name="image" :size="36" />
                <div class="pf-archive-empty-text">Nessuna immagine generata ancora.</div>
            </div>
            <div v-else class="pf-archive-grid">
                <div class="pf-archive-item pf-archive-item--selected">
                    <img :src="form.imagePreviewUrl" alt="Immagine generata, selezionata per il post" />
                </div>
            </div>
        </div>

        <!-- Selezione attiva -->
        <div v-if="form.imagePreviewUrl" class="pf-active-media pf-fade-in">
            <div class="pf-active-media-thumb">
                <img :src="form.imagePreviewUrl" alt="Anteprima immagine selezionata per il post" />
            </div>
            <div class="pf-active-media-text">
                <div class="pf-active-media-title">Immagine selezionata per il post</div>
                <div class="pf-active-media-sub">
                    {{ { upload: 'Caricata dal dispositivo', generated: 'Generata con AI', archive: "Dall'archivio" }[form.img_source] || '' }}
                </div>
            </div>
            <button type="button" class="pf-active-media-remove" aria-label="Rimuovi immagine" @click="removeImage">×</button>
        </div>
    </div>
</template>
