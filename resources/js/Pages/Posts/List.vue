<script setup>

import { Head } from '@inertiajs/vue3';
import {Link} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import ModalReady from "@/Components/ModalReady.vue";
import Table from "@/Components/Table/Table.vue";
import TableSearch from "@/Components/Table/TableSearch.vue";
import TablePagination from "@/Components/Table/TablePagination.vue";
import {ref} from "vue";
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
                        structure: [{
                            class: 'text-center',
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

                                let socialArray = new Array();
                                d.meta_facebook_on == 1 ? socialArray.push('<i class=\'fa-brands fa-facebook\'></i>')  : '';
                                d.meta_instagram_on == 1 ? socialArray.push('<i class=\'fa-brands fa-instagram\'></i>')  : '';
                                d.wordpress_on == 1 ? socialArray.push('<i class=\'fa-brands fa-wordpress-simple\'></i>')  : '';
                                d.newsletter_on == 1 ? socialArray.push('<i class=\'fa-regular fa-envelope\'></i>')  : '';

                                let html = ''

                                html += d.title
                                html += '<br>'
                                html += '<small>'
                                html += d.user.name + ' - ' + d.user.email

                                if (d.user.child_on === 1) {

                                    html += '<br><strong> - Manager</strong>'

                                }

                                html += '<br>'
                                html += '<div class=\'row md:!hidden\'>'
                                    html += '<div class=\'col-4\'>'
                                    html += d.comments.length + ' <i class=\'fa-regular fa-comments\'></i>'
                                    html += '</div>'
                                    html += '<div class=\'col\'>'
                                    html += socialArray.join('&nbsp;&nbsp;');
                                    html += '</div>'
                                html += '</div>'

                                html += __date(d.published_at, 'day')
                                html += ' '
                                html += __date(d.published_at, 'hour')
                                html += '</small>'

                                return html
                            }
                        }, /*{
                            class: 'text-left hidden sm:table-cell',
                            label: 'Titolo',
                            field: 'title'
                        }, {
                            class: 'text-left hidden sm:table-cell',
                            label: 'Account',
                            field: 'user.name',
                            fnc: function (d) {

                                let html = d.user.name

                                if (d.user.child_on === 1) {

                                    html += '<strong> - Manager</strong>'

                                }

                                html += '<br><small>'
                                html += d.user.email
                                html += '</small>'

                                return html
                            }
                        }, {
                            class: 'text-center w-[20%] hidden sm:table-cell',
                            label: 'Data',
                            field: 'published_at',
                            fnc: function (d) {

                                let html = '<small>'
                                html += __date(d.published_at, 'day')
                                html += ' '
                                html += __date(d.published_at, 'hour')
                                html += '</small>'

                                return html

                            }
                        }, */{
                            class: 'text-center w-[20%] hidden sm:table-cell',
                            label: 'Media',
                            field: 'media_channel',
                            fnc: function (d) {

                                let socialArray = new Array();
                                d.meta_facebook_on == 1 ? socialArray.push('<i class=\'fa-brands fa-facebook\'></i>')  : '';
                                d.meta_instagram_on == 1 ? socialArray.push('<i class=\'fa-brands fa-instagram\'></i>')  : '';
                                d.wordpress_on == 1 ? socialArray.push('<i class=\'fa-brands fa-wordpress-simple\'></i>')  : '';
                                d.newsletter_on == 1 ? socialArray.push('<i class=\'fa-regular fa-envelope\'></i>')  : '';

                                let html = ''
                                html += socialArray.join('&nbsp;&nbsp;&nbsp;');

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
                            class: 'text-center w-[5%] hidden sm:table-cell',
                            label: 'Stato',
                            field: 'published',
                            fnc: function (d) {

                                let html = ''

                                if (d.published == 1) {
                                    html += '<div class=\'text-center text-green-500\'>'
                                    // html += '<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\' class=\'size-6\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z\'/></svg>'
                                    html += '<i class=\'fa-regular fa-circle-check\'></i>'
                                    html += '</div>'
                                } else {
                                    html += '<div class=\'text-center text-yellow-500\'>'
                                    // html += '<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\' class=\'size-6\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z\' /></svg>'
                                    html += '<i class=\'fa-regular fa-clock\'></i>'
                                    html += '</div>'
                                }

                                return html

                            }
                        }, {
                            class: 'w-[1%]',
                            classBtn: 'btn-dark',
                            btnCustom: true,
                            route: 'post.edit',
                            emit: 'btnCustom_ShowOrEdit',
                            fnc: function (d) {

                                let html = ''

                                if (d.published === '1') {

                                    html += '<div class=\'btn btn-info\'>';
                                    html += '<svg class=\'w-4 h-4 xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z\'/></svg>'
                                    html += '</div>';

                                } else {

                                    html += '<div class=\'btn btn-dark\'>';
                                    html += '<svg class=\'w-4 h-4 xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10\'/></svg>'
                                    html += '</div>';
                                }

                                return html
                            }
                        }/*, {
                            class: 'w-[1%]',
                            classBtn: '',
                            btnShow: function (d) {
                                return d.published === '1'
                            },
                            route: 'post.show'
                        }, {
                            class: 'w-[1%]',
                            classBtn: 'btn-dark',
                            btnEdit: function (d) {
                                return d.published !== '1'
                            },
                            route: 'post.edit'
                        }*/, {
                            class: 'w-[1%]',
                            classBtn: 'btn-dark',
                            btnDel: true,
                            route: 'post.destroy'
                        }],
                    }"
                   @btnCustom_ShowOrEdit="(d) => {

                       let url;

                       if (d.published === '1') {
                           url = route('post.show', d.id);
                       } else {
                           url = route('post.edit', d.id);
                       }

                       Inertia.visit(url, {
                           method: 'get',
                           only: ['posts'],
                           headers: {
                               'X-Inertia': true,
                           },
                           replace: true,
                           preserveState: false,
                           data: {
                               inertiaVisit: true
                           }
                       })

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
                    <span class="font-semibold">
                        {{ modalData.title }}
                    </span>
                    ?
                </template>

            </ModalReady>

            <TablePagination :links="data.links" />

        </ApplicationContainer>

    </AuthenticatedLayout>

</template>

<style scoped>

</style>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

export default {
    data() {
        let posts = ref(this.$props.data.data);
        const token = this.$props.token.plainTextToken;
        const isLoading = ref(false);
        const error = ref(null);
        let interval = null;

        const fetchPosts = () => {
            isLoading.value = true;
            axios.get('/public/index.php/api/posts',  {
                    headers: {
                        Authorization: `Bearer ${token}`
                    }
                }).then(response => {
                    const newPosts = response.data;

                    // Aggiorna solo i post esistenti che hanno lo stesso ID
                    newPosts.forEach(newPost => {
                        const index = posts.value.findIndex(post => post.id === newPost.id);
                        if (index !== -1) {
                            // Se il post esiste già, sostituisci solo quello
                            posts.value[index] = newPost;
                        } else {
                            // Altrimenti, aggiungi il nuovo post
                            posts.value.push(newPost);
                        }
                    });

                    error.value = null; // Reset errore
                })
                .catch(err => {
                    console.error(err);
                    error.value = 'Errore nel recupero dei dati';
                })
                .finally(() => {
                    isLoading.value = false;
                });
        };

        onMounted(() => {
            fetchPosts(); // Caricamento iniziale
            interval = setInterval(fetchPosts, 5000); // 5 secondi
        });

        onUnmounted(() => {
            clearInterval(interval); // Pulisci l'intervallo
        });

        return { posts, isLoading, error };
    },
};
</script>
