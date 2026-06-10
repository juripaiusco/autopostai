<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

// Asset statico servito da public/ — niente trasformazione asset di Vite
// (stesso binding runtime usato in GuestLayout.vue).
const logo = '/images/faper3-logo.png';

const props = defineProps({
    current: { type: String, required: true },
    user: { type: String, required: true },
    isOpen: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const acctOpen = ref(false);

const links = [
    { id: 'dashboard', label: 'Dashboard', icon: 'dashboard', route: 'dashboard' },
    { id: 'account', label: 'Account', icon: 'users', route: 'account' },
    { id: 'posts', label: 'Posts', icon: 'chat', route: 'posts' },
    { id: 'schedule', label: 'Calendarizza', icon: 'calendar', route: 'schedule' },
    { id: 'settings', label: 'Impostazioni', icon: 'settings', route: 'settings' },
];

function handleNavigate() {
    emit('close');
    acctOpen.value = false;
}

function logout() {
    handleNavigate();
    router.post(route('logout'));
}
</script>

<template>
    <div class="sidebar-overlay" :class="{ 'sb-open': isOpen }" @click="emit('close')"></div>

    <aside class="sidebar" :class="{ 'sb-open': isOpen }">
        <div class="sb-top">
            <img class="sb-logo" :src="logo" alt="FaPer3" />
            <span style="font-size: 11px; line-height: 1.35; max-width: 120px; margin-left: 20px">
                <span style="color: var(--g400); font-weight: 400">Comunica come un </span>
                <span style="color: var(--sky); font-weight: 700">professionista.</span>
            </span>
        </div>

        <Link class="btn btn-dark sb-new" :href="route('posts.create')" @click="handleNavigate">
            <Icon name="plus" :size="18" />Nuovo post
        </Link>

        <nav class="sb-nav">
            <Link v-for="l in links" :key="l.id" :href="route(l.route)"
                  class="sb-link" :class="{ active: current === l.id }"
                  @click="handleNavigate">
                <Icon :name="l.icon" :size="20" />{{ l.label }}
            </Link>
        </nav>

        <div class="sb-foot">
            <slot name="footer-top" />
            <div class="sb-credits">
                <div class="sb-credits-row"><span>Crediti utilizzati</span><span>62%</span></div>
                <div class="sb-bar"><div class="sb-bar-fill" style="width: 62%"></div></div>
            </div>
            <div style="position: relative">
                <div v-if="acctOpen" class="card sb-acct-menu">
                    <Link :href="route('settings')" class="sb-acct-item" @click="handleNavigate">
                        <Icon name="users" :size="17" />Profilo
                    </Link>
                    <Link :href="route('settings')" class="sb-acct-item" @click="handleNavigate">
                        <Icon name="settings" :size="17" />Impostazioni account
                    </Link>
                    <div class="sb-acct-sep"></div>
                    <button type="button" class="sb-acct-item sb-acct-item--danger" @click="logout">
                        <Icon name="logout" :size="17" />Esci
                    </button>
                </div>
                <button type="button" class="sb-user" :class="{ open: acctOpen }" @click="acctOpen = !acctOpen">
                    <div class="sb-avatar">{{ user.charAt(0) }}</div>
                    <div style="min-width: 0; text-align: left">
                        <div class="sb-user-name">{{ user }}</div>
                        <div class="sb-user-role">Amministratore</div>
                    </div>
                    <span class="sb-user-caret"><Icon name="chevron" :size="16" /></span>
                </button>
            </div>
        </div>
    </aside>
</template>
