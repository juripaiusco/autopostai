<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

const query = ref('');
const results = ref({ posts: [], accounts: [] });
const open = ref(false);
const loading = ref(false);
const inputRef = ref(null);
let debounceTimer = null;
let requestSeq = 0;

function runSearch(q) {
    if (!q) {
        results.value = { posts: [], accounts: [] };
        open.value = false;
        return;
    }

    const seq = ++requestSeq;
    loading.value = true;

    fetch(`/search?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((data) => {
            if (seq !== requestSeq) return; // risposta di una digitazione precedente, scartata
            results.value = data;
            open.value = true;
        })
        .finally(() => {
            if (seq === requestSeq) loading.value = false;
        });
}

function onInput() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => runSearch(query.value.trim()), 300);
}

function goTo(url) {
    open.value = false;
    query.value = '';
    router.visit(url);
}

function onKeydown(e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        inputRef.value?.focus();
    } else if (e.key === 'Escape') {
        open.value = false;
        inputRef.value?.blur();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="topbar-search" :class="{ 'ts-open': open }">
        <Icon name="search" :size="17" />
        <input
            ref="inputRef"
            v-model="query"
            placeholder="Cerca post, sotto-utenti… (⌘K)"
            @input="onInput"
            @focus="open = results.posts.length + results.accounts.length > 0"
        />

        <div v-if="open" class="card ts-dropdown">
            <div v-if="results.posts.length" class="ts-group">
                <div class="ts-group-label">Post</div>
                <button v-for="p in results.posts" :key="`p${p.id}`" type="button" class="ts-item" @click="goTo(p.url)">
                    <span class="ts-item-title">{{ p.title }}</span>
                    <span class="ts-item-meta">{{ p.status }}</span>
                </button>
            </div>

            <div v-if="results.accounts.length" class="ts-group">
                <div class="ts-group-label">Sotto-utenti</div>
                <button v-for="a in results.accounts" :key="`a${a.id}`" type="button" class="ts-item" @click="goTo(a.url)">
                    <span class="ts-item-title">{{ a.name }}</span>
                    <span class="ts-item-meta">{{ a.role }}</span>
                </button>
            </div>

            <div v-if="!loading && !results.posts.length && !results.accounts.length" class="ts-empty">
                Nessun risultato
            </div>
        </div>
    </div>
</template>
