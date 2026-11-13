<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Sidebar from '@/Components/Sidebar.vue';
import GlobalSearch from '@/Components/GlobalSearch.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import ScopeSelector from '@/Components/ScopeSelector.vue';
import ScopeContextBar from '@/Components/ScopeContextBar.vue';
import MobileTabBar from '@/Components/Layout/MobileTabBar.vue';
import { useUserScope } from '@/Composables/useUserScope';
import { usePullToRefresh } from '@/Composables/usePullToRefresh';

// Asset statico servito da public/ — niente trasformazione asset di Vite
// (stesso binding runtime usato in GuestLayout.vue).
const logo = '/images/faper3-logo.png';

defineProps({
    current: { type: String, required: true },
    user: { type: String, required: true },
});

const sidebarOpen = ref(false);

// Mobile: a menu aperto blocca lo scroll della pagina dietro (classe su <html>,
// vedi app.css). Su desktop il menu non si apre mai, quindi resta inerte.
watch(sidebarOpen, (open) => document.documentElement.classList.toggle('sb-lock', open));
onUnmounted(() => document.documentElement.classList.remove('sb-lock'));

// "Filtra per utente": condiviso globalmente (HandleInertiaRequests), quindi
// disponibile identico su ogni pagina che usa questo layout, non solo Dashboard.
const isAdmin = computed(() => usePage().props.isAdmin);
const canFilter = computed(() => usePage().props.isAdmin || usePage().props.isManager);
const filterableUsers = computed(() => usePage().props.filterableUsers ?? []);
const activeUserId = computed(() => usePage().props.activeUserId ?? null);
const activeUser = computed(() => usePage().props.activeUser ?? null);
// Consumo AI del mese (HandleInertiaRequests::usage): null = barra nascosta.
const usage = computed(() => usePage().props.usage ?? null);
const appVersion = computed(() => usePage().props.app?.version);
const { setScope } = useUserScope();

// Mobile: trascina giù dall'alto per ricaricare i dati della pagina.
const { pull, refreshing, threshold: ptrThreshold } = usePullToRefresh();

// Toast globale alimentato dal flash di sessione (es. dopo un redirect post-save).
const toast = ref(null);
let toastTimer = null;

watch(
    () => usePage().props.flash?.toast,
    (msg) => {
        if (!msg) return;
        toast.value = msg;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { toast.value = null; }, 4000);
    },
    { immediate: true },
);
</script>

<template>
    <div class="app has-sidebar">
        <Sidebar :current="current" :user="user" :is-open="sidebarOpen" @close="sidebarOpen = false">
            <template v-if="$slots['sidebar-footer-top']" #footer-top>
                <slot name="sidebar-footer-top" />
            </template>
            <template v-if="canFilter" #user-filter>
                <ScopeSelector
                    :users="filterableUsers"
                    :model-value="activeUserId"
                    variant="sidebar"
                    :general-sub="isAdmin ? 'Tutti gli utenti' : 'I tuoi utenti'"
                    @update:model-value="setScope"
                />
            </template>
        </Sidebar>

        <div class="main-col">
            <header class="topbar">
                <Link class="tb-logo-link" :href="route('dashboard')" aria-label="FaPer3 — home">
                    <img class="tb-logo" :src="logo" alt="FaPer3" />
                </Link>

                <GlobalSearch />

                <div v-if="usage" class="tb-credits-inline">
                    <div class="tci-row">
                        <span class="tci-label">Crediti utilizzati</span>
                        <span class="tci-pct">{{ usage.percent }}%</span>
                    </div>
                    <div class="tci-bar"><div class="tci-fill" :class="{ 'is-full': usage.percent >= 90 }" :style="{ width: usage.percent + '%' }"></div></div>
                </div>

                <div class="topbar-right">
                    <span class="tb-icon tb-chat" title="Messaggi"><Icon name="chat" :size="20" /></span>
                    <NotificationBell />
                </div>

                <button class="hamburger" :aria-label="sidebarOpen ? 'Chiudi menu' : 'Apri menu'" @click="sidebarOpen = !sidebarOpen">
                    <Icon :name="sidebarOpen ? 'x' : 'menu'" :size="20" />
                </button>
            </header>

            <ScopeContextBar v-if="activeUser" :user="activeUser" @clear="setScope(null)" />

            <div v-if="$slots['page-header']" class="page-header-slot">
                <slot name="page-header" />
            </div>

            <main>
                <div class="container">
                    <slot />
                </div>
                <footer v-if="appVersion" class="app-foot">FaPer3 - v.{{ appVersion }}</footer>
            </main>
        </div>

        <div v-show="pull > 0 || refreshing" class="ptr" :class="{ 'ptr--settle': !pull || refreshing }"
             :style="{ '--ptr-y': pull + 'px', opacity: refreshing ? 1 : Math.min(1, pull / ptrThreshold) }"
             role="status" :aria-label="refreshing ? 'Aggiornamento in corso' : undefined">
            <span class="ptr-icon" :class="{ 'is-spinning': refreshing, 'is-ready': pull >= ptrThreshold }"
                  :style="refreshing ? null : { transform: `rotate(${pull * 3}deg)` }">
                <Icon name="refresh" :size="20" />
            </span>
        </div>

        <MobileTabBar :current="current" @navigate="sidebarOpen = false" />

        <Teleport to="body">
            <div v-if="toast" class="acc-toast">
                <Icon name="check" :size="17" />{{ toast }}
            </div>
        </Teleport>
    </div>
</template>
