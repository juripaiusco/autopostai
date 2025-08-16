<script setup>

import { Popover, PopoverButton, PopoverPanel, PopoverOverlay } from '@headlessui/vue'
import {__date} from "@/ComponentsExt/Date.js";

import { useNotifications } from "@/Composables/useNotifications.js";
const {
    notifications,
    notificationsEnabled,
    notify_read_web,
    notify_clicked,
    handleClick,
    subscribeUser,
    unsubscribeUser
} = useNotifications();

</script>

<template>
    <!-- Notification Bell new version -->
    <Popover class="relative z-30">
        <!-- Bottone campanella -->
        <PopoverButton class="focus:outline-none" @click="handleClick">
            <div
                class="mt-2"
                :class="[
                    notify_read_web === 0 && notify_clicked === false
                    ? 'text-sky-500 animate-ring'
                    : 'text-gray-300 dark:text-gray-600',
                ]"
            >
                <svg
                    class="size-6"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
                    />
                </svg>
            </div>
        </PopoverButton>

        <!-- Overlay -->
        <transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <PopoverOverlay class="fixed inset-0 bg-black/40 backdrop-blur-sm z-30" />
        </transition>

        <!-- Pannello notifiche -->
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-1 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-1 opacity-0"
        >
            <PopoverPanel
                class="fixed inset-12 mx-auto max-h-96 sm:w-80 bg-white rounded-lg shadow-lg ring-1 ring-black/5 z-30"
            >
                <div class="flex w-full px-4 py-2 bg-gray-50 border-b rounded-t-lg items-center justify-between">

                    <!-- Titolo -->
                    <h3 class="text-sm font-semibold text-gray-700">
                        Novità di FaPer3
                    </h3>

                    <!-- Pulsante -->
                    <button v-if="!notificationsEnabled"
                            @click="subscribeUser"
                            type="button"
                            class="btn btn-sm btn-secondary">

                        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.143 17.082a24.248 24.248 0 0 0 3.844.148m-3.844-.148a23.856 23.856 0 0 1-5.455-1.31 8.964 8.964 0 0 0 2.3-5.542m3.155 6.852a3 3 0 0 0 5.667 1.97m1.965-2.277L21 21m-4.225-4.225a23.81 23.81 0 0 0 3.536-1.003A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6.53 6.53m10.245 10.245L6.53 6.53M3 3l3.53 3.53" />
                        </svg>

                    </button>

                    <button v-if="notificationsEnabled"
                            @click="unsubscribeUser"
                            type="button"
                            class="btn btn-sm btn-primary">

                        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" />
                        </svg>

                    </button>

                </div>

                <!-- Lista notifiche -->
                <ul class="divide-y divide-gray-200 max-h-96 overflow-auto">
                    <li v-for="n in notifications" :key="n.id">
                        <a
                            :href="n.url ? n.url : '#'"
                            :target="n.url ? '_blank' : ''"
                            class="block px-4 py-3 hover:bg-gray-50 transition"
                        >
                            <div class="flex items-center font-medium text-sm text-gray-800">

                                <span v-if="n.url"
                                      class="mr-1 text-gray-400">

                                    <svg class="size-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                    </svg>

                                </span>

                                <span>
                                    {{ n.title }}
                                </span>
                            </div>
                            <p class="text-gray-600 text-xs mb-1">{{ n.body }}</p>
                            <p class="text-gray-400 text-xs">
                                {{ __date(n.sent_at, 'day') }} - {{ __date(n.sent_at, 'hour') }}
                            </p>
                        </a>
                    </li>
                </ul>

                <!-- Pulsante "Vedi tutte" -->
                <!-- <div class="px-4 py-2 border-t">
                    <a
                        href="/notifiche"
                        class="block text-sm text-center text-sky-600 hover:underline"
                    >
                        Vedi tutte
                    </a>
                </div> -->
            </PopoverPanel>
        </transition>
    </Popover>
</template>

<style scoped>

</style>
