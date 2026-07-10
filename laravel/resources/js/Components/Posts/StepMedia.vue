<script setup>
import { ref, computed } from 'vue';
import Icon from '@/Components/Icon.vue';
import ImageLightbox from '@/Components/Posts/ImageLightbox.vue';

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

const totalImages = computed(() => props.form.existingImages.length + props.form.newImages.length);
const allPreviews = computed(() => [
    ...props.form.existingImages.map((i) => i.url),
    ...props.form.newImages.map((i) => i.previewUrl),
]);
const lightboxIndex = ref(null);

/* ---------------- Carica ---------------- */
function addFiles(fileList) {
    const files = Array.from(fileList || []).filter((f) => f.type.startsWith('image/'));
    if (!files.length) return;
    const additions = files.map((file) => ({ file, previewUrl: URL.createObjectURL(file) }));
    emit('set', 'newImages', [...props.form.newImages, ...additions]);
    emit('set', 'img_source', 'upload');
}

function onDrop(e) {
    addFiles(e.dataTransfer?.files);
}

function onFileChange(e) {
    addFiles(e.target.files);
    e.target.value = '';
}

function removeExisting(index) {
    const arr = [...props.form.existingImages];
    arr.splice(index, 1);
    emit('set', 'existingImages', arr);
}

function removeNew(index) {
    const arr = [...props.form.newImages];
    const [removed] = arr.splice(index, 1);
    if (removed) URL.revokeObjectURL(removed.previewUrl);
    emit('set', 'newImages', arr);
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
    const file = dataUrlToFile(genResultUrl.value, `generata-ai-${Date.now()}.png`);
    emit('set', 'newImages', [...props.form.newImages, { file, previewUrl: genResultUrl.value }]);
    emit('set', 'img_source', 'generated');
    genStep.value = 0;
    genPrompt.value = '';
    genResultUrl.value = null;
    tab.value = 'carica';
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
            <div class="pf-dropzone" role="button" tabindex="0" aria-label="Carica immagini"
                @dragover.prevent @drop.prevent="onDrop" @click="fileInput?.click()"
                @keydown.enter.prevent="fileInput?.click()" @keydown.space.prevent="fileInput?.click()">
                <Icon name="image" :size="46" />
                <div class="pf-dropzone-title">Trascina qui le immagini</div>
                <div class="pf-dropzone-sub">oppure <b>sfoglia i file</b></div>
                <div class="pf-dropzone-hint">JPG, PNG, WebP — max 10 MB ciascuna, puoi selezionarne più di una</div>
                <input ref="fileInput" type="file" accept="image/*" multiple style="display:none" @change="onFileChange" tabindex="-1" />
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
        <div v-else-if="tab === 'archivio'" class="pf-archive-empty">
            <Icon name="image" :size="36" />
            <div class="pf-archive-empty-text">Archivio immagini generate in arrivo.</div>
        </div>

        <!-- Immagini selezionate per il post -->
        <div v-if="totalImages > 0" class="pf-media-grid">
            <div v-for="(img, i) in form.existingImages" :key="'existing-' + img.filename" class="pf-media-grid-item">
                <img :src="img.url" :alt="'Immagine ' + (i + 1)" @click="lightboxIndex = i" />
                <button type="button" class="pf-media-grid-remove" aria-label="Rimuovi immagine" @click="removeExisting(i)">×</button>
            </div>
            <div v-for="(img, i) in form.newImages" :key="'new-' + i" class="pf-media-grid-item">
                <img :src="img.previewUrl" :alt="'Nuova immagine ' + (i + 1)" @click="lightboxIndex = form.existingImages.length + i" />
                <button type="button" class="pf-media-grid-remove" aria-label="Rimuovi immagine" @click="removeNew(i)">×</button>
            </div>
        </div>

        <ImageLightbox v-if="lightboxIndex !== null" :images="allPreviews" :index="lightboxIndex" @close="lightboxIndex = null" />
    </div>
</template>
