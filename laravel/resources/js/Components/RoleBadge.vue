<script setup>
const ROLE = {
    amministratore: { label: 'Admin', bg: '#ede9fe', border: '#a78bfa', fg: '#5b21b6' },
    manager: { label: 'Manager', bg: '#dbeafe', border: '#60a5fa', fg: '#1d4ed8' },
    utente: { label: 'Utente', bg: 'var(--g100)', border: 'var(--g300)', fg: 'var(--g600)' },
};

const props = defineProps({
    role: { type: String, required: true },
});

const s = ROLE[props.role] ?? ROLE.utente;
</script>

<template>
    <span :style="{
        display: 'inline-flex', alignItems: 'center',
        padding: '2px 9px', borderRadius: 9999,
        border: `1px solid ${s.border}`,
        background: s.bg, color: s.fg,
        fontSize: '11px', fontWeight: 600, whiteSpace: 'nowrap',
    }">{{ s.label }}</span>
</template>
