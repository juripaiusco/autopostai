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

const props = defineProps({
    data: Object,
    filters: Object,
});

let modalShow = ref(false);
let modalData = ref(props.data);

</script>

<template>

    <Head title="Account" ></Head>

    <AuthenticatedLayout>

        <template #header>

            <ApplicationHeader :breadcrumb-array="['Account', 'Lista']" />

        </template>

        <ApplicationContainer>

            <div class="inline-flex w-full mb-6">

                <div class="lg:w-3/4 mr-4 lg:mr-0">

                    <div class="flex flex-row space-x-4">

                        <Link class="btn btn-dark w-[120px]"
                              :href="route('user.create')">
                            Nuovo
                        </Link>

                        <Link class="btn btn-warning w-[120px] !hidden sm:!block"
                              :href="route('user.index', {
                                  'orderby': 'name',
                                  'ordertype': 'asc',
                                  's': '',
                                  'filter': 'users'
                              })">
                            Utenti
                        </Link>

                        <Link class="btn btn-primary w-[120px] !hidden sm:!block"
                              :href="route('user.index', {
                                  'orderby': 'name',
                                  'ordertype': 'asc',
                                  's': '',
                                  'filter': 'managers'
                              })">
                            Manager
                        </Link>

                    </div>

                </div>

                <div class="w-full lg:w-1/4">

                    <TableSearch placeholder="Cerca account"
                                 route-search="user.index"
                                 :filters="filters"></TableSearch>

                </div>

            </div>

            <Table class="table-striped"
                   :data="{
                        filters: filters,
                        tblName: 'post',
                        routeSearch: 'user.index',
                        data: data.data,
                        route_row: 'user.edit',
                        structure: [{
                            class: 'text-left w-[5%] hidden md:table-cell',
                            label: '',
                            field: '',
                            fnc: function (d) {

                                let html = ''

                                if (d.child_on === 1) {

                                    html += '<div class=\'text-center\'>'
                                    html += '<i class=\'fa-solid fa-user-tie\'></i>'
                                    html += '</div>'

                                } else {

                                    html += '<div class=\'text-center\'>'
                                    html += '<i class=\'fa-solid fa-user\'></i>'
                                    html += '</div>'

                                }

                                return html
                            }
                        }, {
                            class: 'text-left',
                            label: 'Account',
                            field: 'name',
                            fnc: function (d) {

                                let html = ''

                                html += d.name

                                if (d.parent.parent_id !== null) {
                                    html += '<small> - <b>' + d.parent.name + '</b></small>'
                                }

                                if (d.child_on === 1) {

                                    html += '<strong> - Manager</strong>'

                                }

                                html += '<br><small>'
                                html += d.email
                                html += '</small>'

                                let tokens_used_total = new Intl.NumberFormat().format(d.tokens_used_total)
                                let tokens_limit = new Intl.NumberFormat().format(d.tokens_limit)
                                let percentual = parseFloat(d.tokens_used_total / d.tokens_limit * 100)
                                let classNameBarContainer = '!bg-green-300'
                                let classNameBar = '!bg-green-500'

                                if (percentual >= 75) {
                                    classNameBarContainer = '!bg-yellow-300'
                                    classNameBar = '!bg-yellow-500'
                                }
                                if (percentual >= 90) {
                                    classNameBarContainer = '!bg-red-300'
                                    classNameBar = '!bg-red-500'
                                }

                                /*if (d.child_on === null) {
                                    html += '<div class=\'md:hidden\'>'

                                    html += tokens_used_total +
                                    ' / ' +
                                    '<small>' +
                                        tokens_limit +
                                    '</small>' +
                                    '&nbsp;&nbsp;&nbsp;&nbsp;<br>' +
                                     '<div class=\'mt-2 progress ' + classNameBarContainer + '\' role=\'progressbar\' style=\'height: 4px\'>' +
                                         '<div class=\'progress-bar ' + classNameBar + '\' style=\'width: ' + percentual + '%\'></div>' +
                                     '</div>'

                                     html += '</div>'
                                }*/

                                /*if (d.parent_id > 1) {
                                    html = '<div class=\'!pl-6\'>' + html + '</div>'
                                }*/

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
                                        channelsArray.push('<i class=\'text-sm ' + channels[index]['css_class'] + '\'></i>')
                                    }
                                }

                                let html = ''
                                html += channelsArray.join('&nbsp;&nbsp;&nbsp;');

                                return html

                            }
                        }, {
                            class: 'text-center w-[5%] hidden md:table-cell',
                            label: 'Post',
                            field: 'post_count'
                        }, {
                            class: 'text-center w-[5%] hidden md:table-cell',
                            label: 'Reply',
                            field: 'reply_count'
                        }, {
                            class: 'text-center sm:w-[1%] hidden sm:table-cell',
                            label: 'Immagini',
                            field: 'image_model_limit',
                            fnc: function (d) {

                                let html = ''

                                if (d.image_model_limit > 0) {
                                    let total = new Intl.NumberFormat().format(d.images_used_total)
                                    let limit = new Intl.NumberFormat().format(d.image_model_limit)
                                    let percentual = parseFloat(total / limit * 100)

                                    let classNameBarContainer = '!bg-green-300'
                                    let classNameBar = '!bg-green-500'

                                    if (percentual > 80) {
                                        classNameBarContainer = '!bg-yellow-300'
                                        classNameBar = '!bg-yellow-500'
                                    }
                                    if (percentual > 95) {
                                        classNameBarContainer = '!bg-red-300'
                                        classNameBar = '!bg-red-500'
                                    }

                                    html += total +
                                        ' / ' +
                                        '<small>' +
                                            limit +
                                        '</small>' +
                                        '<br>' +
                                        '<div class=\'mt-2 progress ' + classNameBarContainer + '\' role=\'progressbar\' style=\'height: 4px\'>' +
                                            '<div class=\'progress-bar ' + classNameBar + '\' style=\'width: ' + percentual + '%\'></div>' +
                                        '</div>'
                                }

                                return html;

                            }
                        }, {
                            class: 'text-right sm:w-[200px]',
                            label: 'Token',
                            field: 'tokens_limit',
                            fnc: function (d) {

                                let tokens_used_total = new Intl.NumberFormat().format(d.tokens_used_total)
                                let tokens_limit = new Intl.NumberFormat().format(d.tokens_limit)
                                let percentual = parseFloat(d.tokens_used_total / d.tokens_limit * 100)
                                let html = ''
                                let classNameBarContainer = '!bg-green-300'
                                let classNameBar = '!bg-green-500'

                                if (percentual > 80) {
                                    classNameBarContainer = '!bg-yellow-300'
                                    classNameBar = '!bg-yellow-500'
                                }
                                if (percentual > 95) {
                                    classNameBarContainer = '!bg-red-300'
                                    classNameBar = '!bg-red-500'
                                }

                                if (d.child_on === null) {
                                    html += (tokens_used_total === '0' ? '-' : tokens_used_total) +
                                    ' / ' +
                                    '<small>' +
                                        tokens_limit +
                                    '</small>' +
                                    '<br>' +
                                     '<div class=\'mt-2 progress ' + classNameBarContainer + '\' role=\'progressbar\' style=\'height: 4px\'>' +
                                         '<div class=\'progress-bar ' + classNameBar + '\' style=\'width: ' + percentual + '%\'></div>' +
                                     '</div>'
                                }

                                return html;

                            }
                        }, /*{
                            class: 'text-center',
                            label: 'Post',
                            field: '',
                            fnc: function (d) {

                                let html = ''

                                if (d.posts.length)
                                    html += d.posts.length

                                return html
                            }
                        }, *//*{
                            class: 'text-left',
                            label: 'E-mail',
                            field: 'email'
                        }, {
                            class: 'text-center w-[10%]',
                            label: 'Manager',
                            field: 'child_on',
                            fnc: function (d) {

                                let html = ''

                                if (d.child_on === 1) {

                                    html += '<div class=\'ml-10 !text-center text-green-500\'>'
                                    html += '<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\' class=\'size-6\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z\'/></svg>'
                                    html += '</div>'

                                }

                                return html
                            }
                        }, */{
                            class: 'w-[1%] hidden sm:table-cell',
                            classBtn: 'btn-dark',
                            btnEdit: true,
                            route: 'user.edit'
                        }, {
                            class: 'w-[1%]',
                            classBtn: 'btn-dark',
                            btnDel: true,
                            route: 'user.destroy'
                        }],
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

                <template #title>Elimina utente</template>
                <template #body>
                    Vuoi eliminare
                    <span class="font-semibold">
                        {{ modalData.name }}
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
