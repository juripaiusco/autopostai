<script setup>

import {Head, Link} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import {useForm} from "@inertiajs/vue3";
import {__date} from "@/ComponentsExt/Date.js";

const props = defineProps({
    data: Object,
    filters: Object,
})

const dataForm = Object.fromEntries(Object.entries(props.data).map((v) => {
    return props.data ? v : '';
}));

const form = useForm(dataForm);

if (form.published_at === '') {
    form.published_at = __date(new Date(), 'date') + ' ' + __date(new Date(), 'hour');
}

if (form.img_ai_check_on === '') {
    form.img_ai_check_on = 1;
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
                    <div class="col-lg">

                        <label class="form-label">
                            Account al quale è collegato il post
                            <br>
                            <small>Imposta quale account deve pubblicare questo post</small>
                        </label>
                        <select v-if="data.users"
                                class="form-select"
                                aria-label="Default select example"
                                v-model="form.user_id">
                            <option disabled value="">Seleziona l'account</option>
                            <option v-for="user in data.users" :key="user.id" :value="user.id">
                                {{ user.name }} - {{ user.email }}
                            </option>
                        </select>
                        <div class="text-red-500 text-center"
                             v-if="form.errors.user_id">{{ __(form.errors.user_id) }}</div>

                        <input v-if="data.user"
                               type="text"
                               class="form-control"
                               disabled
                               :value="(form.user.name + ' - ' + form.user.email)" />

                        <br>

                        <div class="row">
                            <div class="col-lg-7">

                                <label class="form-label">
                                    Titolo
                                    <br>
                                    <small>Solo ad uso interno, non viene pubblicato</small>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       v-model="form.title" />
                                <div class="text-red-500 text-center"
                                     v-if="form.errors.title">{{ __(form.errors.title) }}</div>

                            </div>
                            <div class="col">

                                <label class="form-label">
                                    Data e Ora pubblicazione
                                    <br>
                                    <small>Scegli quando pubblicare il post</small>
                                </label>
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
                            <br>
                            <small>L'AI interpresta il testo e genera un contenuto in base alle tue indicazioni</small>
                        </label>
                        <textarea class="form-control h-[216px]"
                                  v-model="form.ai_prompt_post"></textarea>

                        <br>

                        <label>
                            <span class="text-gray-500 text-[0.9em]">
                                Scegli in quale canale pubblicare il tuo post
                            </span>
                        </label>

                        <div class="row !mt-2">
                            <div class="col-lg">

                                <div class="form-check form-switch !mb-3">

                                    <input class="form-check-input"
                                           type="checkbox"
                                           id="meta_facebook"
                                           true-value="1"
                                           false-value="0"
                                           v-model="form.meta_facebook_on"
                                           checked />

                                    <label class="form-check-label"
                                           for="meta_facebook">
                                        <span class="text-gray-500 text-[0.9em]">Facebook</span>
                                    </label>

                                </div>

                            </div>
                            <div class="col-lg">

                                <div class="form-check form-switch !mb-3">

                                    <input class="form-check-input"
                                           type="checkbox"
                                           id="meta_instagram"
                                           true-value="1"
                                           false-value="0"
                                           v-model="form.meta_instagram_on"
                                           checked />

                                    <label class="form-check-label"
                                           for="meta_instagram">
                                        <span class="text-gray-500 text-[0.9em]">Instagram</span>
                                    </label>

                                </div>

                            </div>
                            <div class="col-lg">

                                <div class="form-check form-switch !mb-3">

                                    <input disabled
                                           class="form-check-input"
                                           type="checkbox"
                                           id="wordpress"
                                           true-value="1"
                                           false-value="0"
                                           v-model="form.wordpress_on"
                                           checked />

                                    <label class="form-check-label"
                                           for="wordpress">
                                        <span class="text-gray-500 text-[0.9em]">WordPress</span>
                                    </label>

                                </div>

                            </div>
                            <div class="col-lg">

                                <div class="form-check form-switch !mb-3">

                                    <input disabled
                                           class="form-check-input"
                                           type="checkbox"
                                           id="newsletter"
                                           true-value="1"
                                           false-value="0"
                                           v-model="form.newsletter_on"
                                           checked />

                                    <label class="form-check-label"
                                           for="newsletter">
                                        <span class="text-gray-500 text-[0.9em]">Newsletter</span>
                                    </label>

                                </div>

                            </div>
                        </div>

                    </div>
                    <div class="col-lg">

                        <br><br>

                        <!-- Mostra l'anteprima -->
                        <div v-if="previewUrl"
                             @click="triggerFileInput"
                             class="
                             cursor-pointer
                             hover:opacity-60">
                            <img :src="previewUrl"
                                 alt="Anteprima immagine"
                                 class="rounded"
                            />
                        </div>
                        <!-- Mostra l'immagine caricata se esiste -->
                        <div v-else
                             @click="triggerFileInput"
                             class="
                             cursor-pointer
                             hover:opacity-60">
                            <img v-if="form.img"
                                 :src="form.img"
                                 :alt="form.title"
                                 class="rounded" >
                        </div>

                        <div
                            v-if="!form.img && !previewUrl"
                            @click="triggerFileInput"
                            class="
                             border
                             border-dashed
                             border-4
                             border-gray-200
                             text-gray-300
                             text-8xl
                             p-20
                             text-center
                             rounded
                             cursor-pointer" >

                            <i class="fa-regular fa-image"></i>

                        </div>

                        <div class="input-group"
                             style="display: none;" >
                            <input type="file"
                                   class="form-control"
                                   @input="form.img = $event.target.files[0]"
                                   @change="onFileChange"
                                   ref="fileInput"
                                   id="img">
                            <label class="input-group-text" for="img">Upload Immagine</label>
                        </div>
                        <progress v-if="form.progress" :value="form.progress.percentage" max="100">
                            {{ form.progress.percentage }}%
                        </progress>

                        <br>

                        <div class="form-check form-switch !mb-3">

                            <input class="form-check-input"
                                   type="checkbox"
                                   id="img_ai_check_on"
                                   true-value="1"
                                   false-value="0"
                                   v-model="form.img_ai_check_on"
                                   checked />

                            <label class="form-check-label"
                                   for="img_ai_check_on">
                                <span class="text-gray-500 text-[0.9em]">
                                    Interpretazione dell'immagine da parte dell'AI
                                    <br>
                                    <small>
                                        Se nel testo del prompt chiedi all'AI di interpretare l'immagine questa
                                        spunta dev'essere attiva
                                    </small>
                                </span>
                            </label>

                        </div>

                        <br>

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

<script>
export default {
    data() {
        return {
            previewUrl: null, // URL per l'anteprima dell'immagine
        };
    },
    methods: {
        triggerFileInput() {
            this.$refs.fileInput.click(); // Simula il click sull'input file
        },
        onFileChange(event) {
            const file = event.target.files[0]; // Ottieni il file selezionato
            if (file) {
                // Usa FileReader per generare l'anteprima
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.previewUrl = e.target.result; // Imposta l'URL dell'anteprima
                };
                reader.readAsDataURL(file); // Leggi il file come Data URL
            } else {
                this.previewUrl = null; // Se nessun file selezionato, resetta
            }
        },
    },
};
</script>
