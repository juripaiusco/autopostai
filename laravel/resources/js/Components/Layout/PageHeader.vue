<script setup>
// Header pagina con breadcrumb. Lo slot #actions ospita contenuto allineato a
// destra (badge, note, bottoni).
import { Link } from '@inertiajs/vue3';

defineProps({
    // [{ label: 'Account', href: route('account') }, { label: 'Lista', current: true }]
    crumbs: { type: Array, required: true },
});
</script>

<template>
    <header class="page-header">
        <div class="page-header__inner">
            <div class="page-header__crumbs">
                <template v-for="(c, i) in crumbs" :key="i">
                    <span v-if="i > 0" class="crumb-sep">›</span>
                    <b v-if="c.current">{{ c.label }}</b>
                    <Link v-else-if="c.href" :href="c.href">{{ c.label }}</Link>
                    <span v-else>{{ c.label }}</span>
                </template>
            </div>
            <slot name="actions" />
        </div>
    </header>
</template>
