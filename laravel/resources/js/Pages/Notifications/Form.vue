<script setup>
import { reactive, ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Combobox, ComboboxInput, ComboboxButton, ComboboxOptions, ComboboxOption } from '@headlessui/vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import SectionCard from '@/Components/Layout/SectionCard.vue';
import FieldRow from '@/Components/UI/FieldRow.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    mode: { type: String, default: 'create' },
    canBroadcastAll: { type: Boolean, default: false },
    recipients: { type: Array, default: () => [] },
    notification: { type: Object, default: null },
});

const userName = computed(() => usePage().props.auth?.user?.name ?? '');

function initialRecipientType() {
    if (!props.notification) return props.canBroadcastAll ? 'all' : 'children';
    if (props.notification.user_id) return 'user';
    return props.notification.audience ?? (props.canBroadcastAll ? 'all' : 'children');
}

const form = reactive({
    title: props.notification?.title ?? '',
    body: props.notification?.body ?? '',
    url: props.notification?.url ?? '',
    recipient_type: initialRecipientType(),
    user_id: props.notification?.user_id ?? null,
});

const saving = ref(false);
const userQuery = ref('');
const selectedUser = computed(() => props.recipients.find((u) => u.id === form.user_id) ?? null);
const filteredRecipients = computed(() => {
    const q = userQuery.value.trim().toLowerCase();
    if (!q) return props.recipients;
    return props.recipients.filter((u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q));
});
function recipientLabel(u) {
    return u ? `${u.name} - ${u.email}` : '';
}

const canSave = computed(() => form.title.trim() && form.body.trim() && (form.recipient_type !== 'user' || form.user_id));

function save() {
    saving.value = true;
    const action = props.mode === 'edit'
        ? (cb) => router.put(route('notifications.update', props.notification.id), form, cb)
        : (cb) => router.post(route('notifications.store'), form, cb);

    action({ onFinish: () => { saving.value = false; } });
}
</script>

<template>
    <Head :title="mode === 'edit' ? 'Modifica notifica' : 'Nuova notifica'" />

    <AppLayout current="notifications" :user="userName">
        <template #page-header>
            <PageHeader :crumbs="[{ label: 'Notifiche', href: route('notifications') }, { label: mode === 'edit' ? 'Modifica' : 'Nuova', current: true }]">
                <template #actions>
                    <button type="button" class="btn btn-secondary btn-sm" @click="router.get(route('notifications'))">Annulla</button>
                    <button type="button" class="btn btn-dark btn-sm" :disabled="!canSave || saving" @click="save">
                        <span v-if="saving" class="btn-spinner" aria-hidden="true"></span>
                        {{ saving ? 'Salvataggio…' : 'Salva e invia' }}
                    </button>
                </template>
            </PageHeader>
        </template>

        <div class="acc-content" style="max-width: 40rem">
            <SectionCard title="Contenuto" subtitle="Titolo e testo mostrati nella notifica push del browser.">
                <FieldRow id="pn-title" label="Titolo">
                    <input id="pn-title" class="control" type="text" v-model="form.title" placeholder="Es. Nuovo post pubblicato" />
                </FieldRow>
                <FieldRow id="pn-body" label="Testo">
                    <textarea id="pn-body" class="control" rows="3" v-model="form.body" placeholder="Testo della notifica…" />
                </FieldRow>
                <FieldRow id="pn-url" label="URL (facoltativo)" help="Dove va l'utente cliccando la notifica." style="margin-bottom:0">
                    <input id="pn-url" class="control" type="text" v-model="form.url" placeholder="/dashboard" />
                </FieldRow>
            </SectionCard>

            <SectionCard title="Destinatari" subtitle="A chi arriva questa notifica.">
                <div class="acc-li-pages">
                    <button v-if="canBroadcastAll" type="button" class="acc-li-page" :class="{ 'acc-li-page--active': form.recipient_type === 'all' }"
                        @click="form.recipient_type = 'all'; form.user_id = null">
                        <span class="acc-li-page-dot" aria-hidden="true"></span>
                        <span class="acc-li-page-name">Tutti gli utenti</span>
                        <Icon v-if="form.recipient_type === 'all'" name="check" :size="16" />
                    </button>
                    <button v-else type="button" class="acc-li-page" :class="{ 'acc-li-page--active': form.recipient_type === 'children' }"
                        @click="form.recipient_type = 'children'; form.user_id = null">
                        <span class="acc-li-page-dot" aria-hidden="true"></span>
                        <span class="acc-li-page-name">Tutti i tuoi utenti</span>
                        <Icon v-if="form.recipient_type === 'children'" name="check" :size="16" />
                    </button>
                    <button type="button" class="acc-li-page" :class="{ 'acc-li-page--active': form.recipient_type === 'user' }"
                        @click="form.recipient_type = 'user'">
                        <span class="acc-li-page-dot" aria-hidden="true"></span>
                        <span class="acc-li-page-name">Un utente specifico</span>
                        <Icon v-if="form.recipient_type === 'user'" name="check" :size="16" />
                    </button>
                </div>

                <div v-if="form.recipient_type === 'user'" class="acc-field pf-combobox" style="margin-top: 14px; margin-bottom: 0">
                    <label class="acc-row-label" for="pn-user">Destinatario</label>
                    <Combobox :model-value="selectedUser" @update:model-value="(u) => (form.user_id = u?.id ?? null)">
                        <div class="pf-combobox-wrap">
                            <ComboboxInput id="pn-user" class="control" :display-value="recipientLabel"
                                placeholder="Cerca account…" @change="userQuery = $event.target.value" />
                            <ComboboxButton class="pf-combobox-btn" aria-label="Apri lista account">▾</ComboboxButton>
                            <ComboboxOptions class="pf-combobox-options">
                                <div v-if="filteredRecipients.length === 0" class="pf-combobox-empty">Nessun account trovato</div>
                                <ComboboxOption v-for="u in filteredRecipients" :key="u.id" :value="u" v-slot="{ active, selected }">
                                    <div class="pf-combobox-option" :class="{ 'pf-combobox-option--active': active, 'pf-combobox-option--selected': selected }">
                                        {{ u.name }} - {{ u.email }}
                                    </div>
                                </ComboboxOption>
                            </ComboboxOptions>
                        </div>
                    </Combobox>
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
