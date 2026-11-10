<script setup>
// Dialog "Un tuo post": cerca tra i post già pubblicati dell'account e
// sceglie su quale canale puntare. Chi lo usa ne ricava lo shortcode
// `[WordPress url id="123"]`, che il worker sostituisce con l'indirizzo vero
// all'uscita (vedi publisher/integrations/shortcodes.py).
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import PostThumb from '@/Components/PostThumb.vue';
import SearchInput from '@/Components/UI/SearchInput.vue';
import { CH_CONFIG } from '@/data/dashboardMock';

const props = defineProps({
    userId: { type: [Number, String], required: true },
    title: { type: String, default: 'Collega un tuo post' },
});

const emit = defineEmits(['pick', 'close']);

const LIMIT = 20; // come PostLinkController::LIMIT

const q = ref('');
const posts = ref([]);
const loading = ref(false);
const loaded = ref(false);
const error = ref(null);
const searchWrap = ref(null);
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
        if (current === seq) {
            loading.value = false;
            loaded.value = true;
        }
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

onMounted(async () => {
    load();
    window.addEventListener('keydown', onKeydown, true);
    await nextTick();
    searchWrap.value?.querySelector('input')?.focus();
});
onUnmounted(() => {
    clearTimeout(timer);
    window.removeEventListener('keydown', onKeydown, true);
});

const footerText = computed(() => {
    if (!loaded.value || error.value || !posts.value.length) return '';
    if (q.value) return posts.value.length === 1 ? '1 risultato' : `${posts.value.length} risultati`;
    return posts.value.length >= LIMIT ? `Ultimi ${LIMIT} post pubblicati` : `${posts.value.length} post pubblicati`;
});

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleDateString('it-IT', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
}

// Colore del canale come variabile: il CSS ne ricava fondo e bordo tenui.
function chipStyle(key) {
    return { '--pl-ch': CH_CONFIG[key]?.color ?? '#6b7280' };
}

function pick(post, channel) {
    emit('pick', { id: post.id, title: post.title, channel: channel.key, label: channel.label, url: channel.url });
}
</script>

<template>
    <div class="overlay" @click="emit('close')">
        <div class="modal modal--wide pl-modal" role="dialog" aria-modal="true" :aria-label="title" @click.stop>
            <div class="pl-head">
                <div>
                    <div class="pl-head-title">{{ title }}</div>
                    <p class="pl-head-sub">Scegli il post e il canale: il link si aggiorna da solo quando il post esce.</p>
                </div>
                <button type="button" class="pl-close" aria-label="Chiudi" @click="emit('close')"><Icon name="x" :size="18" /></button>
            </div>

            <div ref="searchWrap" class="pl-search">
                <SearchInput v-model="q" placeholder="Cerca per titolo o numero del post" @clear="q = ''" />
            </div>

            <div class="pl-list" :class="{ 'is-busy': loading && loaded }" aria-live="polite" :aria-busy="loading">
                <template v-if="loading && !loaded">
                    <div v-for="n in 3" :key="n" class="pl-row pl-row--skeleton" aria-hidden="true">
                        <span class="pl-skel pl-skel--thumb" />
                        <div class="pl-row-main">
                            <span class="pl-skel pl-skel--title" />
                            <span class="pl-skel pl-skel--line" />
                            <span class="pl-skel pl-skel--line pl-skel--short" />
                        </div>
                    </div>
                </template>

                <div v-else-if="error" class="pl-state">
                    <Icon name="warning" :size="26" />
                    <p>{{ error }}</p>
                    <button type="button" class="btn btn-secondary btn-sm" @click="load">Riprova</button>
                </div>

                <div v-else-if="!posts.length" class="pl-state">
                    <Icon :name="q ? 'search' : 'send'" :size="26" />
                    <p v-if="q">Nessun post pubblicato corrisponde a «{{ q }}».</p>
                    <p v-else>Non ci sono ancora post pubblicati da collegare.</p>
                    <button v-if="q" type="button" class="pl-state-link" @click="q = ''">Mostra tutti</button>
                </div>

                <template v-else>
                    <div v-for="post in posts" :key="post.id" class="pl-row">
                        <PostThumb :src="post.cover" :fallback="post.thumb" :size="52" />
                        <div class="pl-row-main">
                            <div class="pl-title" :title="post.title">{{ post.title }}</div>
                            <div class="pl-meta">
                                <span class="pl-id">#{{ post.id }}</span>
                                <span aria-hidden="true">·</span>
                                <span>Pubblicato il {{ formatDate(post.published_at) }}</span>
                            </div>
                            <p v-if="post.excerpt" class="pl-excerpt">{{ post.excerpt }}</p>
                        </div>
                        <div class="pl-channels">
                            <button v-for="c in post.channels" :key="c.key" type="button" class="pl-chip" :style="chipStyle(c.key)"
                                :title="c.url" :aria-label="`Collega «${post.title}» su ${c.label}`" @click="pick(post, c)">
                                <ChannelIcon :id="c.key" :size="13" />
                                <span>{{ c.label }}</span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="modal-foot pl-foot">
                <span class="pl-foot-info">{{ footerText }}</span>
                <button type="button" class="btn btn-secondary btn-sm" @click="emit('close')">Annulla</button>
            </div>
        </div>
    </div>
</template>
