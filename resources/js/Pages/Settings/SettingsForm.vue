<script setup>

import SettingsFormTabWordPress from "@/Pages/Settings/formTabs/SettingsFormTabWordPress.vue";
import SettingsFormTabAI from "@/Pages/Settings/formTabs/SettingsFormTabAI.vue";
import SettingsFormTabNewsletter from "@/Pages/Settings/formTabs/SettingsFormTabNewsletter.vue";
import SettingsFormTabGoogle from "@/Pages/Settings/formTabs/SettingsFormTabGoogle.vue";
import SettingsFormTabMeta from "@/Pages/Settings/formTabs/SettingsFormTabMeta.vue";
import SettingsFormTabOpenAI from "@/Pages/Settings/formTabs/SettingsFormTabOpenAI.vue";

const props = defineProps({
    form: Object,
})

</script>

<template>

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

            <!-- <button class="nav-link"
                    id="nav-google-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#nav-google"
                    type="button"
                    role="tab"
                    aria-controls="nav-google"
                    aria-selected="false">Google</button> -->

            <button v-if="!$page.props.auth.user.parent_id && !$page.props.auth.user.child_on"
                    class="nav-link"
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

            <!-- <button class="nav-link"
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
                    aria-selected="false">Newsletter</button> -->

        </div>

    </nav>

    <div class="tab-content" id="nav-tabContent">

        <div class="tab-pane fade show active pt-4"
             id="nav-ai"
             role="tabpanel"
             aria-labelledby="nav-ai-tab"
             tabindex="0">

            <SettingsFormTabAI :form=form />

        </div>

        <div class="tab-pane fade show pt-4"
             id="nav-google"
             role="tabpanel"
             aria-labelledby="nav-google-tab"
             tabindex="0">

            <SettingsFormTabGoogle :form=form />

        </div>

        <div class="tab-pane fade show pt-4"
             id="nav-openai"
             role="tabpanel"
             aria-labelledby="nav-openai-tab"
             tabindex="0">

            <SettingsFormTabOpenAI :form=form />

        </div>

        <div class="tab-pane fade show pt-4"
             id="nav-meta"
             role="tabpanel"
             aria-labelledby="nav-meta-tab"
             tabindex="0">

            <SettingsFormTabMeta :form=form />

        </div>

        <div class="tab-pane fade show pt-4"
             id="nav-wordpress"
             role="tabpanel"
             aria-labelledby="nav-wordpress-tab"
             tabindex="0">

            <SettingsFormTabWordPress :form=form />

        </div>

        <div class="tab-pane fade show pt-4"
             id="nav-newsletter"
             role="tabpanel"
             aria-labelledby="nav-newsletter-tab"
             tabindex="0">

            <SettingsFormTabNewsletter :form=form />

        </div>

    </div>

</template>

<style scoped>
    .tab-content .tab-pane {
        padding-top: 30px !important;
    }
</style>
