<script setup>
import { Link } from '@inertiajs/vue3';

// Link di pagination Inertia (array `links` del paginator Laravel).
// Le label arrivano già come HTML (« / ») → v-html.
defineProps({
    links: { type: Array, required: true },
});
</script>

<template>
    <div class="pagination">
        <Link
            v-for="(link, i) in links"
            :key="i"
            :href="link.url ?? '#'"
            class="pagination__link"
            :class="{
                'pagination__link--active': link.active,
                'pagination__link--disabled': !link.url,
            }"
            :aria-current="link.active ? 'page' : null"
            :aria-disabled="!link.url ? 'true' : null"
            :tabindex="!link.url ? -1 : null"
            preserve-scroll
            preserve-state
            v-html="link.label"
        />
    </div>
</template>
