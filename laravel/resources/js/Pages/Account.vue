<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import RoleBadge from '@/Components/RoleBadge.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import TokenBar from '@/Components/TokenBar.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { CH_CONFIG } from '@/data/dashboardMock';

const props = defineProps({
    users: { type: Object, required: true },
    isAdmin: { type: Boolean, required: true },
    filters: { type: Object, required: true },
    counts: { type: Object, required: true },
});

const userName = computed(() => usePage().props.auth?.user?.name ?? 'Mario Rossi');

const search = ref(props.filters.search);
const deleteTarget = ref(null);

const TABS = computed(() => [
    { id: 'tutti', label: 'Tutti', count: props.counts.tutti },
    { id: 'utenti', label: 'Utenti', count: props.counts.utenti },
    ...(props.isAdmin ? [{ id: 'manager', label: 'Manager', count: props.counts.manager }] : []),
]);

function reload(extra = {}) {
    router.get(route('account'), {
        filter: props.filters.filter,
        search: search.value,
        sort: props.filters.sort,
        dir: props.filters.dir,
        ...extra,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function setFilter(id) {
    reload({ filter: id, page: undefined });
}

function handleSort(col) {
    if (props.filters.sort === col) {
        reload({ dir: props.filters.dir === 'asc' ? 'desc' : 'asc' });
    } else {
        reload({ sort: col, dir: 'asc' });
    }
}

function clearSearch() {
    search.value = '';
    reload({ search: '', page: undefined });
}

let searchTimer = null;
watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => reload({ search: value, page: undefined }), 350);
});

function sortIconFor(col) {
    if (props.filters.sort !== col) return '↕';
    return props.filters.dir === 'asc' ? '↑' : '↓';
}
function sortIconColor(col) {
    return props.filters.sort === col ? 'var(--sky-strong)' : 'var(--g300)';
}

function confirmDelete(id) {
    router.delete(route('account.destroy', id), {
        preserveScroll: true,
        onFinish: () => { deleteTarget.value = null; },
    });
}

const colSpan = computed(() => (props.isAdmin ? 8 : 7));
</script>

<template>
    <Head title="Account" />

    <AppLayout :current="'account'" :user="userName">
        <header class="page-header" style="background: #fff; box-shadow: 0 1px 0 var(--g200); margin: -30px -30px 24px">
            <div style="padding: 18px 28px; display: flex; align-items: center; justify-content: space-between">
                <div style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--g500)">
                    <span>Account</span>
                    <span style="color: var(--g300)">›</span>
                    <b style="color: var(--ink)">Lista</b>
                </div>
                <span v-if="!isAdmin" style="font-size: 12px; color: var(--g500); background: var(--g100); padding: 4px 10px; border-radius: 9999px; border: 1px solid var(--g200)">
                    Stai vedendo i tuoi {{ counts.tutti }} sub-utenti
                </span>
            </div>
        </header>

        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; flex-wrap: wrap">
            <button class="btn btn-dark" style="gap: 7px">
                <Icon name="plus" :size="17" />Nuovo
            </button>

            <div style="display: flex; gap: 4px; background: #fff; border: 1px solid var(--g200); border-radius: var(--radius); padding: 3px">
                <button v-for="t in TABS" :key="t.id"
                    @click="setFilter(t.id)"
                    :style="{
                        border: 'none', borderRadius: '5px', padding: '6px 14px',
                        fontSize: '13px', fontWeight: 500, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '6px',
                        background: filters.filter === t.id ? 'var(--sky-50)' : 'transparent',
                        color: filters.filter === t.id ? 'var(--sky-strong)' : 'var(--g500)',
                        transition: 'all .15s',
                    }"
                >
                    {{ t.label }}
                    <span :style="{
                        fontSize: '11px', fontWeight: 700, minWidth: '18px', textAlign: 'center',
                        padding: '1px 5px', borderRadius: '9999px',
                        background: filters.filter === t.id ? 'var(--sky-200)' : 'var(--g100)',
                        color: filters.filter === t.id ? 'var(--sky-strong)' : 'var(--g500)',
                    }">{{ t.count }}</span>
                </button>
            </div>

            <div style="margin-left: auto; display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--g200); border-radius: var(--radius); padding: 8px 12px; min-width: 240px">
                <Icon name="search" :size="16" style="color: var(--g400); flex-shrink: 0" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cerca account…"
                    style="border: none; outline: none; font-family: var(--font-ui); font-size: 13.5px; color: var(--ink); width: 100%; background: transparent"
                />
                <button v-if="search" @click="clearSearch" style="border: none; background: none; cursor: pointer; color: var(--g400); display: flex; padding: 0">
                    <Icon name="x" :size="14" />
                </button>
            </div>
        </div>

        <div class="card" style="overflow-x: auto">
            <table class="table" style="width: 100%">
                <thead>
                    <tr>
                        <th @click="handleSort('name')" style="cursor: pointer; user-select: none; white-space: nowrap">
                            Account <span :style="{ color: sortIconColor('name'), fontSize: '10px', marginLeft: '3px' }">{{ sortIconFor('name') }}</span>
                        </th>
                        <th v-if="isAdmin">Ruolo</th>
                        <th>Canali</th>
                        <th @click="handleSort('post')" style="cursor: pointer; user-select: none; text-align: center; white-space: nowrap">
                            Post <span :style="{ color: sortIconColor('post'), fontSize: '10px', marginLeft: '3px' }">{{ sortIconFor('post') }}</span>
                        </th>
                        <th @click="handleSort('reply')" style="cursor: pointer; user-select: none; text-align: center; white-space: nowrap">
                            Reply <span :style="{ color: sortIconColor('reply'), fontSize: '10px', marginLeft: '3px' }">{{ sortIconFor('reply') }}</span>
                        </th>
                        <th @click="handleSort('immagini')" style="cursor: pointer; user-select: none; text-align: center; white-space: nowrap">
                            Immagini <span :style="{ color: sortIconColor('immagini'), fontSize: '10px', marginLeft: '3px' }">{{ sortIconFor('immagini') }}</span>
                        </th>
                        <th @click="handleSort('tokenUsed')" style="cursor: pointer; user-select: none; text-align: right; white-space: nowrap">
                            Token <span :style="{ color: sortIconColor('tokenUsed'), fontSize: '10px', marginLeft: '3px' }">{{ sortIconFor('tokenUsed') }}</span>
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="users.data.length === 0">
                        <td :colspan="colSpan" style="text-align: center; padding: 48px 16px; color: var(--g400)">
                            <Icon name="users" :size="32" style="margin: 0 auto 10px; opacity: .35" />
                            <div style="font-size: 14px">Nessun utente trovato</div>
                        </td>
                    </tr>
                    <tr v-for="u in users.data" :key="u.id">
                        <td style="min-width: 200px">
                            <div style="display: flex; align-items: center; gap: 11px">
                                <UserAvatar :name="u.name" />
                                <div style="min-width: 0">
                                    <div style="font-weight: 600; font-size: 14px; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{{ u.name }}</div>
                                    <div style="font-size: 12px; color: var(--g500); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{{ u.email }}</div>
                                </div>
                            </div>
                        </td>
                        <td v-if="isAdmin"><RoleBadge :role="u.role" /></td>
                        <td>
                            <div style="display: flex; gap: 4px; flex-wrap: wrap">
                                <span v-for="c in u.channels" :key="c" class="ch-chip" :title="CH_CONFIG[c]?.label ?? c">
                                    <ChannelIcon :id="c" :size="13" />
                                </span>
                            </div>
                        </td>
                        <td style="text-align: center; font-variant-numeric: tabular-nums">{{ u.post }}</td>
                        <td style="text-align: center; font-variant-numeric: tabular-nums">{{ u.reply }}</td>
                        <td style="text-align: center; font-variant-numeric: tabular-nums">{{ u.immagini }}</td>
                        <td style="text-align: right; min-width: 160px">
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

            <div v-if="users.data.length > 0" style="padding: 12px 20px; border-top: 1px solid var(--g100); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px">
                <span style="font-size: 12.5px; color: var(--g400)">
                    {{ users.total }} {{ users.total === 1 ? 'utente' : 'utenti' }}{{ filters.search ? ' trovati' : '' }}
                </span>

                <div style="display: flex; align-items: center; gap: 4px">
                    <Link v-for="(link, i) in users.links" :key="i"
                        :href="link.url ?? '#'"
                        :style="{
                            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                            minWidth: '30px', height: '30px', padding: '0 8px', borderRadius: 'var(--radius)',
                            fontSize: '12.5px', fontWeight: 600, textDecoration: 'none',
                            border: '1px solid var(--g200)',
                            background: link.active ? 'var(--sky-50)' : '#fff',
                            color: link.active ? 'var(--sky-strong)' : (link.url ? 'var(--g600)' : 'var(--g300)'),
                            pointerEvents: link.url ? 'auto' : 'none',
                        }"
                        preserve-scroll preserve-state
                        v-html="link.label"
                    />
                </div>

                <button v-if="filters.search" @click="clearSearch" style="font-size: 12px; color: var(--sky-strong); background: none; border: none; cursor: pointer; text-decoration: underline">
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
            <span style="color: var(--g500); font-size: 13px; margin-top: 6px; display: block">
                Questa azione è irreversibile. I post associati rimarranno nel sistema.
            </span>
        </ConfirmModal>
    </AppLayout>
</template>
