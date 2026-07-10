<script setup>
import { ref, computed } from 'vue';
import Icon from '@/Components/Icon.vue';
import ImageLightbox from '@/Components/Posts/ImageLightbox.vue';

const props = defineProps({
    form: { type: Object, required: true },
    targetUserId: { type: [Number, String], default: null },
});

const emit = defineEmits(['set']);

const tab = ref('carica');
const fileInput = ref(null);

const TABS = [
    ['carica', 'Carica'],
    ['genera', 'Genera con AI'],
    ['archivio', 'Archivio'],
];

function selectTab(id) {
    tab.value = id;
    if (id === 'archivio') loadArchive();
}

const totalImages = computed(() => props.form.existingImages.length + props.form.newImages.length);
const allPreviews = computed(() => [
    ...props.form.existingImages.map((i) => i.url),
    ...props.form.newImages.map((i) => i.previewUrl),
]);
const lightboxIndex = ref(null);

function csrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Immagine gia' ospitata lato server (nuova generazione o pescata
 * dall'archivio) -> scaricata come blob e accodata a newImages, cosi'
 * rientra nello stesso flusso di upload di un file scelto a mano.
 */
async function attachHostedImage({ url, filename }) {
    const blob = await (await fetch(url)).blob();
    const file = new File([blob], filename, { type: blob.type || 'image/png' });
    emit('set', 'newImages', [...props.form.newImages, { file, previewUrl: url }]);
    emit('set', 'img_source', 'generated');
}

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

/* ---------------- Genera con AI (mock, persistito in archivio) ---------------- */
const genStep = ref(0); // 0=prompt 1=loading 2=result
const genPrompt = ref('');
const genResultUrl = ref(null);
const genResultFilename = ref(null);
const genFromArchive = ref(false); // true se il risultato mostrato viene dall'archivio (gia' aggiunto al post)
const genError = ref(null);
let genTimer = null;

const PALETTES = [
    ['#b07a4a', '#7a4a1a'],
    ['#6a8aaa', '#3a5a7a'],
    ['#6a9a6a', '#3a6a3a'],
    ['#7a6a9a', '#4a3a6a'],
    ['#8a6a5a', '#5a3a2a'],
];

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

function handleGenerate() {
    if (!genPrompt.value.trim()) return;
    genStep.value = 1;
    genError.value = null;
    clearTimeout(genTimer);
    genTimer = setTimeout(async () => {
        try {
            const [c1, c2] = PALETTES[Math.floor(Math.random() * PALETTES.length)];
            const blob = await (await fetch(gradientDataUrl(c1, c2))).blob();

            const body = new FormData();
            body.append('prompt', genPrompt.value);
            body.append('image', blob, 'generata-ai.png');

            const res = await fetch(route('posts.image-generate', props.targetUserId), {
                method: 'POST',
                headers: { 'X-XSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                body,
            });
            if (!res.ok) throw new Error('generate failed');
            const json = await res.json();

            genResultUrl.value = json.url;
            genResultFilename.value = json.filename;
            genFromArchive.value = false;
            genStep.value = 2;
            archiveLoaded.value = false; // il prossimo tab Archivio rifa' il fetch e la mostra
        } catch (e) {
            genError.value = "Generazione non riuscita, riprova.";
            genStep.value = 0;
        }
    }, 1700);
}

async function saveAndUse() {
    if (!genResultUrl.value || !genResultFilename.value) return;
    await attachHostedImage({ url: genResultUrl.value, filename: genResultFilename.value });
    resetGenState();
    tab.value = 'carica';
}

function resetGenState() {
    genStep.value = 0;
    genPrompt.value = '';
    genResultUrl.value = null;
    genResultFilename.value = null;
    genFromArchive.value = false;
}

function regenerate() {
    genStep.value = 0;
    genResultUrl.value = null;
    genResultFilename.value = null;
    genFromArchive.value = false;
}

/* ---------------- Archivio ---------------- */
const archiveImages = ref([]);
const archiveLoading = ref(false);
const archiveLoaded = ref(false);
const archiveError = ref(null);

async function loadArchive() {
    if (archiveLoaded.value || archiveLoading.value) return;
    archiveLoading.value = true;
    archiveError.value = null;
    try {
        const res = await fetch(route('posts.image-archive', props.targetUserId), { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('fetch failed');
        const json = await res.json();
        archiveImages.value = json.images;
        archiveLoaded.value = true;
    } catch (e) {
        archiveError.value = "Impossibile caricare l'archivio.";
    } finally {
        archiveLoading.value = false;
    }
}

async function pickFromArchive(img) {
    await attachHostedImage({ url: img.url, filename: img.filename });
    genPrompt.value = img.prompt ?? '';
    genResultUrl.value = img.url;
    genResultFilename.value = img.filename;
    genFromArchive.value = true;
    genStep.value = 2;
    tab.value = 'genera';
}
</script>

<template>
    <div>
        <div class="pf-tabs">
            <button v-for="[id, label] in TABS" :key="id" type="button"
                class="pf-tab" :class="{ 'pf-tab--active': tab === id }"
                @click="selectTab(id)">{{ label }}</button>
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
                <div v-if="genError" class="pf-gen-error">{{ genError }}</div>
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
                <div v-if="genPrompt" class="pf-gen-result-prompt">"{{ genPrompt }}"</div>
                <div v-else class="pf-gen-result-prompt pf-gen-result-prompt--empty">Prompt non disponibile per questa immagine.</div>
                <div class="pf-gen-actions">
                    <div v-if="genFromArchive" class="pf-gen-added-badge">
                        <Icon name="check" :size="15" />Già aggiunta al post
                    </div>
                    <button v-else type="button" class="btn btn-dark" style="flex:1" @click="saveAndUse">
                        <Icon name="bookmark" :size="16" />Salva e usa nel post
                    </button>
                    <button type="button" class="btn btn-secondary" @click="regenerate">Rifai</button>
                </div>
            </div>
        </div>

        <!-- Archivio -->
        <div v-else-if="tab === 'archivio'">
            <div v-if="archiveLoading" class="pf-gen-loading">
                <div class="pf-spinner"></div>
                <div class="pf-gen-loading-title">Carico l'archivio…</div>
            </div>
            <div v-else-if="archiveError" class="pf-archive-empty">
                <Icon name="image" :size="36" />
                <div class="pf-archive-empty-text">{{ archiveError }}</div>
            </div>
            <div v-else-if="archiveImages.length === 0" class="pf-archive-empty">
                <Icon name="image" :size="36" />
                <div class="pf-archive-empty-text">Nessuna immagine generata ancora.</div>
            </div>
            <div v-else class="pf-archive-grid">
                <div v-for="img in archiveImages" :key="img.filename" class="pf-archive-item" role="button" tabindex="0"
                    :aria-label="img.prompt ? `Usa immagine: ${img.prompt}` : 'Usa immagine'"
                    @click="pickFromArchive(img)" @keydown.enter="pickFromArchive(img)">
                    <img :src="img.url" :alt="img.prompt ?? 'Immagine generata'" />
                </div>
            </div>
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
