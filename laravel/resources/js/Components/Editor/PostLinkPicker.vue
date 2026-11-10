<script setup>
// Dialog "Un tuo post": cerca tra i post già pubblicati dell'account e
// sceglie su quale canale puntare. Chi lo usa ne ricava lo shortcode
// `[canale url id=N]`, che il worker sostituisce con l'indirizzo vero
// all'uscita (vedi publisher/integrations/shortcodes.py).
import { ref, watch, onMounted, onUnmounted } from 'vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import SearchInput from '@/Components/UI/SearchInput.vue';

const props = defineProps({
    userId: { type: [Number, String], required: true },
    title: { type: String, default: 'Collega un tuo post' },
});

const emit = defineEmits(['pick', 'close']);

const q = ref('');
const posts = ref([]);
const loading = ref(false);
const error = ref(null);
let timer = null;
let seq = 0;

async function load() {
    const current = ++seq;
    loading.value = true;
    error.value = null;
    try {
        const url = route('posts.link-targets', props.userId) + (q.value ? `?q=${encodeURIComponent(q.value)}` : '');
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error();
        const json = await res.json();
        if (current === seq) posts.value = json.posts ?? [];
    } catch {
        if (current === seq) error.value = 'Impossibile caricare i post, riprova.';
    } finally {
        if (current === seq) loading.value = false;
    }
}

watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(load, 250);
});

// In cattura e senza propagazione: Esc chiude solo questo dialog, non il
// modal che lo contiene (es. "Modifica post" nella pagina del post).
function onKeydown(e) {
    if (e.key !== 'Escape') return;
    e.stopPropagation();
    emit('close');
}

onMounted(() => {
    load();
    window.addEventListener('keydown', onKeydown, true);
});
onUnmounted(() => {
    clearTimeout(timer);
    window.removeEventListener('keydown', onKeydown, true);
});

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleDateString('it-IT', { day: '2-digit', month: 'short', year: 'numeric' }) : '';
}

function pick(post, channel) {
    emit('pick', { id: post.id, title: post.title, channel: channel.key, label: channel.label, url: channel.url });
}
</script>

<template>
    <div class="overlay" @click="emit('close')">
        <div class="modal modal--wide" role="dialog" aria-modal="true" :aria-label="title" @click.stop>
            <div class="modal-head">{{ title }}</div>
            <div class="modal-body">
                <p class="pl-help">Scegli un post già pubblicato e il canale: nel testo finirà il link a quel post, aggiornato al momento dell'uscita.</p>
                <SearchInput v-model="q" placeholder="Cerca per titolo o numero" @clear="q = ''" />
                <div class="pl-list" aria-live="polite">
                    <div v-if="loading && !posts.length" class="pl-empty">Caricamento…</div>
                    <div v-else-if="error" class="modal-error" role="alert">{{ error }}</div>
                    <div v-else-if="!posts.length" class="pl-empty">
                        {{ q ? 'Nessun post pubblicato con questo titolo.' : 'Nessun post pubblicato con un indirizzo da collegare.' }}
                    </div>
                    <div v-for="post in posts" :key="post.id" class="pl-row">
                        <div class="pl-row-main">
                            <div class="pl-title">{{ post.title }}</div>
                            <div class="pl-meta">#{{ post.id }} · {{ formatDate(post.published_at) }}</div>
                        </div>
                        <div class="pl-channels">
                            <button v-for="c in post.channels" :key="c.key" type="button" class="pl-channel"
                                :title="`Collega il post su ${c.label}: ${c.url}`" @click="pick(post, c)">
                                <ChannelIcon :id="c.key" :size="12" />{{ c.label }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary btn-sm" @click="emit('close')">Annulla</button>
            </div>
        </div>
    </div>
</template>
