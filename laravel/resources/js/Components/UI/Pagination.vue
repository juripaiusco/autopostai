<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

// Link di pagination Inertia (array `links` del paginator Laravel): il primo
// e l'ultimo sono sempre Precedente/Successiva → resi come frecce (le label
// testuali sbordavano su mobile); gli altri sono numeri o "..." (HTML → v-html).
const props = defineProps({
    links: { type: Array, required: true },
});

const isPrev = (i) => i === 0;
const isNext = (i) => i === props.links.length - 1;
</script>

<template>
    <nav class="pagination" aria-label="Paginazione">
        <Link
            v-for="(link, i) in links"
            :key="i"
            :href="link.url ?? '#'"
            class="pagination__link"
            :class="{
                'pagination__link--active': link.active,
                'pagination__link--disabled': !link.url,
                'pagination__link--arrow': isPrev(i) || isNext(i),
            }"
            :aria-current="link.active ? 'page' : null"
            :aria-disabled="!link.url ? 'true' : null"
            :aria-label="isPrev(i) ? 'Pagina precedente' : isNext(i) ? 'Pagina successiva' : null"
            :tabindex="!link.url ? -1 : null"
            preserve-scroll
            preserve-state
        >
            <Icon v-if="isPrev(i)" name="chevron" :size="15" class="pagination__arrow pagination__arrow--prev" />
            <Icon v-else-if="isNext(i)" name="chevron" :size="15" class="pagination__arrow pagination__arrow--next" />
            <span v-else v-html="link.label" />
        </Link>
    </nav>
</template>
