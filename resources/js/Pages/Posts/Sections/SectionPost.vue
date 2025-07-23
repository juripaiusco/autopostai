<script setup>

import {__date} from "@/ComponentsExt/Date.js";
import {__} from "@/ComponentsExt/Translations.js";
import SectionComments from "@/Pages/Posts/Sections/SectionComments.vue";
import {ref} from "vue";
import axios from "axios";

const props = defineProps({
    data: Object,
    form: Object,
    filters: Object,
    auth: Object,
    token: String
});

let form = props.form;
let app_url = import.meta.env.VITE_APP_URL;
const token = props.token; // Token dalla prop
const error = ref(null);

/**
 * Imposto i canali che l'utente può utilizzare, di default i valori
 * sono null, ma quando seleziono l'utente questa variabile
 * cambia e ridefinisce i canali che si possono usare.
 */
let channel_user_can_set = ref([]);

if (form.id || (form.user && form.user.id)) {

    let user_channels = JSON.parse(form.user.channels);
    setChannels(user_channels, form.id ? props.data.channels : null)

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
    const user_channels = JSON.parse(user.channels);
    setChannels(user_channels)
}

/**
 * Imposta la variabili channels e quali canali possono essere attivati
 * dall'utente.
 * @param user_channels
 * @param user_channels_value
 */
function setChannels(user_channels, user_channels_value = null) {
    for (let index in user_channels) {
        channel_user_can_set.value[index] = user_channels[index].on;
        form.channels[index] = user_channels[index];

        if (user_channels_value) {
            form.channels[index] = user_channels_value[index];
        }

        if (!form.id) {
            form.channels[index]['on'] = '0';
        }
    }
}

let channelOptionsGETLoad = ref(false);
let channelOptions = ref([]);
function channelOptionsGET(userId, channel, getType) {

    channelOptionsGETLoad.value = true;

    axios
        .get(app_url + '/index.php/api/' + channel + '-' + getType + '/' + userId, {
            headers: {
                Authorization: `Bearer ${token}`,
            },
        })
        .then((response) => {
            console.log(response.data[getType]);

            if (!channelOptions.value[channel]) {
                channelOptions.value[channel] = {};
            }

            channelOptions.value[channel][getType] = response.data[getType];
            form.channels[channel]['options'][getType] = response.data[getType];

            error.value = null; // Reset errore
        })
        .catch((err) => {
            console.error(err);
            error.value = 'Errore nel recupero dei dati';
        })
        .finally(() => {
            channelOptionsGETLoad.value = false;
        });
}

</script>

<template>

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
               :value="(form.user?.name + ' - ' + form.user?.email)" />

        <br>

    </div>

    <div class="row">
        <div class="col-lg"
             :class="{
                'mb-6 lg:mb-0' : form.schedule !== 1
             }">

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
        <div v-if="form.schedule !== 1"
             class="col-lg-5">

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
        <small>
            L'AI genera un contenuto in base alle tue indicazioni. Se premi il pulsante "Anteprima" puoi vedere come
            sarà il testo del post, invece se salvi direttamente senza anteprima, il post sarà creato automaticamente
            dall'AI.
        </small>
    </label>
    <textarea class="form-control h-[216px]"
              :class="{'!border !border-red-500' : form.errors.ai_prompt_post}"
              v-model="form.ai_prompt_post"
              placeholder="Esempio:
Crea un post per Facebook, utilizza massimo 500 caratteri, racconta quanto è bello mangiare la pizza"></textarea>
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
             class="col-6 col-lg-3">

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

    <!-- Channels Options - START -->
    <div class="hidden"
         :class="{'!block' : form.channels['wordpress']['on'] === '1'}"
         id="wordpress-options">

        <div class="card">
            <div class="card-header">

                <div class="flex flex-row items-center">
                    <div class="w-1/2 text-gray-500">
                        <span class="font-bold">WordPress</span> Opzioni
                    </div>
                    <div class="w-1/2 text-right">

                        <button class="btn btn-sm btn-link transition-transform duration-300"
                                :class="{'spin-speed' : channelOptionsGETLoad === true}"
                                type="button"
                                @click="channelOptionsGET(form.user_id, 'wordpress', 'categories')">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                        </button>

                    </div>
                </div>

            </div>
            <div class="card-body">

                <label class="form-label">
                    Categoria
                    <br>
                    <small>Seleziona una o più categorie nella quale vuoi venga pubblicato il post</small>
                </label>

                <div class="row !mt-2">
                    <div v-if="
                                form.channels['wordpress'] &&
                                form.channels['wordpress']['options'] &&
                                form.channels['wordpress']['options']['categories']"
                         v-for="(category, index) in form.channels['wordpress']['options']['categories']"
                         :key="index"
                         class="col-6 col-lg-4">

                        <div class="form-check form-switch !mb-3">

                            <input class="form-check-input"
                                   type="checkbox"
                                   :id="category.id"
                                   true-value="1"
                                   false-value="0"
                                   v-model="form.channels['wordpress']['options']['categories'][index]['on']"
                                   checked />

                            <label class="form-check-label"
                                   :for="category.id">
                                <span class="text-gray-500 text-[0.9em]">{{ category.name }}</span>
                            </label>

                        </div>

                    </div>
                </div>

            </div>
        </div>

    </div>
    <!-- Channels Options - END -->

    <div class="hidden sm:block">
        <br>
        <div class="card !bg-gray-100 dark:!bg-gray-900/40">
            <div class="card-body">
                <SectionComments :data="data" :form="form" />
            </div>
        </div>
    </div>

</template>

<style scoped>
@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Rotazione veloce continua */
.spin-speed {
    animation: spin 0.6s linear infinite;
}
</style>
