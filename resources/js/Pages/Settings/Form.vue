<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, Link, useForm} from '@inertiajs/vue3';
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import SettingsForm from "@/Pages/Settings/SettingsForm.vue";

const props = defineProps({
    data: Object,
})

const dataForm = Object.fromEntries(Object.entries(props.data).map((v) => {
    return props.data ? v : '';
}));

const form = useForm(dataForm);

</script>

<template>
    <Head title="Impostazioni" />

    <AuthenticatedLayout>
        <template #header>

            <ApplicationHeader :breadcrumb-array="['Impostazioni']" />

        </template>

        <ApplicationContainer>

            <form @submit.prevent="form.post(route(
                form.id ? 'settings.update' : 'settings.store',
                form.id ? form.id : ''
                ))">

                <SettingsForm :form="form" />

                <div class="text-right mt-10 flex flex-wrap justify-center md:justify-end">

                    <div class="w-[100%] text-center md:w-auto">
                        <button type="submit"
                                class="btn btn-success w-[100%] md:w-[120px]">Salva</button>
                    </div>

                </div>

            </form>

        </ApplicationContainer>

    </AuthenticatedLayout>
</template>

<style scoped>

</style>
