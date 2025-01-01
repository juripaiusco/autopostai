<script setup>

import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import {Head, Link} from "@inertiajs/vue3";
import {__date} from "../../ComponentsExt/Date.js";

const props = defineProps({
    data: Object,
    filters: Object,
})

let mediaChannelArray = new Array();

if (props.data.meta_facebook_on === '1')
    mediaChannelArray.push('Facebook');

if (props.data.meta_instagram_on === '1')
    mediaChannelArray.push('Instagram');

if (props.data.wordpress_on === '1')
    mediaChannelArray.push('WordPress');

if (props.data.newsletter_on === '1')
    mediaChannelArray.push('Newsletter');

</script>

<template>

    <Head title="Clienti" />

    <AuthenticatedLayout>

        <template #header>

            <ApplicationHeader :breadcrumb-array="[
                'Post',
                data.title
            ]" />

        </template>

        <ApplicationContainer>

            <div class="text-right mb-10">

                <Link class="btn btn-secondary w-[120px]"
                      :href="data.saveRedirect">
                    Indietro
                </Link>

            </div>

            <div class="row">
                <div class="col-lg">

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
                        Pubblicato su:
                    </label>
                    {{ mediaChannelArray.join(' / ') }}

                </div>
                <div class="col-lg">

                    <h2 class="text-xl font-bold text-center">Contenuto generato dall'AI</h2>

                    <br>

                    <div class="card">
                        <div class="card-body">
                            {{ data.ai_content }}
                        </div>
                    </div>

                </div>
                <div class="col-lg text-center">

                    <h2 class="text-xl font-bold text-center">Commenti</h2>

                    <br>

                    <div v-for="comment in data.comments">
                        <div class="card text-left mb-2">
                            <div class="card-body">
                                <label class="form-label">
                                    <li v-if="comment.channel === 'facebook'"
                                        class="fa-brands fa-facebook"></li>
                                    <li v-if="comment.channel === 'instagram'"
                                        class="fa-brands fa-instagram"></li>
                                    <li v-if="comment.channel === 'wordpress'"
                                        class="fa-brands fa-wordpress"></li>
                                    &nbsp;{{ comment.from_name }} ha scritto il {{ __date(comment.created_time) }}:
                                </label>
                                {{ comment.message }}

                                <div v-if="comment.reply" class="mt-5">
                                    <label class="form-label">
                                        Risposta:
                                    </label>
                                    {{ comment.reply }}
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="text-right mt-10">

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
