<script setup>
import Icon from '@/Components/Icon.vue';

defineProps({
    user: { type: Object, required: true },
});

defineEmits(['clear']);

const ROLE_LABEL = { amministratore: 'Admin', manager: 'Manager', utente: 'Utente' };
</script>

<template>
    <div class="scope-ctx">
        <span class="scope-ctx-ic"><Icon name="info" :size="17" /></span>
        <span class="scope-ctx-tx">
            Stai visualizzando i dati di <b>{{ user.name }}</b> · {{ ROLE_LABEL[user.role] }}
        </span>
        <button type="button" class="scope-ctx-clear" @click="$emit('clear')">
            <Icon name="arrowRight" :size="14" style="transform: rotate(180deg)" />
            Torna alla vista generale
        </button>
    </div>
</template>
