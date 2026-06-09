<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import StatusPill from '@/Components/StatusPill.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { CH_CONFIG } from '@/data/dashboardMock';

const props = defineProps({
    posts: { type: Array, required: true },
});

const HEADERS = ['', 'Titolo', 'Canali', 'Data', 'Views', 'Commenti', ''];
function alignFor(h) {
    if (h === 'Views' || h === 'Commenti') return 'right';
    if (h === '') return 'center';
    return 'left';
}

const localPosts = ref([...props.posts]);
watch(() => props.posts, (v) => { localPosts.value = [...v]; });

const pendingDelete = ref(null);
const undoPost      = ref(null);
let   undoTimer     = null;

onBeforeUnmount(() => { clearTimeout(undoTimer); });

function askDelete(post) {
    pendingDelete.value = post;
}

function confirmDelete() {
    const post  = pendingDelete.value;
    const index = localPosts.value.findIndex(p => p.id === post.id);
    localPosts.value = localPosts.value.filter(p => p.id !== post.id);
    pendingDelete.value = null;
    undoPost.value = { post, index };
    clearTimeout(undoTimer);
    undoTimer = setTimeout(() => { undoPost.value = null; }, 5000);
}

function undoDelete() {
    if (!undoPost.value) return;
    const { post, index } = undoPost.value;
    const next = [...localPosts.value];
    next.splice(index, 0, post);
    localPosts.value = next;
    clearTimeout(undoTimer);
    undoPost.value = null;
}

function cancelDelete() {
    pendingDelete.value = null;
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
                <tr v-for="p in localPosts" :key="p.id">
                    <td style="width: 56px; padding-left: 16px; padding-right: 8px">
                        <div :style="{ width: '40px', height: '40px', borderRadius: '8px', background: p.thumb,
                                       flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center' }"></div>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: var(--ink); margin-bottom: 4px;
                                    max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">
                            {{ p.title }}
                        </div>
                        <StatusPill :status="p.status" />
                    </td>
                    <td>
                        <div style="display: flex; gap: 6px; align-items: center">
                            <span v-for="c in p.channels" :key="c" class="ch-chip" :title="CH_CONFIG[c].label" :style="{ '--ch-hue': CH_CONFIG[c].color }">
                                <ChannelIcon :id="c" :size="13" />
                            </span>
                        </div>
                    </td>
                    <td style="color: var(--g500); font-size: 13px; white-space: nowrap">{{ p.date }}</td>
                    <td style="text-align: right; font-weight: 600; color: var(--ink)">
                        <span v-if="p.views > 0">{{ p.views.toLocaleString('it-IT') }}</span>
                        <span v-else style="color: var(--g300)">—</span>
                    </td>
                    <td style="text-align: right; color: var(--g600)">
                        <span v-if="p.comments > 0">{{ p.comments }}</span>
                        <span v-else style="color: var(--g300)">—</span>
                    </td>
                    <td style="padding-right: 16px">
                        <div class="row-actions">
                            <button class="icon-btn icon-btn--ghost" title="Modifica" aria-label="Modifica post" @click="() => {}">
                                <Icon name="pencil" :size="16" />
                            </button>
                            <button class="icon-btn icon-btn--ghost icon-btn--danger" title="Elimina" aria-label="Elimina post" @click="askDelete(p)">
                                <Icon name="trash" :size="16" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <ConfirmModal
        v-if="pendingDelete"
        title="Elimina post"
        confirm-label="Elimina"
        :danger="true"
        @confirm="confirmDelete"
        @cancel="cancelDelete"
    >
        Vuoi eliminare <b>{{ pendingDelete.title }}</b>?
        Questa azione non può essere annullata.
    </ConfirmModal>

    <Teleport to="body">
        <div v-if="undoPost" class="toast-undo" role="status" aria-live="polite">
            <span>Post eliminato</span>
            <button class="toast-undo__btn" @click="undoDelete">Annulla</button>
        </div>
    </Teleport>
</template>
