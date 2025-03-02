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

function submit() {
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

            <!-- <div class="max-sm:text-center mb-10">

                <Link class="btn btn-secondary w-[120px]"
                      :href="data.saveRedirect">
                    Indietro
                </Link>

            </div> -->

            <div class="row">
                <div class="col-lg whitespace-pre-line">

                    <h2 class="text-xl font-bold text-center">Istruzioni inviate all'AI</h2>

                    <br>

                    <img :src="data.img"
                         class="rounded" >

                    <br>

                    <label class="form-label">
                        Proprietario del post
                    </label>
                    {{ data.user.name }} - {{ data.user.email }}

                    <br><br>

                    <label class="form-label">
                        Data pubblicazione
                    </label>
                    {{ __date(data.published_at) }}

                    <br><br>

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
                       class="mr-2">
                        <div v-if="channel.url"
                             class="inline">
                            <a :href="channel.url"
                               target="_blank">
                                <i v-if="channel.on === '1'"
                                   :class="channel.css_class"></i>
                            </a>
                        </div>
                        <div v-else
                             class="inline">
                            <i v-if="channel.on === '1'"
                               :class="channel.css_class"></i>
                        </div>
                    </span>

                </div>
                <div class="col-lg">

                    <h2 class="text-xl font-bold text-center max-sm:mt-6">Contenuto generato dall'AI</h2>

                    <br>

                    <div class="card">
                        <div class="card-body whitespace-pre-line">
                            {{ data.ai_content }}

                            <br>
                            <button class="btn btn-sm btn-primary mt-2 w-1/2"
                                    @click="edit_ai_content = true">
                                Modifica
                            </button>
                            <br>

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

                </div>
                <div class="col-lg text-center">

                    <h2 class="text-xl font-bold text-center mt-6 lg:mt-0">Commenti</h2>

                    <br>

                    <div v-for="comment in data.comments">
                        <div class="card text-left mb-2">
                            <div class="card-body whitespace-pre-line">
                                <label class="form-label">
                                    <li v-if="comment.channel === 'facebook'"
                                        class="fa-brands fa-facebook"></li>
                                    <li v-if="comment.channel === 'instagram'"
                                        class="fa-brands fa-instagram"></li>
                                    <li v-if="comment.channel === 'wordpress'"
                                        class="fa-brands fa-wordpress"></li>
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
