<script setup>
import { computed } from 'vue';

const props = defineProps({
    used: { type: Number, required: true },
    total: { type: Number, required: true },
});

const pct = computed(() => (props.total > 0 ? Math.min((props.used / props.total) * 100, 100) : 0));
const warn = computed(() => pct.value >= 80);
const fmt = (n) => Math.round(Number(n)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
</script>

<template>
    <div style="min-width: 140px">
        <div style="display: flex; justify-content: flex-end; gap: 4px; font-size: 12px; color: var(--g500); margin-bottom: 5px">
            <span :style="{ fontWeight: 600, color: warn ? 'var(--danger)' : 'var(--ink)' }">{{ fmt(used) }}</span>
            <span style="color: var(--g300)">/</span>
            <span>{{ fmt(total) }}</span>
        </div>
        <div style="height: 6px; border-radius: 9999px; background: var(--g200); overflow: hidden">
            <div :style="{
                height: '100%', borderRadius: '9999px',
                width: pct + '%',
                background: pct === 0 ? 'var(--g200)' : (warn ? 'var(--danger)' : 'var(--st-pub-bd)'),
                transition: 'width .3s ease',
            }"></div>
        </div>
    </div>
</template>
