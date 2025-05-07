<script setup>

import {Link} from "@inertiajs/vue3";

const props = defineProps({
    form: Object,
})

</script>

<template>

    <label class="form-label">Client ID</label>
    <input type="text"
           class="form-control"
           v-model="form.linkedin_client_id"
           :disabled="$page.props.auth.user.parent_id" />
    <div class="text-red-500 text-center"
         v-if="form && form.errors && form.errors.linkedin_client_id">{{ __(form.errors.linkedin_client_id) }}</div>

    <br>

    <label class="form-label">Client Secret</label>
    <input type="text"
           class="form-control"
           v-model="form.linkedin_client_secret"
           :disabled="$page.props.auth.user.parent_id" />
    <div class="text-red-500 text-center"
         v-if="form && form.errors && form.errors.linkedin_client_secret">{{ __(form.errors.linkedin_client_secret) }}</div>

    <br>

    <label class="form-label">ID della pagina LinkedIn</label>
    <input type="text"
           class="form-control"
           v-model="form.linkedin_company_urn"
           :disabled="$page.props.auth.user.parent_id" />
    <div class="text-red-500 text-center"
         v-if="form && form.errors && form.errors.linkedin_company_urn">{{ __(form.errors.linkedin_company_urn) }}</div>

    <br>

    <a class="btn btn-primary w-[100%] md:w-[120px]"
       :class="{'disabled': !form.linkedin_client_id}"
       target="_blank"
       :href="route('linkedin.redirect', { linkedin_client_id: form.linkedin_client_id ? form.linkedin_client_id : '' })">
        Get Token
    </a>
    <br>
    <small class="text-gray-500">
        Prima salva i dati (Client ID, ...), poi recupera il token, e poi il tuo account LinkedIn è connesso
    </small>

</template>

<style scoped>

</style>
