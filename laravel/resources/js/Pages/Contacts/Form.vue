<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Combobox, ComboboxInput, ComboboxButton, ComboboxOptions, ComboboxOption } from '@headlessui/vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import SectionCard from '@/Components/Layout/SectionCard.vue';
import FieldRow from '@/Components/UI/FieldRow.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    mode: { type: String, default: 'create' },
    accounts: { type: Array, default: () => [] },
    defaultUserId: { type: Number, default: null },
    contact: { type: Object, default: null },
});

const userName = computed(() => usePage().props.auth?.user?.name ?? '');

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
const accountQuery = ref('');
const selectedAccount = computed(() => props.accounts.find((a) => a.id === form.user_id) ?? null);
const filteredAccounts = computed(() => {
    const q = accountQuery.value.trim().toLowerCase();
    if (!q) return props.accounts;
    return props.accounts.filter((a) => a.name.toLowerCase().includes(q) || a.email.toLowerCase().includes(q));
});
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
                <div class="acc-field pf-combobox" style="margin-bottom: 0">
                    <label class="acc-row-label" for="ct-account">Account</label>
                    <Combobox :model-value="selectedAccount" @update:model-value="(a) => (form.user_id = a?.id ?? null)">
                        <div class="pf-combobox-wrap">
                            <ComboboxInput id="ct-account" class="control" :display-value="accountLabel"
                                placeholder="Cerca account…" @change="accountQuery = $event.target.value" />
                            <ComboboxButton class="pf-combobox-btn" aria-label="Apri lista account">▾</ComboboxButton>
                            <ComboboxOptions class="pf-combobox-options">
                                <div v-if="filteredAccounts.length === 0" class="pf-combobox-empty">Nessun account trovato</div>
                                <ComboboxOption v-for="a in filteredAccounts" :key="a.id" :value="a" v-slot="{ active, selected }">
                                    <div class="pf-combobox-option" :class="{ 'pf-combobox-option--active': active, 'pf-combobox-option--selected': selected }">
                                        {{ a.name }} - {{ a.email }}
                                    </div>
                                </ComboboxOption>
                            </ComboboxOptions>
                        </div>
                    </Combobox>
                </div>
            </SectionCard>

            <SectionCard v-else-if="selectedAccount" title="Account" :subtitle="accountLabel(selectedAccount)" />

            <SectionCard title="Contatto" subtitle="Email e stato di iscrizione.">
                <FieldRow id="ct-email" label="Email">
                    <input id="ct-email" class="control" type="email" v-model="form.email" placeholder="nome@dominio.it" />
                </FieldRow>
                <FieldRow id="ct-status" label="Stato" style="margin-bottom: 0">
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

<style scoped>
.pf-combobox-wrap { position: relative; }
.pf-combobox-btn { position: absolute; top: 0; right: 0; height: 100%; width: 32px; display: flex; align-items: center; justify-content: center; color: var(--g500); background: transparent; border: none; cursor: pointer; }
.pf-combobox-options { position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; right: 0; max-height: 240px; overflow-y: auto; background: #fff; border: 1px solid var(--g300); border-radius: var(--radius); box-shadow: 0 8px 24px rgba(0, 0, 0, .12); padding: 4px; }
.pf-combobox-empty { padding: 8px 10px; font-size: 13px; color: var(--g500); }
.pf-combobox-option { padding: 8px 10px; font-size: 13.5px; color: var(--g700); border-radius: 6px; cursor: pointer; }
.pf-combobox-option--active { background: var(--sky); color: #fff; }
.pf-combobox-option--selected { font-weight: 600; }
</style>
