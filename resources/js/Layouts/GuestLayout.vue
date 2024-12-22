<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="min-h-screen flex flex-col items-center pt-[50px] bg-gray-100 dark:bg-gray-900"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="w-20 h-20 fill-current text-gray-500 m-auto" />
                <br>
                <h1 class="text-3xl mb-[20px] dark:text-white">
                    AutoPost<span class="font-bold">AI</span> <small class="text-sm">v.1</small>
                </h1>
            </Link>
        </div>

        <div
            class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
