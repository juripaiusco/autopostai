<script setup>

defineProps({
    placeholder: String,
    routeSearch: String,
    varSearch: String,
    filters: Object
});

</script>

<template>

    <input :name="varSearchName"
           :placeholder="placeholder"
           autocomplete="off"
           class="form-control"
           v-model="params[varSearchName]"
           type="search" />

</template>

<script>
export default {
    data() {

        let varSearchName = this.$props.varSearch === undefined ? 's' : this.$props.varSearch;

        return {
            varSearchName: varSearchName,
            params: {
                s: this.filters[varSearchName],
            }
        }
    },
    watch: {
        params: {
            handler() {

                let params = this.params;

                if (this.filters.orderby && this.filters.ordertype) {
                    params.orderby = this.filters.orderby;
                    params.ordertype = this.filters.ordertype;
                }

                Object.keys(params).forEach(k => {
                    if (params[k] === '') {
                        delete params[k];
                    }
                })

                this.$inertia.get(
                    this.routeSearch.includes('/') === true ? this.routeSearch : this.route(this.routeSearch),
                    params,
                    {
                        replace: true,
                        preserveState: true,
                        preserveScroll: true,
                    }
                );
            },
            deep: true,
        }
    }
}
</script>
