<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="min-h-screen flex flex-col items-center pt-[80px] sm:bg-gray-100 dark:bg-gray-900"
    >
        <div>
            <Link :href="usePage().props.app.changelog_url">
                <ApplicationLogo class="w-20 h-20 fill-current text-gray-500 m-auto mb-8" />
                <!-- <div class="mt-2 mb-[20px] text-[.8em] font-bold text-sky-400 dark:text-white text-center">
                    v.{{ usePage().props.app.version }}
                </div> -->
            </Link>
        </div>

        <div
            class="w-full sm:max-w-md mt-6 px-6 py-4 sm:bg-white sm:dark:bg-gray-800 sm:shadow-md overflow-hidden sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
