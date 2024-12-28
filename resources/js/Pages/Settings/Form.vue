<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, useForm} from '@inertiajs/vue3';
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

                <div class="text-right mt-10">

                    <button type="submit"
                            class="btn btn-success ml-2 w-[120px]">Salva</button>

                </div>

            </form>

        </ApplicationContainer>

    </AuthenticatedLayout>
</template>

<style scoped>

</style>
