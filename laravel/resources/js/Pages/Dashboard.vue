<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const user = computed(() => usePage().props.auth?.user);

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="dash">
        <div class="dash-card">
            <h1 class="dash-title">Dashboard</h1>
            <p v-if="user" class="dash-sub">Bentornato, <strong>{{ user.name }}</strong>.</p>
            <p class="dash-hint">
                Scaffold placeholder — il design completo della dashboard arriverà in uno step successivo.
            </p>
            <button type="button" class="dash-logout" @click="logout">Esci</button>
        </div>
    </div>
</template>

<style scoped>
.dash {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    background: var(--bg-canvas);
}
.dash-card {
    width: 100%;
    max-width: 30rem;
    background: var(--bg-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    padding: 32px;
    text-align: center;
}
.dash-title {
    font-size: var(--text-2xl);
    font-weight: var(--w-bold);
    color: var(--fg1);
    margin: 0 0 8px;
}
.dash-sub {
    color: var(--fg1);
    margin: 0 0 4px;
}
.dash-hint {
    font-size: var(--text-sm);
    color: var(--fg2);
    margin: 0 0 24px;
    line-height: 1.5;
}
.dash-logout {
    height: 44px;
    padding: 0 22px;
    border: 1px solid transparent;
    border-radius: var(--radius);
    background: var(--ink);
    color: #fff;
    font-weight: var(--w-semibold);
    font-size: var(--text-sm);
    cursor: pointer;
    transition: background .15s ease;
}
.dash-logout:hover {
    background: var(--gray-700);
}
</style>
