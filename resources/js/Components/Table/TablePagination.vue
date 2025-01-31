<script setup>

import {Link} from "@inertiajs/vue3";
import {__} from "@/ComponentsExt/Translations.js";

defineProps({
    links: Array,
    preserveState: Boolean
})

</script>

<template>

    <div v-if="links.length > 3">

        <div class="flex flex-wrap -mb-1">
            <template v-for="(link, k) in links" :key="k">

                <div v-if="link.url === null"
                     class="mr-1 mb-1 px-4 py-2 text-sm leading-4 text-gray-300 border rounded dark:text-gray-700 dark:border-gray-600"
                     v-html="__(link.label)" />

                <Link
                    v-else
                    class="mr-1 mb-1 px-4 py-2 text-sm leading-4 border rounded text-gray-500 hover:bg-sky-100 hover:text-sky-400 focus:border-sky-500 focus:text-sky-500 dark:border-gray-600 dark:hover:bg-gray-900 dark:hover:text-sky-700 dark:hover:border-gray-600"
                    :class="{ 'bg-sky-100 text-sky-400 border-sky-200 dark:bg-gray-900 dark:text-sky-700 dark:border-sky-700': link.active }"
                    :href="link.url"
                    v-html="__(link.label)"
                    preserve-scroll
                    :preserveState="preserveState" />

            </template>
        </div>
    </div>

</template>
