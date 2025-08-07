<script setup>

import {Head, Link, usePage} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import {useForm} from "@inertiajs/vue3";
import {ref} from "vue";
import {__} from "../../ComponentsExt/Translations.js";
import axios from "axios";
import ModalReady from "@/Components/ModalReady.vue";
import SectionComments from "@/Pages/Posts/Sections/SectionComments.vue";
import ProgressBar from "@/Components/ProgressBar.vue";
import SectionPost from "@/Pages/Posts/Sections/SectionPost.vue";
import {__date} from "@/ComponentsExt/Date.js";

const props = defineProps({
    data: Object,
    filters: Object,
    token: Object,
    auth: Object
});

let modalShow = ref(false);
let modalData = ref({
    'confirmBtnText': 'Sì',
    'confirmBtnClass': 'btn btn-danger',
    'confirmFNC': true,
});

const dataForm = Object.fromEntries(Object.entries(props.data).map((v) => {
    return props.data ? v : '';
}));

/**
 * Se nuovo post la data viene impostata come attuale
 */
/*if (dataForm['published_at'] === '') {
    dataForm['published_at'] = __date(new Date(), 'date') + ' ' + __date(new Date(), 'hour');
}*/

dataForm['ai_prompt_img'] = '';
dataForm['duplicate'] = false;

const form = useForm(dataForm);

const app_url = import.meta.env.VITE_APP_URL;

// ----------------------------------------------------------

/**
 * Gestione dell'input file per l'immagine
 * @type {Ref<UnwrapRef<*[]>, UnwrapRef<*[]> | *[]>}
 */
const previewUrls = ref([])
const fileInput = ref(null)

function triggerFileInput() {
    fileInput.value.click()
}

function onFileChange(event) {
    const files = Array.from(event.target.files)
    form.img_selected = selectedImage = []
    previewUrls.value = []
    form.img = files

    // Verifico se l'AI deve interpretare l'immagine
    if (files.length > 1) {
        form.img_ai_check_on = 0
    }

    files.forEach(file => {
        const reader = new FileReader()
        reader.onload = (e) => {
            previewUrls.value.push(e.target.result)
        }
        reader.readAsDataURL(file)
    })
}

// ----------------------------------------------------------

/**
 * Genero l'immagine in base al prompt inserito
 * @type {Ref<UnwrapRef<boolean>, UnwrapRef<boolean> | boolean>}
 */
const ai_prompt_img_loading = ref(false);
const ai_prompt_img_path = ref(null);
const token = props.token.plainTextToken;
let images_array = ref(props.data.files);
let images_used = ref(props.auth.images_used);

const startJob = async () => {
    ai_prompt_img_loading.value = true;
    ai_prompt_img_path.value = null;

    try {
        // Avvia il job tramite API
        const { data } = await axios.post(
            app_url + "/index.php/api/start-job",
            {
                user_id: form.user_id,
                prompt: form.ai_prompt_img,
            },
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
                ai_prompt_img_path.value = statusResponse.data.image_url;
                images_used.value = statusResponse.data.images_used;
                images_array.value.unshift({
                    'image_url': statusResponse.data.image_url,
                    'prompt': statusResponse.data.prompt,
                });
                form.img = form.img_selected = ai_prompt_img_path.value;
                selectedImage = 0;
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

function modalDeleteImg(img, index) {
    modalData.value.route = route('post.destroy_image', [img.image_url.split('/').pop()]);
    modalData.value.img = img.image_url;
    modalData.value.index = index;
    document.activeElement.blur();
}

/**
 * Seleziona l'immagine
 * @type {[null] extends [Ref] ? IfAny<null, Ref<null>, null> : Ref<UnwrapRef<null>, UnwrapRef<null> | null>}
 */
let selectedImage = ref(null);

function selectImage(index) {
    selectedImage = index; // Imposta l'immagine selezionata
}

let edit_ai_content = ref(false);
let ai_content_preview = ref(false);
const ai_prompt_post_loading = ref(false);

function previewAIContent() {
    ai_content_preview.value = true;
    ai_prompt_post_loading.value = true;

    form.ai_content = '';
    form.post(route('post.preview'), {
        preserveScroll: true,
        preserveUrl: true,
        onSuccess: () => {
            ai_prompt_post_loading.value = false;
            form.ai_content = usePage().props.data.ai_content;
            form.user = usePage().props.data.user;
            form.id = usePage().props.data.id;
        },
    })
}

</script>

<template>

    <Head title="Clienti" />

    <AuthenticatedLayout>

        <template #header>

            <ApplicationHeader :breadcrumb-array="[
                'Posts',
                data.id ?
                    form.title :
                        form.title.length > 0 ? form.title : 'Nuovo Post'
            ]" />

        </template>

        <ApplicationContainer>

            <form @submit.prevent="form.post(route(
                form.id ? 'post.update' : 'post.store',
                form.id ? form.id : ''
                ))"
                  ref="postMainForm">

                <div class="row">
                    <div class="col-lg">

                        <SectionPost :data="data"
                                     :form="form"
                                     :filters="filters"
                                     :token="token"
                                     :auth="auth" />

                    </div>
                    <div class="col-lg">

                        <nav class="mt-2">
                            <div class="nav nav-underline nav-justified"
                                 id="nav-tab"
                                 role="tablist">

                                <button class="nav-link active"
                                        id="nav-upload-img-tab"
                                        data-bs-toggle="tab"
                                        data-bs-target="#nav-upload-img"
                                        type="button"
                                        role="tab"
                                        aria-controls="nav-upload-img"
                                        aria-selected="true">Immagine</button>

                                <button v-if="
                                        (!$page.props.auth.user.parent_id && !$page.props.auth.user.child_on) ||
                                        $page.props.auth.user.image_model_limit > 0
                                        "
                                        class="nav-link"
                                        id="nav-create-img-tab"
                                        data-bs-toggle="tab"
                                        data-bs-target="#nav-create-img"
                                        type="button"
                                        role="tab"
                                        aria-controls="nav-create-img"
                                        aria-selected="true">Genera</button>

                                <button v-if="
                                        (!$page.props.auth.user.parent_id && !$page.props.auth.user.child_on) ||
                                        $page.props.auth.user.image_model_limit > 0
                                        "
                                        class="nav-link"
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

                                <div v-if="previewUrls.length || (form.img && typeof form.img[0] === 'string')"
                                     @click="triggerFileInput"
                                     class="flex flex-col gap-4 cursor-pointer hover:opacity-60">

                                    <!-- Prima immagine -->
                                    <img
                                        :src="(previewUrls.length ? previewUrls : form.img)[0]"
                                        :alt="form.title + ' 1'"
                                        class="rounded w-full object-cover"
                                    />

                                    <!-- Le altre immagini in griglia -->
                                    <div class="grid grid-cols-3 md:grid-cols-4 gap-4">
                                        <img
                                            v-for="(url, index) in (previewUrls.length ? previewUrls.slice(1) : form.img.slice(1))"
                                            :key="'preview-' + (index + 1)"
                                            :src="url"
                                            :alt="form.title + ' ' + (index + 2)"
                                            class="rounded w-full object-cover aspect-square"
                                        />
                                    </div>

                                </div>

                                <!-- Mostra un'immagine placeholder -->
                                <div
                                    v-if="!form.img && !previewUrls.length"
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
                                           multiple
                                           accept="image/*"
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
                                           :disabled="form.img && form.img.length > 1"
                                           v-model="form.img_ai_check_on"
                                           checked />

                                    <label class="form-check-label"
                                           for="img_ai_check_on">
                                <span class="text-gray-500 text-[0.9em]">
                                    Interpretazione dell'immagine da parte dell'AI
                                    <br>
                                    <small>
                                        Se nel testo del prompt chiedi all'AI di interpretare l'immagine questa
                                        spunta dev'essere attiva.
                                        <br>
                                        FUNZIONA SOLO CON UNA IMMAGINE.
                                    </small>
                                </span>
                                    </label>

                                </div>

                            </div>

                            <div v-if="
                                    (!$page.props.auth.user.parent_id && !$page.props.auth.user.child_on) ||
                                    $page.props.auth.user.image_model_limit > 0
                                 "
                                 class="tab-pane fade show pt-4"
                                 id="nav-create-img"
                                 role="tabpanel"
                                 aria-labelledby="nav-create-img-tab"
                                 tabindex="0">

                                <div>

                                    <label v-if="!ai_prompt_img_loading"
                                           class="form-label">
                                        Prompt immagine
                                        <br>
                                        <small>L'AI genera un'immagine in base alle tue indicazioni.</small>
                                    </label>
                                    <label v-if="ai_prompt_img_loading"
                                           class="form-label">
                                        Generazione immagine
                                        <br>
                                        <small>L'AI sta generando l'immagine per te.</small>
                                    </label>

                                    <div v-if="$page.props.auth.user.image_model_limit > 0"
                                         class="mt-[7.5px] mb-4">
                                        <label class="form-label">
                                            <small>
                                                Puoi generare ancora
                                                {{ $page.props.auth.user.image_model_limit - images_used }}
                                                immagini

                                                <ProgressBar
                                                    :classNameBarContainer="'!h-[2px]'"
                                                    :percent="images_used / $page.props.auth.user.image_model_limit * 100" />
                                            </small>
                                        </label>
                                    </div>

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

                                    <div v-if="!ai_prompt_img_path"
                                         class="textarea-wrapper">
                                        <div v-if="ai_prompt_img_loading"
                                             class="loader-overlay">
                                            <div class="loader"></div>
                                        </div>
                                        <textarea class="form-control h-[216px]"
                                                  :disabled="
                                                  ai_prompt_img_loading ||
                                                  (images_used >= $page.props.auth.user.image_model_limit &&
                                                  $page.props.auth.user.parent_id)
                                                  "
                                                  :class="{
                                                  '!border !border-red-500' : form.errors.ai_prompt_img,
                                                  '!text-gray-500' : ai_prompt_img_loading  ||
                                                  images_used >= $page.props.auth.user.image_model_limit
                                              }"
                                                  v-model="form.ai_prompt_img"></textarea>
                                        <div class="text-red-500 text-center text-xs"
                                             v-if="form.errors.ai_prompt_img">{{ __(form.errors.ai_prompt_img) }}</div>

                                        <br>

                                        <div class="text-center">
                                            <button @click="startJob"
                                                    :disabled="
                                                    ai_prompt_img_loading ||
                                                    (images_used >= $page.props.auth.user.image_model_limit &&
                                                    $page.props.auth.user.parent_id) ||
                                                    !form.user_id
                                                    "
                                                    type="button"
                                                    class="btn btn-primary">Genera immagine</button>
                                        </div>
                                    </div>

                                </div>

                            </div>

                            <div class="tab-pane fade show pt-4"
                                 id="nav-select-img"
                                 role="tabpanel"
                                 aria-labelledby="nav-select-img-tab"
                                 tabindex="0">

                                <div class="row sm:py-4 sm:max-h-[854px] sm:overflow-y-auto">
                                    <div v-for="(file, index) in images_array"
                                         :key="index"
                                         class="col-4 col-lg-3 mb-4 relative">

                                        <img class="rounded cursor-pointer image_ai_generated brightness-[0.75]"
                                             :class="{
                                                'image_ai_generated_selected': selectedImage === index,
                                                '!brightness-125': selectedImage === index,
                                                'animate-glow': selectedImage === index,
                                                'shadow-md': selectedImage === index,
                                                'shadow-[#38bdf8]/80': selectedImage === index,
                                             }"
                                             :src="file.image_url"
                                             :alt="file.image_url"
                                             @click="
                                             previewUrls = [];
                                             form.img = form.img_selected = [file.image_url];
                                             form.ai_prompt_img = file.prompt;
                                             selectImage(index);">

                                        <div v-if="selectedImage === index"
                                             class="absolute mt-[-35px] right-4 bg-white text-gray-500 py-1 px-2 rounded">
                                            <button type="button"
                                                    @click="modalShow = true; modalDeleteImg(file, index)">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>

                                    </div>
                                </div>

                            </div>

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

                <div class="screen-wrapper"
                     v-if="ai_prompt_post_loading === true">
                    <div class="loader-overlay">
                        <div class="loader"></div>
                    </div>
                </div>

                <div class="hidden"
                     :class="{'!block': form.ai_content || ai_content_preview}">

                    <br>

                    <label class="form-label">
                        Anteprima del post
                        <br>
                        <small>
                            Questo è il post che sarà pubblicato, se vuoi lo puoi modificare.
                        </small>
                    </label>
                    <div v-if="edit_ai_content === false"
                         class="card whitespace-pre-line">
                        <div class="card-body">
                            <span v-if="ai_prompt_post_loading === true"
                                  class="text-sm">
                                Sto generando il contenuto...
                            </span>
                            {{ form.ai_content }}
                            <br>
                            <button class="btn btn-sm btn-primary mt-2 w-1/2"
                                    @click="edit_ai_content = true">
                                Modifica
                            </button>

                            <br>

                            <small class="text-[11px] text-gray-500">
                                <span v-if="data.tokens">
                                    {{ usePage().props.auth.user.parent_id ?
                                        data.tokens.reduce((sum, token) => sum + token.tokens_used, 0) / 1000 :
                                        data.tokens.reduce((sum, token) => sum + token.tokens_used, 0) }}
                                    {{ usePage().props.auth.user.parent_id ? 'crediti' : ' token' }}
                                </span>
                                <!-- <span v-else
                                      class="text-red-500">
                                    Error: token non disponibile
                                </span> -->
                            </small>
                        </div>
                    </div>
                    <div v-if="edit_ai_content === true">
                        <textarea class="form-control h-[216px]"
                                  :class="{'!border !border-red-500' : form.errors.ai_content}"
                                  v-model="form.ai_content"></textarea>
                        <div class="text-red-500 text-center text-xs"
                             v-if="form.errors.ai_content">{{ __(form.errors.ai_content) }}</div>
                        <button class="btn btn-sm btn-success mt-2 w-1/2"
                                @click="edit_ai_content = false">
                            Salva
                        </button>
                    </div>

                </div>

                <div class="text-right mt-10 mb-4 sm:mb-3 flex flex-wrap justify-center md:justify-end">

                    <div class="text-center w-[100%] md:w-auto">
                        <button :disabled="!form.id && !form.user_id && !data.user?.id"
                                type="button"
                                class="btn btn-primary !text-2xl sm:!text-lg w-[100%] md:w-[248px] !flex items-center justify-center gap-2"
                                @click="previewAIContent">
                            <span class="inline-flex">
                                Anteprima AI
                            </span>
                            <span class="inline-flex shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                                </svg>
                            </span>
                        </button>
                    </div>

                </div>

                <div class="text-right mb-6 sm:mb-3 flex flex-wrap justify-center md:justify-end">

                    <div class="text-center w-[100%] md:w-auto">
                        <button :disabled="!form.id && !form.user_id && !data.user?.id"
                                type="button"
                                class="btn btn-light !text-2xl sm:!text-lg w-[100%] md:w-[248px] !flex items-center justify-center gap-2"
                                @click="form.duplicate = true; $refs.postMainForm.requestSubmit();">
                            <span class="inline-flex">
                                Salva e Aggiungi
                            </span>
                        </button>
                    </div>

                </div>

                <div class="text-right flex flex-wrap justify-center md:justify-end">

                    <div class="w-1/2 text-center md:w-auto pr-2">
                        <Link class="btn btn-secondary w-[100%] md:w-[120px]"
                              :href="data.saveRedirect">
                            Annulla
                        </Link>
                    </div>

                    <div class="w-1/2 text-center md:w-auto">
                        <button type="submit"
                                class="btn btn-success w-[100%] md:w-[120px]">Salva</button>
                    </div>

                </div>

            </form>

            <ModalReady :show="modalShow"
                        :data="modalData"
                        @close="modalShow = false"
                        @fncConfirm="(data_img) => {
                            images_array.splice(data_img.index, 1);
                            modalShow = false;
                            form.ai_prompt_img = ai_prompt_img_path = form.img = form.img_selected = selectedImage = null;
                            axios.get(data_img.route)
                                .then(response => {
                                    console.log(response);
                                })
                                .catch(error => {
                                    console.log(error);
                                });
                        }">

                <template #title>Elimina immagine</template>
                <template #body>
                    Vuoi eliminare questa immagine?

                    <img class="mt-6 sm:w-1/2 mx-auto rounded"
                         :src="modalData.img"
                         :alt="modalData.img">
                </template>

            </ModalReady>

        </ApplicationContainer>

    </AuthenticatedLayout>

</template>

<style scoped>
.image_ai_generated {
    transition: all .2s;
}
.image_ai_generated_selected {
    /*border: 4px solid #38bdf8;*/
    transform: scale(1.1);
}

.loader {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    max-width: 6rem;
    margin-top: -1rem;
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
.screen-wrapper {
    position: fixed;
    display: block;
    top: 0;
    left: 0;
    width: 100%; /* Adatta la larghezza secondo necessità */
    height: 100%;
    background-color: rgba(0, 0, 0, 0.3);
    z-index: 100;
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
