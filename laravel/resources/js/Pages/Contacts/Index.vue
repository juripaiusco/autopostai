<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import StatusPill from '@/Components/StatusPill.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import FilterTabs from '@/Components/UI/FilterTabs.vue';
import SearchInput from '@/Components/UI/SearchInput.vue';
import SortableTh from '@/Components/UI/SortableTh.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { useTableFilters } from '@/Composables/useTableFilters';

const props = defineProps({
    contacts: { type: Object, required: true },
    showAccount: { type: Boolean, required: true },
    availableTags: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
    counts: { type: Object, required: true },
});

const userName = computed(() => usePage().props.auth?.user?.name ?? 'Mario Rossi');

const { search, reload, setFilter, handleSort, clearSearch } = useTableFilters({
    routeName: 'contacts',
    filters: () => props.filters,
});

const TABS = computed(() => [
    { id: 'tutti', label: 'Tutti', count: props.counts.tutti },
    { id: 'active', label: 'Attivi', count: props.counts.active },
    { id: 'unverified', label: 'Da verificare', count: props.counts.unverified },
    { id: 'bounced', label: 'Bounced', count: props.counts.bounced },
    { id: 'unsubscribed', label: 'Disiscritti', count: props.counts.unsubscribed },
]);

function setTag(value) {
    reload({ tag: value, page: undefined });
}

const deleteTarget = ref(null);
const deleting = ref(false);
const deleteError = ref(null);
const colSpan = computed(() => (props.showAccount ? 6 : 5));
const listKey = computed(() => `${props.filters.filter}-${props.filters.search}-${props.filters.tag}-${props.filters.sort}-${props.filters.dir}-${props.contacts.current_page}`);

function openDelete(c) {
    deleteTarget.value = c;
    deleteError.value = null;
}

function cancelDelete() {
    if (deleting.value) return;
    deleteTarget.value = null;
    deleteError.value = null;
}

function confirmDelete(id) {
    deleting.value = true;
    deleteError.value = null;
    router.delete(route('contacts.destroy', id), {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null; },
        onError: () => { deleteError.value = 'Eliminazione non riuscita. Riprova.'; },
        onFinish: () => { deleting.value = false; },
    });
}
</script>

<template>
    <Head title="Contatti" />

    <AppLayout :current="'contacts'" :user="userName">
        <template #page-header>
            <PageHeader :crumbs="[{ label: 'Contatti', current: true }]" />
        </template>

        <div class="list-toolbar">
            <a :href="route('contacts.create')" class="btn btn-dark">
                <Icon name="plus" :size="17" />Nuovo contatto
            </a>

            <FilterTabs :model-value="filters.filter" :tabs="TABS" @update:model-value="setFilter" />

            <SearchInput v-model="search" placeholder="Cerca email…" @clear="clearSearch" />

            <select v-if="availableTags.length" class="control" style="width: auto" :value="filters.tag" @change="setTag($event.target.value)">
                <option value="">Tutti i tag</option>
                <option v-for="t in availableTags" :key="t" :value="t">{{ t }}</option>
            </select>
        </div>

        <div class="card table-wrap">
            <table class="table table--responsive-cards">
                <colgroup>
                    <col style="width: 28%">
                    <col style="width: 24%">
                    <col style="width: 14%">
                    <col v-if="showAccount" style="width: 16%">
                    <col style="width: 12%">
                    <col style="width: 90px">
                </colgroup>
                <thead>
                    <tr>
                        <SortableTh label="Email" column="email" :sort="filters.sort" :dir="filters.dir" @sort="handleSort" />
                        <th>Tag</th>
                        <SortableTh label="Stato" column="status" :sort="filters.sort" :dir="filters.dir" @sort="handleSort" />
                        <th v-if="showAccount">Account</th>
                        <SortableTh label="Creato" column="created" :sort="filters.sort" :dir="filters.dir" @sort="handleSort" />
                        <th></th>
                    </tr>
                </thead>
                <Transition name="table-fade" mode="out-in">
                    <tbody v-if="contacts.data.length === 0" :key="`${listKey}-empty`">
                        <tr>
                            <td :colspan="colSpan" class="table-empty">
                                <Icon name="contacts" :size="32" class="table-empty__icon" />
                                <template v-if="filters.search">
                                    <div class="table-empty__text">Nessun risultato per «{{ filters.search }}»</div>
                                    <button type="button" class="list-footer__clear" @click="clearSearch">Cancella ricerca</button>
                                </template>
                                <div v-else class="table-empty__text">Nessun contatto trovato</div>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else :key="`${listKey}-rows`">
                        <tr v-for="c in contacts.data" :key="c.id"
                            class="tr-clickable"
                            @click="router.get(route('contacts.edit', c.id))">
                            <td data-label="Email">{{ c.email }}</td>
                            <td data-label="Tag">
                                <span v-if="c.tags.length === 0" class="text-xs text-gray-500">—</span>
                                <span v-for="t in c.tags" :key="t" class="ch-chip" style="width: auto; padding: 0 8px; border-radius: 999px; font-size: 11px; margin-right: 4px">{{ t }}</span>
                            </td>
                            <td data-label="Stato"><StatusPill :status="c.status" /></td>
                            <td v-if="showAccount" data-label="Account">{{ c.owner ?? '—' }}</td>
                            <td data-label="Creato">{{ c.createdAt }}</td>
                            <td data-label="">
                                <div class="row-actions">
                                    <a :href="route('contacts.edit', c.id)" class="icon-btn icon-btn--ghost" title="Modifica" :aria-label="`Modifica ${c.email}`" @click.stop>
                                        <Icon name="pencil" :size="15" />
                                    </a>
                                    <button class="icon-btn icon-btn--ghost icon-btn--danger" title="Elimina" :aria-label="`Elimina ${c.email}`" @click.stop="openDelete(c)">
                                        <Icon name="trash" :size="15" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Transition>
            </table>

            <div v-if="contacts.data.length > 0" class="list-footer">
                <span class="list-footer__info">
                    {{ contacts.total }} {{ contacts.total === 1 ? 'contatto' : 'contatti' }}{{ filters.search ? ' trovati' : '' }}
                </span>

                <Pagination :links="contacts.links" />

                <button v-if="filters.search" class="list-footer__clear" @click="clearSearch">
                    Cancella filtro
                </button>
            </div>
        </div>

        <ConfirmModal
            v-if="deleteTarget"
            title="Elimina contatto"
            confirm-label="Elimina"
            pending-label="Eliminazione…"
            danger
            :loading="deleting"
            :error="deleteError"
            @cancel="cancelDelete"
            @confirm="confirmDelete(deleteTarget.id)"
        >
            Sei sicuro di voler eliminare <b>{{ deleteTarget.email }}</b>?<br />
            <span class="modal-note">
                Il contatto viene rimosso dalla lista invii futuri (eliminazione reversibile).
            </span>
        </ConfirmModal>
    </AppLayout>
</template>
