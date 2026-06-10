<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import Icon from '@/Components/Icon.vue';
import FormField from '@/Components/UI/FormField.vue';

defineProps({
    // Messaggio di stato flashato in sessione (es. dopo logout). Opzionale.
    status: { type: String, default: null },
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
                <!-- Reset password non ancora implementato (feature Fortify disattivata) -->
                <a href="#" class="forgot" @click.prevent>Password dimenticata?</a>
            </div>

            <button type="submit" class="btn-submit acc-ink" :disabled="form.processing">
                <template v-if="form.processing">
                    <span class="btn-spinner" aria-hidden="true"></span> Accesso in corso…
                </template>
                <template v-else>Accedi</template>
            </button>

            <div class="auth-foot">
                <!-- Registrazione non ancora implementata (feature Fortify disattivata) -->
                <div class="signup">Non hai un account? <a href="#" @click.prevent>Registrati</a></div>
                <div class="ver">v.{{ usePage().props.app.version }}</div>
            </div>
        </form>
    </GuestLayout>
</template>

<style scoped>
/* ---------- Card ---------- */
.auth-card {
    width: 100%;
    max-width: 28rem;
    background: var(--bg-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    padding: 36px 36px 28px;
}
.auth-card__head {
    margin-bottom: 26px;
}
.auth-card__title {
    font-size: var(--text-2xl);
    font-weight: var(--w-bold);
    letter-spacing: -0.01em;
    margin: 0 0 6px;
    color: var(--fg1);
}
.auth-card__sub {
    font-size: var(--text-sm);
    color: var(--fg2);
    margin: 0;
    line-height: 1.5;
}

/* ---------- Banner di stato ---------- */
.status-banner {
    margin-bottom: 18px;
    font-size: var(--text-sm);
    font-weight: var(--w-medium);
    color: var(--status-published-fg);
    background: color-mix(in srgb, var(--status-published-bg) 55%, white);
    border: 1px solid var(--status-published-border);
    border-radius: var(--radius);
    padding: 10px 12px;
}

/* ---------- Toggle mostra/nascondi password (adornment dello slot #trailing) ----------
   Gli stili del campo (.field*, .input-wrap, .input-error) sono globali in app.css. */
.pw-toggle {
    position: absolute;
    right: 7px;
    top: 50%;
    transform: translateY(-50%);
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: var(--fg3);
    border-radius: var(--radius);
    cursor: pointer;
    transition: color .15s ease, background .15s ease;
}
.pw-toggle:hover {
    color: var(--fg2);
    background: var(--bg-sunken);
}
.pw-toggle:focus-visible {
    outline: 2px solid var(--sky);
    outline-offset: 1px;
}

/* ---------- Riga: ricordami + password dimenticata ---------- */
.form-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 6px 2px 22px;
}
.remember {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    font-size: var(--text-sm);
    color: var(--fg2);
    cursor: pointer;
    user-select: none;
}
.remember input {
    appearance: none;
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    margin: 0;
    border: 1px solid var(--border-input);
    border-radius: var(--radius-sm);
    background: var(--bg-surface);
    cursor: pointer;
    position: relative;
    transition: background .15s ease, border-color .15s ease;
}
.remember input:checked {
    background: var(--sky);
    border-color: var(--sky);
}
.remember input:checked::after {
    content: "";
    position: absolute;
    left: 5px;
    top: 1.5px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}
.remember input:focus-visible {
    outline: 2px solid var(--sky);
    outline-offset: 2px;
}
.forgot {
    font-size: var(--text-sm);
    color: var(--sky-strong);
    text-decoration: none;
    font-weight: var(--w-medium);
    white-space: nowrap;
}
.forgot:hover {
    text-decoration: underline;
}

/* ---------- Bottone submit ---------- */
.btn-submit {
    width: 100%;
    height: 50px;
    border: 1px solid transparent;
    border-radius: var(--radius);
    font-family: inherit;
    font-size: var(--text-sm);
    font-weight: var(--w-semibold);
    text-transform: uppercase;
    letter-spacing: .12em;
    color: #fff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: background .15s ease, box-shadow .15s ease, transform .05s ease;
}
.btn-submit:active {
    transform: translateY(1px);
}
.btn-submit:disabled {
    cursor: default;
    opacity: .65;
}
.btn-submit.acc-ink {
    background: var(--ink);
}
.btn-submit.acc-ink:hover:not(:disabled) {
    background: var(--gray-700);
}
.btn-spinner {
    width: 17px;
    height: 17px;
    border: 2.5px solid rgba(255, 255, 255, .4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin .7s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ---------- Footer / versione ---------- */
.auth-foot {
    margin-top: 22px;
    text-align: center;
    font-size: var(--text-xs);
    color: var(--gray-300);
}
.auth-foot .signup {
    color: var(--fg2);
}
.auth-foot .signup a {
    color: var(--sky-strong);
    font-weight: var(--w-semibold);
    text-decoration: none;
}
.auth-foot .signup a:hover {
    text-decoration: underline;
}
.auth-foot .ver {
    margin-top: 14px;
    color: var(--gray-300);
}

@media (prefers-reduced-motion: reduce) {
    .btn-spinner { animation-duration: 0s; }
}
</style>
