<script setup>

import {Head, Link, router} from "@inertiajs/vue3";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import TableSearch from "@/Components/Table/TableSearch.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ModalReady from "@/Components/ModalReady.vue";
import Table from "@/Components/Table/Table.vue";
import TablePagination from "@/Components/Table/TablePagination.vue";
import {ref} from "vue";
import {__date} from "@/ComponentsExt/Date.js";

const props = defineProps({
    data: Object,
    filters: Object,
    token: Object,
});

let modalShow = ref(false);
let modalData = ref(props.data);

</script>

<template>

    <Head title="Notifiche" ></Head>

    <AuthenticatedLayout>

        <template #header>

            <ApplicationHeader :breadcrumb-array="['Notifiche', 'Lista']" />

        </template>

        <ApplicationContainer>

            <div class="inline-flex w-full mb-6">

                <div class="lg:w-3/4 mr-4 lg:mr-0">

                    <Link class="btn btn-dark w-[120px]"
                          :href="route('notification.create')">
                        Nuovo
                    </Link>

                </div>

                <div class="w-full lg:w-1/4">

                    <TableSearch placeholder="Cerca notifica"
                                 route-search="notification.index"
                                 :filters="filters"></TableSearch>

                </div>

            </div>

            <Table class="table-striped"
                   :data="{
                        filters: filters,
                        tblName: 'notification',
                        routeSearch: 'notification.index',
                        data: data.data,
                        route_row: 'notification.edit',
                        structure: [{
                            class: 'text-left',
                            label: 'Titolo',
                            field: 'title'
                        }, {
                            class: 'text-left',
                            label: 'Body',
                            field: 'body'
                        }, {
                            class: 'text-left',
                            label: 'URL',
                            field: 'url'
                        }, {
                            class: 'text-center',
                            label: 'data',
                            field: 'created_at',
                            fnc: function (d) {

                                let html = '<small>'

                                if (d.created_at !== null) {
                                    html += __date(d.created_at, 'day')
                                    html += ' '
                                    html += __date(d.created_at, 'hour')
                                } else {
                                    html += '<div class=\'flex items-center justify-center\'>';
                                    html += '<svg class=\'text-red-500 size-6\' xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z\' /></svg>';
                                    html += '</div>';
                                }

                                html += '</small>'

                                return html

                            }
                        }, {
                            class: 'w-[1%] hidden sm:table-cell',
                            classBtn: 'btn-dark',
                            btnEdit: true,
                            route: 'notification.edit'
                        }, {
                            class: 'w-[1%]',
                            classBtn: 'btn-dark',
                            btnDel: true,
                            route: 'notification.destroy'
                        }, {
                            class: 'w-[1%] hidden sm:table-cell',
                            classBtn: 'btn-dark',
                            btnCustom: true,
                            route: 'notification.send',
                            emit: 'btnCustom_notificationSend',
                            fnc: function (d) {

                                let html = ''
                                let className = 'btn-secondary';

                                if (d.sent === null) {
                                    className = 'btn-dark';
                                }

                                html += '<div class=\'btn ' + className + '\'>';
                                html += '<svg class=\'size-4\'xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z\' /></svg>'
                                html += '</div>';

                                return html
                            }
                        }],
                    }"
                   @btnCustom_notificationSend="(d) => {

                       let url;

                       if (d.sent === '1') {
                           url = '#';
                       } else {
                           url = route('notification.send');
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

                <template #title>Elimina notifica</template>
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
