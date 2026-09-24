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

function save() {
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
                    <button type="button" class="btn btn-secondary btn-sm" @click="router.get(route('contacts'))">Annulla</button>
                    <button type="button" class="btn btn-dark btn-sm" :disabled="!canSave || saving" @click="save">
                        <span v-if="saving" class="btn-spinner" aria-hidden="true"></span>
                        {{ saving ? 'Salvataggio…' : 'Salva' }}
                    </button>
                </template>
            </PageHeader>
        </template>

        <div class="acc-content" style="max-width: 40rem">
            <SectionCard v-if="mode === 'create' && accounts.length > 0" title="Account" subtitle="A quale account appartiene questo contatto.">
                <div class="acc-field" style="margin-bottom: 0">
                    <label class="acc-row-label" for="ct-account">Account</label>
                    <ComboboxSelect id="ct-account" v-model="form.user_id" :options="accounts"
                        placeholder="Cerca account…" empty-text="Nessun account trovato" />
                </div>
            </SectionCard>

            <SectionCard v-else-if="selectedAccount" title="Account" :subtitle="accountLabel(selectedAccount)" />

            <SectionCard title="Contatto" subtitle="Email e stato di iscrizione.">
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
        </div>
    </AppLayout>
</template>
