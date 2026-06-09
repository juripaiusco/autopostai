<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Sidebar from '@/Components/Sidebar.vue';

// Asset statico servito da public/ — niente trasformazione asset di Vite
// (stesso binding runtime usato in GuestLayout.vue).
const logo = '/images/faper3-logo.png';

defineProps({
    current: { type: String, required: true },
    user: { type: String, required: true },
});

const sidebarOpen = ref(false);
const searchQuery = ref('');
</script>

<template>
    <div class="app has-sidebar">
        <Sidebar :current="current" :user="user" :is-open="sidebarOpen" @close="sidebarOpen = false">
            <template v-if="$slots['sidebar-footer-top']" #footer-top>
                <slot name="sidebar-footer-top" />
            </template>
        </Sidebar>

        <div class="main-col">
            <header class="topbar">
                <Link class="tb-logo-link" :href="route('dashboard')" aria-label="FaPer3 — home">
                    <img class="tb-logo" :src="logo" alt="FaPer3" />
                </Link>

                <div class="topbar-search">
                    <Icon name="search" :size="17" />
                    <input v-model="searchQuery" placeholder="Cerca post, canali, impostazioni…" />
                </div>

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

            <main>
                <div class="container">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
