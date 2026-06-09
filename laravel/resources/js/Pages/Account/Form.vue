<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
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
        countHelp: 'Numero massimo di commenti a cui rispondere ogni giorno.',
        unit: 'commenti / giorno',
    },
    mail: {
        optTitle: 'Rispondi alle email',
        optHelp: "Per la Newsletter le risposte non sono commenti pubblici: l'AI risponde via email.",
        countTitle: 'A quante email rispondere',
        countHelp: 'Numero massimo di email a cui rispondere ogni giorno.',
        unit: 'email / giorno',
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

function defaultChannels() {
    return Object.fromEntries(
        CHANNELS.map(ch => [ch.id, { enabled: false, comments: false, count: 5, connected: false }])
    );
}

function buildForm(account) {
    if (!account) {
        return {
            name: '', email: '', password: '',
            canSubusers: false, manager: '', tokensMonth: '', imagesDay: '',
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
const verifying  = reactive({});

const activeCh  = computed(() => CHANNELS.filter(c => form.channels[c.id].enabled).length);
const title     = computed(() => props.mode === 'create' ? 'Nuovo account' : (form.name || 'Account'));
const saveLabel = computed(() => props.mode === 'create' ? 'Crea account' : 'Salva');

/* ------------------------------------------------------------------ */
/* Completion widget                                                    */
/* ------------------------------------------------------------------ */

const completion = computed(() => {
    const checks = [
        !!form.name,
        !!form.email,
        !!(form.password && form.password.replace(/•/g, '').length > 0),
        CHANNELS.some(c => form.channels[c.id].enabled),
        !!(form.ai.profile && form.ai.profile.length > 30),
        !!form.openai.apiKey,
        !!(form.meta.pageId || form.wordpress.url || form.linkedin.clientId ||
           form.newsletter.mailchimp.apiKey || form.newsletter.brevo.apiKey),
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

function verify(key, onResult) {
    verifying[key] = true;
    onResult('checking');
    setTimeout(() => {
        verifying[key] = false;
        onResult('ok');
        set(key + '.connected', true);
    }, 1100);
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

        <!-- Sticky sub-header -->
        <div class="acc-head">
            <div class="acc-head-inner">
                <div class="acc-crumb">
                    <span>Account</span>
                    <span class="sep">›</span>
                    <b>{{ title }}</b>
                </div>
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
                    <button type="button" class="btn btn-light btn-sm" @click="doCancel">Annulla</button>
                    <button type="button" class="btn btn-success btn-sm" @click="doSave">
                        <Icon name="check" :size="16" />{{ saveLabel }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="acc-content">

            <!-- Workspace tabs -->
            <div class="acc-mtabs">
                <button type="button" class="acc-mtab" :class="{ active: activeTab === 'profile' }" @click="activeTab = 'profile'">
                    <Icon name="users" :size="16" />Profilo &amp; Piano
                </button>
                <button type="button" class="acc-mtab" :class="{ active: activeTab === 'canali' }" @click="activeTab = 'canali'">
                    <Icon name="chat" :size="16" />Canali
                    <span class="ct">{{ activeCh }}</span>
                </button>
                <button type="button" class="acc-mtab" :class="{ active: activeTab === 'imp' }" @click="activeTab = 'imp'">
                    <Icon name="sparkles" :size="16" />AI &amp; Integrazioni
                </button>
            </div>

            <!-- ──────────────── Tab: Profilo & Piano ──────────────── -->
            <div v-if="activeTab === 'profile'" class="acc-grid-2 acc-grid-2--equal acc-reveal">

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
                        <label class="acc-row-label">Nome</label>
                        <span class="acc-row-help">Solo ad uso interno, non viene pubblicato.</span>
                        <input class="control" type="text" :value="form.name" placeholder="Es. Trattoria da Marco"
                            @input="set('name', $event.target.value)" />
                    </div>
                    <div class="acc-field">
                        <label class="acc-row-label">E-mail</label>
                        <span class="acc-row-help">Serve per l'accesso. Solo ad uso interno, non viene pubblicata.</span>
                        <input class="control" type="email" :value="form.email" placeholder="nome@dominio.it"
                            @input="set('email', $event.target.value)" />
                    </div>
                    <div class="acc-field" style="margin-bottom:0">
                        <label class="acc-row-label">Password</label>
                        <span class="acc-row-help">{{ mode === 'edit' ? 'Lascia invariato per non cambiarla.' : 'Scegli una password sicura.' }}</span>
                        <SecretField :model-value="form.password" placeholder="Almeno 8 caratteri"
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

                    <!-- Manager assegnato -->
                    <div class="acc-field">
                        <label class="acc-row-label">Manager assegnato</label>
                        <span class="acc-row-help">{{ form.manager ? 'Account gestito da un manager.' : "L'account non ha ancora un manager." }}</span>
                        <select class="control" :value="form.manager" @change="set('manager', $event.target.value)">
                            <option value="">Nessun manager</option>
                            <option v-for="m in managers" :key="m.id" :value="m.id">{{ m.name }}</option>
                            <option v-if="!managers.length" value="mock-1">Studio Sociale · agenzia</option>
                            <option v-if="!managers.length" value="mock-2">Marketing interno</option>
                        </select>
                        <!-- Edit mode: inline usage bar -->
                        <div v-if="mode === 'edit' && account?.usage" class="acc-inline-usage">
                            <div class="acc-inline-usage-bar">
                                <div class="acc-inline-usage-fill"
                                    :style="{ width: Math.min(Math.round(2 / 5 * 100), 100) + '%', background: 'var(--sky)' }" />
                            </div>
                            <div class="acc-inline-usage-row">
                                <span>sotto-utenti attivi</span>
                                <span class="acc-inline-usage-val" style="color:var(--sky-strong)">2 / 5</span>
                            </div>
                        </div>
                    </div>

                    <!-- Token al mese -->
                    <div class="acc-field">
                        <label class="acc-row-label">Token al mese</label>
                        <span class="acc-row-help">Numero massimo di token utilizzabili al mese.</span>
                        <div class="acc-input-unit">
                            <input class="control" type="number" :value="form.tokensMonth" placeholder="50000"
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

                    <!-- Immagini al giorno -->
                    <div class="acc-field" style="margin-bottom:0">
                        <label class="acc-row-label">Immagini al giorno</label>
                        <span class="acc-row-help">Numero massimo di immagini generabili al giorno.</span>
                        <div class="acc-input-unit">
                            <input class="control" type="number" :value="form.imagesDay" placeholder="20"
                                @input="set('imagesDay', $event.target.value)" />
                            <span class="unit">img / die</span>
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

            <!-- ──────────────── Tab: Canali ──────────────── -->
            <div v-if="activeTab === 'canali'" class="acc-reveal">
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
                            class="acc-ch" :class="{ on: form.channels[ch.id].enabled }">

                            <!-- Channel header (toggle row) -->
                            <div class="acc-ch-head" @click="set('channels.' + ch.id + '.enabled', !form.channels[ch.id].enabled)">
                                <div :class="['acc-ch-ic', ch.ic, !form.channels[ch.id].enabled ? 'off' : '']">
                                    <ChannelIcon :id="ch.id" :size="20" />
                                </div>
                                <div class="acc-ch-grow">
                                    <div :class="['acc-ch-name', !form.channels[ch.id].enabled ? 'off' : '']">{{ ch.label }}</div>
                                    <div class="acc-ch-meta">
                                        {{ form.channels[ch.id].enabled
                                            ? (form.channels[ch.id].comments ? CH_COPY[ch.kind].optTitle + ' · attivo' : 'Pubblicazione attiva')
                                            : ch.meta }}
                                    </div>
                                </div>
                                <div class="acc-ch-right" @click.stop>
                                    <ConnectionBadge v-if="form.channels[ch.id].enabled"
                                        :state="form.channels[ch.id].connected ? 'ok' : 'off'" />
                                    <span v-else class="acc-ch-off-tag">Non attivo</span>
                                    <ToggleSwitch :model-value="form.channels[ch.id].enabled"
                                        @update:model-value="set('channels.' + ch.id + '.enabled', $event)" />
                                </div>
                            </div>

                            <!-- Channel body (expanded when enabled) -->
                            <div v-if="form.channels[ch.id].enabled" class="acc-ch-body acc-reveal">

                                <!-- Not connected warning -->
                                <div v-if="!form.channels[ch.id].connected"
                                    class="acc-ch-opt" style="border-color:var(--st-sch-bd);background:#fffdf3">
                                    <div class="acc-ch-opt-row">
                                        <div class="acc-ch-opt-txt">
                                            <div class="acc-ch-opt-title" style="color:var(--st-sch-fg)">Canale non ancora collegato</div>
                                            <div class="acc-ch-opt-help">Verifica le credenziali nelle impostazioni per poter pubblicare.</div>
                                        </div>
                                        <button type="button" class="acc-verify"
                                            :disabled="verifying['ch-' + ch.id]"
                                            @click="verify('channels.' + ch.id, s => { if (s === 'checking') verifying['ch-' + ch.id] = true; else { verifying['ch-' + ch.id] = false; set('channels.' + ch.id + '.connected', true); } })">
                                            <span v-if="verifying['ch-' + ch.id]" class="acc-spin" />
                                            <Icon v-else name="link" :size="15" />
                                            {{ verifying['ch-' + ch.id] ? 'Verifica…' : 'Verifica connessione' }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Comments/replies option -->
                                <div class="acc-ch-opt">
                                    <div class="acc-ch-opt-row">
                                        <div class="acc-ch-opt-txt">
                                            <div class="acc-ch-opt-title">{{ CH_COPY[ch.kind].optTitle }}</div>
                                            <div class="acc-ch-opt-help">{{ CH_COPY[ch.kind].optHelp }}</div>
                                        </div>
                                        <ToggleSwitch :model-value="form.channels[ch.id].comments"
                                            @update:model-value="set('channels.' + ch.id + '.comments', $event)" />
                                    </div>

                                    <!-- Reply count stepper (when replies enabled) -->
                                    <div v-if="form.channels[ch.id].comments" class="acc-count-field acc-reveal">
                                        <div class="acc-ch-opt-row">
                                            <div class="acc-ch-opt-txt">
                                                <div class="acc-ch-opt-title" style="font-weight:500;color:var(--g600)">{{ CH_COPY[ch.kind].countTitle }}</div>
                                                <div class="acc-ch-opt-help">{{ CH_COPY[ch.kind].countHelp }}</div>
                                            </div>
                                            <div class="acc-count">
                                                <NumberStepper :model-value="form.channels[ch.id].count" :min="1" :max="200"
                                                    @update:model-value="set('channels.' + ch.id + '.count', $event)" />
                                                <span style="font-size:12.5px;color:var(--g500);white-space:nowrap">{{ CH_COPY[ch.kind].unit }}</span>
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
            <div v-if="activeTab === 'imp'" class="acc-reveal">
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
                                    <label class="acc-row-label">Profilo dell'AI</label>
                                    <span class="acc-row-help">Descrivi chi è l'AI: nome, ruolo, personalità e tono. È il prompt di sistema.</span>
                                    <textarea class="control" :value="form.ai.profile" style="min-height:150px"
                                        placeholder="Descrivi nel modo più dettagliato possibile il profilo che deve avere l'AI…"
                                        @input="set('ai.profile', $event.target.value)" />
                                </div>
                                <div class="acc-field">
                                    <label class="acc-row-label">Cosa deve sapere l'AI</label>
                                    <span class="acc-row-help">Le informazioni concrete da conoscere: orari, prodotti, regole.</span>
                                    <textarea class="control" :value="form.ai.knows" style="min-height:130px"
                                        placeholder="Scrivi quello che vuoi che l'AI conosca…"
                                        @input="set('ai.knows', $event.target.value)" />
                                </div>
                                <div class="acc-field" style="margin-bottom:0">
                                    <label class="acc-row-label">Come deve commentare l'AI</label>
                                    <span class="acc-row-help">Lo stile generale con cui l'AI risponde ai commenti e alle email.</span>
                                    <textarea class="control" :value="form.ai.commentStyle" style="min-height:110px"
                                        placeholder="Scrivi come l'AI deve commentare e rispondere…"
                                        @input="set('ai.commentStyle', $event.target.value)" />
                                </div>
                            </div>

                            <!-- OpenAI fields -->
                            <div v-else-if="activeIntg === 'openai'">
                                <div class="acc-field">
                                    <label class="acc-row-label">API Key</label>
                                    <span class="acc-row-help">La chiave segreta del tuo account OpenAI. Viene usata per generare testi e immagini.</span>
                                    <SecretField :model-value="form.openai.apiKey" placeholder="sk-proj-…"
                                        @update:model-value="set('openai.apiKey', $event)" />
                                </div>
                                <div class="acc-verify-row">
                                    <ConnectionBadge :state="form.openai.connected ? 'ok' : (verifying['openai'] ? 'checking' : 'off')" />
                                    <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
                                    <button type="button" class="acc-verify" style="margin-left:auto"
                                        :disabled="verifying['openai']"
                                        @click="verifying['openai'] = true; setTimeout(() => { verifying['openai'] = false; set('openai.connected', true); }, 1100)">
                                        <span v-if="verifying['openai']" class="acc-spin" />
                                        <Icon v-else :name="form.openai.connected ? 'check' : 'link'" :size="15" />
                                        {{ verifying['openai'] ? 'Verifica…' : form.openai.connected ? 'Riverifica' : 'Verifica connessione' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Meta fields -->
                            <div v-else-if="activeIntg === 'meta'">
                                <div class="acc-field">
                                    <label class="acc-row-label">ID della pagina Facebook</label>
                                    <span class="acc-row-help">L'ID numerico della pagina su cui pubblicare. Instagram pubblica tramite la pagina collegata.</span>
                                    <input class="control" type="text" :value="form.meta.pageId" placeholder="es. 104882736591023"
                                        @input="set('meta.pageId', $event.target.value)" />
                                </div>
                                <div class="acc-verify-row">
                                    <ConnectionBadge :state="form.meta.connected ? 'ok' : (verifying['meta'] ? 'checking' : 'off')" />
                                    <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
                                    <button type="button" class="acc-verify" style="margin-left:auto"
                                        :disabled="verifying['meta']"
                                        @click="verifying['meta'] = true; setTimeout(() => { verifying['meta'] = false; set('meta.connected', true); }, 1100)">
                                        <span v-if="verifying['meta']" class="acc-spin" />
                                        <Icon v-else :name="form.meta.connected ? 'check' : 'link'" :size="15" />
                                        {{ verifying['meta'] ? 'Verifica…' : form.meta.connected ? 'Riverifica' : 'Verifica connessione' }}
                                    </button>
                                </div>
                            </div>

                            <!-- LinkedIn fields -->
                            <div v-else-if="activeIntg === 'linkedin'">
                                <div class="acc-grid-2">
                                    <div class="acc-field">
                                        <label class="acc-row-label">Client ID</label>
                                        <span class="acc-row-help">Dall'app LinkedIn Developers.</span>
                                        <input class="control" type="text" :value="form.linkedin.clientId" placeholder="86xxxxxxxxxx"
                                            @input="set('linkedin.clientId', $event.target.value)" />
                                    </div>
                                    <div class="acc-field">
                                        <label class="acc-row-label">Client Secret</label>
                                        <span class="acc-row-help">Tienilo riservato.</span>
                                        <SecretField :model-value="form.linkedin.clientSecret" placeholder="••••••••"
                                            @update:model-value="set('linkedin.clientSecret', $event)" />
                                    </div>
                                </div>
                                <div class="acc-field">
                                    <label class="acc-row-label">ID della pagina LinkedIn</label>
                                    <span class="acc-row-help">La pagina aziendale su cui pubblicare.</span>
                                    <input class="control" type="text" :value="form.linkedin.pageId" placeholder="es. 7654321"
                                        @input="set('linkedin.pageId', $event.target.value)" />
                                </div>
                                <div class="acc-field">
                                    <label class="acc-row-label">
                                        Token LinkedIn <span class="acc-readonly-tag">solo lettura</span>
                                    </label>
                                    <span class="acc-row-help">Generato automaticamente dopo l'autorizzazione. Non modificabile a mano.</span>
                                    <SecretField :model-value="form.linkedin.token" :read-only="true" :copyable="true"
                                        placeholder="Si genera dopo «Verifica connessione»" />
                                </div>
                                <div class="acc-verify-row">
                                    <ConnectionBadge :state="form.linkedin.connected ? 'ok' : (verifying['linkedin'] ? 'checking' : 'off')" />
                                    <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
                                    <button type="button" class="acc-verify" style="margin-left:auto"
                                        :disabled="verifying['linkedin']"
                                        @click="verifying['linkedin'] = true; setTimeout(() => { verifying['linkedin'] = false; set('linkedin.connected', true); }, 1100)">
                                        <span v-if="verifying['linkedin']" class="acc-spin" />
                                        <Icon v-else :name="form.linkedin.connected ? 'check' : 'link'" :size="15" />
                                        {{ verifying['linkedin'] ? 'Verifica…' : form.linkedin.connected ? 'Riverifica' : 'Verifica connessione' }}
                                    </button>
                                </div>
                            </div>

                            <!-- WordPress fields -->
                            <div v-else-if="activeIntg === 'wordpress'">
                                <div class="acc-field">
                                    <label class="acc-row-label">URL del sito</label>
                                    <span class="acc-row-help">L'indirizzo del tuo sito WordPress.</span>
                                    <input class="control" type="url" :value="form.wordpress.url" placeholder="https://iltuosito.it"
                                        @input="set('wordpress.url', $event.target.value)" />
                                </div>
                                <div class="acc-grid-2">
                                    <div class="acc-field">
                                        <label class="acc-row-label">Username</label>
                                        <span class="acc-row-help">Utente con permessi di pubblicazione.</span>
                                        <input class="control" type="text" :value="form.wordpress.username" placeholder="admin"
                                            @input="set('wordpress.username', $event.target.value)" />
                                    </div>
                                    <div class="acc-field">
                                        <label class="acc-row-label">Application Password</label>
                                        <span class="acc-row-help">Usa una password applicativa, non quella di accesso.</span>
                                        <SecretField :model-value="form.wordpress.password" placeholder="xxxx xxxx xxxx xxxx"
                                            @update:model-value="set('wordpress.password', $event)" />
                                    </div>
                                </div>
                                <div class="acc-field">
                                    <label class="acc-row-label">Categoria ID</label>
                                    <span class="acc-row-help">L'ID della categoria in cui salvare gli articoli.</span>
                                    <input class="control" type="text" :value="form.wordpress.categoryId" placeholder="es. 8"
                                        @input="set('wordpress.categoryId', $event.target.value)" />
                                </div>
                                <div class="acc-verify-row">
                                    <ConnectionBadge :state="form.wordpress.connected ? 'ok' : (verifying['wordpress'] ? 'checking' : 'off')" />
                                    <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
                                    <button type="button" class="acc-verify" style="margin-left:auto"
                                        :disabled="verifying['wordpress']"
                                        @click="verifying['wordpress'] = true; setTimeout(() => { verifying['wordpress'] = false; set('wordpress.connected', true); }, 1100)">
                                        <span v-if="verifying['wordpress']" class="acc-spin" />
                                        <Icon v-else :name="form.wordpress.connected ? 'check' : 'link'" :size="15" />
                                        {{ verifying['wordpress'] ? 'Verifica…' : form.wordpress.connected ? 'Riverifica' : 'Verifica connessione' }}
                                    </button>
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
                                        <div class="acc-acc-head" @click="openNl = openNl === p.id ? '' : p.id">
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
                                                    <label class="acc-row-label">API Key</label>
                                                    <span class="acc-row-help">Dalla sezione Account › Extra › API keys di MailChimp.</span>
                                                    <SecretField :model-value="form.newsletter.mailchimp.apiKey" placeholder="xxxxxxxx-us21"
                                                        @update:model-value="set('newsletter.mailchimp.apiKey', $event)" />
                                                </div>
                                                <div class="acc-grid-2">
                                                    <div class="acc-field">
                                                        <label class="acc-row-label">Server prefix</label>
                                                        <span class="acc-row-help">Es. us21 (è nel dominio della tua dashboard).</span>
                                                        <input class="control" type="text" :value="form.newsletter.mailchimp.serverPrefix" placeholder="us21"
                                                            @input="set('newsletter.mailchimp.serverPrefix', $event.target.value)" />
                                                    </div>
                                                    <div class="acc-field">
                                                        <label class="acc-row-label">Audience ID</label>
                                                        <span class="acc-row-help">La lista a cui inviare.</span>
                                                        <input class="control" type="text" :value="form.newsletter.mailchimp.audienceId" placeholder="9f3c1a7b2e"
                                                            @input="set('newsletter.mailchimp.audienceId', $event.target.value)" />
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Brevo -->
                                            <template v-else-if="p.id === 'brevo'">
                                                <div class="acc-field">
                                                    <label class="acc-row-label">API Key</label>
                                                    <span class="acc-row-help">Dalla sezione SMTP &amp; API di Brevo.</span>
                                                    <SecretField :model-value="form.newsletter.brevo.apiKey" placeholder="xkeysib-…"
                                                        @update:model-value="set('newsletter.brevo.apiKey', $event)" />
                                                </div>
                                                <div class="acc-grid-2">
                                                    <div class="acc-field">
                                                        <label class="acc-row-label">List ID</label>
                                                        <span class="acc-row-help">L'ID numerico della lista.</span>
                                                        <input class="control" type="text" :value="form.newsletter.brevo.listId" placeholder="es. 12"
                                                            @input="set('newsletter.brevo.listId', $event.target.value)" />
                                                    </div>
                                                    <div class="acc-field">
                                                        <label class="acc-row-label">Mittente</label>
                                                        <span class="acc-row-help">Email verificata come mittente.</span>
                                                        <input class="control" type="email" :value="form.newsletter.brevo.sender" placeholder="news@dominio.it"
                                                            @input="set('newsletter.brevo.sender', $event.target.value)" />
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- SMTP -->
                                            <template v-else-if="p.id === 'smtp'">
                                                <div class="acc-grid-2">
                                                    <div class="acc-field">
                                                        <label class="acc-row-label">Host SMTP</label>
                                                        <span class="acc-row-help">Il server della tua casella email.</span>
                                                        <input class="control" type="text" :value="form.newsletter.smtp.host" placeholder="smtp.dominio.it"
                                                            @input="set('newsletter.smtp.host', $event.target.value)" />
                                                    </div>
                                                    <div class="acc-field">
                                                        <label class="acc-row-label">Porta</label>
                                                        <span class="acc-row-help">Di solito 587 (TLS) o 465 (SSL).</span>
                                                        <input class="control" type="text" :value="form.newsletter.smtp.port" placeholder="587"
                                                            @input="set('newsletter.smtp.port', $event.target.value)" />
                                                    </div>
                                                </div>
                                                <div class="acc-grid-2">
                                                    <div class="acc-field">
                                                        <label class="acc-row-label">Username</label>
                                                        <span class="acc-row-help">L'utente di autenticazione.</span>
                                                        <input class="control" type="text" :value="form.newsletter.smtp.username" placeholder="news@dominio.it"
                                                            @input="set('newsletter.smtp.username', $event.target.value)" />
                                                    </div>
                                                    <div class="acc-field">
                                                        <label class="acc-row-label">Password</label>
                                                        <span class="acc-row-help">La password della casella.</span>
                                                        <SecretField :model-value="form.newsletter.smtp.password" placeholder="••••••••"
                                                            @update:model-value="set('newsletter.smtp.password', $event)" />
                                                    </div>
                                                </div>
                                                <div class="acc-grid-2">
                                                    <div class="acc-field">
                                                        <label class="acc-row-label">Cifratura</label>
                                                        <span class="acc-row-help">Protocollo di sicurezza.</span>
                                                        <select class="control" :value="form.newsletter.smtp.encryption"
                                                            @change="set('newsletter.smtp.encryption', $event.target.value)">
                                                            <option value="tls">TLS</option>
                                                            <option value="ssl">SSL</option>
                                                            <option value="none">Nessuna</option>
                                                        </select>
                                                    </div>
                                                    <div class="acc-field">
                                                        <label class="acc-row-label">Mittente</label>
                                                        <span class="acc-row-help">Indirizzo che vedranno gli iscritti.</span>
                                                        <input class="control" type="text" :value="form.newsletter.smtp.sender"
                                                            placeholder="Trattoria &lt;news@dominio.it&gt;"
                                                            @input="set('newsletter.smtp.sender', $event.target.value)" />
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Verify row -->
                                            <div style="display:flex;align-items:center;gap:12px;margin-top:6px;padding-top:16px;border-top:1px solid var(--g100)">
                                                <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
                                                <button type="button" class="acc-verify" style="margin-left:auto"
                                                    :disabled="verifying['nl-' + p.id]"
                                                    @click="verifying['nl-' + p.id] = true; setTimeout(() => { verifying['nl-' + p.id] = false; set('newsletter.' + p.id + '.connected', true); }, 1100)">
                                                    <span v-if="verifying['nl-' + p.id]" class="acc-spin" />
                                                    <Icon v-else :name="form.newsletter[p.id].connected ? 'check' : 'link'" :size="15" />
                                                    {{ verifying['nl-' + p.id] ? 'Verifica…' : form.newsletter[p.id].connected ? 'Riverifica' : 'Verifica connessione' }}
                                                </button>
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
