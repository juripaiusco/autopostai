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

            <div class="max-sm:text-center mb-10">

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
                    <i v-if="data.meta_facebook_on === '1'"
                       class="fa-brands fa-facebook"></i>&nbsp;
                    <i v-if="data.meta_instagram_on === '1'"
                       class="fa-brands fa-instagram"></i>&nbsp;
                    <i v-if="data.wordpress_on === '1'"
                       class="fa-brands fa-wordpress-simple"></i>&nbsp;
                    <i v-if="data.newsletter_on === '1'"
                       class="fa-regular fa-envelope"></i>

                </div>
                <div class="col-lg">

                    <h2 class="text-xl font-bold text-center max-sm:mt-6">Contenuto generato dall'AI</h2>

                    <br>

                    <div class="card">
                        <div class="card-body">
                            {{ data.ai_content }}
                        </div>
                    </div>

                </div>
                <div class="col-lg text-center">

                    <h2 class="text-xl font-bold text-center max-sm:mt-6">Commenti</h2>

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
                                    <small class="text-[11px] text-gray-500">{{ __date(comment.reply_created_time) }}</small>
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
