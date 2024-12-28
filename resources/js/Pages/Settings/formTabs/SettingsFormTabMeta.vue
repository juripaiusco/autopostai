<script setup>

const props = defineProps({
    form: Object,
})

</script>

<template>

    <label class="form-label">ID della pagina Facebook</label>
    <input type="text"
           class="form-control"
           v-model="form.meta_page_id" />
    <div class="text-red-500 text-center"
         v-if="form && form.errors && form.errors.meta_page_id">{{ __(form.errors.meta_page_id) }}</div>

</template>

<style scoped>

</style>
