<script setup>

import {Head, Link} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import {useForm} from "@inertiajs/vue3";
import {__date} from "@/ComponentsExt/Date.js";
import {ref} from "vue";
import {__} from "../../ComponentsExt/Translations.js";
import axios from "axios";

const props = defineProps({
    data: Object,
    filters: Object,
    token: Object,
})

const dataForm = Object.fromEntries(Object.entries(props.data).map((v) => {
    return props.data ? v : '';
}));

const form = useForm(dataForm);

form.ai_prompt_img = '';

// Se nuovo post la data viene impostata come attuale
if (form.published_at === '') {
    form.published_at = __date(new Date(), 'date') + ' ' + __date(new Date(), 'hour');
}

/**
 * Imposto i canali che l'utente può utilizzare, di default i valori
 * sono null, ma quando seleziono l'utente questa variabile
 * cambia e ridefinisce i canali che si possono usare.
 */
let channel_user_can_set = ref([]);

if (form.id || (form.user && form.user.id)) {

    let channel_user = JSON.parse(form.user.channels);

    for (let index in channel_user) {
        channel_user_can_set.value[index] = channel_user[index]['on'];
    }

} else {

    for (let index in props.data.channels) {
        channel_user_can_set.value[index] = props.data.channels[index]['on'];
    }
}

/**
 * In base all'utente selezionato, abilito o meno i canali in cui il post
 * può essere pubblicato.
 */
function checkChannelsByUser() {

    const user = props.data.users.find(u => u.id === form.user_id);
    const user_channel = JSON.parse(user.channels);

    for (let index in user_channel) {
        channel_user_can_set.value[index] = user_channel[index].on;
        form.channels[index] = user_channel[index];
        form.channels[index]['on'] = '0';
    }
}

const ai_prompt_img_loading = ref(false);
const ai_prompt_img_path = ref(null);
const token = props.token.plainTextToken;
let images_array = ref(props.data.files);

const startJob = async () => {
    ai_prompt_img_loading.value = true;
    ai_prompt_img_path.value = null;
    const app_url = import.meta.env.VITE_APP_URL;

    try {
        // Avvia il job tramite API
        const { data } = await axios.post(
            app_url + "/index.php/api/start-job",
            { prompt: form.ai_prompt_img },
            {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            }
        );
        const jobId = data.job_id;

        // Polling per verificare lo stato del job
        let status = "pending";
        while (status === "pending" || status === "running") {
            const statusResponse = await axios.get(
                app_url + `/index.php/api/check-job-status/${jobId}`,
                {
                    headers: {
                        Authorization: `Bearer ${token}`
                    }
                }
            );
            status = statusResponse.data.status;

            if (status === "completed") {
                ai_prompt_img_path.value = statusResponse.data.image_path;
                images_array.value.unshift(ai_prompt_img_path.value);
                form.img_selected = ai_prompt_img_path.value;
                break;
            }

            if (status === "failed") {
                alert("Errore nella generazione dell’immagine.");
                break;
            }

            await new Promise((resolve) => setTimeout(resolve, 5000)); // Aspetta 5 secondi
        }
    } catch (error) {
        console.error(error);
        alert("Si è verificato un errore.");
    } finally {
        ai_prompt_img_loading.value = false;
    }
};

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

                        <div v-if="!$page.props.auth.user.parent_id || $page.props.auth.user.child_on">

                            <label class="form-label">
                                Account al quale è collegato il post
                                <br>
                                <small>
                                    <span v-if="form.id">
                                        Il post verrà pubblicato dall'account {{ form.user.email }}
                                    </span>
                                    <span v-else>
                                        Imposta quale account deve pubblicare questo post
                                    </span>
                                </small>
                            </label>
                            <select v-if="data.users"
                                    class="form-select"
                                    aria-label="Default select example"
                                    @change="checkChannelsByUser"
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

                        </div>

                        <div class="row">
                            <div class="col-lg-7 mb-6 lg:mb-0">

                                <label class="form-label">
                                    Titolo
                                    <br>
                                    <small>Solo ad uso interno, non viene pubblicato</small>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       :class="{'!border !border-red-500' : form.errors.title}"
                                       v-model="form.title" />
                                <div class="text-red-500 text-center text-xs"
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
                            <small>L'AI interpreta il testo e genera un contenuto in base alle tue indicazioni</small>
                        </label>
                        <textarea class="form-control h-[216px]"
                                  :class="{'!border !border-red-500' : form.errors.ai_prompt_post}"
                                  v-model="form.ai_prompt_post"></textarea>
                        <div class="text-red-500 text-center text-xs"
                             v-if="form.errors.ai_prompt_post">{{ __(form.errors.ai_prompt_post) }}</div>

                        <br>

                        <label>
                            <span class="text-gray-500 text-[0.9em]">
                                Scegli in quale canale pubblicare il tuo post
                            </span>
                        </label>

                        <div class="row !mt-2">
                            <div v-for="(channel, index) in data.channels"
                                 :key="index"
                                 class="col-lg">

                                <div class="form-check form-switch !mb-3">

                                    <input :disabled="channel_user_can_set[index] === '0' || channel_user_can_set[index] === null"
                                           class="form-check-input"
                                           type="checkbox"
                                           :id="index"
                                           true-value="1"
                                           false-value="0"
                                           v-model="form.channels[index]['on']"
                                           checked />

                                    <label class="form-check-label"
                                           :for="index">
                                        <span class="text-gray-500 text-[0.9em]">{{ channel.name }}</span>
                                    </label>

                                </div>

                            </div>
                        </div>

                    </div>
                    <div class="col-lg">

                        <nav class="mt-2">
                            <div class="nav nav-underline nav-fill" id="nav-tab" role="tablist">

                                <button class="nav-link active"
                                        id="nav-upload-img-tab"
                                        data-bs-toggle="tab"
                                        data-bs-target="#nav-upload-img"
                                        type="button"
                                        role="tab"
                                        aria-controls="nav-upload-img"
                                        aria-selected="true">Immagine</button>

                                <button class="nav-link"
                                        id="nav-create-img-tab"
                                        data-bs-toggle="tab"
                                        data-bs-target="#nav-create-img"
                                        type="button"
                                        role="tab"
                                        aria-controls="nav-create-img"
                                        aria-selected="true">Genera</button>

                                <button class="nav-link"
                                        id="nav-select-img-tab"
                                        data-bs-toggle="tab"
                                        data-bs-target="#nav-select-img"
                                        type="button"
                                        role="tab"
                                        aria-controls="nav-select-img"
                                        aria-selected="true">Scegli</button>

                            </div>
                        </nav>

                        <div class="tab-content" id="nav-tabContent">

                            <div class="tab-pane fade show active pt-4"
                                 id="nav-upload-img"
                                 role="tabpanel"
                                 aria-labelledby="nav-upload-img-tab"
                                 tabindex="0">

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
                                     dark:border-gray-500
                                     text-gray-300
                                     dark:text-gray-500
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

                            </div>

                            <div class="tab-pane fade show pt-4"
                                 id="nav-create-img"
                                 role="tabpanel"
                                 aria-labelledby="nav-create-img-tab"
                                 tabindex="0">

                                <div v-if="ai_prompt_img_path"
                                     class="
                                     cursor-pointer
                                     hover:opacity-60">
                                    <img v-if="ai_prompt_img_path"
                                         @click="ai_prompt_img_path = ''"
                                         :src="ai_prompt_img_path"
                                         :alt="ai_prompt_img_path"
                                         class="rounded" >
                                </div>
                                <div v-if="!ai_prompt_img_path">

                                    <label v-if="!ai_prompt_img_loading"
                                           class="form-label">
                                        Prompt immagine
                                        <br>
                                        <small>L'AI interpreta il testo e genera un'immagine in base alle tue indicazioni</small>
                                    </label>
                                    <label v-if="ai_prompt_img_loading"
                                           class="form-label">
                                        Generazione immagine
                                        <br>
                                        <small>L'AI sta generando l'immagine per te.</small>
                                    </label>
                                    <div class="textarea-wrapper">
                                        <div v-if="ai_prompt_img_loading"
                                             class="loader-overlay">
                                            <div class="loader"></div>
                                        </div>
                                        <textarea class="form-control h-[216px]"
                                                  :disabled="ai_prompt_img_loading"
                                                  :class="{
                                                  '!border !border-red-500' : form.errors.ai_prompt_img,
                                                  '!text-gray-500' : ai_prompt_img_loading
                                              }"
                                                  v-model="form.ai_prompt_img"></textarea>
                                        <div class="text-red-500 text-center text-xs"
                                             v-if="form.errors.ai_prompt_img">{{ __(form.errors.ai_prompt_img) }}</div>
                                    </div>

                                    <br><br>

                                    <div class="text-center">
                                        <button @click="startJob"
                                                :disabled="ai_prompt_img_loading"
                                                type="button"
                                                class="btn btn-primary">Genera immagine</button>
                                    </div>

                                </div>

                            </div>

                            <div class="tab-pane fade show pt-4"
                                 id="nav-select-img"
                                 role="tabpanel"
                                 aria-labelledby="nav-select-img-tab"
                                 tabindex="0">

                                <div class="row">
                                    <div v-for="(file, index) in images_array"
                                         :key="index"
                                         class="col-3 mb-4">

                                        <img class="rounded cursor-pointer image_ai_generated"
                                             :class="{ image_ai_generated_selected: selectedImage === index }"
                                             :src="file"
                                             :alt="file"
                                             @click="form.img_selected = file; selectImage(index);">

                                    </div>
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
.image_ai_generated {
    transition: all .2s;
}
.image_ai_generated_selected {
    border: 4px solid #38bdf8;
    transform: scale(1.1);
}

.loader {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    max-width: 6rem;
    margin-top: 3rem;
    margin-bottom: 3rem;
}
.loader:before,
.loader:after {
    content: "";
    position: absolute;
    border-radius: 50%;
    animation: pulsOut 1.8s ease-in-out infinite;
    filter: drop-shadow(0 0 1rem rgba(255, 255, 255, 0.75));
}
.loader:before {
    width: 100%;
    padding-bottom: 100%;
    box-shadow: inset 0 0 0 1rem #fff;
    animation-name: pulsIn;
}
.loader:after {
    width: calc(100% - 2rem);
    padding-bottom: calc(100% - 2rem);
    box-shadow: 0 0 0 0 #fff;
}

@keyframes pulsIn {
    0% {
        box-shadow: inset 0 0 0 1rem #fff;
        opacity: 1;
    }
    50%, 100% {
        box-shadow: inset 0 0 0 0 #fff;
        opacity: 0;
    }
}

@keyframes pulsOut {
    0%, 50% {
        box-shadow: 0 0 0 0 #fff;
        opacity: 0;
    }
    100% {
        box-shadow: 0 0 0 1rem #fff;
        opacity: 1;
    }
}


.textarea-wrapper {
    position: relative;
    display: inline-block;
    width: 100%; /* Adatta la larghezza secondo necessità */
}
/*.loader {
    width: 30px;
    height: 30px;
}*/
.loader-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10;
    pointer-events: none; /* Non blocca l'interazione con il textarea */
}
</style>

<script>
export default {
    data: function () {
        return {
            previewUrl: null, // URL per l'anteprima dell'immagine
            selectedImage: null
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
        selectImage(index) {
            this.selectedImage = index; // Imposta l'immagine selezionata
        },
    },
};
</script>
