<script setup>
import FieldRow from '@/Components/UI/FieldRow.vue';
import SecretField from '@/Components/UI/SecretField.vue';
import ConnectionBadge from '@/Components/UI/ConnectionBadge.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    modelValue: { type: Object, required: true }, // { clientId, clientSecret, pageId, token, connected }
});

const emit = defineEmits(['update', 'get-token']);
</script>

<template>
    <div>
        <div class="acc-grid-2">
            <FieldRow id="acc-li-clientid" label="Client ID" help="Dall'app LinkedIn Developers.">
                <input id="acc-li-clientid" class="control" type="text" :value="modelValue.clientId" placeholder="86xxxxxxxxxx"
                    @input="emit('update', 'clientId', $event.target.value)" />
            </FieldRow>
            <FieldRow id="acc-li-secret" label="Client Secret" help="Tienilo riservato.">
                <SecretField id="acc-li-secret" :model-value="modelValue.clientSecret" placeholder="••••••••"
                    @update:model-value="emit('update', 'clientSecret', $event)" />
            </FieldRow>
        </div>
        <FieldRow id="acc-li-pageid" label="ID della pagina LinkedIn" help="La pagina aziendale su cui pubblicare.">
            <input id="acc-li-pageid" class="control" type="text" :value="modelValue.pageId" placeholder="es. 7654321"
                @input="emit('update', 'pageId', $event.target.value)" />
        </FieldRow>
        <div class="acc-field">
            <label class="acc-row-label" for="acc-li-token">
                Token LinkedIn <span class="acc-readonly-tag">solo lettura</span>
            </label>
            <span class="acc-row-help">
                Generato automaticamente dopo l'autorizzazione. Non modificabile a mano.
                <template v-if="modelValue.tokenExpiresAt"> Scade {{ modelValue.tokenExpiresAt }}.</template>
            </span>
            <SecretField id="acc-li-token" :model-value="modelValue.token" :read-only="true" :copyable="true"
                placeholder="Si genera dopo «Ottieni token»" />
        </div>
        <div v-if="modelValue.sharedWithCount" class="acc-hint-inline" style="margin-bottom: 14px">
            <Icon name="info" :size="14" />
            Questa app LinkedIn è condivisa con altri {{ modelValue.sharedWithCount }} account: aggiornando il token qui, si aggiorna per tutti loro.
        </div>
        <div class="acc-verify-row">
            <ConnectionBadge :state="modelValue.connected ? 'ok' : 'off'" />
            <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
            <button type="button" class="acc-verify" style="margin-left:auto" @click="emit('get-token')">
                <Icon name="link" :size="15" />
                {{ modelValue.connected ? 'Rinnova token' : 'Ottieni token' }}
            </button>
        </div>
    </div>
</template>
