<script setup>
import { computed } from 'vue';

const ROLE_LABEL = {
    amministratore: 'Admin',
    manager: 'Manager',
    utente: 'Utente',
};

const props = defineProps({
    role: { type: String, required: true },
});

const label = computed(() => ROLE_LABEL[props.role] ?? ROLE_LABEL.utente);
const variant = computed(() => (props.role in ROLE_LABEL ? props.role : 'utente'));
</script>

<template>
    <span class="role-badge" :class="`role-badge--${variant}`">{{ label }}</span>
</template>
