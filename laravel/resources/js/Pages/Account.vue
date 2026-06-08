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
const colSpan = computed(() => (props.isAdmin ? 8 : 7));

function confirmDelete(id) {
    router.delete(route('account.destroy', id), {
        preserveScroll: true,
        onFinish: () => { deleteTarget.value = null; },
    });
}
</script>

<template>
    <Head title="Account" />

    <AppLayout :current="'account'" :user="userName">
        <PageHeader :crumbs="[{ label: 'Account' }, { label: 'Lista', current: true }]">
            <template #actions>
                <span v-if="!isAdmin" class="page-header__note">
                    Stai vedendo i tuoi {{ counts.tutti }} sub-utenti
                </span>
            </template>
        </PageHeader>

        <div class="list-toolbar">
            <button class="btn btn-dark">
                <Icon name="plus" :size="17" />Nuovo
            </button>

            <FilterTabs :model-value="filters.filter" :tabs="TABS" @update:model-value="setFilter" />

            <SearchInput v-model="search" placeholder="Cerca account…" @clear="clearSearch" />
        </div>

        <div class="card table-wrap">
            <table class="table">
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
                <tbody>
                    <tr v-if="users.data.length === 0">
                        <td :colspan="colSpan" class="table-empty">
                            <Icon name="users" :size="32" class="table-empty__icon" />
                            <div class="table-empty__text">Nessun utente trovato</div>
                        </td>
                    </tr>
                    <tr v-for="u in users.data" :key="u.id">
                        <td class="user-td">
                            <div class="user-cell">
                                <UserAvatar :name="u.name" />
                                <div class="user-cell__meta">
                                    <div class="user-cell__name">{{ u.name }}</div>
                                    <div class="user-cell__email">{{ u.email }}</div>
                                </div>
                            </div>
                        </td>
                        <td v-if="isAdmin"><RoleBadge :role="u.role" /></td>
                        <td>
                            <div class="ch-list">
                                <span v-for="c in u.channels" :key="c" class="ch-chip" :title="CH_CONFIG[c]?.label ?? c">
                                    <ChannelIcon :id="c" :size="13" />
                                </span>
                            </div>
                        </td>
                        <td class="num">{{ u.post }}</td>
                        <td class="num">{{ u.reply }}</td>
                        <td class="num">{{ u.immagini }}</td>
                        <td class="token-td">
                            <TokenBar :used="u.tokenUsed" :total="u.tokenTotal" />
                        </td>
                        <td>
                            <div class="row-actions">
                                <button class="icon-btn icon-btn--ghost" title="Modifica">
                                    <Icon name="pencil" :size="15" />
                                </button>
                                <button class="icon-btn icon-btn--ghost icon-btn--danger" title="Elimina" @click="deleteTarget = u">
                                    <Icon name="trash" :size="15" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
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
            danger
            @cancel="deleteTarget = null"
            @confirm="confirmDelete(deleteTarget.id)"
        >
            Sei sicuro di voler eliminare <b>{{ deleteTarget.name }}</b>?<br />
            <span class="modal-note">
                Questa azione è irreversibile. I post associati rimarranno nel sistema.
            </span>
        </ConfirmModal>
    </AppLayout>
</template>
