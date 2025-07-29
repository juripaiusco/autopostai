<script setup>

import {computed, ref} from "vue";
import axios from "axios";

const props = defineProps({
    data: Object,
    form: Object,
    token: String
});

let form = props.form;

const selectedUserChannels = computed(() => {
    if (props.data.channels_user) {
        try {
            return JSON.parse(props.data.channels_user);
        } catch (e) {
            console.error('Errore nel parsing di channels_user:', e);
            return {};
        }
    } else if (props.data.users) {
        const user = props.data.users.find(user => user.id === props.form.user_id);
        if (!user) return {};

        try {
            return typeof user.channels === 'string'
                ? JSON.parse(user.channels)
                : user.channels || {};
        } catch (e) {
            console.error('Errore nel parsing dei canali dell\'utente:', e);
            return {};
        }
    }
});

const token = props.token; // Token dalla prop
const error = ref(null);
let app_url = import.meta.env.VITE_APP_URL;
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
    <div class="card">
        <div class="card-header">

            <div class="flex flex-row items-center">
                <div class="w-1/2 text-gray-500">
                    <span class="font-bold">{{ selectedUserChannels?.wordpress?.name }}</span> Opzioni
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
                Categorie
                <br>
                <small>Seleziona le categorie nelle quali vuoi venga pubblicato il post</small>
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
