<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const userName = computed(() => usePage().props.auth?.user?.name ?? 'Mario Rossi');
</script>

<template>
    <Head title="Calendarizza" />
    <AppLayout current="schedule" :user="userName">
        <div class="card card-pad" style="max-width: 30rem; margin: 60px auto; text-align: center">
            <h1 style="margin: 0 0 8px; font-size: var(--text-2xl); font-weight: var(--w-bold); color: var(--fg1)">Calendarizza</h1>
            <p style="margin: 0; font-size: var(--text-sm); color: var(--fg2); line-height: 1.5">
                Scaffold placeholder — questa sezione arriverà in uno step successivo.
            </p>
        </div>
    </AppLayout>
</template>
