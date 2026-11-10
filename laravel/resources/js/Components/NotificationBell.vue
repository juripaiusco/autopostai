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

async function openPanel() {
    open.value = true;
    if (loading.value) return;

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

function close() {
    open.value = false;
}

// Data come in v1: "Adesso" / "N min fa" / "N ore fa" in giornata, poi gg/mm/aaaa.
function relativeDate(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    const now = new Date();
    if (date.toDateString() !== now.toDateString()) {
        return date.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }
    const min = Math.floor((now - date) / 60000);
    if (min < 1) return 'Adesso';
    if (min < 60) return `${min} min fa`;
    const h = Math.floor(min / 60);
    return h === 1 ? '1 ora fa' : `${h} ore fa`;
}

function fullDate(iso) {
    return iso ? new Date(iso).toLocaleString('it-IT', { dateStyle: 'long', timeStyle: 'short' }) : '';
}

function onKeydown(e) {
    if (e.key === 'Escape' && open.value) close();
}

onMounted(() => {
    checkUnread();
    pollTimer = setInterval(checkUnread, 30000);
    document.addEventListener('keydown', onKeydown);
});
onUnmounted(() => {
    clearInterval(pollTimer);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div class="tb-notif">
        <button type="button" class="tb-icon" :class="{ alert: unread }" title="Notifiche"
            :aria-expanded="open" aria-haspopup="dialog" @click="openPanel">
            <Icon name="bell" :size="20" :class="{ 'notif-ring': unread }" />
            <i v-if="unread" class="tb-dot"></i>
        </button>

        <Teleport to="body">
            <Transition name="notif-fade">
                <div v-if="open" class="notif-overlay" @click="close"></div>
            </Transition>
            <Transition name="notif-rise">
                <section v-if="open" class="card notif-panel" role="dialog" aria-modal="true" aria-labelledby="notif-title">
                    <header class="notif-head">
                        <h3 id="notif-title" class="notif-title">Novità di FaPer3</h3>
                        <button type="button" class="notif-close" aria-label="Chiudi" @click="close">
                            <Icon name="x" :size="16" />
                        </button>
                    </header>

                    <div v-if="loading" class="ts-empty">Caricamento…</div>
                    <ul v-else-if="items.length" class="notif-list">
                        <li v-for="n in items" :key="n.id">
                            <component :is="n.url ? 'a' : 'div'" class="notif-item"
                                v-bind="n.url ? { href: n.url, target: '_blank', rel: 'noopener' } : {}">
                                <div class="notif-item-top">
                                    <Icon v-if="n.url" name="link" :size="12" class="notif-item-link" />
                                    <span class="notif-item-title">{{ n.title }}</span>
                                    <time class="notif-item-date" :datetime="n.createdAt" :title="fullDate(n.createdAt)">
                                        {{ relativeDate(n.createdAt) }}
                                    </time>
                                </div>
                                <p v-if="n.body" class="notif-item-body">{{ n.body }}</p>
                            </component>
                        </li>
                    </ul>
                    <div v-else class="ts-empty">Nessuna notifica</div>
                </section>
            </Transition>
        </Teleport>
    </div>
</template>
