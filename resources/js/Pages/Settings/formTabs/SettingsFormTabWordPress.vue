<script setup>

const props = defineProps({
    form: Object,
})

</script>

<template>

    <label class="form-label">URL</label>
    <input type="text"
           class="form-control"
           v-model="form.wordpress_url" />
    <div class="text-red-500 text-center"
         v-if="form && form.errors && form.errors.wordpress_url">{{ __(form.errors.wordpress_url) }}</div>

    <br>

    <label class="form-label">Username</label>
    <input type="text"
           class="form-control"
           v-model="form.wordpress_username" />
    <div class="text-red-500 text-center"
         v-if="form && form.errors && form.errors.wordpress_username">{{ __(form.errors.wordpress_username) }}</div>

    <br>

    <label class="form-label">Password</label>
    <input type="text"
           class="form-control"
           v-model="form.wordpress_password" />
    <div class="text-red-500 text-center"
         v-if="form && form.errors && form.errors.wordpress_password">{{ __(form.errors.wordpress_password) }}</div>

    <br>

    <label class="form-label">Categoria ID</label>
    <input type="text"
           class="form-control"
           v-model="form.wordpress_cat_id" />
    <div class="text-red-500 text-center"
         v-if="form && form.errors && form.errors.wordpress_cat_id">{{ __(form.errors.wordpress_cat_id) }}</div>

</template>

<style scoped>

</style>
