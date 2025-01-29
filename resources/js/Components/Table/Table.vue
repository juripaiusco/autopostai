<script setup>
import TableTh from "@/Components/Table/TableTh.vue";
import {Link, router} from "@inertiajs/vue3";
// import { defineEmits } from "vue";

defineProps({
    data: Object,
});

const emit = defineEmits();

function routeRow(route_row, route_emit, data, struct) {

    if (struct.btnShow !== true &&
        struct.btnEdit !== true &&
        struct.btnDel !== true) {

        if (route_emit !== undefined) {
            emit(route_emit, data);
        }

        if (route_row !== undefined) {
            router.visit(route(route_row, data.id))
        }
    }
}

</script>

<template>

    <table class="table table-hover">
        <thead>
        <tr>
            <template v-for="struct in data.structure">

                <TableTh :struct="struct"
                         :data="data" />

            </template>
        </tr>
        </thead>
        <tbody>
        <tr v-for="d in data.data" :key="d.id" :id="(data.tblName ? data.tblName : 'row') + '-' + d.id">
            <template v-for="struct in data.structure">

                <td v-if="
                        (!struct.btnShow && !struct.btnEdit && !struct.btnDel)

                        ||

                        ((typeof struct.btnShow !== 'function' && struct.btnShow === true)
                        ||
                        (typeof struct.btnShow === 'function' && struct.btnShow(d) === true))

                        ||

                        ((typeof struct.btnEdit !== 'function' && struct.btnEdit === true)
                        ||
                        (typeof struct.btnEdit === 'function' && struct.btnEdit(d) === true))

                        ||

                        ((typeof struct.btnDel !== 'function' && struct.btnDel === true)
                        ||
                        (typeof struct.btnDel === 'function' && struct.btnDel(d) === true))
                    "
                    @click="routeRow(data.route_row, data.route_emit, d, struct)"
                    class="align-middle"
                    :class="struct.class, struct.classData, {
                        'text-center': (struct.btnShow === true || struct.btnEdit === true || struct.btnDel === true),
                        'cursor-pointer': data.route_row || data.route_emit
                    }">

                    <template v-if="typeof struct.fnc !== 'function'">
                        {{ show(d, struct) }}
                    </template>

                    <div v-if="struct.btnCustom !== true && typeof struct.fnc === 'function'" v-html="show(d, struct)" />

                    <!-- Button Custom -->
                    <template v-if="struct.btnCustom === true">

                        <Link class="btn btn-sm"
                              v-if="struct.emit === undefined"
                              :href="struct.route.includes('/') === true ? struct.route : route(struct.route, d.id)"
                              :data="struct.filters ? struct.filters : data.filters"
                              :preserveState="data.preserveState">

                            <template v-if="typeof struct.fnc !== 'function'">
                                {{ show(d, struct) }}
                            </template>

                            <div v-if="typeof struct.fnc === 'function'" v-html="show(d, struct)" />

                        </Link>

                        <!-- IF il pulsante Custom presenta un emit -->
                        <button v-if="struct.emit !== undefined"
                                type="button"
                                class="btn btn-sm"
                                @click="$emit(
                                    struct.emit,
                                    d,
                                    struct.route.includes('/') === true ? struct.route : route(struct.route, d.id)
                                )"
                        >

                            <template v-if="typeof struct.fnc !== 'function'">
                                {{ show(d, struct) }}
                            </template>

                            <div v-if="typeof struct.fnc === 'function'" v-html="show(d, struct)" />

                        </button>

                    </template>

                    <!-- Button Show -->
                    <template v-if="
                        (typeof struct.btnShow !== 'function' && struct.btnShow === true)
                        ||
                        (typeof struct.btnShow === 'function' && struct.btnShow(d) === true)
                    ">

                        <Link class="btn btn-info"
                              :class="struct.classBtn"
                              :href="route(struct.route, d.id)">

                            <svg class="w-4 h-4"
                                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>

                        </Link>

                    </template>

                    <!-- Button Edit -->
                    <template v-if="
                        (typeof struct.btnEdit !== 'function' && struct.btnEdit === true)
                        ||
                        (typeof struct.btnEdit === 'function' && struct.btnEdit(d) === true)
                    ">

                        <Link class="btn btn-warning"
                              :class="struct.classBtn"
                              :href="route(struct.route, d.id)">

                            <svg class="w-4 h-4"
                                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>

                        </Link>

                    </template>

                    <!-- Button Delete -->
                    <template v-if="
                        (typeof struct.btnDel !== 'function' && struct.btnDel === true)
                        ||
                        (typeof struct.btnDel === 'function' && struct.btnDel(d) === true)
                    ">

                        <button class="btn btn-danger"
                                type="button"
                                :class="struct.classBtn"
                                @click="$emit('openModal', d, route(struct.route, d.id))">

                            <svg class="w-4 h-4"
                                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>

                        </button>

                    </template>

                </td>

            </template>
        </tr>
        </tbody>
    </table>

</template>

<script>
export default {
    data()
    {
        return {}
    },
    methods: {
        show (d, struct) {

            // START - Genero il dato
            let data = '';

            if (struct.field && struct.field.includes('.') === true) {

                let keys = struct.field.split('.');

                if (eval('d.' + keys[0])) {
                    data = eval('d.' + struct.field);
                }

            } else {

                data = d[struct.field];
            }
            // END - Genero il dato

            // Se esiste una funziona manipolo il dato con la funzione
            if (typeof struct.fnc === 'function') {

                data = struct.fnc(d);

            }

            return data;

        }
    }
}
</script>
