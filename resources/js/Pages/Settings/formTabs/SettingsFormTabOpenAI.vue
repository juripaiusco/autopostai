<script setup>

const props = defineProps({
    form: Object,
})

</script>

<template>

    <label class="form-label">API Key</label>
    <input type="text"
           class="form-control"
           v-model="form.openai_api_key" />
    <div class="text-red-500 text-center"
         v-if="form && form.errors && form.errors.openai_api_key">{{ __(form.errors.openai_api_key) }}</div>

</template>

<style scoped>

</style>
