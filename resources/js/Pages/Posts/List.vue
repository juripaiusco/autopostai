<script setup>

import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import ApplicationContainer from "@/Components/ApplicationContainer.vue";

const props = defineProps({
    data: Object,
    filters: Object,
});

</script>

<template>

    <Head title="Posts" ></Head>

    <AuthenticatedLayout>

        <template #header>

            <ApplicationHeader :breadcrumb-array="['Posts', 'Lista']" />

        </template>

        <ApplicationContainer>

            Lista

        </ApplicationContainer>

    </AuthenticatedLayout>

</template>

<style scoped>

</style>
