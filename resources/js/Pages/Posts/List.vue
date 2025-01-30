<script setup>

import { Head } from '@inertiajs/vue3';
import {Link, router} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import ModalReady from "@/Components/ModalReady.vue";
import Table from "@/Components/Table/Table.vue";
import TableSearch from "@/Components/Table/TableSearch.vue";
import TablePagination from "@/Components/Table/TablePagination.vue";
import {ref, computed, onMounted, onUnmounted} from "vue";
import axios from "axios";
import {__date} from "@/ComponentsExt/Date.js";
import {Inertia} from "@inertiajs/inertia";

const props = defineProps({
    data: Object,
    filters: Object,
    token: Object,
});

let modalShow = ref(false);
let modalData = ref(props.data);
let app_url = import.meta.env.VITE_APP_URL;

// Stato reattivo
const token = props.token.plainTextToken; // Token dalla prop
const isLoading = ref(false);
const error = ref(null);

// Collegare direttamente `posts` ai dati iniziali con computed
const posts = computed(() => props.data.data);

let interval = null;

// Funzione per recuperare i post
const fetchPosts = () => {
    isLoading.value = true;
    axios
        .get(app_url + '/index.php/api/posts', {
            headers: {
                Authorization: `Bearer ${token}`,
            },
        })
        .then((response) => {
            const newPosts = response.data;

            // Aggiorna solo i post esistenti che hanno lo stesso ID
            newPosts.forEach((newPost) => {
                const index = posts.value.findIndex((post) => post.id === newPost.id);
                if (index !== -1) {
                    // Se il post esiste già, sostituisci solo quello
                    posts.value[index] = newPost;
                } else {
                    // Altrimenti, aggiungi il nuovo post
                    // posts.value.push(newPost);
                }
            });

            error.value = null; // Reset errore
        })
        .catch((err) => {
            console.error(err);
            error.value = 'Errore nel recupero dei dati';
        })
        .finally(() => {
            isLoading.value = false;
        });
};

// Effetti al montaggio e smontaggio del componente
onMounted(() => {
    interval = setInterval(fetchPosts, 30000); // 30 secondi
});

onUnmounted(() => {
    clearInterval(interval); // Pulisci l'intervallo
});

</script>

<template>

    <Head title="Posts" ></Head>

    <AuthenticatedLayout>

        <template #header>

            <ApplicationHeader :breadcrumb-array="['Posts', 'Lista']" />

        </template>

        <ApplicationContainer>

            <div class="inline-flex w-full mb-6">

                <div class="lg:w-3/4 mr-4 lg:mr-0">

                    <Link class="btn btn-dark w-[120px]"
                          :href="route('post.create')">
                        Nuovo
                    </Link>

                </div>

                <div class="w-full lg:w-1/4">

                    <TableSearch placeholder="Cerca post"
                                 route-search="post.index"
                                 :filters="filters"></TableSearch>

                </div>

            </div>

            <Table class="table-striped"
                   :data="{
                        filters: filters,
                        tblName: 'post',
                        routeSearch: 'post.index',
                        data: posts,
                        route_emit: 'btnCustom_ShowOrEdit',
                        structure: [{
                            class: 'text-center w-[70px]',
                            label: '',
                            field: 'img',
                            fnc: function (d) {

                                let html = ''

                                if (d.img) {
                                    html += '<img src=\'' + app_url + '/storage/posts/' + d.id + '/' + d.img + '\' class=\'w-12 h-12 m-auto object-cover rounded-lg\' />'
                                }

                                return html

                            }
                        }, {
                            class: 'text-left',
                            label: 'Post',
                            field: 'published_at',
                            fnc: function (d) {

                                let channels = JSON.parse(d.channels);
                                let channelsArray = [];

                                for (let index in channels) {
                                    if (channels[index]['on'] === '1') {
                                        channelsArray.push('<i class=\'' + channels[index]['css_class'] + '\'></i>')
                                    }
                                }

                                let html = ''

                                html += '<span class=\'hidden sm:inline\'>' + d.title + '</span>'
                                html += '<span class=\'inline sm:hidden\'>' + d.title.substring(0, 25) + '</span>'

                                if (d.title.length >= 25) {
                                    html += '<span class=\'inline sm:hidden\'> ...</span>'
                                }

                                if (!$page.props.auth.user.parent_id || $page.props.auth.user.child_on) {
                                    html += '<br>'
                                    html += '<small>'
                                    html += d.user.name + '<span class=\'hidden sm:inline\'> - ' + d.user.email + '</span>'
                                    html += '</small>'
                                }

                                if (d.published == 0) {
                                    html += '<div class=\'sm:hidden my-1 flex justify-around text-xs p-1 rounded border border-1 bg-yellow-200 border-yellow-500 text-yellow-600 dark:bg-yellow-900 dark:border-yellow-500 dark:text-yellow-500\'>'
                                } else if (d.published == 1 && d.task_complete == 0) {
                                    html += '<div class=\'sm:hidden my-1 flex justify-around text-xs p-1 rounded border border-1 bg-green-200 border-green-500 text-green-600 dark:bg-green-900 dark:border-green-500 dark:text-green-500\'>'
                                } else if (d.task_complete == 1) {
                                    html += '<div class=\'sm:hidden my-1 flex justify-around text-xs p-1 rounded border border-1 bg-gray-200 border-gray-500 text-gray-600 dark:bg-gray-900 dark:border-gray-500 dark:text-gray-500\'>'
                                }

                                html += '<div>' + channelsArray.join('&nbsp;&nbsp;') + '</div>'
                                html += '<div><i class=\'fa-regular fa-comments\'></i> ' + d.comments.length + '</div>'
                                html += '<div>' + __date(d.published_at, 'day') + ' ' + __date(d.published_at, 'hour') + '</div>'

                                return html
                            }
                        }, {
                            class: 'text-center w-[15%] hidden sm:table-cell',
                            label: 'Media',
                            field: 'channels',
                            fnc: function (d) {

                                let channels = JSON.parse(d.channels);
                                let channelsArray = [];

                                for (let index in channels) {
                                    if (channels[index]['on'] === '1') {
                                        channelsArray.push('<i class=\'' + channels[index]['css_class'] + '\'></i>')
                                    }
                                }

                                let html = ''
                                html += channelsArray.join('&nbsp;&nbsp;&nbsp;');

                                return html

                            }
                        }, {
                            class: 'text-center w-[15%] hidden sm:table-cell',
                            label: 'Pubblicazione',
                            field: 'published_at',
                            fnc: function (d) {

                                let html = '<small>'
                                html += __date(d.published_at, 'day')
                                html += ' '
                                html += __date(d.published_at, 'hour')
                                html += '</small>'

                                return html

                            }
                        }, {
                            class: 'text-center w-[5%] hidden sm:table-cell',
                            label: 'Commenti',
                            field: 'comments',
                            fnc: function (d) {

                                let html = ''
                                html += d.comments.length

                                return html

                            }
                        }, {
                            class: 'text-right w-[5%] hidden sm:table-cell',
                            label: 'Token',
                            field: 'token.tokens_used',
                            fnc: function (d) {

                                let token_used_total = 0

                                if (d.token && d.token.tokens_used) {
                                    token_used_total += d.token.tokens_used
                                }

                                for (let i in d.comments) {
                                    if (d.comments[i].token) {
                                        token_used_total += d.comments[i].token.tokens_used
                                    }
                                }

                                return token_used_total

                            }
                        }, {
                            class: 'w-[1%] hidden sm:table-cell',
                            classBtn: 'btn-dark',
                            btnCustom: true,
                            route: 'post.edit',
                            emit: 'btnCustom_ShowOrEdit',
                            fnc: function (d) {

                                let html = ''

                                if (d.published === '1') {

                                    let className = 'btn-success';

                                    if (d.task_complete === '1') {
                                        className = 'btn-secondary';
                                    }

                                    html += '<div class=\'btn ' + className + '\'>';
                                    html += '<svg class=\'w-4 h-4 xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z\'/></svg>'
                                    html += '</div>';

                                } else {

                                    html += '<div class=\'btn btn-warning\'>';
                                    html += '<svg class=\'w-4 h-4 xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10\'/></svg>'
                                    html += '</div>';
                                }

                                return html
                            }
                        }, {
                            class: 'w-[1%]',
                            classBtn: 'btn-dark',
                            btnDel: true,
                            route: 'post.delete'
                        }],
                    }"
                   @btnCustom_ShowOrEdit="(d) => {

                       let url;

                       if (d.published === '1') {
                           url = route('post.show', d.id);
                       } else {
                           url = route('post.edit', d.id);
                       }

                       router.visit(url)

                   }"
                   @openModal="(data, route) => {
                       modalData = data;
                       modalData.confirmURL = route;
                       modalData.confirmBtnClass = 'btn-danger';
                       modalData.confirmBtnText = 'Sì';
                       modalShow = true;
                   }" />

            <ModalReady :show="modalShow"
                        :data="modalData"
                        @close="modalShow = false">

                <template #title>Elimina post</template>
                <template #body>
                    Vuoi eliminare
                    <br>
                    <span class="font-semibold">
                        {{ modalData.title }}
                    </span>
                    ?

                    <div v-if="modalData.published == 1">
                        <br>

                        <div class="alert alert-warning">
                            Il post verrà eliminato da tutti i canali tranne che per Instagram.
                            <br>
                            Per Instagram bisogna eliminare il post manualmente.
                        </div>
                    </div>

                </template>

            </ModalReady>

            <TablePagination :links="data.links" />

        </ApplicationContainer>

    </AuthenticatedLayout>

</template>

<style scoped>

</style>
