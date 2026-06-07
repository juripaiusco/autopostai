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

    <main style="min-height: 100vh; padding: 2rem;">
        <h1>Dashboard</h1>
        <p v-if="user">Bentornato, {{ user.name }}.</p>
        <button type="button" @click="logout">Esci</button>
    </main>
</template>
