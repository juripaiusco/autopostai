<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import StepBar from '@/Components/Posts/StepBar.vue';
import StepWrite from '@/Components/Posts/StepWrite.vue';
import StepMedia from '@/Components/Posts/StepMedia.vue';
import StepPublish from '@/Components/Posts/StepPublish.vue';

const props = defineProps({
    mode: { type: String, default: 'create' }, // 'create' | 'edit'
    channelsMeta: { type: Object, default: () => ({}) }, // { [channelId]: { available, replyOn } }
    users: { type: Array, default: () => [] },
    prefill: { type: Object, default: null },
    post: { type: Object, default: null },
});

const userName = computed(() => usePage().props.auth?.user?.name ?? '');

const CHANNELS_CFG = [
    { id: 'facebook', label: 'Facebook', limit: 63206 },
    { id: 'instagram', label: 'Instagram', limit: 2200 },
    { id: 'linkedin', label: 'LinkedIn', limit: 3000 },
    { id: 'wordpress', label: 'WordPress', limit: null },
    { id: 'newsletter', label: 'Newsletter', limit: null },
];

const selectedUser = computed(() => props.users.find((u) => u.id === form.user_id));

// Sempre tutti i 5 canali: quelli non abilitati sull'account restano visibili
// ma disattivati, cosi' si vede cosa gestisce FaPer3 anche se non e' collegato.
const activeChannelsMeta = computed(() => {
    if (props.mode === 'edit') return props.channelsMeta;
    if (props.users.length > 0) return selectedUser.value?.channelsMeta ?? {};
    return props.channelsMeta;
});

const channels = computed(() => CHANNELS_CFG.map((c) => ({
    ...c,
    available: !!activeChannelsMeta.value[c.id]?.available,
    replyOn: !!activeChannelsMeta.value[c.id]?.replyOn,
})));

function defaultChannelOptions(id) {
    if (id === 'wordpress') return { categories: [] };
    if (id === 'newsletter') return { list: null };
    return { comments_enabled: false, auto_reply_enabled: false };
}

// Il post gia' salvato ha 'on'/'available' misti alle opzioni nel JSON grezzo:
// qui si tiene solo cio' che serve al form (i canali selezionati -> opzioni).
function channelsFromPost(rawChannels) {
    const result = {};
    for (const [id, data] of Object.entries(rawChannels ?? {})) {
        if (!data?.on) continue;
        const { on, ...opts } = data;
        result[id] = { ...defaultChannelOptions(id), ...opts };
    }
    return result;
}

function buildForm() {
    if (props.mode === 'edit' && props.post) {
        return {
            title: props.post.title ?? '',
            channels: channelsFromPost(props.post.channels),
            ai_prompt_post: props.post.ai_prompt_post ?? '',
            ai_prompt_comment: props.post.ai_prompt_comment ?? '',
            ai_content: props.post.ai_content ?? '',
            existingImages: (props.post.images ?? []).map((i) => ({ filename: i.filename, url: i.url })),
            newImages: [],
            img_source: props.post.img_source ?? null,
            published_at: props.post.published_at ?? '',
        };
    }

    return {
        title: props.prefill?.title ?? '',
        user_id: null,
        channels: {},
        ai_prompt_post: props.prefill?.ai_prompt_post ?? '',
        ai_prompt_comment: props.prefill?.ai_prompt_comment ?? '',
        ai_content: '',
        existingImages: [],
        newImages: [],
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

function toggleChannel(id) {
    if (form.channels[id]) {
        delete form.channels[id];
    } else {
        form.channels[id] = defaultChannelOptions(id);
    }
}

function setChannelOption(id, field, value) {
    form.channels[id] = { ...form.channels[id], [field]: value };
}

if (props.mode === 'create' && props.users.length > 0) {
    watch(() => form.user_id, () => { form.channels = {}; });
}

const canAdvance = computed(() => {
    if (step.value === 0) {
        if (props.mode === 'create' && props.users.length > 0 && !form.user_id) return false;
        return Object.keys(form.channels).length > 0;
    }
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

// Account per cui si sta scrivendo — serve per gli endpoint di fetch live
// (categorie WordPress, liste Newsletter), che leggono le credenziali di
// QUELL'account, non necessariamente di chi sta compilando il form.
const targetUserId = computed(() => {
    if (props.mode === 'edit') return props.post.ownerId;
    if (props.users.length > 0) return form.user_id;
    return usePage().props.auth?.user?.id ?? null;
});

function buildPayload(action) {
    const { existingImages, newImages, ...rest } = form;

    return {
        ...rest,
        action,
        images: newImages.map((i) => i.file),
        keep_images: JSON.stringify(existingImages.map((i) => i.filename)),
    };
}

function submit(action) {
    saving.value = true;
    const payload = buildPayload(action);
    if (props.mode === 'edit') {
        router.put(route('posts.update', props.post.id), payload, {
            forceFormData: true,
            onFinish: () => { saving.value = false; },
        });
        return;
    }
    router.post(route('posts.store'), payload, {
        forceFormData: true,
        onFinish: () => { saving.value = false; },
    });
}
</script>

<template>
    <Head :title="mode === 'edit' ? 'Modifica post' : 'Nuovo post'" />

    <AppLayout :current="'posts'" :user="userName">
        <template #page-header>
            <PageHeader :crumbs="[{ label: 'Post', href: route('posts') }, { label: mode === 'edit' ? 'Modifica post' : 'Nuovo post', current: true }]" />
        </template>

        <div class="acc-content">
            <div class="card card-pad">
                <StepBar :current="step" :labels="STEP_LABELS" :free-nav="mode === 'edit'" @goto="goto" />

                <Transition name="pf-step" mode="out-in">
                    <div class="pf-step-content" :key="step">
                        <StepWrite v-if="step === 0" :form="form" :channels="channels" :users="users"
                            :mode="mode" :owner="post?.owner ?? null" :target-user-id="targetUserId"
                            @set="set" @toggle-channel="toggleChannel" @set-channel-option="setChannelOption" />
                        <StepMedia v-else-if="step === 1" :form="form" :target-user-id="targetUserId" @set="set" />
                        <StepPublish v-else :form="form" :channels="channels" :saving="saving" :mode="mode"
                            :target-user-id="targetUserId"
                            @set="set" @back="back" @save="submit('save')" @save-and-add="submit('save_and_add')" />
                    </div>
                </Transition>

                <div v-if="step < 2" class="pf-footer">
                    <button v-if="step > 0" type="button" class="btn btn-secondary" @click="back">← Indietro</button>
                    <div class="spacer"></div>
                    <button type="button" class="btn btn-dark" :disabled="!canAdvance" @click="next">Avanti →</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
