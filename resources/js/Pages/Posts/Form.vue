<script setup>

import {Head} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import {useForm} from "@inertiajs/vue3";
import {ref} from "vue";

const props = defineProps({
    data: Object,
    filters: Object,
})

const dataForm = Object.fromEntries(Object.entries(props.data).map((v) => {
    return props.data ? v : '';
}));

const form = useForm(dataForm);

let modalShow = ref(false);
let modalData = ref(props.data);

</script>

<template>

    <Head title="Clienti" />

    <AuthenticatedLayout>

        <template #header>

            <ApplicationHeader :breadcrumb-array="[
                'Post',
                data.id ?
                    form.title :
                        form.title.length > 0 ? form.title : 'Nuovo Post'
            ]" />

        </template>

        <ApplicationContainer>

            <form @submit.prevent="form.post(route(
                form.id ? 'post.update' : 'post.store',
                form.id ? form.id : ''
                ))">

                <label class="form-label">Titolo</label>
                <input type="text"
                       class="form-control"
                       v-model="form.title" />
                <div class="text-red-500 text-center"
                     v-if="form.errors.title">{{ __(form.errors.title) }}</div>

                <br>

                <label class="form-label">
                    Prompt
                </label>
                <textarea class="form-control h-[216px]"
                          v-model="form.prompt"></textarea>

                <div class="text-right mt-10">

                    <!-- <Link class="btn btn-secondary w-[120px]"
                          :href="data.saveRedirect">
                        Annulla
                    </Link> -->

                    <button type="submit"
                            class="btn btn-success ml-2 w-[120px]">Salva</button>

                </div>

            </form>

        </ApplicationContainer>

    </AuthenticatedLayout>

</template>

<style scoped>

</style>
