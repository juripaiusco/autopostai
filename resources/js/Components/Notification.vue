<script setup lang="ts">

import {usePage} from "@inertiajs/vue3";
import {onMounted, onBeforeUnmount, ref} from 'vue';
import { Popover } from 'bootstrap';

const btn = ref(null);
let popoverInstance = null;

onMounted(() => {
    if (btn.value) {
        popoverInstance = new Popover(btn.value);
    }
});

onBeforeUnmount(() => {
    if (popoverInstance) popoverInstance.dispose();
});

</script>

<template>
    <!-- Notification Bell new version -->
    <div class="animate-ring">

        <button
            ref="btn"
            type="button"
            class="btn btn-link position-relative"
            data-bs-toggle="popover"
            data-bs-placement="bottom"
            :data-bs-content="'Nuova versione v.' + usePage().props.app.version"
        >
            <svg class="text-sky-500 size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" />
            </svg>
        </button>
    </div>
</template>

<style scoped>

</style>
