<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import Icon from '@/Components/Icon.vue';
import FormField from '@/Components/UI/FormField.vue';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const showPw = ref(false);

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('password.update'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Nuova password" />

    <GuestLayout>
        <form class="auth-card" novalidate @submit.prevent="submit">
            <div class="auth-card__head">
                <h1 class="auth-card__title">Scegli una nuova password</h1>
                <p class="auth-card__sub">Per {{ email }}</p>
            </div>

            <FormField
                id="password"
                v-model="form.password"
                :type="showPw ? 'text' : 'password'"
                label="Nuova password"
                placeholder="Almeno 8 caratteri"
                autocomplete="new-password"
                required
                :autofocus="true"
                :error="form.errors.password"
            >
                <template #trailing>
                    <button
                        type="button"
                        class="pw-toggle"
                        :aria-label="showPw ? 'Nascondi password' : 'Mostra password'"
                        @click="showPw = !showPw"
                    >
                        <Icon :name="showPw ? 'eyeOff' : 'eye'" :size="20" />
                    </button>
                </template>
            </FormField>

            <FormField
                id="password_confirmation"
                v-model="form.password_confirmation"
                :type="showPw ? 'text' : 'password'"
                label="Conferma password"
                placeholder="Ripeti la password"
                autocomplete="new-password"
                required
                :error="form.errors.password_confirmation"
            />

            <button type="submit" class="btn-submit acc-ink" :disabled="form.processing" style="margin-top: 22px">
                <template v-if="form.processing">
                    <span class="auth-spinner" aria-hidden="true"></span> Salvataggio…
                </template>
                <template v-else>Salva nuova password</template>
            </button>
        </form>
    </GuestLayout>
</template>

<!-- Stili condivisi con le altre pagine Auth/*.vue in resources/css/pages/auth.css -->
