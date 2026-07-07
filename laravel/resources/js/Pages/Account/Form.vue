<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import SectionCard from '@/Components/Layout/SectionCard.vue';
import Icon from '@/Components/Icon.vue';
import SecretField from '@/Components/UI/SecretField.vue';
import ToggleSwitch from '@/Components/UI/ToggleSwitch.vue';
import FieldRow from '@/Components/UI/FieldRow.vue';
import OptionRow from '@/Components/UI/OptionRow.vue';
import InlineUsageBar from '@/Components/UI/InlineUsageBar.vue';
import ChannelSettingsRow from '@/Components/Domain/Account/ChannelSettingsRow.vue';
import IntegrationMenu from '@/Components/Domain/Account/IntegrationMenu.vue';
import IntegrationPanelHeader from '@/Components/Domain/Account/IntegrationPanelHeader.vue';
import AiFields from '@/Components/Domain/Account/AiFields.vue';
import OpenAiFields from '@/Components/Domain/Account/OpenAiFields.vue';
import MetaFields from '@/Components/Domain/Account/MetaFields.vue';
import LinkedinFields from '@/Components/Domain/Account/LinkedinFields.vue';
import WordpressFields from '@/Components/Domain/Account/WordpressFields.vue';
import NewsletterFields from '@/Components/Domain/Account/NewsletterFields.vue';

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
    if (props.mode === 'create') {
        showToast('Salva prima l\'account, poi potrai collegare LinkedIn');
        return;
    }
    // Navigazione piena (non Inertia): si esce verso il consenso LinkedIn.
    window.location.href = form.linkedin.connectUrl;
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

function chConn(chId) {
    return intgConn(CH_TO_INTG[chId]);
}

const integrationConnStates = computed(() => Object.fromEntries(INTEGRATIONS.map(i => [i.id, intgConn(i.id)])));

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
            <PageHeader :crumbs="[{ label: 'Account', href: route('account') }, { label: title, current: true }]">
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
                <SectionCard title="Profilo Account"
                    :subtitle="mode === 'edit' ? 'Dati di accesso dell\'account.' : 'Sta venendo creato da ' + (account?.createdBy ?? userName)"
                    :with-aside="mode === 'edit'">
                    <template #aside>
                        <span v-if="mode === 'edit'" class="acc-chip">
                            <Icon name="pencil" :size="14" />Modificato {{ account?.updatedAt ?? '—' }}
                        </span>
                    </template>

                    <FieldRow id="acc-name" label="Nome" help="Solo ad uso interno, non viene pubblicato.">
                        <input id="acc-name" class="control" type="text" :value="form.name" placeholder="Es. Trattoria da Marco"
                            @input="set('name', $event.target.value)" />
                    </FieldRow>
                    <FieldRow id="acc-email" label="E-mail" help="Serve per l'accesso. Solo ad uso interno, non viene pubblicata.">
                        <input id="acc-email" class="control" type="email" :value="form.email" placeholder="nome@dominio.it"
                            @input="set('email', $event.target.value)" />
                    </FieldRow>
                    <FieldRow id="acc-password" label="Password" style="margin-bottom:0"
                        :help="mode === 'edit' ? 'Lascia invariato per non cambiarla.' : 'Scegli una password sicura.'">
                        <SecretField id="acc-password" :model-value="form.password" placeholder="Almeno 8 caratteri"
                            @update:model-value="set('password', $event)" />
                    </FieldRow>
                </SectionCard>

                <!-- Account Manager -->
                <SectionCard title="Account Manager" subtitle="Gestione amministrativa dell'account: limiti, piano e gerarchia.">

                    <!-- Sub-utenti toggle -->
                    <OptionRow style="margin-bottom: 22px" title="L'account può creare sotto-utenti"
                        help="Attivalo se questo account gestisce più brand e deve poter creare account collegati.">
                        <template #control>
                            <ToggleSwitch :model-value="form.canSubusers" @update:model-value="set('canSubusers', $event)" />
                        </template>
                    </OptionRow>

                    <!-- Sotto-utenti / Manager, Token al mese, Immagini al giorno -->
                    <div class="acc-field-row acc-field-row--3">
                        <FieldRow v-if="form.canSubusers" id="acc-subusers-limit" label="Numero massimo sotto-utenti"
                            help="Quanti utenti si possono creare." style="margin-bottom:0">
                            <div class="acc-input-unit">
                                <input id="acc-subusers-limit" class="control" type="number" :value="form.subusersLimit" placeholder="5"
                                    @input="set('subusersLimit', $event.target.value)" />
                                <span class="unit">utenti</span>
                            </div>
                            <InlineUsageBar v-if="mode === 'edit' && account?.usage" label="sotto-utenti attivi"
                                :current="account.usage.subusersActive ?? 0" :max="Number(form.subusersLimit) || 1" />
                        </FieldRow>
                        <FieldRow v-else id="acc-manager" label="Manager assegnato" style="margin-bottom:0"
                            :help="form.manager ? 'Account gestito da un manager.' : &quot;L'account non ha ancora un manager.&quot;">
                            <select id="acc-manager" class="control" :value="form.parent_id" @change="set('manager', $event.target.value)">
                                <option value="">Nessun manager</option>
                                <option v-for="m in managers" :key="m.id" :value="m.id">{{ m.name }}</option>
                                <option v-if="!managers.length" value="mock-1">Studio Sociale · agenzia</option>
                                <option v-if="!managers.length" value="mock-2">Marketing interno</option>
                            </select>
                        </FieldRow>

                        <FieldRow id="acc-tokens-month" label="Token al mese" help="Numero di token utilizzabili al mese." style="margin-bottom:0">
                            <div class="acc-input-unit">
                                <input id="acc-tokens-month" class="control" type="number" :value="form.tokensMonth" placeholder="50000"
                                    @input="set('tokensMonth', $event.target.value)" />
                                <span class="unit">token</span>
                            </div>
                            <InlineUsageBar v-if="mode === 'edit' && account?.usage" label="token usati questo mese"
                                :current="account.usage.tokensUsed" :max="Number(form.tokensMonth) || 50000"
                                :formatter="(n) => n.toLocaleString('it')" />
                        </FieldRow>

                        <FieldRow id="acc-images-day" label="Immagini al giorno" help="Immagini generabili al giorno." style="margin-bottom:0">
                            <div class="acc-input-unit">
                                <input id="acc-images-day" class="control" type="number" :value="form.imagesDay" placeholder="20"
                                    @input="set('imagesDay', $event.target.value)" />
                                <span class="unit">img al dì</span>
                            </div>
                            <InlineUsageBar v-if="mode === 'edit' && account?.usage" label="immagini generate oggi"
                                :current="account.usage.imagesUsed" :max="Number(form.imagesDay) || 20"
                                color="var(--st-pub-bd)" value-color="var(--st-pub-fg)" />
                        </FieldRow>
                    </div>
                </SectionCard>
            </div>

            <!-- ──────────────── Tab: Canali ──────────────── -->
            <div v-if="activeTab === 'canali'" id="acc-panel-canali" role="tabpanel" aria-labelledby="acc-tab-canali" class="acc-reveal">
                <SectionCard title="Canali di pubblicazione" with-aside
                    subtitle="Scegli come gestire la comunicazione e su quali canali può pubblicare questo account.">
                    <template #aside>
                        <span class="acc-chip">
                            <Icon name="check" :size="14" />{{ activeCh }} attivi
                        </span>
                    </template>

                    <div class="acc-channels">
                        <ChannelSettingsRow v-for="ch in CHANNELS" :key="ch.id"
                            :channel="ch" :copy="CH_COPY[ch.kind]"
                            :on="form.channels[ch.id].on" :reply-on="form.channels[ch.id].reply_on"
                            :reply-n="form.channels[ch.id].reply_n" :connection-state="chConn(ch.id)"
                            @update:on="set('channels.' + ch.id + '.on', $event)"
                            @toggle-reply="onToggleReplyOn(ch.id, $event)"
                            @update:reply-n="set('channels.' + ch.id + '.reply_n', $event)"
                            @go-to-integration="goToIntegration(ch.id)" />
                    </div>
                </SectionCard>
            </div>

            <!-- ──────────────── Tab: AI & Integrazioni ──────────────── -->
            <div v-if="activeTab === 'imp'" id="acc-panel-imp" role="tabpanel" aria-labelledby="acc-tab-imp" class="acc-reveal">
                <SectionCard title="AI & Integrazioni" subtitle="Profilo dell'AI e credenziali dei servizi. Le chiavi sono uniche, non duplicarle.">
                    <div class="acc-imenu-wrap">
                        <IntegrationMenu :items="INTEGRATIONS" :active="activeIntg" :conn-states="integrationConnStates"
                            @select="activeIntg = $event" />

                        <!-- Right panel -->
                        <div class="acc-ipanel">
                            <IntegrationPanelHeader :id="activeIntg"
                                :name="INTEGRATIONS.find(i => i.id === activeIntg)?.name"
                                :sub="INTEGRATIONS.find(i => i.id === activeIntg)?.sub"
                                :tint="INTG_TINT[activeIntg]" :conn-state="integrationConnStates[activeIntg]" />

                            <AiFields v-if="activeIntg === 'ai'" :model-value="form.ai"
                                @update="(field, value) => set('ai.' + field, value)" />

                            <OpenAiFields v-else-if="activeIntg === 'openai'" :model-value="form.openai"
                                @update="(field, value) => set('openai.' + field, value)" />

                            <MetaFields v-else-if="activeIntg === 'meta'" :model-value="form.meta"
                                @update="(field, value) => set('meta.' + field, value)" />

                            <LinkedinFields v-else-if="activeIntg === 'linkedin'" :model-value="form.linkedin"
                                @update="(field, value) => set('linkedin.' + field, value)" @get-token="getLinkedinToken" />

                            <WordpressFields v-else-if="activeIntg === 'wordpress'" :model-value="form.wordpress"
                                @update="(field, value) => set('wordpress.' + field, value)" />

                            <NewsletterFields v-else-if="activeIntg === 'newsletter'" :model-value="form.newsletter"
                                @update="(provider, field, value) => set('newsletter.' + provider + '.' + field, value)" />
                        </div>
                    </div>
                </SectionCard>
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
