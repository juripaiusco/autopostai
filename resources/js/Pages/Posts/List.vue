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
                        data: data.data,
                        structure: [{
                            class: 'text-left',
                            label: 'Titolo',
                            field: 'title'
                        }, {
                            class: 'w-[1%]',
                            classBtn: 'ml-[8px] btn-dark',
                            btnEdit: true,
                            route: 'post.edit'
                        }, {
                            class: 'w-[1%]',
                            classBtn: 'mr-[8px] btn-dark',
                            btnDel: true,
                            route: 'post.destroy'
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

                <template #title>Elimina cliente</template>
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
