<script setup>

import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import {Head, Link, useForm} from "@inertiajs/vue3";
import {__date} from "../../ComponentsExt/Date.js";
import {ref} from "vue";

const props = defineProps({
    data: Object,
    filters: Object,
})

const dataForm = Object.fromEntries(Object.entries(props.data).map((v) => {
    return props.data ? v : '';
}));

const form = useForm(dataForm);

function submit(update_on_channels = false) {

    if (update_on_channels === true) {
        form['updated'] = 2;
    }

    form.post(route('post.update_no_redirect', form.id), {
        preserveScroll: true
    })
}

let edit_ai_prompt_comment = ref(false);
let edit_ai_content = ref(false);

</script>

<template>

    <Head title="Clienti" />

    <AuthenticatedLayout>

        <template #header>

            <ApplicationHeader :breadcrumb-array="[
                'Posts',
                data.title
            ]" />

        </template>

        <ApplicationContainer>

            <h2 class="text-xl font-bold mb-3">Istruzioni inviate all'AI</h2>

            <div class="row">
                <div v-if="data.img.length > 0"
                     class="col-lg-4">

                    <div v-if="data.img && typeof data.img[0] === 'string'"
                         class="
                         flex flex-wrap gap-4
                         cursor-pointer
                         hover:opacity-60">
                        <img v-for="(url, index) in data.img"
                             :key="'backend-' + index"
                             :src="url"
                             :alt="data.title + ' ' + (index + 1)"
                             :class="{'w-24 h-24': index > 0}"
                             class="rounded" />
                    </div>

                </div>
                <div class="col-lg whitespace-pre-line">

                    <div class="row">
                        <div class="col-lg !mt-6 sm:!mt-0">

                            <label class="form-label">
                                Proprietario del post
                            </label>
                            {{ data.user.name }} - {{ data.user.email }}

                        </div>
                        <div class="col-lg !mt-6 sm:!mt-0 sm:text-right">

                            <label class="form-label">
                                Data pubblicazione
                            </label>
                            {{ __date(data.published_at) }}

                        </div>
                    </div>

                    <br>

                    <label class="form-label">
                        Titolo
                    </label>
                    {{ data.title }}

                    <br><br>

                    <label class="form-label">
                        Prompt
                    </label>
                    {{ data.ai_prompt_post }}

                    <br><br>

                    <label class="form-label">
                        Prompt commenti
                    </label>
                    <div v-if="edit_ai_prompt_comment === false">
                        {{ form.ai_prompt_comment }}
                        <br>
                        <button class="btn btn-sm btn-primary mt-2 w-1/2"
                                @click="edit_ai_prompt_comment = true">
                            Modifica
                        </button>
                    </div>
                    <div v-if="edit_ai_prompt_comment === true">
                        <textarea class="form-control h-[216px]"
                                  v-model="form.ai_prompt_comment"></textarea>
                        <button class="btn btn-sm btn-success mt-2 w-1/2"
                                @click="edit_ai_prompt_comment = false; submit()">
                            Salve
                        </button>
                    </div>

                    <br>

                    <label class="form-label">
                        Pubblicato su:
                    </label>

                    <span v-for="(channel, index) in channels = JSON.parse(data.channels)"
                          :key="index"
                          class="mr-4 sm:mr-2">
                        <div class="inline">
                            <a :href="channel.url ? channel.url : '#'"
                               :target="channel.url ? '_blank' : ''">
                                <i v-if="channel.on === '1'"
                                   class="text-[2em] sm:text-[1em]"
                                   :class="channel.css_class"></i>
                            </a>
                        </div>
                    </span>

                </div>
            </div>

            <hr class="my-6">

            <h2 class="text-xl font-bold mb-3 max-sm:mt-6">Contenuto generato dall'AI</h2>

            <div class="card">
                <div class="card-body whitespace-pre-line">

                    <div v-if="edit_ai_content === false">
                        {{ form.ai_content }}

                        <br>
                        <div v-for="(channel, index) in channels = JSON.parse(data.channels)">
                            <div v-if="channel.name === 'WordPress' && channel.on === '1'">
                                <button class="btn btn-sm btn-primary mt-2 w-1/2"
                                        @click="edit_ai_content = true">
                                    Modifica
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="edit_ai_content === true">
                                <textarea class="form-control h-[216px]"
                                          v-model="form.ai_content"></textarea>
                        <button class="btn btn-sm btn-success mt-2 w-1/2"
                                @click="edit_ai_content = false; submit(true)">
                            Salve
                        </button>
                    </div>

                    <small class="text-[11px] text-gray-500">
                        {{ __date(data.published_at) }}
                        -
                        <span v-if="data.token.tokens_used">
                                     {{ data.token.tokens_used }} token
                                </span>
                        <span v-else
                              class="text-red-500">
                                    Error: token non disponibile
                                </span>
                    </small>

                </div>
            </div>

            <div v-if="data.comments.length > 0">

                <hr class="my-6">

                <h2 class="text-xl font-bold  mb-3 max-sm:mt-6">Commenti</h2>

                <div v-for="comment in data.comments">
                    <div class="card text-left mb-2">
                        <div class="card-body whitespace-pre-line">
                            <label class="form-label">
                                <li :class="'fa-brands fa-' + comment.channel"></li>
                                &nbsp;{{ comment.from_name }} ha scritto:
                            </label>
                            {{ comment.message }}
                            <br>
                            <small class="text-[11px] text-gray-500">{{ __date(comment.message_created_time) }}</small>

                            <div v-if="comment.reply" class="mt-5">
                                <label class="form-label">
                                    Risposta:
                                </label>
                                {{ comment.reply }}
                                <br>
                                <small class="text-[11px] text-gray-500">
                                    {{ __date(comment.reply_created_time) }}
                                    -
                                    <span v-if="comment.token">
                                            {{ comment.token.tokens_used }} token
                                        </span>
                                    <span v-else
                                          class="text-red-500">
                                            Error: token non disponibile
                                        </span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="max-sm:text-center mt-10">

                <Link class="btn btn-secondary w-[120px]"
                      :href="data.saveRedirect">
                    Indietro
                </Link>

            </div>

        </ApplicationContainer>

    </AuthenticatedLayout>

</template>

<style scoped>

</style>
