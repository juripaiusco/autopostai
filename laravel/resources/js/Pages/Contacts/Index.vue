<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';

const userName = computed(() => usePage().props.auth?.user?.name ?? 'Mario Rossi');
</script>

<template>
    <Head title="Contatti" />

    <AppLayout :current="'contacts'" :user="userName">
        <template #page-header>
            <PageHeader :crumbs="[{ label: 'Contatti', current: true }]" />
        </template>

        <div class="card" style="padding: 24px">
            <p style="color: var(--g500); margin: 0">
                Gestione contatti in arrivo.
            </p>
        </div>
    </AppLayout>
</template>
