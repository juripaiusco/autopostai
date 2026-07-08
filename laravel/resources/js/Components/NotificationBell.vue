<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import Icon from '@/Components/Icon.vue';
import { csrfHeader } from '@/lib/csrf';

const unread = ref(false);
const open = ref(false);
const loading = ref(false);
const items = ref([]);
let pollTimer = null;

async function checkUnread() {
    try {
        const res = await fetch(route('push.unread'), { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        unread.value = (await res.json()).unread;
    } catch {
        // silenzioso: un polling che fallisce una volta non deve disturbare l'uso dell'app
    }
}

async function toggleOpen() {
    open.value = !open.value;
    if (!open.value || loading.value) return;

    loading.value = true;
    try {
        const res = await fetch(route('push.mark-read'), {
            method: 'POST',
            headers: { Accept: 'application/json', ...csrfHeader() },
        });
        if (res.ok) {
            items.value = (await res.json()).notifications;
            unread.value = false;
        }
    } finally {
        loading.value = false;
    }
}

function onClickOutside(e) {
    if (!e.target.closest('.tb-notif')) open.value = false;
}

onMounted(() => {
    checkUnread();
    pollTimer = setInterval(checkUnread, 30000);
    document.addEventListener('click', onClickOutside);
});
onUnmounted(() => {
    clearInterval(pollTimer);
    document.removeEventListener('click', onClickOutside);
});
</script>

<template>
    <div class="tb-notif" style="position: relative">
        <button type="button" class="tb-icon alert" title="Notifiche" @click.stop="toggleOpen">
            <Icon name="bell" :size="20" />
            <i v-if="unread" class="tb-dot"></i>
        </button>

        <div v-if="open" class="card ts-dropdown" style="width: 320px; left: auto; right: 0">
            <div v-if="loading" class="ts-empty">Caricamento…</div>
            <template v-else-if="items.length">
                <a v-for="n in items" :key="n.id" :href="n.url || '#'" class="ts-item" style="flex-direction: column; align-items: flex-start; gap: 2px; height: auto">
                    <span class="ts-item-title" style="font-weight: 600">{{ n.title }}</span>
                    <span style="font-size: 12px; color: var(--g500); white-space: normal">{{ n.body }}</span>
                </a>
            </template>
            <div v-else class="ts-empty">Nessuna notifica</div>
        </div>
    </div>
</template>
