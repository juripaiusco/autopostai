<script setup>
import { reactive, ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import SectionCard from '@/Components/Layout/SectionCard.vue';
import Icon from '@/Components/Icon.vue';
import FieldRow from '@/Components/UI/FieldRow.vue';
import SecretField from '@/Components/UI/SecretField.vue';
import AiFields from '@/Components/Domain/Account/AiFields.vue';

const props = defineProps({
    account: { type: Object, required: true },
    isSimpleUser: { type: Boolean, default: false },
    ai: { type: Object, default: null },
});

const userName = computed(() => usePage().props.auth?.user?.name ?? props.account.name);

const activeTab = ref('account');
const form = reactive({
    name: props.account.name,
    email: props.account.email,
    password: '',
    ai: props.isSimpleUser ? { ...props.ai } : { profile: '', knows: '', commentStyle: '' },
});
const dirty = ref(false);
const toast = ref(null);
let toastTimer = null;

function set(path, value) {
    const keys = path.split('.');
    let obj = form;
    for (let i = 0; i < keys.length - 1; i++) obj = obj[keys[i]];
    obj[keys[keys.length - 1]] = value;
    dirty.value = true;
}

function save() {
    router.put(route('settings.update'), form, {
        preserveScroll: true,
        onSuccess: () => {
            form.password = '';
            dirty.value = false;
            toast.value = 'Impostazioni salvate';
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => { toast.value = null; }, 2400);
        },
    });
}
</script>

<template>
    <Head title="Impostazioni" />
    <AppLayout current="settings" :user="userName">
        <template #page-header>
            <PageHeader :crumbs="[{ label: 'Impostazioni', current: true }]">
                <template #actions>
                    <div class="acc-head-meta">
                        <span v-if="dirty" class="acc-dirty">
                            <span class="acc-dirty-dot" />Modifiche non salvate
                        </span>
                        <button type="button" class="btn btn-primary btn-sm" @click="save">
                            <Icon name="check" :size="16" />Salva
                        </button>
                    </div>
                </template>
            </PageHeader>
        </template>

        <div class="acc-content">
            <div class="acc-mtabs" role="tablist">
                <button type="button" class="acc-mtab" role="tab" :aria-selected="activeTab === 'account'"
                    :class="{ active: activeTab === 'account' }" @click="activeTab = 'account'">
                    <Icon name="users" :size="16" />Il mio account
                </button>
                <button v-if="isSimpleUser" type="button" class="acc-mtab" role="tab" :aria-selected="activeTab === 'ai'"
                    :class="{ active: activeTab === 'ai' }" @click="activeTab = 'ai'">
                    <Icon name="sparkles" :size="16" />Profilo AI
                </button>
            </div>

            <div v-if="activeTab === 'account'" class="acc-reveal" style="max-width: 32rem">
                <SectionCard title="Il mio account" subtitle="Le tue credenziali di accesso a FaPer3.">
                    <FieldRow id="me-name" label="Nome">
                        <input id="me-name" class="control" type="text" :value="form.name" @input="set('name', $event.target.value)" />
                    </FieldRow>
                    <FieldRow id="me-email" label="E-mail">
                        <input id="me-email" class="control" type="email" :value="form.email" @input="set('email', $event.target.value)" />
                    </FieldRow>
                    <FieldRow id="me-password" label="Password" style="margin-bottom:0" help="Lascia vuoto per non cambiarla.">
                        <SecretField id="me-password" :model-value="form.password" placeholder="Almeno 8 caratteri"
                            @update:model-value="set('password', $event)" />
                    </FieldRow>
                </SectionCard>
            </div>

            <div v-if="isSimpleUser && activeTab === 'ai'" class="acc-reveal">
                <SectionCard title="Profilo AI"
                    subtitle="Come deve comportarsi l'AI quando scrive per te. Le impostazioni di base le ha definite chi gestisce il tuo account: qui puoi affinarle.">
                    <AiFields :model-value="form.ai" @update="(field, value) => set('ai.' + field, value)" />
                </SectionCard>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="toast" class="acc-toast">
                <Icon name="check" :size="17" />{{ toast }}
            </div>
        </Teleport>
    </AppLayout>
</template>
