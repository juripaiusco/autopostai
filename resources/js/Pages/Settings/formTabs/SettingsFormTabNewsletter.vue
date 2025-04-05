<script setup>

const props = defineProps({
    form: Object,
})

</script>

<template>

    <div class="card">
        <div class="card-header">
            MailChimp
        </div>
        <div class="card-body">

            <label class="form-label">API Key</label>
            <input type="text"
                   class="form-control"
                   v-model="form.mailchimp_api" />
            <div class="text-red-500 text-center"
                 v-if="form && form.errors && form.errors.mailchimp_api">{{ __(form.errors.mailchimp_api) }}</div>

            <br>

            <div class="row">
                <div class="col-lg">

                    <label class="form-label">Datacenter</label>
                    <input type="text"
                           class="form-control"
                           v-model="form.mailchimp_datacenter" />
                    <div class="text-red-500 text-center"
                         v-if="form && form.errors && form.errors.mailchimp_datacenter">{{ __(form.errors.mailchimp_datacenter) }}</div>

                </div>
                <div class="col-lg">

                    <label class="form-label">List ID</label>
                    <input type="text"
                           class="form-control"
                           v-model="form.mailchimp_list_id" />
                    <div class="text-red-500 text-center"
                         v-if="form && form.errors && form.errors.mailchimp_list_id">{{ __(form.errors.mailchimp_list_id) }}</div>

                </div>
            </div>

            <br>

            <div class="row">
                <div class="col-lg">

                    <label class="form-label">From Name</label>
                    <input type="text"
                           class="form-control"
                           v-model="form.mailchimp_from_name" />
                    <div class="text-red-500 text-center"
                         v-if="form && form.errors && form.errors.mailchimp_from_name">{{ __(form.errors.mailchimp_from_name) }}</div>

                </div>
                <div class="col-lg">

                    <label class="form-label">From Email</label>
                    <input type="text"
                           class="form-control"
                           v-model="form.mailchimp_from_email" />
                    <div class="text-red-500 text-center"
                         v-if="form && form.errors && form.errors.mailchimp_from_email">{{ __(form.errors.mailchimp_from_email) }}</div>

                </div>
            </div>

            <br>

            <label class="form-label">Template</label>
            <textarea class="form-control h-[300px]"
                      v-model="form.mailchimp_template"></textarea>

            <br>

            <label class="form-label">CTA Template</label>
            <textarea class="form-control h-[300px]"
                      v-model="form.mailchimp_template_cta"></textarea>

        </div>
    </div>

    <!-- <br>

    <div class="card">
        <div class="card-header">
            Brevo
        </div>
        <div class="card-body">

            <label class="form-label">API Key</label>
            <input type="text"
                   class="form-control"
                   v-model="form.brevo_api" />
            <div class="text-red-500 text-center"
                 v-if="form && form.errors && form.errors.brevo_api">{{ __(form.errors.brevo_api) }}</div>

            <br>

        </div>
    </div> -->

</template>

<style scoped>

</style>
