<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    used: { type: Number, required: true },
    total: { type: Number, required: true },
    index: { type: Number, default: 0 },
});

const pct = computed(() => (props.total > 0 ? Math.min((props.used / props.total) * 100, 100) : 0));
const warn = computed(() => pct.value >= 80);
const fmt = (n) => Math.round(Number(n)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
const ariaLabel = computed(() => `${fmt(props.used)} di ${fmt(props.total)}`);

// Entrance: il fill parte da 0 e cresce fino a pct dopo il mount.
const grown = ref(false);
onMounted(() => requestAnimationFrame(() => { grown.value = true; }));
const fillDelay = computed(() => `${Math.min(props.index, 9) * 50}ms`);
</script>

<template>
    <div
        class="token-bar"
        role="progressbar"
        :aria-valuenow="used"
        :aria-valuemin="0"
        :aria-valuemax="total"
        :aria-label="ariaLabel"
    >
        <div class="token-bar__head" aria-hidden="true">
            <span class="token-bar__used" :class="{ 'token-bar__used--warn': warn }">{{ fmt(used) }}</span>
            <span class="token-bar__sep">/</span>
            <span>{{ fmt(total) }}</span>
        </div>
        <div class="token-bar__track">
            <div
                class="token-bar__fill"
                :class="{ 'token-bar__fill--warn': warn, 'token-bar__fill--empty': pct === 0 }"
                :style="{ width: (grown ? pct : 0) + '%', transitionDelay: fillDelay }"
            ></div>
        </div>
    </div>
</template>
