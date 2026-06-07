<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
});

const count = ref(0);
</script>

<template>
    <Head title="Welcome" />

    <main class="flex min-h-screen flex-col items-center justify-center gap-8 bg-gray-50 px-6 text-gray-900">
        <div class="text-center">
            <p class="text-sm font-medium uppercase tracking-widest text-gray-400">
                {{ appName }} v2
            </p>
            <h1 class="mt-2 text-4xl font-semibold">
                Inertia 3 + Vue 3
            </h1>
            <p class="mt-3 text-gray-500">
                Scaffolding attivo. Stack accoppiato a Laravel 13.
            </p>
        </div>

        <div class="flex items-center gap-4">
            <button
                type="button"
                class="rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-gray-700"
                @click="count++"
            >
                Contatore: {{ count }}
            </button>
            <button
                v-if="count > 0"
                type="button"
                class="text-sm font-medium text-gray-500 underline-offset-4 hover:underline"
                @click="count = 0"
            >
                reset
            </button>
        </div>

        <p class="text-xs text-gray-400">
            Click incrementa senza ricaricare la pagina → reattività Vue + navigazione Inertia OK.
        </p>
    </main>
</template>
