<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, useForm} from '@inertiajs/vue3';
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import Meta from "@/Pages/Settings/formTabs/Meta.vue";
import AI from "@/Pages/Settings/formTabs/AI.vue";
import Google from "@/Pages/Settings/formTabs/Google.vue";
import OpenAI from "@/Pages/Settings/formTabs/OpenAI.vue";
import WordPress from "@/Pages/Settings/formTabs/WordPress.vue";
import Newsletter from "@/Pages/Settings/formTabs/Newsletter.vue";

const props = defineProps({
    data: Object,
})

const dataForm = Object.fromEntries(Object.entries(props.data).map((v) => {
    return props.data ? v : '';
}));

const form = useForm(dataForm);

</script>

<template>
    <Head title="Impostazioni" />

    <AuthenticatedLayout>
        <template #header>

            <ApplicationHeader :breadcrumb-array="['Impostazioni']" />

        </template>

        <ApplicationContainer>

            <form @submit.prevent="form.post(route(
                form.id ? 'settings.update' : 'settings.store',
                form.id ? form.id : ''
                ))">

                <nav>
                    <div class="nav nav-underline nav-fill" id="nav-tab" role="tablist">

                        <button class="nav-link active"
                                id="nav-ai-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#nav-ai"
                                type="button"
                                role="tab"
                                aria-controls="nav-ai"
                                aria-selected="true">AI</button>

                        <button class="nav-link"
                                id="nav-google-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#nav-google"
                                type="button"
                                role="tab"
                                aria-controls="nav-google"
                                aria-selected="false">Google</button>

                        <button class="nav-link"
                                id="nav-openai-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#nav-openai"
                                type="button"
                                role="tab"
                                aria-controls="nav-openai"
                                aria-selected="false">OpenAI</button>

                        <button class="nav-link"
                                id="nav-meta-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#nav-meta"
                                type="button"
                                role="tab"
                                aria-controls="nav-meta"
                                aria-selected="false">Meta</button>

                        <button class="nav-link"
                                id="nav-wordpress-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#nav-wordpress"
                                type="button"
                                role="tab"
                                aria-controls="nav-wordpress"
                                aria-selected="false">WordPress</button>

                        <button class="nav-link"
                                id="nav-newsletter-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#nav-newsletter"
                                type="button"
                                role="tab"
                                aria-controls="nav-newsletter"
                                aria-selected="false">Newsletter</button>

                    </div>

                </nav>

                <div class="tab-content" id="nav-tabContent">

                    <div class="tab-pane fade show active pt-4"
                         id="nav-ai"
                         role="tabpanel"
                         aria-labelledby="nav-ai-tab"
                         tabindex="0">

                        <AI :form=form />

                    </div>

                    <div class="tab-pane fade show pt-4"
                         id="nav-google"
                         role="tabpanel"
                         aria-labelledby="nav-google-tab"
                         tabindex="0">

                        <Google :form=form />

                    </div>

                    <div class="tab-pane fade show pt-4"
                         id="nav-openai"
                         role="tabpanel"
                         aria-labelledby="nav-openai-tab"
                         tabindex="0">

                        <OpenAI :form=form />

                    </div>

                    <div class="tab-pane fade show pt-4"
                         id="nav-meta"
                         role="tabpanel"
                         aria-labelledby="nav-meta-tab"
                         tabindex="0">

                        <Meta :form=form />

                    </div>

                    <div class="tab-pane fade show pt-4"
                         id="nav-wordpress"
                         role="tabpanel"
                         aria-labelledby="nav-wordpress-tab"
                         tabindex="0">

                        <WordPress :form=form />

                    </div>

                    <div class="tab-pane fade show pt-4"
                         id="nav-newsletter"
                         role="tabpanel"
                         aria-labelledby="nav-newsletter-tab"
                         tabindex="0">

                        <Newsletter :form=form />

                    </div>

                </div>

                <div class="text-right mt-10">

                    <button type="submit"
                            class="btn btn-success ml-2 w-[120px]">Salva</button>

                </div>

            </form>

        </ApplicationContainer>

    </AuthenticatedLayout>
</template>

<style scoped>
    .tab-content .tab-pane {
        padding-top: 30px !important;
    }
</style>
