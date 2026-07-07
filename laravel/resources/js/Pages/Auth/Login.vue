<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import Icon from '@/Components/Icon.vue';
import FormField from '@/Components/UI/FormField.vue';

defineProps({
    // Messaggio di stato flashato in sessione (es. dopo logout). Opzionale.
    status: { type: String, default: null },
    canResetPassword: { type: Boolean, default: false },
    canRegister: { type: Boolean, default: false },
});

const showPw = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    // useForm di Inertia 3 preserva i valori al fallimento: manteniamo l'email
    // (e gli errori) visibili, ripulendo solo la password.
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Accedi" />

    <GuestLayout>
        <form class="auth-card" novalidate @submit.prevent="submit">
            <div class="auth-card__head">
                <h1 class="auth-card__title">Bentornato</h1>
                <p class="auth-card__sub">
                    Accedi a FaPer3 per gestire e pubblicare i tuoi contenuti su tutti i canali.
                </p>
            </div>

            <div v-if="status" class="status-banner" role="status">{{ status }}</div>

            <!-- Nome utente (email) -->
            <FormField
                id="email"
                v-model="form.email"
                type="email"
                label="Nome utente"
                placeholder="name@example.com"
                autocomplete="username"
                required
                :autofocus="true"
                :error="form.errors.email"
            />

            <!-- Password -->
            <FormField
                id="password"
                v-model="form.password"
                :type="showPw ? 'text' : 'password'"
                label="Password"
                placeholder="Inserisci la password"
                autocomplete="current-password"
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

            <!-- Ricordami + Password dimenticata -->
            <div class="form-row">
                <label class="remember">
                    <input v-model="form.remember" type="checkbox" />
                    <span>Ricordami</span>
                </label>
                <a v-if="canResetPassword" :href="route('password.request')" class="forgot">Password dimenticata?</a>
            </div>

            <button type="submit" class="btn-submit acc-ink" :disabled="form.processing">
                <template v-if="form.processing">
                    <span class="auth-spinner" aria-hidden="true"></span> Accesso in corso…
                </template>
                <template v-else>Accedi</template>
            </button>

            <div class="auth-foot">
                <div v-if="canRegister" class="signup">Non hai un account? <a :href="route('register')">Registrati</a></div>
                <div class="ver">v.{{ usePage().props.app.version }}</div>
            </div>
        </form>
    </GuestLayout>
</template>

<!-- Stili condivisi con le altre pagine Auth/*.vue in resources/css/pages/auth.css -->

