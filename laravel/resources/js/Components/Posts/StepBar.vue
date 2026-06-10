<script setup>
const props = defineProps({
    current: { type: Number, required: true },
    labels: { type: Array, default: () => ['Scrivi', 'Media', 'Pubblica'] },
});

const emit = defineEmits(['goto']);

function stepClass(i) {
    if (i < props.current) return 'pf-step--done pf-step--clickable';
    if (i === props.current) return 'pf-step--active';
    return '';
}

function goto(i) {
    if (i < props.current) emit('goto', i);
}
</script>

<template>
    <div class="pf-stepbar">
        <template v-for="(label, i) in labels" :key="i">
            <button type="button" class="pf-step" :class="stepClass(i)" @click="goto(i)">
                <span class="pf-step-circle">{{ i < current ? '✓' : i + 1 }}</span>
                <span class="pf-step-label">{{ label }}</span>
            </button>
            <div v-if="i < labels.length - 1" class="pf-step-line" :class="{ 'pf-step-line--done': i < current }" />
        </template>
    </div>
</template>
