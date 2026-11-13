<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import SectionCard from '@/Components/Layout/SectionCard.vue';
import ComboboxSelect from '@/Components/UI/ComboboxSelect.vue';
import FieldRow from '@/Components/UI/FieldRow.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    mode: { type: String, default: 'create' },
    accounts: { type: Array, default: () => [] },
    defaultUserId: { type: Number, default: null },
    contact: { type: Object, default: null },
});

const userName = computed(() => usePage().props.auth?.user?.name ?? '');
// Errori di validazione del server (email duplicata, indirizzo soppresso…):
// prima non venivano mostrati e il salvataggio sembrava ignorato.
const errors = computed(() => usePage().props.errors ?? {});

const STATUSES = [
    { id: 'active', label: 'Attivo' },
    { id: 'unverified', label: 'Da verificare' },
    { id: 'bounced', label: 'Bounced' },
    { id: 'unsubscribed', label: 'Disiscritto' },
];

const form = reactive({
    user_id: props.defaultUserId ?? props.accounts[0]?.id ?? null,
    email: props.contact?.email ?? '',
    first_name: props.contact?.first_name ?? '',
    last_name: props.contact?.last_name ?? '',
    status: props.contact?.status ?? 'active',
    tags: [...(props.contact?.tags ?? [])],
});

const saving = ref(false);
const selectedAccount = computed(() => props.accounts.find((a) => a.id === form.user_id) ?? null);
function accountLabel(a) {
    return a ? `${a.name} - ${a.email}` : '';
}

// Tag noti per l'account selezionato + quelli aggiunti al volo in questa
// sessione di editing (visibili subito, creati in DB solo al salvataggio).
const localTags = ref([...(selectedAccount.value?.tags ?? [])]);
watch(() => form.user_id, () => {
    if (props.mode !== 'create') return;
    localTags.value = [...(selectedAccount.value?.tags ?? [])];
    form.tags = [];
});

const newTagName = ref('');

function toggleTag(name) {
    const i = form.tags.indexOf(name);
    if (i === -1) form.tags.push(name);
    else form.tags.splice(i, 1);
}

function addTag() {
    const name = newTagName.value.trim();
    if (!name) return;
    if (!localTags.value.includes(name)) localTags.value.push(name);
    if (!form.tags.includes(name)) form.tags.push(name);
    newTagName.value = '';
}

const canSave = computed(() => !!form.user_id && /\S+@\S+\.\S+/.test(form.email));

// ── Import da CSV (solo in creazione) ────────────────────────────────
// Il file si legge qui solo per anteprima e mappatura colonne; l'import
// vero lo fa il server (ImportContacts) sul file caricato.
const tab = ref('single');

const DELIMITERS = [
    { id: ';', label: 'Punto e virgola ( ; )' },
    { id: ',', label: 'Virgola ( , )' },
    { id: '\t', label: 'Tabulazione' },
];
const IMPORT_STATUSES = STATUSES.filter((s) => ['active', 'unverified'].includes(s.id));
const MAP_FIELDS = [
    { id: 'email', label: 'Email', required: true, guess: /^(e-?mail|mail|indirizzo)/i },
    { id: 'first_name', label: 'Nome', guess: /^(nome|name|first)/i },
    { id: 'last_name', label: 'Cognome', guess: /^(cognome|surname|last)/i },
    { id: 'tags', label: 'Tag', help: 'Più tag nella stessa cella, separati da virgola.', guess: /^tag/i },
];

const csv = reactive({
    file: null,
    text: '',
    delimiter: ';',
    hasHeader: true,
    status: 'active',
    map: { email: null, first_name: null, last_name: null, tags: null },
});
const csvInput = ref(null);
const csvError = ref(null);

// Parser minimale: separatore scelto, campi tra virgolette ("" = virgoletta).
function parseCsv(text, delimiter) {
    const rows = [];
    let row = [], cell = '', quoted = false;
    for (let i = 0; i < text.length; i++) {
        const ch = text[i];
        if (quoted) {
            if (ch === '"' && text[i + 1] === '"') { cell += '"'; i++; }
            else if (ch === '"') quoted = false;
            else cell += ch;
        } else if (ch === '"' && cell === '') quoted = true;
        else if (ch === delimiter) { row.push(cell); cell = ''; }
        else if (ch === '\n' || ch === '\r') {
            if (ch === '\r' && text[i + 1] === '\n') i++;
            row.push(cell); rows.push(row); row = []; cell = '';
        } else cell += ch;
    }
    if (cell !== '' || row.length) { row.push(cell); rows.push(row); }
    return rows.filter((r) => r.some((c) => c.trim() !== ''));
}

const csvRows = computed(() => (csv.text ? parseCsv(csv.text, csv.delimiter) : []));
const columnCount = computed(() => Math.max(0, ...csvRows.value.slice(0, 50).map((r) => r.length)));
const columns = computed(() => Array.from({ length: columnCount.value }, (_, i) => {
    const head = csv.hasHeader ? (csvRows.value[0]?.[i] ?? '').trim() : '';
    return { id: i, name: head ? `${head} (col. ${i + 1})` : `Colonna ${i + 1}` };
}));
const dataRows = computed(() => (csv.hasHeader ? csvRows.value.slice(1) : csvRows.value));
const previewRows = computed(() => dataRows.value.slice(0, 5).map((r) => {
    const at = (key) => (csv.map[key] === null ? '' : (r[csv.map[key]] ?? '').trim());
    return { email: at('email'), name: `${at('first_name')} ${at('last_name')}`.trim(), tags: at('tags').split(',').map((t) => t.trim()).filter(Boolean) };
}));

function guessMapping() {
    const used = new Set();
    for (const f of MAP_FIELDS) {
        const col = csv.hasHeader
            ? columns.value.find((c) => !used.has(c.id) && f.guess.test((csvRows.value[0]?.[c.id] ?? '').trim()))
            : null;
        csv.map[f.id] = col ? col.id : null;
        if (col) used.add(col.id);
    }
    // Senza intestazione riconoscibile: la prima colonna che contiene "@".
    if (csv.map.email === null) {
        const first = dataRows.value[0] ?? [];
        const i = first.findIndex((c) => /\S+@\S+\.\S+/.test(c));
        if (i !== -1) csv.map.email = i;
    }
}

function detectDelimiter(text) {
    const line = text.split(/\r?\n/)[0] ?? '';
    const best = DELIMITERS
        .map((d) => ({ id: d.id, n: line.split(d.id).length - 1 }))
        .sort((a, b) => b.n - a.n)[0];
    return best.n > 0 ? best.id : ';';
}

function readAs(file, encoding) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(reader.error);
        reader.readAsText(file, encoding);
    });
}

async function loadCsv(file) {
    csvError.value = null;
    if (!file) return;
    if (!/\.(csv|txt)$/i.test(file.name)) {
        csvError.value = 'Scegli un file .csv (o .txt).';
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        csvError.value = 'Il file supera 5 MB.';
        return;
    }
    let text = await readAs(file, 'utf-8');
    // Caratteri non validi in UTF-8 → probabilmente salvato da Excel in Windows-1252.
    if (text.includes('\uFFFD')) text = await readAs(file, 'windows-1252');
    text = text.replace(/^\uFEFF/, '');

    csv.file = file;
    csv.text = text;
    csv.delimiter = detectDelimiter(text);
    guessMapping();
}

function onCsvChange(e) { loadCsv(e.target.files?.[0]); e.target.value = ''; }
function onCsvDrop(e) { loadCsv(e.dataTransfer?.files?.[0]); }
function resetCsv() { csv.file = null; csv.text = ''; csvError.value = null; }

watch(() => [csv.delimiter, csv.hasHeader], () => { if (csv.text) guessMapping(); });

const canImport = computed(() => !!form.user_id && !!csv.file && csv.map.email !== null && dataRows.value.length > 0);

function importCsv() {
    saving.value = true;
    router.post(route('contacts.import'), {
        user_id: form.user_id,
        file: csv.file,
        // Il tab da solo verrebbe tolto dal TrimStrings di Laravel.
        delimiter: csv.delimiter === '\t' ? 'tab' : csv.delimiter,
        has_header: csv.hasHeader ? 1 : 0,
        status: csv.status,
        map: { ...csv.map },
    }, {
        forceFormData: true,
        onFinish: () => { saving.value = false; },
    });
}

const isCsv = computed(() => props.mode === 'create' && tab.value === 'csv');

function save() {
    if (isCsv.value) return importCsv();
    saving.value = true;
    const action = props.mode === 'edit'
        ? (cb) => router.put(route('contacts.update', props.contact.id), form, cb)
        : (cb) => router.post(route('contacts.store'), form, cb);

    action({ onFinish: () => { saving.value = false; } });
}
</script>

<template>
    <Head :title="mode === 'edit' ? 'Modifica contatto' : 'Nuovo contatto'" />

    <AppLayout current="contacts" :user="userName">
        <template #page-header>
            <PageHeader :crumbs="[{ label: 'Contatti', href: route('contacts') }, { label: mode === 'edit' ? 'Modifica' : 'Nuovo', current: true }]">
                <template #actions>
                    <div class="acc-head-meta">
                        <button type="button" class="btn btn-secondary btn-sm" @click="router.get(route('contacts'))">Annulla</button>
                        <button type="button" class="btn btn-dark btn-sm" :disabled="(isCsv ? !canImport : !canSave) || saving" @click="save">
                            <span v-if="saving" class="btn-spinner" aria-hidden="true"></span>
                            <template v-if="isCsv">{{ saving ? 'Importazione…' : `Importa ${dataRows.length || ''}`.trim() }}</template>
                            <template v-else>{{ saving ? 'Salvataggio…' : 'Salva' }}</template>
                        </button>
                    </div>
                </template>
            </PageHeader>
        </template>

        <div class="acc-content" :style="{ maxWidth: isCsv ? '52rem' : '40rem' }">
            <SectionCard v-if="mode === 'create' && accounts.length > 0" title="Account" subtitle="A quale account appartiene questo contatto.">
                <div class="acc-field" style="margin-bottom: 0">
                    <label class="acc-row-label" for="ct-account">Account</label>
                    <ComboboxSelect id="ct-account" v-model="form.user_id" :options="accounts"
                        placeholder="Cerca account…" empty-text="Nessun account trovato" />
                </div>
            </SectionCard>

            <SectionCard v-else-if="selectedAccount" title="Account" :subtitle="accountLabel(selectedAccount)" />

            <div v-if="mode === 'create'" class="acc-mtabs" role="tablist">
                <button type="button" class="acc-mtab" role="tab" id="ct-tab-single" :aria-selected="tab === 'single'" aria-controls="ct-panel-single"
                    :class="{ active: tab === 'single' }" @click="tab = 'single'">
                    <Icon name="pencil" :size="16" />Singolo
                </button>
                <button type="button" class="acc-mtab" role="tab" id="ct-tab-csv" :aria-selected="tab === 'csv'" aria-controls="ct-panel-csv"
                    :class="{ active: tab === 'csv' }" @click="tab = 'csv'">
                    <Icon name="users" :size="16" />Da file CSV
                </button>
            </div>

            <!-- ──────────────── Tab: Da file CSV ──────────────── -->
            <div v-if="isCsv" id="ct-panel-csv" role="tabpanel" aria-labelledby="ct-tab-csv">
                <SectionCard title="File" subtitle="CSV esportato da Excel, Google Sheets o da un altro gestionale. Max 5 MB.">
                    <div v-if="!csv.file" class="pf-dropzone" style="height: 170px" role="button" tabindex="0" aria-label="Carica file CSV"
                        @dragover.prevent @drop.prevent="onCsvDrop" @click="csvInput?.click()"
                        @keydown.enter.prevent="csvInput?.click()" @keydown.space.prevent="csvInput?.click()">
                        <Icon name="contacts" :size="46" />
                        <div class="pf-dropzone-title">Trascina qui il file CSV</div>
                        <div class="pf-dropzone-sub">oppure <b>sfoglia i file</b></div>
                        <div class="pf-dropzone-hint">Una riga per contatto, con almeno una colonna email</div>
                    </div>
                    <div v-else style="display: flex; align-items: center; justify-content: space-between; gap: 12px">
                        <div class="acc-hint-inline" style="margin: 0">
                            <Icon name="check" :size="14" /><b>{{ csv.file.name }}</b>&nbsp;— {{ dataRows.length }} {{ dataRows.length === 1 ? 'riga' : 'righe' }}
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" @click="resetCsv">Cambia file</button>
                    </div>
                    <input ref="csvInput" type="file" accept=".csv,.txt,text/csv" style="display: none" tabindex="-1" @change="onCsvChange" />
                    <span v-if="csvError || errors.file" class="acc-row-error" role="alert">{{ csvError || errors.file }}</span>

                    <div v-if="csv.file" class="acc-field-row" style="margin: 18px 0 0">
                        <FieldRow id="ct-csv-delimiter" label="Separatore" :error="errors.delimiter">
                            <select id="ct-csv-delimiter" class="control" v-model="csv.delimiter">
                                <option v-for="d in DELIMITERS" :key="d.id" :value="d.id">{{ d.label }}</option>
                            </select>
                        </FieldRow>
                        <FieldRow id="ct-csv-header" label="Prima riga">
                            <label class="acc-hint-inline" style="margin: 10px 0 0; cursor: pointer">
                                <input id="ct-csv-header" type="checkbox" v-model="csv.hasHeader" />&nbsp;È l'intestazione (nomi delle colonne)
                            </label>
                        </FieldRow>
                    </div>
                </SectionCard>

                <template v-if="csv.file">
                    <SectionCard title="Corrispondenza colonne" subtitle="Quale colonna del file va in quale campo. Le altre colonne vengono ignorate.">
                        <div class="acc-field-row" style="margin-bottom: 0">
                            <FieldRow v-for="f in MAP_FIELDS" :key="f.id" :id="`ct-map-${f.id}`"
                                :label="f.required ? `${f.label} *` : f.label" :help="f.help" :error="errors[`map.${f.id}`]">
                                <select :id="`ct-map-${f.id}`" class="control" v-model="csv.map[f.id]">
                                    <option :value="null">{{ f.required ? '— scegli la colonna —' : '— non importare —' }}</option>
                                    <option v-for="c in columns" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </FieldRow>
                        </div>
                    </SectionCard>

                    <SectionCard title="Anteprima" :subtitle="`Prime ${previewRows.length} righe di ${dataRows.length}, come verranno importate.`">
                        <table class="table table--responsive-cards">
                            <thead>
                                <tr><th>Email</th><th>Nome</th><th>Tag</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="(r, i) in previewRows" :key="i">
                                    <td data-label="Email">{{ r.email || '—' }}</td>
                                    <td data-label="Nome">{{ r.name || '—' }}</td>
                                    <td data-label="Tag">
                                        <span v-if="r.tags.length === 0" class="text-xs muted">—</span>
                                        <span v-for="t in r.tags" :key="t" class="ch-chip" style="width: auto; padding: 0 8px; border-radius: 999px; font-size: 11px; margin-right: 4px">{{ t }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </SectionCard>

                    <SectionCard title="Opzioni" subtitle="Le email già presenti nell'account non vengono duplicate: ricevono solo i tag del file. Disiscritti e bounce vengono saltati.">
                        <FieldRow id="ct-csv-status" label="Stato dei nuovi contatti" style="margin-bottom: 0" :error="errors.status"
                            help="Usa “Attivo” solo se queste persone hanno dato il consenso a ricevere la newsletter.">
                            <select id="ct-csv-status" class="control" v-model="csv.status">
                                <option v-for="s in IMPORT_STATUSES" :key="s.id" :value="s.id">{{ s.label }}</option>
                            </select>
                        </FieldRow>
                    </SectionCard>
                </template>
            </div>

            <!-- ──────────────── Tab: Singolo ──────────────── -->
            <template v-else>
            <SectionCard title="Contatto" subtitle="Nome, email e stato di iscrizione.">
                <div class="acc-field-row">
                    <FieldRow id="ct-first-name" label="Nome" :error="errors.first_name">
                        <input id="ct-first-name" class="control" type="text" v-model="form.first_name" placeholder="Mario" autocomplete="off" />
                    </FieldRow>
                    <FieldRow id="ct-last-name" label="Cognome" :error="errors.last_name">
                        <input id="ct-last-name" class="control" type="text" v-model="form.last_name" placeholder="Rossi" autocomplete="off" />
                    </FieldRow>
                </div>
                <FieldRow id="ct-email" label="Email" :error="errors.email">
                    <input id="ct-email" class="control" type="email" v-model="form.email" placeholder="nome@dominio.it" />
                </FieldRow>
                <FieldRow id="ct-status" label="Stato" style="margin-bottom: 0" :error="errors.status">
                    <select id="ct-status" class="control" v-model="form.status">
                        <option v-for="s in STATUSES" :key="s.id" :value="s.id">{{ s.label }}</option>
                    </select>
                </FieldRow>
            </SectionCard>

            <SectionCard title="Tag" subtitle="Categorie per organizzare e filtrare i contatti.">
                <div v-if="localTags.length" class="acc-li-pages">
                    <button v-for="t in localTags" :key="t" type="button"
                        class="acc-li-page" :class="{ 'acc-li-page--active': form.tags.includes(t) }"
                        @click="toggleTag(t)">
                        <span class="acc-li-page-dot" aria-hidden="true"></span>
                        <span class="acc-li-page-name">{{ t }}</span>
                        <Icon v-if="form.tags.includes(t)" name="check" :size="16" />
                    </button>
                </div>
                <div v-else class="acc-hint-inline" style="margin-bottom: 10px">
                    <Icon name="info" :size="14" />Nessun tag ancora per questo account.
                </div>

                <div style="display: flex; gap: 8px; margin-top: 12px">
                    <input class="control" type="text" v-model="newTagName" placeholder="Nuovo tag (es. clienti)" @keydown.enter.prevent="addTag" />
                    <button type="button" class="btn btn-secondary btn-sm" @click="addTag">Aggiungi</button>
                </div>
            </SectionCard>
            </template>
        </div>
    </AppLayout>
</template>
