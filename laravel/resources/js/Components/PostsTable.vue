<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import StatusPill from '@/Components/StatusPill.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import PostThumb from '@/Components/PostThumb.vue';
import PostActivity from '@/Components/Domain/PostActivity.vue';
import { CH_CONFIG } from '@/data/dashboardMock';

defineProps({
    posts: { type: Array, required: true },
});

const HEADERS = ['', 'Titolo', 'Canali', 'Data', 'Attività', ''];
function alignFor(h) {
    if (h === 'Attività') return 'right';
    if (h === '') return 'center';
    return 'left';
}

// Stessa regola della lista Post: i pubblicati aprono il dettaglio, il resto il form.
function openPost(p) {
    router.get(['published', 'done', 'partial'].includes(p.status) ? route('posts.show', p.id) : route('posts.edit', p.id));
}

const deleteTarget = ref(null);
const deleting = ref(false);
const deleteError = ref(null);

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
</script>

<template>
    <div style="overflow-x: auto">
        <table class="table" style="min-width: 680px">
            <thead>
                <tr>
                    <th v-for="(h, i) in HEADERS" :key="i" :style="{ textAlign: alignFor(h) }">{{ h }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="p in posts" :key="p.id" class="tr-clickable" @click="openPost(p)">
                    <td style="width: 56px; padding-left: 16px; padding-right: 8px">
                        <PostThumb :src="p.cover" :fallback="p.thumb" />
                    </td>
                    <td>
                        <div style="font-weight: 600; color: var(--ink); margin-bottom: 4px;
                                    max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">
                            {{ p.title }}
                        </div>
                        <StatusPill :status="p.status" :monitoring-until="p.monitoringUntil" :sending="p.newsletter?.sending ?? false" />
                    </td>
                    <td>
                        <div style="display: flex; gap: 6px; align-items: center">
                            <component :is="p.channelUrls?.[c] ? 'a' : 'span'" v-for="c in p.channels" :key="c" class="ch-chip"
                                :href="p.channelUrls?.[c]" :target="p.channelUrls?.[c] ? '_blank' : null"
                                :rel="p.channelUrls?.[c] ? 'noopener noreferrer' : null"
                                :title="p.channelUrls?.[c] ? `Apri su ${CH_CONFIG[c].label}` : CH_CONFIG[c].label"
                                :style="{ '--ch-hue': CH_CONFIG[c].color }"
                                @click.stop>
                                <ChannelIcon :id="c" :size="13" />
                            </component>
                        </div>
                    </td>
                    <td style="color: var(--g500); font-size: 13px; white-space: nowrap">{{ p.date }}</td>
                    <td style="text-align: right">
                        <PostActivity :comments="p.comments" :channels="p.channels" :newsletter="p.newsletter" />
                    </td>
                    <td style="padding-right: 16px">
                        <div class="row-actions">
                            <button class="icon-btn icon-btn--ghost icon-btn--danger" title="Elimina" :aria-label="`Elimina ${p.title}`" @click.stop="openDelete(p)">
                                <Icon name="trash" :size="16" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
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
</template>
