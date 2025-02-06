<script setup>

import {Head, Link, useForm} from "@inertiajs/vue3";
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import SectionPost from "@/Pages/Posts/Sections/SectionPost.vue";
import {__} from "@/ComponentsExt/Translations.js";
import {__date} from "@/ComponentsExt/Date.js";
import SectionComments from "@/Pages/Posts/Sections/SectionComments.vue";

const props = defineProps({
    data: Object,
    filters: Object,
    auth: Object
});

const dataForm = Object.fromEntries(Object.entries(props.data).map((v) => {
    return props.data ? v : '';
}));

/**
 * Se nuovo post la data viene impostata come attuale
 */
if (dataForm['published_at'] === '') {
    dataForm['published_at'] = [];
    dataForm['published_at'].push(__date(new Date(), 'date') + ' ' + __date(new Date(), 'hour'));
}

const form = useForm(dataForm);

form.schedule = 1;

function addDate() {
    form.published_at.push(form.published_at[form.published_at.length - 1]);
}
function delDate(index) {
    form.published_at.splice(index, 1);
}

</script>

<template>

    <Head title="Calendarizza" />

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

                <div class="row">
                    <div class="col-lg">

                        <SectionPost :data="data"
                                     :form="form"
                                     :filters="filters"
                                     :auth="auth" />

                    </div>
                    <div class="col-lg !mt-6 lg:!mt-0">

                        <label class="form-label">
                            Imposta il calendario editoriale
                            <br>
                            <small>Aggiungi le date di pubblicazione dei post</small>
                        </label>

                        <div v-for="(published_at, index) in form.published_at"
                             :key="index"
                             class="row mb-4">
                            <div class="col">

                                <input type="datetime-local"
                                       class="form-control"
                                       v-model="form.published_at[index]" />

                            </div>
                            <div class="col-2">

                                <button v-if="index < form.published_at.length - 1"
                                        type="button"
                                        class="btn btn-danger w-[100%] h-[100%]"
                                        @click="delDate(index)">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 sm:size-6 m-auto">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>

                                <button v-else type="button"
                                        class="btn btn-primary w-[100%] h-[100%]"
                                        @click="addDate">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 sm:size-6 m-auto">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </button>

                            </div>
                        </div>

                        <div class="alert alert-warning">
                            Verranno creati tanti post quante date vengono impostate, poi però ogni post bisogna
                            modificarlo per renderlo unico e per definire l'immagine del post.
                        </div>

                    </div>
                </div>

                <div class="sm:hidden">
                    <br>
                    <div class="card !bg-gray-100 dark:!bg-gray-900/40">
                        <div class="card-body">
                            <SectionComments :data="data" :form="form" />
                        </div>
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
