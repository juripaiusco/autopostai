<script setup>

import {__date} from "@/ComponentsExt/Date.js";
import {__} from "@/ComponentsExt/Translations.js";
import SectionComments from "@/Pages/Posts/Sections/SectionComments.vue";
import {ref} from "vue";

const props = defineProps({
    data: Object,
    form: Object,
    filters: Object,
    auth: Object
});

let form = props.form;

/**
 * Imposto i canali che l'utente può utilizzare, di default i valori
 * sono null, ma quando seleziono l'utente questa variabile
 * cambia e ridefinisce i canali che si possono usare.
 */
let channel_user_can_set = ref([]);

if (form.id || (form.user && form.user.id)) {

    let user_channel = JSON.parse(form.user.channels);
    setChannels(user_channel)

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
    setChannels(user_channel)
}

/**
 * Imposta la variabili channels e quali canali possono essere attivati
 * dall'utente.
 * @param user_channel
 */
function setChannels(user_channel) {
    for (let index in user_channel) {
        channel_user_can_set.value[index] = user_channel[index].on;
        form.channels[index] = user_channel[index];

        if (!form.id) {
            form.channels[index]['on'] = '0';
        }
    }
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
               :value="(form.user.name + ' - ' + form.user.email)" />

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
        <small>L'AI genera un contenuto in base alle tue indicazioni</small>
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

</style>
