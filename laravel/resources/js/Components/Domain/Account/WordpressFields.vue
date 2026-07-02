<script setup>
import FieldRow from '@/Components/UI/FieldRow.vue';
import SecretField from '@/Components/UI/SecretField.vue';
import ConnectionBadge from '@/Components/UI/ConnectionBadge.vue';

defineProps({
    modelValue: { type: Object, required: true }, // { url, username, password, categoryId, connected }
});

const emit = defineEmits(['update']);
</script>

<template>
    <div>
        <FieldRow id="acc-wp-url" label="URL del sito" help="L'indirizzo del tuo sito WordPress.">
            <input id="acc-wp-url" class="control" type="url" :value="modelValue.url" placeholder="https://iltuosito.it"
                @input="emit('update', 'url', $event.target.value)" />
        </FieldRow>
        <div class="acc-grid-2">
            <FieldRow id="acc-wp-username" label="Username" help="Utente con permessi di pubblicazione.">
                <input id="acc-wp-username" class="control" type="text" :value="modelValue.username" placeholder="admin"
                    @input="emit('update', 'username', $event.target.value)" />
            </FieldRow>
            <FieldRow id="acc-wp-password" label="Application Password" help="Usa una password applicativa, non quella di accesso.">
                <SecretField id="acc-wp-password" :model-value="modelValue.password" placeholder="xxxx xxxx xxxx xxxx"
                    @update:model-value="emit('update', 'password', $event)" />
            </FieldRow>
        </div>
        <FieldRow id="acc-wp-category" label="Categoria ID" help="L'ID della categoria in cui salvare gli articoli.">
            <input id="acc-wp-category" class="control" type="text" :value="modelValue.categoryId" placeholder="es. 8"
                @input="emit('update', 'categoryId', $event.target.value)" />
        </FieldRow>
        <div class="acc-verify-row">
            <ConnectionBadge :state="modelValue.connected ? 'ok' : 'off'" />
            <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
        </div>
    </div>
</template>
