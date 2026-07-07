<script setup>
import FieldRow from '@/Components/UI/FieldRow.vue';
import SecretField from '@/Components/UI/SecretField.vue';
import ConnectionBadge from '@/Components/UI/ConnectionBadge.vue';
import Icon from '@/Components/Icon.vue';

defineProps({
    modelValue: { type: Object, required: true }, // { url, username, password, categoryId, connected, categories }
});

const emit = defineEmits(['update', 'fetch-categories']);
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

        <div v-if="modelValue.categories?.length" class="acc-field">
            <label class="acc-row-label">Categoria</label>
            <span class="acc-row-help">Scegli la categoria del sito in cui salvare gli articoli.</span>
            <div class="acc-li-pages">
                <button
                    v-for="cat in modelValue.categories"
                    :key="cat.id"
                    type="button"
                    class="acc-li-page"
                    :class="{ 'acc-li-page--active': String(modelValue.categoryId) === String(cat.id) }"
                    @click="emit('update', 'categoryId', cat.id)"
                >
                    <span class="acc-li-page-dot" aria-hidden="true"></span>
                    <span class="acc-li-page-name">{{ cat.name }}</span>
                    <Icon v-if="String(modelValue.categoryId) === String(cat.id)" name="check" :size="16" />
                </button>
            </div>
        </div>
        <FieldRow v-else id="acc-wp-category" label="Categoria ID" help="L'ID della categoria in cui salvare gli articoli. Si popola come elenco dopo aver caricato le categorie.">
            <input id="acc-wp-category" class="control" type="text" :value="modelValue.categoryId" placeholder="es. 8"
                @input="emit('update', 'categoryId', $event.target.value)" />
        </FieldRow>

        <div class="acc-verify-row">
            <ConnectionBadge :state="modelValue.connected ? 'ok' : 'off'" />
            <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
            <button type="button" class="acc-verify" style="margin-left:auto" @click="emit('fetch-categories')">
                <Icon name="link" :size="15" />
                {{ modelValue.categories?.length ? 'Aggiorna categorie' : 'Carica categorie' }}
            </button>
        </div>
    </div>
</template>
