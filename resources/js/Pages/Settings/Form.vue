<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, useForm} from '@inertiajs/vue3';
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";

const props = defineProps({
    data: Object,
})

const dataForm = Object.fromEntries(Object.entries(props.data).map((v) => {
    return props.data ? v : '';
}));

const form = useForm(dataForm);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>

            <ApplicationHeader :breadcrumb-array="['Impostazioni']" />

        </template>

        <ApplicationContainer>

            <form @submit.prevent="form.post(route(
                form.id ? 'service.update' : 'service.store',
                form.id ? form.id : ''
                ))">

                <h2 class="text-3xl mb-2">AI</h2>

                <label class="form-label">Descrivi chi deve impersonare l'AI</label>
                <textarea class="form-control h-[216px]"
                          v-model="form.ia_thinking"></textarea>


                <br>

                <h2 class="text-3xl mb-2">Meta</h2>

                <label class="form-label">ID della pagina</label>
                <input type="text"
                       class="form-control"
                       placeholder="es. 12345678910"
                       v-model="form.meta_page_id" />
                <div class="text-red-500 text-center"
                     v-if="form.errors.meta_page_id">{{ __(form.errors.meta_page_id) }}</div>

            </form>

        </ApplicationContainer>

    </AuthenticatedLayout>
</template>
