<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import GuestLayout from '../../Layouts/GuestLayout.vue';

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
            <div class="field" :class="{ 'has-error': form.errors.email }">
                <label class="field-label" for="email">Nome utente</label>
                <div class="input-wrap">
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        name="email"
                        placeholder="name@example.com"
                        autocomplete="username"
                        required
                    />
                </div>
                <div v-if="form.errors.email" class="input-error">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                        <line x1="12" y1="9" x2="12" y2="13" />
                        <line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg>
                    {{ form.errors.email }}
                </div>
            </div>

            <!-- Password -->
            <div class="field field--pw" :class="{ 'has-error': form.errors.password }">
                <label class="field-label" for="password">Password</label>
                <div class="input-wrap">
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPw ? 'text' : 'password'"
                        name="password"
                        placeholder="Inserisci la password"
                        autocomplete="current-password"
                        required
                    />
                    <button
                        type="button"
                        class="pw-toggle"
                        :aria-label="showPw ? 'Nascondi password' : 'Mostra password'"
                        @click="showPw = !showPw"
                    >
                        <svg v-if="showPw" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                            <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                            <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                            <line x1="2" y1="2" x2="22" y2="22" />
                        </svg>
                        <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                    </button>
                </div>
                <div v-if="form.errors.password" class="input-error">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                        <line x1="12" y1="9" x2="12" y2="13" />
                        <line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg>
                    {{ form.errors.password }}
                </div>
            </div>

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
                <div class="ver">v.2.4.1</div>
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

/* ---------- Campo con label impilata ---------- */
.field {
    margin-bottom: 18px;
}
.field-label {
    display: block;
    font-size: var(--text-sm);
    font-weight: var(--w-semibold);
    color: var(--fg1);
    margin: 0 0 7px 2px;
}
.input-wrap {
    position: relative;
}
.field input {
    width: 100%;
    height: 48px;
    padding: 0 14px;
    border: 1px solid var(--border-input);
    border-radius: var(--radius);
    background: var(--bg-surface);
    font-family: inherit;
    font-size: var(--text-base);
    color: var(--fg1);
    box-shadow: var(--shadow-sm);
    transition: border-color .15s ease, box-shadow .15s ease;
}
.field input::placeholder {
    color: var(--gray-400);
}
.field input:focus {
    outline: none;
    border-color: var(--sky);
    box-shadow: 0 0 0 3px rgba(56, 189, 248, .28);
}
.field--pw input {
    padding-right: 46px;
}
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

/* ---------- Stato errore ---------- */
.field.has-error input {
    border-color: var(--danger);
}
.field.has-error input:focus {
    box-shadow: 0 0 0 3px rgba(220, 53, 69, .22);
}
.field.has-error .field-label {
    color: var(--danger);
}
.input-error {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 7px 4px 0;
    font-size: var(--text-xs);
    color: var(--status-error-fg);
    font-weight: var(--w-medium);
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
