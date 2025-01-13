<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";

const props = defineProps({
    data: Object,
    filters: Object,
});

</script>

<template>

    <Head title="Dashboard" />

    <AuthenticatedLayout>

        <template #header>

            <ApplicationHeader :breadcrumb-array="['Dashboard']" />

        </template>

        <ApplicationContainer>

            <div class="row text-center">
                <div v-if="data.users !== false" class="col-lg mb-6 lg:mb-0">

                    <div class="card">
                        <div class="card-header">

                            Account
                            <small>( non manager )</small>

                        </div>
                        <div class="card-body">

                            {{ data.users.length }}

                        </div>
                    </div>

                </div>
                <div class="col-lg mb-6 lg:mb-0">

                    <div class="card">
                        <div class="card-header">

                            Posts

                        </div>
                        <div class="card-body">

                            {{ data.posts.length }}

                        </div>
                    </div>

                </div>
                <div class="col-lg">

                    <div class="card">
                        <div class="card-header">

                            Commenti

                        </div>
                        <div class="card-body">

                            {{ data.comments.length }}

                        </div>
                    </div>

                </div>
            </div>

        </ApplicationContainer>

    </AuthenticatedLayout>
</template>
