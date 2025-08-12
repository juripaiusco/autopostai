<script setup>

import {Head, Link, useForm} from "@inertiajs/vue3";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import {__} from "@/ComponentsExt/Translations.js";

const props = defineProps({
    data: Object,
    filters: Object,
})

const dataForm = Object.fromEntries(Object.entries(props.data).map((v) => {
    return props.data ? v : '';
}));

const form = useForm(dataForm);

</script>

<template>

    <Head title="Notifiche" />

    <AuthenticatedLayout>

        <template #header>

            <ApplicationHeader :breadcrumb-array="[
                'Notifiche',
                data.id ?
                    form.title :
                        form.title.length > 0 ? form.title : 'Nuova Notifica'
            ]" />

        </template>

        <ApplicationContainer>

            <div v-if="form.errors.message"
                 class="alert alert-danger">

                {{ form.errors.message }}

            </div>

            <form @submit.prevent="form.post(route(
                form.id ? 'notification.update' : 'notification.store',
                form.id ? form.id : ''
                ))">

                <label class="form-label">
                    Titolo
                    <br>
                    <small>Titolo della notifica</small>
                </label>
                <input type="text"
                       class="form-control"
                       :class="{'!border !border-red-500' : form.errors.title}"
                       v-model="form.title" />
                <div class="text-red-500 text-center text-xs"
                     v-if="form.errors.title">{{ __(form.errors.title) }}</div>

                <br>

                <label class="form-label">
                    Body
                    <br>
                    <small>Body della notifica</small>
                </label>
                <input type="text"
                       class="form-control"
                       :class="{'!border !border-red-500' : form.errors.body}"
                       v-model="form.body" />
                <div class="text-red-500 text-center text-xs"
                     v-if="form.errors.body">{{ __(form.errors.body) }}</div>

                <br>

                <label class="form-label">
                    URL
                    <br>
                    <small>URL della notifica, dev'essere legato alla webApp</small>
                </label>
                <input type="text"
                       class="form-control"
                       :class="{'!border !border-red-500' : form.errors.url}"
                       v-model="form.url" />
                <div class="text-red-500 text-center text-xs"
                     v-if="form.errors.url">{{ __(form.errors.url) }}</div>

                <br>

                <label class="form-label">
                    URL Web
                    <br>
                    <small>Questo URL viene mostrato nella lista web delle notifiche</small>
                </label>
                <input type="text"
                       class="form-control"
                       :class="{'!border !border-red-500' : form.errors.url_web}"
                       v-model="form.url_web" />
                <div class="text-red-500 text-center text-xs"
                     v-if="form.errors.url_web">{{ __(form.errors.url_web) }}</div>

                <div class="text-right mt-10 flex flex-wrap justify-center md:justify-end">

                    <div class="w-1/2 text-center md:w-auto">
                        <Link class="btn btn-secondary w-[100%] md:w-[120px]"
                              :href="data.saveRedirect">
                            Annulla
                        </Link>
                    </div>

                    <div class="w-1/2 text-center md:w-auto">
                        <button type="submit"
                                class="btn btn-success ml-2 w-[100%] md:w-[120px]"
                                :disabled="form.processing">Salva</button>
                    </div>

                </div>

            </form>

        </ApplicationContainer>

    </AuthenticatedLayout>

</template>

<style scoped>

</style>
