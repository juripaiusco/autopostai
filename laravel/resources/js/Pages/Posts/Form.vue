<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import StepBar from '@/Components/Posts/StepBar.vue';
import StepWrite from '@/Components/Posts/StepWrite.vue';
import StepMedia from '@/Components/Posts/StepMedia.vue';
import StepPublish from '@/Components/Posts/StepPublish.vue';

const props = defineProps({
    mode: { type: String, default: 'create' }, // 'create' (per ora l'unica)
    channelsAvailable: { type: Array, default: () => [] },
    prefill: { type: Object, default: null },
});

const userName = computed(() => usePage().props.auth?.user?.name ?? '');

const CHANNELS_CFG = [
    { id: 'facebook', label: 'Facebook', limit: 63206 },
    { id: 'instagram', label: 'Instagram', limit: 2200 },
    { id: 'linkedin', label: 'LinkedIn', limit: 3000 },
    { id: 'wordpress', label: 'WordPress', limit: null },
    { id: 'newsletter', label: 'Newsletter', limit: null },
];

const channels = computed(() => CHANNELS_CFG.filter((c) => props.channelsAvailable.includes(c.id)));

function buildForm() {
    return {
        title: props.prefill?.title ?? '',
        channels: props.prefill?.channels ?? [],
        ai_prompt_post: props.prefill?.ai_prompt_post ?? '',
        comments_enabled: props.prefill?.comments_enabled ?? true,
        auto_reply_enabled: props.prefill?.auto_reply_enabled ?? false,
        ai_prompt_comment: props.prefill?.ai_prompt_comment ?? '',
        ai_content: '',
        image: null,
        imagePreviewUrl: null,
        img_source: null,
        published_at: '',
    };
}

const form = reactive(buildForm());
const step = ref(0);
const saving = ref(false);

const STEP_LABELS = ['Scrivi', 'Media', 'Pubblica'];

function set(key, value) {
    form[key] = value;
}

const canAdvance = computed(() => {
    if (step.value === 0) return form.channels.length > 0;
    return true;
});

function next() {
    if (!canAdvance.value) return;
    step.value = Math.min(step.value + 1, STEP_LABELS.length - 1);
}

function back() {
    step.value = Math.max(step.value - 1, 0);
}

function goto(i) {
    step.value = i;
}

function submit(action) {
    saving.value = true;
    router.post(route('posts.store'), { ...form, action }, {
        forceFormData: true,
        onFinish: () => { saving.value = false; },
    });
}
</script>

<template>
    <Head title="Nuovo post" />

    <AppLayout :current="'posts'" :user="userName">
        <template #page-header>
            <PageHeader :crumbs="[{ label: 'Post' }, { label: 'Nuovo post', current: true }]" />
        </template>

        <div class="acc-content">
            <div class="card card-pad">
                <StepBar :current="step" :labels="STEP_LABELS" @goto="goto" />

                <div class="pf-step-content">
                    <StepWrite v-if="step === 0" :form="form" :channels="channels" @set="set" />
                    <StepMedia v-else-if="step === 1" :form="form" @set="set" />
                    <StepPublish v-else :form="form" :channels="channels" :saving="saving"
                        @set="set" @back="back" @save="submit('save')" @save-and-add="submit('save_and_add')" />
                </div>

                <div v-if="step < 2" class="pf-footer">
                    <button v-if="step > 0" type="button" class="btn btn-secondary" @click="back">← Indietro</button>
                    <div class="spacer"></div>
                    <button type="button" class="btn btn-dark" :disabled="!canAdvance" @click="next">Avanti →</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
