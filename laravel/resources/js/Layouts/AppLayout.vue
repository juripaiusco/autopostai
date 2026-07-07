<script setup>
import { ref, computed, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Sidebar from '@/Components/Sidebar.vue';
import GlobalSearch from '@/Components/GlobalSearch.vue';
import ScopeSelector from '@/Components/ScopeSelector.vue';
import ScopeContextBar from '@/Components/ScopeContextBar.vue';
import { useUserScope } from '@/Composables/useUserScope';

// Asset statico servito da public/ — niente trasformazione asset di Vite
// (stesso binding runtime usato in GuestLayout.vue).
const logo = '/images/faper3-logo.png';

defineProps({
    current: { type: String, required: true },
    user: { type: String, required: true },
});

const sidebarOpen = ref(false);

// "Filtra per utente": condiviso globalmente (HandleInertiaRequests), quindi
// disponibile identico su ogni pagina che usa questo layout, non solo Dashboard.
const isAdmin = computed(() => usePage().props.isAdmin);
const canFilter = computed(() => usePage().props.isAdmin || usePage().props.isManager);
const filterableUsers = computed(() => usePage().props.filterableUsers ?? []);
const activeUserId = computed(() => usePage().props.activeUserId ?? null);
const activeUser = computed(() => usePage().props.activeUser ?? null);
const { setScope } = useUserScope();

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

                <div class="tb-credits-inline">
                    <div class="tci-row">
                        <span class="tci-label">Crediti utilizzati</span>
                        <span class="tci-pct">62%</span>
                    </div>
                    <div class="tci-bar"><div class="tci-fill" style="width: 62%"></div></div>
                </div>

                <div class="topbar-right">
                    <span class="tb-icon tb-chat" title="Messaggi"><Icon name="chat" :size="20" /></span>
                    <span class="tb-icon alert" title="Novità FaPer3">
                        <Icon name="bell" :size="20" />
                        <i class="tb-dot"></i>
                    </span>
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
            </main>
        </div>

        <Teleport to="body">
            <div v-if="toast" class="acc-toast">
                <Icon name="check" :size="17" />{{ toast }}
            </div>
        </Teleport>
    </div>
</template>
