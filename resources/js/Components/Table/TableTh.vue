<script setup>

defineProps({
    struct: Object,
    data: Object,
})

</script>

<template>

    <th :class="struct.class">

        <template v-if="struct.btnShow !== true && struct.btnEdit !== true && struct.btnDel !== true">

            <div @click="sort(struct.field, struct.order)"
                 class="inline-flex cursor-pointer">

                <div>
                    {{ struct.label }}
                </div>

                <div class="ml-2 w-[12px] h-[12px]"
                     :class="{
                        'invisible': params.orderby !== struct.field,
                        'hidden': struct.order === false
                    }">
                    <svg :class="{ 'text-gray-300': params.ordertype === 'desc' && params.orderby === struct.field }"
                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                    </svg>
                    <svg :class="{ 'text-gray-300': params.ordertype === 'asc' && params.orderby === struct.field }"
                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </div>

            </div>

        </template>

    </th>

</template>

<script>

export default {
    data() {
        return {
            params: {
                orderby: this.data.filters.orderby,
                ordertype: this.data.filters.ordertype,
            }
        }
    },
    methods: {
        sort(field, order) {

            if (order !== false) {

                if (this.params.orderby !== field) {
                    this.params.ordertype = 'asc';
                } else {
                    this.params.ordertype = this.params.ordertype === 'asc' ? 'desc' : 'asc';
                }

                this.params.s = this.data.filters.s;
                this.params.orderby = field;

            }
        }
    },
    watch: {
        params: {
            handler() {
                this.$inertia.get(
                    this.data.routeSearch.includes('/') === true ? this.data.routeSearch : this.route(this.data.routeSearch),
                    this.params,
                    {
                        replace: true,
                        preserveState: this.data.preserveState,
                        preserveScroll: true,
                    }
                );
            },
            deep: true
        }
    }
}

</script>
