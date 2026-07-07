<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import FormField from '@/Components/UI/FormField.vue';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({
    email: '',
});

function submit() {
    form.post(route('password.email'));
}
</script>

<template>
    <Head title="Password dimenticata" />

    <GuestLayout>
        <form class="auth-card" novalidate @submit.prevent="submit">
            <div class="auth-card__head">
                <h1 class="auth-card__title">Password dimenticata?</h1>
                <p class="auth-card__sub">
                    Inserisci la tua email: ti mandiamo un link per sceglierne una nuova.
                </p>
            </div>

            <div v-if="status" class="status-banner" role="status">{{ status }}</div>

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

            <button type="submit" class="btn-submit acc-ink" :disabled="form.processing" style="margin-top: 22px">
                <template v-if="form.processing">
                    <span class="auth-spinner" aria-hidden="true"></span> Invio in corso…
                </template>
                <template v-else>Invia link di reset</template>
            </button>

            <div class="auth-foot">
                <div class="signup">Ti sei ricordato la password? <a :href="route('login')">Accedi</a></div>
            </div>
        </form>
    </GuestLayout>
</template>

<!-- Stili condivisi con le altre pagine Auth/*.vue in resources/css/pages/auth.css -->
