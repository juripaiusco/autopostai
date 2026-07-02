<script setup>
defineProps({
    title: { type: String, required: true },
    help: { type: String, default: null },
    variant: { type: String, default: 'default' }, // 'default' | 'warning' | 'success'
});

const VARIANT_STYLE = {
    warning: { borderColor: 'var(--st-sch-bd)', background: 'var(--st-sch-bg)' },
    success: { borderColor: 'var(--st-pub-bd)', background: 'var(--st-pub-bg)' },
};

const VARIANT_TITLE_COLOR = {
    warning: 'var(--st-sch-fg)',
    success: 'var(--st-pub-fg)',
};
</script>

<template>
    <div class="acc-ch-opt" :style="VARIANT_STYLE[variant]">
        <div class="acc-ch-opt-row">
            <div class="acc-ch-opt-txt">
                <div class="acc-ch-opt-title" :style="{ color: VARIANT_TITLE_COLOR[variant] }">{{ title }}</div>
                <div v-if="help" class="acc-ch-opt-help">{{ help }}</div>
            </div>
            <slot name="control" />
        </div>
        <slot />
    </div>
</template>
