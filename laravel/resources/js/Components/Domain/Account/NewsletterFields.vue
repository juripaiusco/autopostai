<script setup>
import { ref } from 'vue';
import ChipPicker from '@/Components/UI/ChipPicker.vue';
import FieldRow from '@/Components/UI/FieldRow.vue';
import SecretField from '@/Components/UI/SecretField.vue';
import ConnectionBadge from '@/Components/UI/ConnectionBadge.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    modelValue: { type: Object, required: true }, // { mailchimp, brevo, smtp, template }
});

const emit = defineEmits(['update', 'fetch-lists']);

const PROVIDERS = [
    { id: 'mailchimp', name: 'MailChimp', sub: 'API key + audience' },
    { id: 'brevo',     name: 'Brevo',     sub: 'API key + lista' },
    { id: 'smtp',      name: 'SMTP custom', sub: 'Server email proprio' },
];

const open = ref('');

function connState(providerId) {
    return props.modelValue[providerId]?.connected ? 'ok' : 'off';
}
</script>

<template>
    <div>
        <div class="acc-nl-template">
            <div class="acc-nl-template-head">
                <Icon name="chat" :size="16" />
                <span class="acc-nl-template-title">Template email</span>
            </div>
            <FieldRow id="acc-nl-template-content" label="HTML del modello"
                help="Il codice HTML usato come modello per generare il contenuto della newsletter.">
                <textarea id="acc-nl-template-content" class="control acc-nl-template-ta" :value="modelValue.template?.content"
                    style="min-height:220px"
                    placeholder="<html>…</html>"
                    @input="emit('update', 'template', 'content', $event.target.value)" />
            </FieldRow>
            <FieldRow id="acc-nl-template-cta" label="Call to action" style="margin-bottom:0"
                help="Il blocco HTML della call-to-action inserito nel modello.">
                <textarea id="acc-nl-template-cta" class="control acc-nl-template-ta" :value="modelValue.template?.cta"
                    style="min-height:90px"
                    placeholder="<a href=…>Scopri di più</a>"
                    @input="emit('update', 'template', 'cta', $event.target.value)" />
            </FieldRow>
        </div>

        <p class="acc-hint-inline" style="margin-bottom:14px">
            <Icon name="info" :size="14" />
            Collega <b style="margin:0 4px;color:var(--g700)">uno</b> dei provider che usi per le tue campagne.
        </p>

        <div class="acc-acc">
            <div v-for="p in PROVIDERS" :key="p.id"
                class="acc-acc-item" :class="{ open: open === p.id }">
                <div class="acc-acc-head" role="button" tabindex="0" :aria-expanded="open === p.id"
                    :aria-label="'Espandi ' + p.name"
                    @click="open = open === p.id ? '' : p.id"
                    @keydown.enter.prevent="open = open === p.id ? '' : p.id"
                    @keydown.space.prevent="open = open === p.id ? '' : p.id">
                    <div class="acc-acc-ic">
                        <Icon :name="p.id === 'smtp' ? 'settings' : 'chat'" :size="18" />
                    </div>
                    <div class="acc-acc-grow">
                        <div class="acc-acc-title">{{ p.name }}</div>
                        <div class="acc-acc-sub">{{ p.sub }}</div>
                    </div>
                    <ConnectionBadge :state="connState(p.id)" />
                    <Icon name="chevron" :size="18" class="acc-acc-chev" />
                </div>

                <div v-if="open === p.id" class="acc-acc-body acc-reveal">
                    <!-- MailChimp -->
                    <template v-if="p.id === 'mailchimp'">
                        <FieldRow id="acc-nl-mc-key" label="API Key" help="Dalla sezione Account › Extra › API keys di MailChimp.">
                            <SecretField id="acc-nl-mc-key" :model-value="modelValue.mailchimp.apiKey" placeholder="xxxxxxxx-us21"
                                :saved-hint="modelValue.mailchimp.apiKeyHint" :cleared="!!modelValue.mailchimp.apiKeyClear"
                                @update:model-value="emit('update', 'mailchimp', 'apiKey', $event)"
                                @clear="emit('update', 'mailchimp', 'apiKeyClear', $event)" />
                        </FieldRow>
                        <FieldRow id="acc-nl-mc-server" label="Server prefix" help="Es. us21 (è nel dominio della tua dashboard).">
                            <input id="acc-nl-mc-server" class="control" type="text" :value="modelValue.mailchimp.serverPrefix" placeholder="us21"
                                @input="emit('update', 'mailchimp', 'serverPrefix', $event.target.value)" />
                        </FieldRow>
                        <div class="acc-grid-2">
                            <FieldRow id="acc-nl-mc-sender-name" label="Nome mittente" help="Il nome che vedranno gli iscritti.">
                                <input id="acc-nl-mc-sender-name" class="control" type="text" :value="modelValue.mailchimp.senderName" placeholder="Trattoria da Mario"
                                    @input="emit('update', 'mailchimp', 'senderName', $event.target.value)" />
                            </FieldRow>
                            <FieldRow id="acc-nl-mc-sender" label="Email mittente" help="Deve essere verificata su MailChimp.">
                                <input id="acc-nl-mc-sender" class="control" type="email" :value="modelValue.mailchimp.sender" placeholder="news@dominio.it"
                                    @input="emit('update', 'mailchimp', 'sender', $event.target.value)" />
                            </FieldRow>
                        </div>

                        <div v-if="modelValue.mailchimp.lists?.length" class="acc-field">
                            <label class="acc-row-label">Audience</label>
                            <span class="acc-row-help">Scegli la lista a cui inviare le campagne.</span>
                            <ChipPicker :options="modelValue.mailchimp.lists" :model-value="modelValue.mailchimp.audienceId"
                                count-label="iscritti" aria-label="Audience MailChimp"
                                @update:model-value="emit('update', 'mailchimp', 'audienceId', $event)" />
                        </div>
                        <FieldRow v-else id="acc-nl-mc-audience" label="Audience ID" help="La lista a cui inviare. Si popola come elenco dopo aver caricato le liste.">
                            <input id="acc-nl-mc-audience" class="control" type="text" :value="modelValue.mailchimp.audienceId" placeholder="9f3c1a7b2e"
                                @input="emit('update', 'mailchimp', 'audienceId', $event.target.value)" />
                        </FieldRow>
                    </template>

                    <!-- Brevo -->
                    <template v-else-if="p.id === 'brevo'">
                        <FieldRow id="acc-nl-brevo-key" label="API Key" help="Dalla sezione SMTP &amp; API di Brevo.">
                            <SecretField id="acc-nl-brevo-key" :model-value="modelValue.brevo.apiKey" placeholder="xkeysib-…"
                                :saved-hint="modelValue.brevo.apiKeyHint" :cleared="!!modelValue.brevo.apiKeyClear"
                                @update:model-value="emit('update', 'brevo', 'apiKey', $event)"
                                @clear="emit('update', 'brevo', 'apiKeyClear', $event)" />
                        </FieldRow>
                        <div class="acc-grid-2">
                            <FieldRow id="acc-nl-brevo-sender-name" label="Nome mittente" help="Il nome che vedranno gli iscritti.">
                                <input id="acc-nl-brevo-sender-name" class="control" type="text" :value="modelValue.brevo.senderName" placeholder="Trattoria da Mario"
                                    @input="emit('update', 'brevo', 'senderName', $event.target.value)" />
                            </FieldRow>
                            <FieldRow id="acc-nl-brevo-sender" label="Email mittente" help="Deve essere verificata su Brevo.">
                                <input id="acc-nl-brevo-sender" class="control" type="email" :value="modelValue.brevo.sender" placeholder="news@dominio.it"
                                    @input="emit('update', 'brevo', 'sender', $event.target.value)" />
                            </FieldRow>
                        </div>

                        <div v-if="modelValue.brevo.lists?.length" class="acc-field">
                            <label class="acc-row-label">Lista</label>
                            <span class="acc-row-help">Scegli la lista a cui inviare le campagne.</span>
                            <ChipPicker :options="modelValue.brevo.lists" :model-value="modelValue.brevo.listId"
                                count-label="iscritti" aria-label="Lista Brevo"
                                @update:model-value="emit('update', 'brevo', 'listId', $event)" />
                        </div>
                        <FieldRow v-else id="acc-nl-brevo-list" label="List ID" help="L'ID numerico della lista. Si popola come elenco dopo aver caricato le liste.">
                            <input id="acc-nl-brevo-list" class="control" type="text" :value="modelValue.brevo.listId" placeholder="es. 12"
                                @input="emit('update', 'brevo', 'listId', $event.target.value)" />
                        </FieldRow>
                    </template>

                    <!-- SMTP -->
                    <template v-else-if="p.id === 'smtp'">
                        <div class="acc-grid-2">
                            <FieldRow id="acc-nl-smtp-host" label="Host SMTP" help="Il server della tua casella email.">
                                <input id="acc-nl-smtp-host" class="control" type="text" :value="modelValue.smtp.host" placeholder="smtp.dominio.it"
                                    @input="emit('update', 'smtp', 'host', $event.target.value)" />
                            </FieldRow>
                            <FieldRow id="acc-nl-smtp-port" label="Porta" help="Di solito 587 (TLS) o 465 (SSL).">
                                <input id="acc-nl-smtp-port" class="control" type="text" :value="modelValue.smtp.port" placeholder="587"
                                    @input="emit('update', 'smtp', 'port', $event.target.value)" />
                            </FieldRow>
                        </div>
                        <div class="acc-grid-2">
                            <FieldRow id="acc-nl-smtp-username" label="Username" help="L'utente di autenticazione.">
                                <input id="acc-nl-smtp-username" class="control" type="text" :value="modelValue.smtp.username" placeholder="news@dominio.it"
                                    @input="emit('update', 'smtp', 'username', $event.target.value)" />
                            </FieldRow>
                            <FieldRow id="acc-nl-smtp-password" label="Password" help="La password della casella.">
                                <SecretField id="acc-nl-smtp-password" :model-value="modelValue.smtp.password" placeholder="••••••••"
                                    :saved-hint="modelValue.smtp.passwordHint" :cleared="!!modelValue.smtp.passwordClear"
                                    @update:model-value="emit('update', 'smtp', 'password', $event)"
                                    @clear="emit('update', 'smtp', 'passwordClear', $event)" />
                            </FieldRow>
                        </div>
                        <div class="acc-grid-2">
                            <FieldRow id="acc-nl-smtp-encryption" label="Cifratura" help="Protocollo di sicurezza.">
                                <select id="acc-nl-smtp-encryption" class="control" :value="modelValue.smtp.encryption"
                                    @change="emit('update', 'smtp', 'encryption', $event.target.value)">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="none">Nessuna</option>
                                </select>
                            </FieldRow>
                            <FieldRow id="acc-nl-smtp-sender" label="Mittente" help="Indirizzo che vedranno gli iscritti.">
                                <input id="acc-nl-smtp-sender" class="control" type="text" :value="modelValue.smtp.sender"
                                    placeholder="Trattoria &lt;news@dominio.it&gt;"
                                    @input="emit('update', 'smtp', 'sender', $event.target.value)" />
                            </FieldRow>
                        </div>
                    </template>

                    <!-- Status row -->
                    <div style="display:flex;align-items:center;gap:12px;margin-top:6px;padding-top:16px;border-top:1px solid var(--g100)">
                        <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
                        <button v-if="p.id === 'mailchimp' || p.id === 'brevo'" type="button" class="acc-verify" style="margin-left:auto" @click="emit('fetch-lists')">
                            <Icon name="link" :size="15" />
                            {{ modelValue[p.id].lists?.length ? 'Aggiorna liste' : 'Carica liste' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
