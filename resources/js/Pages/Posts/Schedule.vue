<script setup>

import {Head, Link, useForm} from "@inertiajs/vue3";
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import SectionPost from "@/Pages/Posts/Sections/SectionPost.vue";

const props = defineProps({
    data: Object,
    filters: Object,
    auth: Object
});

const dataForm = Object.fromEntries(Object.entries(props.data).map((v) => {
    return props.data ? v : '';
}));

dataForm['schedule'] = 1;

const form = useForm(dataForm);

</script>

<template>

    <Head title="Clienti" />

    <AuthenticatedLayout>

        <template #header>

            <ApplicationHeader :breadcrumb-array="[
                'Posts',
                data.id ?
                    form.title :
                        form.title.length > 0 ? form.title : 'Calendarizza'
            ]" />

        </template>

        <ApplicationContainer>

            <form @submit.prevent="form.post(route('post.schedule_store'))">

                <input type="hidden" v-model="form.schedule">

                <div class="row">
                    <div class="col-lg">

                        <SectionPost :data="data"
                                     :form="form"
                                     :filters="filters"
                                     :auth="auth" />

                    </div>
                    <div class="col-lg">

                        Scegli le date di pubblicazione

                    </div>
                </div>

                <div class="text-right mt-10 flex flex-wrap justify-center md:justify-end">

                    <div class="w-1/2 text-center md:w-auto">
                        <Link class="btn btn-secondary w-[100%] md:w-[120px]"
                              :href="data.saveRedirect">
                            Annulla
                        </Link>
                    </div>

                    <div class="w-1/2 text-center md:w-auto">
                        <button type="submit"
                                class="btn btn-success ml-2 w-[100%] md:w-[120px]">Salva</button>
                    </div>

                </div>

            </form>

        </ApplicationContainer>

    </AuthenticatedLayout>
</template>

<style scoped>

</style>
