<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import Pagination from '@/Components/UI/Pagination.vue';

const props = defineProps({
    notifications: { type: Object, required: true },
});

const userName = computed(() => usePage().props.auth?.user?.name ?? '');

const deleteTarget = ref(null);
const deleting = ref(false);

function openDelete(n) {
    deleteTarget.value = n;
}

function cancelDelete() {
    if (deleting.value) return;
    deleteTarget.value = null;
}

function confirmDelete(id) {
    deleting.value = true;
    router.delete(route('notifications.destroy', id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <Head title="Notifiche" />

    <AppLayout current="notifications" :user="userName">
        <template #page-header>
            <PageHeader :crumbs="[{ label: 'Notifiche', current: true }]">
                <template #actions>
                    <a :href="route('notifications.create')" class="btn btn-dark">
                        <Icon name="plus" :size="17" />Nuova notifica
                    </a>
                </template>
            </PageHeader>
        </template>

        <div class="card table-wrap">
            <table class="table table--responsive-cards">
                <colgroup>
                    <col style="width: 34%">
                    <col style="width: 18%">
                    <col style="width: 14%">
                    <col style="width: 16%">
                    <col style="width: 110px">
                </colgroup>
                <thead>
                    <tr>
                        <th>Notifica</th>
                        <th>Destinatario</th>
                        <th>Stato</th>
                        <th>Creata</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody v-if="notifications.data.length === 0">
                    <tr>
                        <td colspan="5" class="table-empty">
                            <Icon name="bell" :size="32" class="table-empty__icon" />
                            <div class="table-empty__text">Nessuna notifica creata</div>
                        </td>
                    </tr>
                </tbody>
                <tbody v-else>
                    <tr v-for="n in notifications.data" :key="n.id">
                        <td data-label="Notifica">
                            <div style="font-weight: 600; color: var(--ink)">{{ n.title }}</div>
                            <div style="font-size: 12.5px; color: var(--g500)">{{ n.body }}</div>
                        </td>
                        <td data-label="Destinatario">{{ n.recipient }}</td>
                        <td data-label="Stato">
                            <span class="pill" :class="n.status === 'inviata' ? 'pill-published' : 'pill-scheduled pill--pulse-warn'">
                                <span class="dot"></span>{{ n.status === 'inviata' ? `Inviata (${n.recipientsCount})` : 'In coda' }}
                            </span>
                        </td>
                        <td data-label="Creata">{{ new Date(n.createdAt).toLocaleString('it-IT') }}</td>
                        <td data-label="">
                            <div class="row-actions">
                                <a v-if="n.status !== 'inviata'" :href="route('notifications.edit', n.id)" class="icon-btn icon-btn--ghost" title="Modifica">
                                    <Icon name="pencil" :size="15" />
                                </a>
                                <button class="icon-btn icon-btn--ghost icon-btn--danger" title="Elimina" @click="openDelete(n)">
                                    <Icon name="trash" :size="15" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="notifications.data.length > 0" class="list-footer">
                <span class="list-footer__info">{{ notifications.total }} notifiche</span>
                <Pagination :links="notifications.links" />
            </div>
        </div>

        <ConfirmModal
            v-if="deleteTarget"
            title="Elimina notifica"
            confirm-label="Elimina"
            pending-label="Eliminazione…"
            danger
            :loading="deleting"
            @cancel="cancelDelete"
            @confirm="confirmDelete(deleteTarget.id)"
        >
            Sei sicuro di voler eliminare <b>{{ deleteTarget.title }}</b>?
        </ConfirmModal>
    </AppLayout>
</template>
