<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import StatusPill from '@/Components/StatusPill.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import FilterTabs from '@/Components/UI/FilterTabs.vue';
import SearchInput from '@/Components/UI/SearchInput.vue';
import SortableTh from '@/Components/UI/SortableTh.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { useTableFilters } from '@/Composables/useTableFilters';
import { CH_CONFIG } from '@/data/dashboardMock';

const props = defineProps({
    posts: { type: Object, required: true },
    showAuthor: { type: Boolean, required: true },
    filters: { type: Object, required: true },
    counts: { type: Object, required: true },
});

const userName = computed(() => usePage().props.auth?.user?.name ?? 'Mario Rossi');

const { search, setFilter, handleSort, clearSearch } = useTableFilters({
    routeName: 'posts',
    filters: () => props.filters,
});

const TABS = computed(() => [
    { id: 'tutti', label: 'Tutti', count: props.counts.tutti },
    { id: 'pubblicati', label: 'Pubblicati', count: props.counts.pubblicati },
    { id: 'programmati', label: 'Programmati', count: props.counts.programmati },
    { id: 'bozze', label: 'Bozze', count: props.counts.bozze },
]);

const deleteTarget = ref(null);
const deleting = ref(false);
const deleteError = ref(null);
const colSpan = computed(() => (props.showAuthor ? 6 : 5));
const listKey = computed(() => `${props.filters.filter}-${props.filters.search}-${props.filters.sort}-${props.filters.dir}-${props.posts.current_page}`);

function openDelete(p) {
    deleteTarget.value = p;
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
    router.delete(route('posts.destroy', id), {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null; },
        onError: () => { deleteError.value = 'Eliminazione non riuscita. Riprova.'; },
        onFinish: () => { deleting.value = false; },
    });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Post" />

    <AppLayout :current="'posts'" :user="userName">
        <template #page-header>
            <PageHeader :crumbs="[{ label: 'Post', href: route('posts') }, { label: 'Lista', current: true }]" />
        </template>

        <div class="list-toolbar">
            <FilterTabs :model-value="filters.filter" :tabs="TABS" @update:model-value="setFilter" />

            <SearchInput v-model="search" placeholder="Cerca post…" @clear="clearSearch" />
        </div>

        <div class="card table-wrap">
            <table class="table table--responsive-cards">
                <colgroup>
                    <col style="width: 28%">
                    <col v-if="showAuthor" style="width: 14%">
                    <col style="width: 14%">
                    <col style="width: 12%">
                    <col style="width: 16%">
                    <col style="width: 10%">
                    <col style="width: 52px">
                </colgroup>
                <thead>
                    <tr>
                        <SortableTh label="Titolo" column="title" :sort="filters.sort" :dir="filters.dir" @sort="handleSort" />
                        <th v-if="showAuthor">Autore</th>
                        <th>Canali</th>
                        <th>Stato</th>
                        <SortableTh label="Pubblicazione" column="publishedAt" :sort="filters.sort" :dir="filters.dir" @sort="handleSort" />
                        <SortableTh label="Commenti" column="comments" align="center" :sort="filters.sort" :dir="filters.dir" @sort="handleSort" />
                        <th></th>
                    </tr>
                </thead>
                <Transition name="table-fade" mode="out-in">
                    <tbody v-if="posts.data.length === 0" :key="`${listKey}-empty`">
                        <tr>
                            <td :colspan="colSpan + 1" class="table-empty">
                                <Icon name="chat" :size="32" class="table-empty__icon" />
                                <template v-if="filters.search">
                                    <div class="table-empty__text">Nessun risultato per «{{ filters.search }}»</div>
                                    <button type="button" class="list-footer__clear" @click="clearSearch">Cancella ricerca</button>
                                </template>
                                <div v-else class="table-empty__text">Nessun post trovato</div>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else :key="`${listKey}-rows`">
                        <tr v-for="p in posts.data" :key="p.id"
                            class="tr-clickable"
                            @click="router.get(p.status === 'published' ? route('posts.show', p.id) : route('posts.edit', p.id))">
                            <td data-label="Titolo">{{ p.title }}</td>
                            <td v-if="showAuthor" data-label="Autore">{{ p.author }}</td>
                            <td data-label="Canali">
                                <div class="ch-list">
                                    <span v-for="c in p.channels" :key="c" class="ch-chip" :title="CH_CONFIG[c]?.label ?? c">
                                        <ChannelIcon :id="c" :size="13" />
                                    </span>
                                </div>
                            </td>
                            <td data-label="Stato"><StatusPill :status="p.status" /></td>
                            <td data-label="Pubblicazione">{{ formatDate(p.publishedAt) }}</td>
                            <td class="num" data-label="Commenti">{{ p.comments }}</td>
                            <td data-label="">
                                <div class="row-actions">
                                    <button class="icon-btn icon-btn--ghost icon-btn--danger" title="Elimina" :aria-label="`Elimina ${p.title}`" @click.stop="openDelete(p)">
                                        <Icon name="trash" :size="15" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Transition>
            </table>

            <div v-if="posts.data.length > 0" class="list-footer">
                <span class="list-footer__info">
                    {{ posts.total }} {{ posts.total === 1 ? 'post' : 'post' }}{{ filters.search ? ' trovati' : '' }}
                </span>

                <Pagination :links="posts.links" />

                <button v-if="filters.search" class="list-footer__clear" @click="clearSearch">
                    Cancella filtro
                </button>
            </div>
        </div>

        <ConfirmModal
            v-if="deleteTarget"
            title="Elimina post"
            confirm-label="Elimina"
            pending-label="Eliminazione…"
            danger
            :loading="deleting"
            :error="deleteError"
            @cancel="cancelDelete"
            @confirm="confirmDelete(deleteTarget.id)"
        >
            Sei sicuro di voler eliminare <b>{{ deleteTarget.title }}</b>?<br />
            <span class="modal-note">
                Questa azione è irreversibile.
            </span>
        </ConfirmModal>
    </AppLayout>
</template>
