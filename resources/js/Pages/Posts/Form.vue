<script setup>

import {Head, Link} from "@inertiajs/vue3";
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

function changeImg() {
    console.log('changeImg');
}

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

                <div class="row">
                    <div class="col">

                        <img :src="form.img"
                             class="rounded" >

                    </div>
                    <div class="col">

                        <div class="row">
                            <div class="col-7">

                                <label class="form-label">Titolo</label>
                                <input type="text"
                                       class="form-control"
                                       v-model="form.title" />
                                <div class="text-red-500 text-center"
                                     v-if="form.errors.title">{{ __(form.errors.title) }}</div>

                            </div>
                            <div class="col">

                                <label class="form-label">Data e Ora pubblicazione</label>
                                <input type="datetime-local"
                                       class="form-control"
                                       v-model="form.published_at" />
                                <div class="text-red-500 text-center"
                                     v-if="form.errors.published_at">{{ __(form.errors.published_at) }}</div>

                            </div>
                        </div>

                        <br>

                        <label class="form-label">
                            Prompt
                        </label>
                        <textarea class="form-control h-[216px]"
                                  v-model="form.prompt"></textarea>

                        <br>

                        <div class="input-group">
                            <input type="file"
                                   class="form-control"
                                   @input="form.img = $event.target.files[0]"
                                   @change="changeImg()"
                                   id="img">
                            <label class="input-group-text" for="img">Upload Immagine</label>
                        </div>
                        <progress v-if="form.progress" :value="form.progress.percentage" max="100">
                            {{ form.progress.percentage }}%
                        </progress>

                        <br>

                        <div class="row">
                            <div class="col">

                                <div class="form-check form-switch !mb-3">

                                    <input class="form-check-input"
                                           type="checkbox"
                                           id="meta_facebook"
                                           true-value="1"
                                           false-value="0"
                                           v-model="form.meta_facebook"
                                           checked />

                                    <label class="form-check-label"
                                           for="meta_facebook">
                                        <span class="text-gray-500 text-[0.9em]">Facebook</span>
                                    </label>

                                </div>

                            </div>
                            <div class="col">

                                <div class="form-check form-switch !mb-3">

                                    <input class="form-check-input"
                                           type="checkbox"
                                           id="meta_instagram"
                                           true-value="1"
                                           false-value="0"
                                           v-model="form.meta_instagram"
                                           checked />

                                    <label class="form-check-label"
                                           for="meta_instagram">
                                        <span class="text-gray-500 text-[0.9em]">Instagram</span>
                                    </label>

                                </div>

                            </div>
                            <div class="col">

                                <div class="form-check form-switch !mb-3">

                                    <input disabled
                                           class="form-check-input"
                                           type="checkbox"
                                           id="wordpress"
                                           true-value="1"
                                           false-value="0"
                                           v-model="form.wordpress"
                                           checked />

                                    <label class="form-check-label"
                                           for="wordpress">
                                        <span class="text-gray-500 text-[0.9em]">WordPress</span>
                                    </label>

                                </div>

                            </div>
                            <div class="col">

                                <div class="form-check form-switch !mb-3">

                                    <input disabled
                                           class="form-check-input"
                                           type="checkbox"
                                           id="newsletter"
                                           true-value="1"
                                           false-value="0"
                                           v-model="form.newsletter"
                                           checked />

                                    <label class="form-check-label"
                                           for="newsletter">
                                        <span class="text-gray-500 text-[0.9em]">Newsletter</span>
                                    </label>

                                </div>

                            </div>
                        </div>

                    </div>
                </div>

                <div class="text-right mt-10">

                    <Link class="btn btn-secondary w-[120px]"
                          :href="data.saveRedirect">
                        Annulla
                    </Link>

                    <button type="submit"
                            class="btn btn-success ml-2 w-[120px]">Salva</button>

                </div>

            </form>

        </ApplicationContainer>

    </AuthenticatedLayout>

</template>

<style scoped>

</style>
