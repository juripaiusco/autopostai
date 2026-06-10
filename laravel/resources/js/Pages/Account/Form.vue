<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import SecretField from '@/Components/UI/SecretField.vue';
import NumberStepper from '@/Components/UI/NumberStepper.vue';
import ConnectionBadge from '@/Components/UI/ConnectionBadge.vue';
import ToggleSwitch from '@/Components/UI/ToggleSwitch.vue';

const props = defineProps({
    mode: { type: String, default: 'create' }, // 'create' | 'edit'
    account: { type: Object, default: null },
    managers: { type: Array, default: () => [] },
});

const userName = computed(() => usePage().props.auth?.user?.name ?? '');

/* ------------------------------------------------------------------ */
/* Static config                                                        */
/* ------------------------------------------------------------------ */

const CHANNELS = [
    { id: 'facebook',   label: 'Facebook',   ic: 'fb', kind: 'social', meta: 'Pubblica post e foto sulla pagina' },
    { id: 'instagram',  label: 'Instagram',  ic: 'ig', kind: 'social', meta: 'Pubblica post e caroselli sul profilo' },
    { id: 'linkedin',   label: 'LinkedIn',   ic: 'li', kind: 'social', meta: 'Pubblica aggiornamenti sulla pagina' },
    { id: 'wordpress',  label: 'WordPress',  ic: 'wp', kind: 'social', meta: 'Pubblica articoli sul blog' },
    { id: 'newsletter', label: 'Newsletter', ic: 'nl', kind: 'mail',   meta: 'Invia campagne email agli iscritti' },
];

const CH_COPY = {
    social: {
        optTitle: 'Rispondi ai commenti',
        optHelp: "L'AI risponde automaticamente ai commenti ricevuti su questo canale.",
        countTitle: 'A quanti commenti rispondere',
        countHelp: 'Numero massimo di commenti a cui rispondere.',
        unit: 'commenti',
    },
    mail: {
        optTitle: 'Rispondi alle email',
        optHelp: "Per la Newsletter le risposte non sono commenti pubblici: l'AI risponde via email.",
        countTitle: 'A quante email rispondere',
        countHelp: 'Numero massimo di email a cui rispondere.',
        unit: 'email',
    },
};

const INTEGRATIONS = [
    { id: 'ai',         name: 'Intelligenza Artificiale', sub: 'Profilo, conoscenze e stile di risposta' },
    { id: 'openai',     name: 'OpenAI',                   sub: 'Chiave API per la generazione' },
    { id: 'meta',       name: 'Meta',                     sub: 'Collegamento Facebook e Instagram' },
    { id: 'linkedin',   name: 'LinkedIn',                 sub: 'Credenziali OAuth e pagina' },
    { id: 'wordpress',  name: 'WordPress',                sub: 'Sito, credenziali e categoria' },
    { id: 'newsletter', name: 'Newsletter',               sub: 'MailChimp · Brevo · SMTP custom' },
];

const INTG_TINT = {
    ai:         { bg: 'var(--sky-50)',              fg: 'var(--sky-strong)' },
    openai:     { bg: 'rgba(16,163,127,.12)',        fg: '#10a37f' },
    meta:       { bg: 'rgba(24,119,242,.12)',        fg: 'var(--ch-fb)' },
    linkedin:   { bg: 'rgba(10,102,194,.12)',        fg: 'var(--ch-li)' },
    wordpress:  { bg: 'rgba(33,117,155,.12)',        fg: 'var(--ch-wp)' },
    newsletter: { bg: 'rgba(75,85,99,.12)',          fg: 'var(--ch-nl)' },
};

const NL_PROVIDERS = [
    { id: 'mailchimp', name: 'MailChimp',   sub: 'API key + audience' },
    { id: 'brevo',     name: 'Brevo',       sub: 'API key + lista' },
    { id: 'smtp',      name: 'SMTP custom', sub: 'Server email proprio' },
];

/* ------------------------------------------------------------------ */
/* Form state                                                           */
/* ------------------------------------------------------------------ */

const CH_TO_INTG = { facebook: 'meta', instagram: 'meta', linkedin: 'linkedin', wordpress: 'wordpress', newsletter: 'newsletter' };

function defaultChannels() {
    return Object.fromEntries(
        CHANNELS.map(ch => [ch.id, { id: null, on: null, reply_on: null, reply_n: 5, options: [] }])
    );
}

function buildForm(account) {
    if (!account) {
        return {
            name: '', email: '', password: '',
            canSubusers: false, manager: '', subusersLimit: '', tokensMonth: '', imagesDay: '',
            channels: defaultChannels(),
            ai: { profile: '', knows: '', commentStyle: '' },
            openai:    { apiKey: '', connected: false },
            meta:      { pageId: '', connected: false },
            linkedin:  { clientId: '', clientSecret: '', pageId: '', token: '', connected: false },
            wordpress: { url: '', username: '', password: '', categoryId: '', connected: false },
            newsletter: {
                mailchimp: { apiKey: '', serverPrefix: '', audienceId: '', connected: false },
                brevo:     { apiKey: '', listId: '', sender: '', connected: false },
                smtp:      { host: '', port: '587', username: '', password: '', encryption: 'tls', sender: '', connected: false },
            },
            usage: null,
        };
    }
    return { ...account, channels: { ...defaultChannels(), ...account.channels } };
}

const form = reactive(buildForm(props.account));
const dirty = ref(false);

function set(path, value) {
    const keys = path.split('.');
    let obj = form;
    for (let i = 0; i < keys.length - 1; i++) obj = obj[keys[i]];
    obj[keys[keys.length - 1]] = value;
    dirty.value = true;
}

/* ------------------------------------------------------------------ */
/* UI state                                                             */
/* ------------------------------------------------------------------ */

const activeTab  = ref('profile');
const activeIntg = ref('ai');
const openNl     = ref('mailchimp');
const toast      = ref(null);
let toastTimer   = null;

const activeCh  = computed(() => CHANNELS.filter(c => form.channels[c.id].on).length);
const title     = computed(() => props.mode === 'create' ? 'Nuovo account' : (form.name || 'Account'));
const saveLabel = computed(() => props.mode === 'create' ? 'Crea account' : 'Salva');

/* ------------------------------------------------------------------ */
/* Completion widget                                                    */
/* ------------------------------------------------------------------ */

const completion = computed(() => {
    const checks = [
        !!form.name,
        !!form.email,
        props.mode === 'edit' || !!(form.password && form.password.replace(/•/g, '').length > 0),
        !!form.tokensMonth,
        !!form.imagesDay,
        CHANNELS.some(c => form.channels[c.id].on),
        !!(form.ai.profile && form.ai.profile.length > 30),
        !!form.openai.apiKey,
        !!(form.meta.connected || form.linkedin.connected || form.wordpress.connected ||
           form.newsletter.mailchimp.connected || form.newsletter.brevo.connected || form.newsletter.smtp.connected),
    ];
    return Math.round(checks.filter(Boolean).length / checks.length * 100);
});

const completionColor = computed(() => {
    if (completion.value >= 80) return 'var(--st-pub-bd)';
    if (completion.value < 40)  return 'var(--st-sch-bd)';
    return 'var(--sky)';
});

const completionHint = computed(() => {
    const p = completion.value;
    if (p === 0)   return "Inizia la configurazione dell'account";
    if (p < 40)    return 'Completa i dati di accesso e attiva un canale';
    if (p < 70)    return 'Configurazione in corso…';
    if (p < 100)   return 'Quasi pronto! Aggiungi le credenziali mancanti';
    return 'Account completamente configurato ✓';
});

/* ------------------------------------------------------------------ */
/* Actions                                                              */
/* ------------------------------------------------------------------ */

function doSave() {
    if (props.mode === 'create') {
        router.post(route('account.store'), form, {
            onSuccess: () => { showToast('Account creato'); dirty.value = false; },
        });
    } else {
        router.put(route('account.update', props.account?.id), form, {
            onSuccess: () => { showToast('Modifiche salvate'); dirty.value = false; },
        });
    }
}

function doCancel() {
    router.get(route('account'));
}

function showToast(msg) {
    toast.value = msg;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.value = null; }, 2400);
}

function getLinkedinToken() {
    showToast('Funzione in arrivo: collegamento OAuth LinkedIn');
}

/* ------------------------------------------------------------------ */
/* Connection state helpers                                             */
/* ------------------------------------------------------------------ */

function intgConn(id) {
    if (id === 'ai') return null;
    if (id === 'newsletter') {
        const n = form.newsletter;
        return (n.mailchimp.connected || n.brevo.connected || n.smtp.connected) ? 'ok' : 'off';
    }
    return form[id]?.connected ? 'ok' : 'off';
}

function nlConn(providerId) {
    return form.newsletter[providerId]?.connected ? 'ok' : 'off';
}

function chConn(chId) {
    return intgConn(CH_TO_INTG[chId]);
}

function onToggleReplyOn(chId, value) {
    set('channels.' + chId + '.reply_on', value);
    if (value && form.channels[chId].reply_n == null) {
        set('channels.' + chId + '.reply_n', 5);
    }
}

function goToIntegration(chId) {
    activeTab.value = 'imp';
    activeIntg.value = CH_TO_INTG[chId];
}
</script>

<template>
    <Head :title="title" />

    <AppLayout :current="'account'" :user="userName">

        <!-- Completion widget injected into sidebar footer -->
        <template #sidebar-footer-top>
            <div class="acc-completion">
                <div class="acc-completion-row">
                    <span class="acc-completion-label">Compilazione account</span>
                    <span class="acc-completion-pct" :style="{ color: completionColor }">{{ completion }}%</span>
                </div>
                <div class="acc-completion-bar">
                    <div class="acc-completion-fill" :style="{ width: completion + '%', background: completionColor }" />
                </div>
                <div class="acc-completion-hint" :style="{ color: completion >= 80 ? 'var(--st-pub-fg)' : 'var(--g400)' }">
                    {{ completionHint }}
                </div>
            </div>
        </template>

        <template #page-header>
            <PageHeader :crumbs="[{ label: 'Account' }, { label: title, current: true }]">
                <template #actions>
                    <div class="acc-head-meta">
                        <span v-if="dirty" class="acc-dirty">
                            <span class="acc-dirty-dot" />Modifiche non salvate
                        </span>
                        <span v-else-if="mode === 'edit'" class="acc-chip acc-chip--active">
                            <span class="acc-chip-dot acc-chip-dot--ok" />Account attivo
                        </span>
                        <span v-else class="acc-chip">
                            <Icon name="info" :size="14" />Bozza non salvata
                        </span>
                        <button type="button" class="btn btn-secondary btn-sm" @click="doCancel">Annulla</button>
                        <button type="button" class="btn btn-primary btn-sm" @click="doSave">
                            <Icon name="check" :size="16" />{{ saveLabel }}
                        </button>
                    </div>
                </template>
            </PageHeader>
        </template>

        <!-- Main content -->
        <div class="acc-content">

            <!-- Workspace tabs -->
            <div class="acc-mtabs" role="tablist">
                <button type="button" class="acc-mtab" role="tab" id="acc-tab-profile" :aria-selected="activeTab === 'profile'" aria-controls="acc-panel-profile"
                    :class="{ active: activeTab === 'profile' }" @click="activeTab = 'profile'">
                    <Icon name="users" :size="16" />Profilo &amp; Piano
                </button>
                <button type="button" class="acc-mtab" role="tab" id="acc-tab-canali" :aria-selected="activeTab === 'canali'" aria-controls="acc-panel-canali"
                    :class="{ active: activeTab === 'canali' }" @click="activeTab = 'canali'">
                    <Icon name="chat" :size="16" />Canali
                    <span class="ct">{{ activeCh }}</span>
                </button>
                <button type="button" class="acc-mtab" role="tab" id="acc-tab-imp" :aria-selected="activeTab === 'imp'" aria-controls="acc-panel-imp"
                    :class="{ active: activeTab === 'imp' }" @click="activeTab = 'imp'">
                    <Icon name="sparkles" :size="16" />AI &amp; Integrazioni
                </button>
            </div>

            <!-- ──────────────── Tab: Profilo & Piano ──────────────── -->
            <div v-if="activeTab === 'profile'" id="acc-panel-profile" role="tabpanel" aria-labelledby="acc-tab-profile" class="acc-grid-2 acc-grid-2--equal acc-grid-2--profile acc-reveal">

                <!-- Profilo Account -->
                <div class="acc-card">
                    <div class="acc-sec-head" :class="{ 'with-aside': mode === 'edit' }">
                        <div>
                            <div class="acc-sec-title">Profilo Account</div>
                            <div class="acc-sec-sub">
                                {{ mode === 'edit' ? 'Dati di accesso dell\'account.' : 'Sta venendo creato da ' + (account?.createdBy ?? userName) }}
                            </div>
                        </div>
                        <span v-if="mode === 'edit'" class="acc-chip">
                            <Icon name="pencil" :size="14" />Modificato {{ account?.updatedAt ?? '—' }}
                        </span>
                    </div>

                    <div class="acc-field">
                        <label class="acc-row-label" for="acc-name">Nome</label>
                        <span class="acc-row-help">Solo ad uso interno, non viene pubblicato.</span>
                        <input id="acc-name" class="control" type="text" :value="form.name" placeholder="Es. Trattoria da Marco"
                            @input="set('name', $event.target.value)" />
                    </div>
                    <div class="acc-field">
                        <label class="acc-row-label" for="acc-email">E-mail</label>
                        <span class="acc-row-help">Serve per l'accesso. Solo ad uso interno, non viene pubblicata.</span>
                        <input id="acc-email" class="control" type="email" :value="form.email" placeholder="nome@dominio.it"
                            @input="set('email', $event.target.value)" />
                    </div>
                    <div class="acc-field" style="margin-bottom:0">
                        <label class="acc-row-label" for="acc-password">Password</label>
                        <span class="acc-row-help">{{ mode === 'edit' ? 'Lascia invariato per non cambiarla.' : 'Scegli una password sicura.' }}</span>
                        <SecretField id="acc-password" :model-value="form.password" placeholder="Almeno 8 caratteri"
                            @update:model-value="set('password', $event)" />
                    </div>
                </div>

                <!-- Account Manager -->
                <div class="acc-card">
                    <div class="acc-sec-head">
                        <div class="acc-sec-title">Account Manager</div>
                        <div class="acc-sec-sub">Gestione amministrativa dell'account: limiti, piano e gerarchia.</div>
                    </div>

                    <!-- Sub-utenti toggle -->
                    <div class="acc-ch-opt" style="margin-bottom: 22px">
                        <div class="acc-ch-opt-row">
                            <div class="acc-ch-opt-txt">
                                <div class="acc-ch-opt-title">L'account può creare sotto-utenti</div>
                                <div class="acc-ch-opt-help">Attivalo se questo account gestisce più brand e deve poter creare account collegati.</div>
                            </div>
                            <ToggleSwitch :model-value="form.canSubusers" @update:model-value="set('canSubusers', $event)" />
                        </div>
                    </div>

                    <!-- Sotto-utenti / Manager, Token al mese, Immagini al giorno -->
                    <div class="acc-field-row acc-field-row--3">
                        <div v-if="form.canSubusers" class="acc-field" style="margin-bottom:0">
                            <label class="acc-row-label" for="acc-subusers-limit">Numero massimo sotto-utenti</label>
                            <span class="acc-row-help">Quanti utenti si possono creare.</span>
                            <div class="acc-input-unit">
                                <input id="acc-subusers-limit" class="control" type="number" :value="form.subusersLimit" placeholder="5"
                                    @input="set('subusersLimit', $event.target.value)" />
                                <span class="unit">utenti</span>
                            </div>
                            <div v-if="mode === 'edit' && account?.usage" class="acc-inline-usage">
                                <div class="acc-inline-usage-bar">
                                    <div class="acc-inline-usage-fill"
                                        :style="{
                                            width: Math.min(Math.round((account.usage.subusersActive ?? 0) / (Number(form.subusersLimit) || 1) * 100), 100) + '%',
                                            background: 'var(--sky)'
                                        }" />
                                </div>
                                <div class="acc-inline-usage-row">
                                    <span>sotto-utenti attivi</span>
                                    <span class="acc-inline-usage-val" style="color:var(--sky-strong)">
                                        {{ account.usage.subusersActive ?? 0 }} / {{ Number(form.subusersLimit) || 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="acc-field" style="margin-bottom:0">
                            <label class="acc-row-label" for="acc-manager">Manager assegnato</label>
                            <span class="acc-row-help">{{ form.manager ? 'Account gestito da un manager.' : "L'account non ha ancora un manager." }}</span>
                            <select id="acc-manager" class="control" :value="form.manager" @change="set('manager', $event.target.value)">
                                <option value="">Nessun manager</option>
                                <option v-for="m in managers" :key="m.id" :value="m.id">{{ m.name }}</option>
                                <option v-if="!managers.length" value="mock-1">Studio Sociale · agenzia</option>
                                <option v-if="!managers.length" value="mock-2">Marketing interno</option>
                            </select>
                        </div>

                        <div class="acc-field" style="margin-bottom:0">
                            <label class="acc-row-label" for="acc-tokens-month">Token al mese</label>
                            <span class="acc-row-help">Numero massimo di token utilizzabili al mese.</span>
                            <div class="acc-input-unit">
                                <input id="acc-tokens-month" class="control" type="number" :value="form.tokensMonth" placeholder="50000"
                                    @input="set('tokensMonth', $event.target.value)" />
                                <span class="unit">token</span>
                            </div>
                            <div v-if="mode === 'edit' && account?.usage" class="acc-inline-usage">
                                <div class="acc-inline-usage-bar">
                                    <div class="acc-inline-usage-fill"
                                        :style="{
                                            width: Math.min(Math.round(account.usage.tokensUsed / (Number(form.tokensMonth) || 50000) * 100), 100) + '%',
                                            background: 'var(--sky)'
                                        }" />
                                </div>
                                <div class="acc-inline-usage-row">
                                    <span>token usati questo mese</span>
                                    <span class="acc-inline-usage-val" style="color:var(--sky-strong)">
                                        {{ account.usage.tokensUsed.toLocaleString('it') }} / {{ (Number(form.tokensMonth) || 50000).toLocaleString('it') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="acc-field" style="margin-bottom:0">
                            <label class="acc-row-label" for="acc-images-day">Immagini al giorno</label>
                            <span class="acc-row-help">Numero massimo di immagini generabili.</span>
                            <div class="acc-input-unit">
                                <input id="acc-images-day" class="control" type="number" :value="form.imagesDay" placeholder="20"
                                    @input="set('imagesDay', $event.target.value)" />
                                <span class="unit">img al dì</span>
                            </div>
                            <div v-if="mode === 'edit' && account?.usage" class="acc-inline-usage">
                                <div class="acc-inline-usage-bar">
                                    <div class="acc-inline-usage-fill"
                                        :style="{
                                            width: Math.min(Math.round(account.usage.imagesUsed / (Number(form.imagesDay) || 20) * 100), 100) + '%',
                                            background: 'var(--st-pub-bd)'
                                        }" />
                                </div>
                                <div class="acc-inline-usage-row">
                                    <span>immagini generate oggi</span>
                                    <span class="acc-inline-usage-val" style="color:var(--st-pub-fg)">
                                        {{ account.usage.imagesUsed }} / {{ Number(form.imagesDay) || 20 }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ──────────────── Tab: Canali ──────────────── -->
            <div v-if="activeTab === 'canali'" id="acc-panel-canali" role="tabpanel" aria-labelledby="acc-tab-canali" class="acc-reveal">
                <div class="acc-card">
                    <div class="acc-sec-head with-aside">
                        <div>
                            <div class="acc-sec-title">Canali di pubblicazione</div>
                            <div class="acc-sec-sub">Scegli come gestire la comunicazione e su quali canali può pubblicare questo account.</div>
                        </div>
                        <span class="acc-chip">
                            <Icon name="check" :size="14" />{{ activeCh }} attivi
                        </span>
                    </div>

                    <div class="acc-channels">
                        <div v-for="ch in CHANNELS" :key="ch.id"
                            class="acc-ch" :class="{ on: form.channels[ch.id].on }">

                            <!-- Channel header (toggle row) -->
                            <div class="acc-ch-head" role="switch" :aria-checked="form.channels[ch.id].on" tabindex="0"
                                :aria-label="'Attiva ' + ch.label"
                                @click="set('channels.' + ch.id + '.on', !form.channels[ch.id].on)"
                                @keydown.enter.prevent="set('channels.' + ch.id + '.on', !form.channels[ch.id].on)"
                                @keydown.space.prevent="set('channels.' + ch.id + '.on', !form.channels[ch.id].on)">
                                <div :class="['acc-ch-ic', ch.ic, !form.channels[ch.id].on ? 'off' : '']">
                                    <ChannelIcon :id="ch.id" :size="20" />
                                </div>
                                <div class="acc-ch-grow">
                                    <div :class="['acc-ch-name', !form.channels[ch.id].on ? 'off' : '']">{{ ch.label }}</div>
                                    <div class="acc-ch-meta">
                                        {{ form.channels[ch.id].on
                                            ? (form.channels[ch.id].reply_on ? CH_COPY[ch.kind].optTitle + ' · attivo' : 'Pubblicazione attiva')
                                            : ch.meta }}
                                    </div>
                                </div>
                                <div class="acc-ch-right" @click.stop>
                                    <ConnectionBadge v-if="form.channels[ch.id].on" :state="chConn(ch.id)" />
                                    <span v-else class="acc-ch-off-tag">Non attivo</span>
                                    <ToggleSwitch :model-value="form.channels[ch.id].on"
                                        @update:model-value="set('channels.' + ch.id + '.on', $event)" />
                                </div>
                            </div>

                            <!-- Channel body (expanded when enabled) -->
                            <div v-if="form.channels[ch.id].on" class="acc-ch-body acc-reveal">

                                <!-- Not connected warning -->
                                <div v-if="chConn(ch.id) !== 'ok'"
                                    class="acc-ch-opt" style="border-color:var(--st-sch-bd);background:var(--st-sch-bg)">
                                    <div class="acc-ch-opt-row">
                                        <div class="acc-ch-opt-txt">
                                            <div class="acc-ch-opt-title" style="color:var(--st-sch-fg)">Canale non ancora collegato</div>
                                            <div class="acc-ch-opt-help">Collega le credenziali in AI &amp; Integrazioni per poter pubblicare.</div>
                                        </div>
                                        <button type="button" class="acc-verify" @click="goToIntegration(ch.id)">
                                            <Icon name="link" :size="15" />
                                            Vai a Integrazioni
                                        </button>
                                    </div>
                                </div>

                                <!-- Connected: what this channel can do -->
                                <div v-else class="acc-ch-opt" style="border-color:var(--st-pub-bd);background:var(--st-pub-bg)">
                                    <div class="acc-ch-opt-row">
                                        <div class="acc-ch-opt-txt">
                                            <div class="acc-ch-opt-title" style="color:var(--st-pub-fg)">Canale collegato</div>
                                            <div class="acc-ch-opt-help">{{ ch.meta }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Comments/replies option -->
                                <div class="acc-ch-opt">
                                    <div class="acc-ch-opt-row">
                                        <div class="acc-ch-opt-txt">
                                            <div class="acc-ch-opt-title">{{ CH_COPY[ch.kind].optTitle }}</div>
                                            <div class="acc-ch-opt-help">{{ CH_COPY[ch.kind].optHelp }}</div>
                                        </div>
                                        <ToggleSwitch :model-value="form.channels[ch.id].reply_on"
                                            @update:model-value="onToggleReplyOn(ch.id, $event)" />
                                    </div>

                                    <!-- Reply count stepper (when replies enabled) -->
                                    <div v-if="form.channels[ch.id].reply_on" class="acc-count-field acc-reveal">
                                        <div class="acc-ch-opt-row">
                                            <div class="acc-ch-opt-txt">
                                                <div class="acc-ch-opt-title" style="font-weight:500;color:var(--g600)">{{ CH_COPY[ch.kind].countTitle }}</div>
                                                <div class="acc-ch-opt-help">{{ CH_COPY[ch.kind].countHelp }}</div>
                                            </div>
                                            <div class="acc-count">
                                                <NumberStepper :model-value="form.channels[ch.id].reply_n ?? 5" :min="1" :max="200"
                                                    @update:model-value="set('channels.' + ch.id + '.reply_n', $event)" />
                                                <!-- <span style="font-size:12.5px;color:var(--g500);white-space:nowrap">{{ CH_COPY[ch.kind].unit }}</span> -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ──────────────── Tab: AI & Integrazioni ──────────────── -->
            <div v-if="activeTab === 'imp'" id="acc-panel-imp" role="tabpanel" aria-labelledby="acc-tab-imp" class="acc-reveal">
                <div class="acc-card">
                    <div class="acc-sec-head">
                        <div class="acc-sec-title">AI &amp; Integrazioni</div>
                        <div class="acc-sec-sub">Profilo dell'AI e credenziali dei servizi. Le chiavi sono uniche, non duplicarle.</div>
                    </div>

                    <div class="acc-imenu-wrap">
                        <!-- Left menu -->
                        <div class="acc-imenu">
                            <button v-for="intg in INTEGRATIONS" :key="intg.id" type="button"
                                class="acc-imenu-btn" :class="{ active: activeIntg === intg.id }"
                                @click="activeIntg = intg.id">
                                <div class="acc-imenu-ic">
                                    <Icon v-if="intg.id === 'ai'" name="sparkles" :size="16" />
                                    <Icon v-else-if="intg.id === 'openai'" name="key" :size="16" />
                                    <ChannelIcon v-else :id="intg.id === 'meta' ? 'facebook' : intg.id === 'newsletter' ? 'newsletter' : intg.id" :size="16" />
                                </div>
                                <div class="acc-imenu-grow">
                                    <span class="acc-imenu-name">{{ intg.name }}</span>
                                    <span class="acc-imenu-st">
                                        {{ intgConn(intg.id) === null ? 'Sempre attivo' : intgConn(intg.id) === 'ok' ? 'Connesso' : 'Non connesso' }}
                                    </span>
                                </div>
                                <span v-if="intgConn(intg.id) !== null" class="acc-imenu-dot"
                                    :style="{ background: intgConn(intg.id) === 'ok' ? 'var(--st-pub-bd)' : 'var(--g300)' }" />
                            </button>
                        </div>

                        <!-- Right panel -->
                        <div class="acc-ipanel">
                            <div class="acc-iphead">
                                <div class="acc-iphead-ic" :style="{ background: INTG_TINT[activeIntg].bg, color: INTG_TINT[activeIntg].fg }">
                                    <Icon v-if="activeIntg === 'ai'" name="sparkles" :size="20" />
                                    <Icon v-else-if="activeIntg === 'openai'" name="key" :size="20" />
                                    <ChannelIcon v-else :id="activeIntg === 'meta' ? 'facebook' : activeIntg === 'newsletter' ? 'newsletter' : activeIntg" :size="20" />
                                </div>
                                <div>
                                    <div class="acc-iphead-t">{{ INTEGRATIONS.find(i => i.id === activeIntg)?.name }}</div>
                                    <div class="acc-iphead-s">{{ INTEGRATIONS.find(i => i.id === activeIntg)?.sub }}</div>
                                </div>
                                <div class="acc-iphead-r">
                                    <ConnectionBadge v-if="intgConn(activeIntg) !== null" :state="intgConn(activeIntg)" />
                                </div>
                            </div>

                            <!-- AI fields -->
                            <div v-if="activeIntg === 'ai'" style="display:flex;flex-direction:column;gap:4px">
                                <div class="acc-field">
                                    <label class="acc-row-label" for="acc-ai-profile">Profilo dell'AI</label>
                                    <span class="acc-row-help">Descrivi chi è l'AI: nome, ruolo, personalità e tono. È il prompt di sistema.</span>
                                    <textarea id="acc-ai-profile" class="control" :value="form.ai.profile" style="min-height:150px"
                                        placeholder="Descrivi nel modo più dettagliato possibile il profilo che deve avere l'AI…"
                                        @input="set('ai.profile', $event.target.value)" />
                                </div>
                                <div class="acc-field">
                                    <label class="acc-row-label" for="acc-ai-knows">Cosa deve sapere l'AI</label>
                                    <span class="acc-row-help">Le informazioni concrete da conoscere: orari, prodotti, regole.</span>
                                    <textarea id="acc-ai-knows" class="control" :value="form.ai.knows" style="min-height:130px"
                                        placeholder="Scrivi quello che vuoi che l'AI conosca…"
                                        @input="set('ai.knows', $event.target.value)" />
                                </div>
                                <div class="acc-field" style="margin-bottom:0">
                                    <label class="acc-row-label" for="acc-ai-comment">Come deve commentare l'AI</label>
                                    <span class="acc-row-help">Lo stile generale con cui l'AI risponde ai commenti e alle email.</span>
                                    <textarea id="acc-ai-comment" class="control" :value="form.ai.commentStyle" style="min-height:110px"
                                        placeholder="Scrivi come l'AI deve commentare e rispondere…"
                                        @input="set('ai.commentStyle', $event.target.value)" />
                                </div>
                            </div>

                            <!-- OpenAI fields -->
                            <div v-else-if="activeIntg === 'openai'">
                                <div class="acc-field">
                                    <label class="acc-row-label" for="acc-openai-key">API Key</label>
                                    <span class="acc-row-help">La chiave segreta del tuo account OpenAI. Viene usata per generare testi e immagini.</span>
                                    <SecretField id="acc-openai-key" :model-value="form.openai.apiKey" placeholder="sk-proj-…"
                                        @update:model-value="set('openai.apiKey', $event)" />
                                </div>
                                <div class="acc-verify-row">
                                    <ConnectionBadge :state="form.openai.connected ? 'ok' : 'off'" />
                                    <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
                                </div>
                            </div>

                            <!-- Meta fields -->
                            <div v-else-if="activeIntg === 'meta'">
                                <div class="acc-field">
                                    <label class="acc-row-label" for="acc-meta-pageid">ID della pagina Facebook</label>
                                    <span class="acc-row-help">L'ID numerico della pagina su cui pubblicare. Instagram pubblica tramite la pagina collegata.</span>
                                    <input id="acc-meta-pageid" class="control" type="text" :value="form.meta.pageId" placeholder="es. 104882736591023"
                                        @input="set('meta.pageId', $event.target.value)" />
                                </div>
                                <div class="acc-verify-row">
                                    <ConnectionBadge :state="form.meta.connected ? 'ok' : 'off'" />
                                    <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
                                </div>
                            </div>

                            <!-- LinkedIn fields -->
                            <div v-else-if="activeIntg === 'linkedin'">
                                <div class="acc-grid-2">
                                    <div class="acc-field">
                                        <label class="acc-row-label" for="acc-li-clientid">Client ID</label>
                                        <span class="acc-row-help">Dall'app LinkedIn Developers.</span>
                                        <input id="acc-li-clientid" class="control" type="text" :value="form.linkedin.clientId" placeholder="86xxxxxxxxxx"
                                            @input="set('linkedin.clientId', $event.target.value)" />
                                    </div>
                                    <div class="acc-field">
                                        <label class="acc-row-label" for="acc-li-secret">Client Secret</label>
                                        <span class="acc-row-help">Tienilo riservato.</span>
                                        <SecretField id="acc-li-secret" :model-value="form.linkedin.clientSecret" placeholder="••••••••"
                                            @update:model-value="set('linkedin.clientSecret', $event)" />
                                    </div>
                                </div>
                                <div class="acc-field">
                                    <label class="acc-row-label" for="acc-li-pageid">ID della pagina LinkedIn</label>
                                    <span class="acc-row-help">La pagina aziendale su cui pubblicare.</span>
                                    <input id="acc-li-pageid" class="control" type="text" :value="form.linkedin.pageId" placeholder="es. 7654321"
                                        @input="set('linkedin.pageId', $event.target.value)" />
                                </div>
                                <div class="acc-field">
                                    <label class="acc-row-label" for="acc-li-token">
                                        Token LinkedIn <span class="acc-readonly-tag">solo lettura</span>
                                    </label>
                                    <span class="acc-row-help">Generato automaticamente dopo l'autorizzazione. Non modificabile a mano.</span>
                                    <SecretField id="acc-li-token" :model-value="form.linkedin.token" :read-only="true" :copyable="true"
                                        placeholder="Si genera dopo «Verifica connessione»" />
                                </div>
                                <div class="acc-verify-row">
                                    <ConnectionBadge :state="form.linkedin.connected ? 'ok' : 'off'" />
                                    <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
                                    <button type="button" class="acc-verify" style="margin-left:auto"
                                        @click="getLinkedinToken">
                                        <Icon name="link" :size="15" />
                                        Ottieni token
                                    </button>
                                </div>
                            </div>

                            <!-- WordPress fields -->
                            <div v-else-if="activeIntg === 'wordpress'">
                                <div class="acc-field">
                                    <label class="acc-row-label" for="acc-wp-url">URL del sito</label>
                                    <span class="acc-row-help">L'indirizzo del tuo sito WordPress.</span>
                                    <input id="acc-wp-url" class="control" type="url" :value="form.wordpress.url" placeholder="https://iltuosito.it"
                                        @input="set('wordpress.url', $event.target.value)" />
                                </div>
                                <div class="acc-grid-2">
                                    <div class="acc-field">
                                        <label class="acc-row-label" for="acc-wp-username">Username</label>
                                        <span class="acc-row-help">Utente con permessi di pubblicazione.</span>
                                        <input id="acc-wp-username" class="control" type="text" :value="form.wordpress.username" placeholder="admin"
                                            @input="set('wordpress.username', $event.target.value)" />
                                    </div>
                                    <div class="acc-field">
                                        <label class="acc-row-label" for="acc-wp-password">Application Password</label>
                                        <span class="acc-row-help">Usa una password applicativa, non quella di accesso.</span>
                                        <SecretField id="acc-wp-password" :model-value="form.wordpress.password" placeholder="xxxx xxxx xxxx xxxx"
                                            @update:model-value="set('wordpress.password', $event)" />
                                    </div>
                                </div>
                                <div class="acc-field">
                                    <label class="acc-row-label" for="acc-wp-category">Categoria ID</label>
                                    <span class="acc-row-help">L'ID della categoria in cui salvare gli articoli.</span>
                                    <input id="acc-wp-category" class="control" type="text" :value="form.wordpress.categoryId" placeholder="es. 8"
                                        @input="set('wordpress.categoryId', $event.target.value)" />
                                </div>
                                <div class="acc-verify-row">
                                    <ConnectionBadge :state="form.wordpress.connected ? 'ok' : 'off'" />
                                    <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
                                </div>
                            </div>

                            <!-- Newsletter fields -->
                            <div v-else-if="activeIntg === 'newsletter'">
                                <p class="acc-hint-inline" style="margin-bottom:14px">
                                    <Icon name="info" :size="14" />
                                    Collega <b style="margin:0 4px;color:var(--g700)">uno</b> dei provider che usi per le tue campagne.
                                </p>

                                <div class="acc-acc">
                                    <div v-for="p in NL_PROVIDERS" :key="p.id"
                                        class="acc-acc-item" :class="{ open: openNl === p.id }">
                                        <div class="acc-acc-head" role="button" tabindex="0" :aria-expanded="openNl === p.id"
                                            :aria-label="'Espandi ' + p.name"
                                            @click="openNl = openNl === p.id ? '' : p.id"
                                            @keydown.enter.prevent="openNl = openNl === p.id ? '' : p.id"
                                            @keydown.space.prevent="openNl = openNl === p.id ? '' : p.id">
                                            <div class="acc-acc-ic">
                                                <Icon :name="p.id === 'smtp' ? 'settings' : 'chat'" :size="18" />
                                            </div>
                                            <div class="acc-acc-grow">
                                                <div class="acc-acc-title">{{ p.name }}</div>
                                                <div class="acc-acc-sub">{{ p.sub }}</div>
                                            </div>
                                            <ConnectionBadge :state="nlConn(p.id)" />
                                            <Icon name="chevron" :size="18" class="acc-acc-chev" />
                                        </div>

                                        <div v-if="openNl === p.id" class="acc-acc-body acc-reveal">
                                            <!-- MailChimp -->
                                            <template v-if="p.id === 'mailchimp'">
                                                <div class="acc-field">
                                                    <label class="acc-row-label" for="acc-nl-mc-key">API Key</label>
                                                    <span class="acc-row-help">Dalla sezione Account › Extra › API keys di MailChimp.</span>
                                                    <SecretField id="acc-nl-mc-key" :model-value="form.newsletter.mailchimp.apiKey" placeholder="xxxxxxxx-us21"
                                                        @update:model-value="set('newsletter.mailchimp.apiKey', $event)" />
                                                </div>
                                                <div class="acc-grid-2">
                                                    <div class="acc-field">
                                                        <label class="acc-row-label" for="acc-nl-mc-server">Server prefix</label>
                                                        <span class="acc-row-help">Es. us21 (è nel dominio della tua dashboard).</span>
                                                        <input id="acc-nl-mc-server" class="control" type="text" :value="form.newsletter.mailchimp.serverPrefix" placeholder="us21"
                                                            @input="set('newsletter.mailchimp.serverPrefix', $event.target.value)" />
                                                    </div>
                                                    <div class="acc-field">
                                                        <label class="acc-row-label" for="acc-nl-mc-audience">Audience ID</label>
                                                        <span class="acc-row-help">La lista a cui inviare.</span>
                                                        <input id="acc-nl-mc-audience" class="control" type="text" :value="form.newsletter.mailchimp.audienceId" placeholder="9f3c1a7b2e"
                                                            @input="set('newsletter.mailchimp.audienceId', $event.target.value)" />
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Brevo -->
                                            <template v-else-if="p.id === 'brevo'">
                                                <div class="acc-field">
                                                    <label class="acc-row-label" for="acc-nl-brevo-key">API Key</label>
                                                    <span class="acc-row-help">Dalla sezione SMTP &amp; API di Brevo.</span>
                                                    <SecretField id="acc-nl-brevo-key" :model-value="form.newsletter.brevo.apiKey" placeholder="xkeysib-…"
                                                        @update:model-value="set('newsletter.brevo.apiKey', $event)" />
                                                </div>
                                                <div class="acc-grid-2">
                                                    <div class="acc-field">
                                                        <label class="acc-row-label" for="acc-nl-brevo-list">List ID</label>
                                                        <span class="acc-row-help">L'ID numerico della lista.</span>
                                                        <input id="acc-nl-brevo-list" class="control" type="text" :value="form.newsletter.brevo.listId" placeholder="es. 12"
                                                            @input="set('newsletter.brevo.listId', $event.target.value)" />
                                                    </div>
                                                    <div class="acc-field">
                                                        <label class="acc-row-label" for="acc-nl-brevo-sender">Mittente</label>
                                                        <span class="acc-row-help">Email verificata come mittente.</span>
                                                        <input id="acc-nl-brevo-sender" class="control" type="email" :value="form.newsletter.brevo.sender" placeholder="news@dominio.it"
                                                            @input="set('newsletter.brevo.sender', $event.target.value)" />
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- SMTP -->
                                            <template v-else-if="p.id === 'smtp'">
                                                <div class="acc-grid-2">
                                                    <div class="acc-field">
                                                        <label class="acc-row-label" for="acc-nl-smtp-host">Host SMTP</label>
                                                        <span class="acc-row-help">Il server della tua casella email.</span>
                                                        <input id="acc-nl-smtp-host" class="control" type="text" :value="form.newsletter.smtp.host" placeholder="smtp.dominio.it"
                                                            @input="set('newsletter.smtp.host', $event.target.value)" />
                                                    </div>
                                                    <div class="acc-field">
                                                        <label class="acc-row-label" for="acc-nl-smtp-port">Porta</label>
                                                        <span class="acc-row-help">Di solito 587 (TLS) o 465 (SSL).</span>
                                                        <input id="acc-nl-smtp-port" class="control" type="text" :value="form.newsletter.smtp.port" placeholder="587"
                                                            @input="set('newsletter.smtp.port', $event.target.value)" />
                                                    </div>
                                                </div>
                                                <div class="acc-grid-2">
                                                    <div class="acc-field">
                                                        <label class="acc-row-label" for="acc-nl-smtp-username">Username</label>
                                                        <span class="acc-row-help">L'utente di autenticazione.</span>
                                                        <input id="acc-nl-smtp-username" class="control" type="text" :value="form.newsletter.smtp.username" placeholder="news@dominio.it"
                                                            @input="set('newsletter.smtp.username', $event.target.value)" />
                                                    </div>
                                                    <div class="acc-field">
                                                        <label class="acc-row-label" for="acc-nl-smtp-password">Password</label>
                                                        <span class="acc-row-help">La password della casella.</span>
                                                        <SecretField id="acc-nl-smtp-password" :model-value="form.newsletter.smtp.password" placeholder="••••••••"
                                                            @update:model-value="set('newsletter.smtp.password', $event)" />
                                                    </div>
                                                </div>
                                                <div class="acc-grid-2">
                                                    <div class="acc-field">
                                                        <label class="acc-row-label" for="acc-nl-smtp-encryption">Cifratura</label>
                                                        <span class="acc-row-help">Protocollo di sicurezza.</span>
                                                        <select id="acc-nl-smtp-encryption" class="control" :value="form.newsletter.smtp.encryption"
                                                            @change="set('newsletter.smtp.encryption', $event.target.value)">
                                                            <option value="tls">TLS</option>
                                                            <option value="ssl">SSL</option>
                                                            <option value="none">Nessuna</option>
                                                        </select>
                                                    </div>
                                                    <div class="acc-field">
                                                        <label class="acc-row-label" for="acc-nl-smtp-sender">Mittente</label>
                                                        <span class="acc-row-help">Indirizzo che vedranno gli iscritti.</span>
                                                        <input id="acc-nl-smtp-sender" class="control" type="text" :value="form.newsletter.smtp.sender"
                                                            placeholder="Trattoria &lt;news@dominio.it&gt;"
                                                            @input="set('newsletter.smtp.sender', $event.target.value)" />
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Status row -->
                                            <div style="display:flex;align-items:center;gap:12px;margin-top:6px;padding-top:16px;border-top:1px solid var(--g100)">
                                                <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast -->
        <Teleport to="body">
            <div v-if="toast" class="acc-toast">
                <Icon name="check" :size="17" />{{ toast }}
            </div>
        </Teleport>

    </AppLayout>
</template>
