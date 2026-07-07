<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import Icon from '@/Components/Icon.vue';
import FormField from '@/Components/UI/FormField.vue';

const showPw = ref(false);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('register.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Crea account" />

    <GuestLayout>
        <form class="auth-card" novalidate @submit.prevent="submit">
            <div class="auth-card__head">
                <h1 class="auth-card__title">Crea il tuo account</h1>
                <p class="auth-card__sub">
                    Diventa amministratore del tuo spazio FaPer3: potrai poi creare manager e sotto-utenti.
                </p>
            </div>

            <FormField
                id="name"
                v-model="form.name"
                type="text"
                label="Nome"
                placeholder="Es. Trattoria da Marco"
                autocomplete="name"
                required
                :autofocus="true"
                :error="form.errors.name"
            />

            <FormField
                id="email"
                v-model="form.email"
                type="email"
                label="Nome utente"
                placeholder="name@example.com"
                autocomplete="username"
                required
                :error="form.errors.email"
            />

            <FormField
                id="password"
                v-model="form.password"
                :type="showPw ? 'text' : 'password'"
                label="Password"
                placeholder="Almeno 8 caratteri"
                autocomplete="new-password"
                required
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
                    <span class="auth-spinner" aria-hidden="true"></span> Creazione in corso…
                </template>
                <template v-else>Crea account</template>
            </button>

            <div class="auth-foot">
                <div class="signup">Hai già un account? <a :href="route('login')">Accedi</a></div>
                <div class="ver">v.{{ usePage().props.app.version }}</div>
            </div>
        </form>
    </GuestLayout>
</template>

<!-- Stili condivisi con le altre pagine Auth/*.vue in resources/css/pages/auth.css -->

