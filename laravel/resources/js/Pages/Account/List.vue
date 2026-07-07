<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import RoleBadge from '@/Components/RoleBadge.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import TokenBar from '@/Components/TokenBar.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import FilterTabs from '@/Components/UI/FilterTabs.vue';
import SearchInput from '@/Components/UI/SearchInput.vue';
import SortableTh from '@/Components/UI/SortableTh.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { useTableFilters } from '@/Composables/useTableFilters';
import { CH_CONFIG } from '@/data/dashboardMock';

const props = defineProps({
    users: { type: Object, required: true },
    isAdmin: { type: Boolean, required: true },
    filters: { type: Object, required: true },
    counts: { type: Object, required: true },
});

const userName = computed(() => usePage().props.auth?.user?.name ?? 'Mario Rossi');

const { search, setFilter, handleSort, clearSearch } = useTableFilters({
    routeName: 'account',
    filters: () => props.filters,
});

const TABS = computed(() => [
    { id: 'tutti', label: 'Tutti', count: props.counts.tutti },
    { id: 'utenti', label: 'Utenti', count: props.counts.utenti },
    ...(props.isAdmin ? [{ id: 'manager', label: 'Manager', count: props.counts.manager }] : []),
]);

const deleteTarget = ref(null);
const deleting = ref(false);
const deleteError = ref(null);
const colSpan = computed(() => (props.isAdmin ? 8 : 7));
const listKey = computed(() => `${props.filters.filter}-${props.filters.search}-${props.filters.sort}-${props.filters.dir}-${props.users.current_page}`);

function openDelete(u) {
    deleteTarget.value = u;
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
    router.delete(route('account.destroy', id), {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null; },
        onError: () => { deleteError.value = 'Eliminazione non riuscita. Riprova.'; },
        onFinish: () => { deleting.value = false; },
    });
}
</script>

<template>
    <Head title="Account" />

    <AppLayout :current="'account'" :user="userName">
        <template #page-header>
            <PageHeader :crumbs="[{ label: 'Account', href: route('account') }, { label: 'Lista', current: true }]">
                <template #actions>
                    <span v-if="!isAdmin" class="page-header__note">
                        Stai vedendo i tuoi {{ counts.tutti }} sub-utenti
                    </span>
                </template>
            </PageHeader>
        </template>

        <div class="list-toolbar">
            <a :href="route('account.create')" class="btn btn-dark">
                <Icon name="plus" :size="17" />Nuovo account
            </a>

            <FilterTabs :model-value="filters.filter" :tabs="TABS" @update:model-value="setFilter" />

            <SearchInput v-model="search" placeholder="Cerca account…" @clear="clearSearch" />
        </div>

        <div class="card table-wrap">
            <table class="table table--responsive-cards">
                <colgroup>
                    <col style="width: 24%">
                    <col v-if="isAdmin" style="width: 10%">
                    <col style="width: 10%">
                    <col style="width: 10%">
                    <col style="width: 10%">
                    <col style="width: 14%">
                    <col style="width: 14%">
                    <col style="width: 110px">
                </colgroup>
                <thead>
                    <tr>
                        <SortableTh label="Account" column="name" :sort="filters.sort" :dir="filters.dir" @sort="handleSort" />
                        <th v-if="isAdmin">Ruolo</th>
                        <th>Canali</th>
                        <SortableTh label="Post" column="post" align="center" :sort="filters.sort" :dir="filters.dir" @sort="handleSort" />
                        <SortableTh label="Reply" column="reply" align="center" :sort="filters.sort" :dir="filters.dir" @sort="handleSort" />
                        <SortableTh label="Immagini" column="immagini" align="center" :sort="filters.sort" :dir="filters.dir" @sort="handleSort" />
                        <SortableTh label="Token" column="tokenUsed" align="right" :sort="filters.sort" :dir="filters.dir" @sort="handleSort" />
                        <th></th>
                    </tr>
                </thead>
                <Transition name="table-fade" mode="out-in">
                    <tbody v-if="users.data.length === 0" :key="`${listKey}-empty`">
                        <tr>
                            <td :colspan="colSpan" class="table-empty">
                                <Icon name="users" :size="32" class="table-empty__icon" />
                                <template v-if="filters.search">
                                    <div class="table-empty__text">Nessun risultato per «{{ filters.search }}»</div>
                                    <button type="button" class="list-footer__clear" @click="clearSearch">Cancella ricerca</button>
                                </template>
                                <div v-else class="table-empty__text">Nessun utente trovato</div>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else :key="`${listKey}-rows`">
                        <tr v-for="(u, i) in users.data" :key="u.id"
                            class="tr-clickable"
                            @click="router.get(route('account.edit', u.id))">
                            <td class="user-td">
                                <div class="user-cell">
                                    <UserAvatar :name="u.name" />
                                    <div class="user-cell__meta">
                                        <div class="user-cell__name">{{ u.name }}</div>
                                        <div class="user-cell__email">{{ u.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td v-if="isAdmin" data-label="Ruolo"><RoleBadge :role="u.role" /></td>
                            <td data-label="Canali">
                                <div class="ch-list">
                                    <span v-for="c in u.channels" :key="c" class="ch-chip" :title="CH_CONFIG[c]?.label ?? c">
                                        <ChannelIcon :id="c" :size="13" />
                                    </span>
                                </div>
                            </td>
                            <td class="num" data-label="Post">{{ u.post }}</td>
                            <td class="num" data-label="Reply">{{ u.reply }}</td>
                            <td class="token-td" data-label="Immagini">
                                <TokenBar v-if="u.imageTotal > 0" :used="u.immagini" :total="u.imageTotal" :index="i" />
                                <span v-else class="num">{{ u.immagini }}</span>
                            </td>
                            <td class="token-td" data-label="Token">
                                <TokenBar :used="u.tokenUsed" :total="u.tokenTotal" :index="i" />
                            </td>
                            <td data-label="">
                                <div class="row-actions">
                                    <a :href="route('account.edit', u.id)" class="icon-btn icon-btn--ghost" title="Modifica" :aria-label="`Modifica ${u.name}`" @click.stop>
                                        <Icon name="pencil" :size="15" />
                                    </a>
                                    <button class="icon-btn icon-btn--ghost icon-btn--danger" title="Elimina" :aria-label="`Elimina ${u.name}`" @click.stop="openDelete(u)">
                                        <Icon name="trash" :size="15" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Transition>
            </table>

            <div v-if="users.data.length > 0" class="list-footer">
                <span class="list-footer__info">
                    {{ users.total }} {{ users.total === 1 ? 'utente' : 'utenti' }}{{ filters.search ? ' trovati' : '' }}
                </span>

                <Pagination :links="users.links" />

                <button v-if="filters.search" class="list-footer__clear" @click="clearSearch">
                    Cancella filtro
                </button>
            </div>
        </div>

        <ConfirmModal
            v-if="deleteTarget"
            title="Elimina utente"
            confirm-label="Elimina"
            pending-label="Eliminazione…"
            danger
            :loading="deleting"
            :error="deleteError"
            @cancel="cancelDelete"
            @confirm="confirmDelete(deleteTarget.id)"
        >
            Sei sicuro di voler eliminare <b>{{ deleteTarget.name }}</b>?<br />
            <span class="modal-note">
                Questa azione è irreversibile. I post associati rimarranno nel sistema.
            </span>
        </ConfirmModal>
    </AppLayout>
</template>
