<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, useForm} from '@inertiajs/vue3';
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import SettingsForm from "@/Pages/Settings/SettingsForm.vue";
import {onMounted, ref, watch} from "vue";

const props = defineProps({
    data: Object,
    success: Object
})

const dataForm = Object.fromEntries(Object.entries(props.data).map((v) => {
    return props.data ? v : '';
}));

const form = useForm(dataForm);


// Crea una variabile reattiva per il messaggio
const successMessage = ref(props.success);

// Aggiorna successMessage quando success cambia
watch(() => props.success, (newMsg) => {
    if (newMsg) {
        successMessage.value = newMsg.msg;
        setTimeout(() => {
            successMessage.value = ''; // Nascondi il messaggio dopo 3 secondi
        }, 3000); // 3 secondi
    }
});

</script>

<template>
    <Head title="Impostazioni" />

    <AuthenticatedLayout>
        <template #header>

            <ApplicationHeader :breadcrumb-array="['Impostazioni']" />

        </template>

        <ApplicationContainer>

            <!-- Mostra il messaggio di successo se presente -->
            <div v-if="successMessage" class="alert alert-success">
                {{ successMessage }}
            </div>

            <!-- <div v-if="successMessage"
                 class="toast show fixed top-4 sm:top-20"
                 role="alert"
                 aria-live="assertive"
                 aria-atomic="true">
                <div class="toast-header">
                    <strong class="me-auto">Impostazioni</strong>
                    <small class="text-body-secondary"></small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    {{ successMessage }}
                </div>
            </div> -->

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
