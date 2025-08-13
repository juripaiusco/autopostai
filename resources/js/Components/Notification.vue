<script setup>

import {onMounted, ref} from 'vue'
import {usePage} from "@inertiajs/vue3";
import axios from 'axios'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import {__date} from "@/ComponentsExt/Date.js";

const notifications = usePage().props.notifications;

const app_url = import.meta.env.VITE_APP_URL;
const VAPID_PUBLIC_KEY = import.meta.env.VITE_VAPID_PUBLIC_KEY;

// Stato per sapere se la campanella è stata cliccata
const clicked = ref(false)
const notificationsEnabled = ref(false)
const token = usePage().props.auth.token_notification

const handleClick = () => {
    clicked.value = true

    axios.get(app_url + '/api/notify-read-web', {
        headers: {
            Authorization: `Bearer ${token}`
        }
    })
}

async function subscribeUser() {
    const permission = await Notification.requestPermission()
    if (permission !== 'granted') return

    // Registra il service worker
    const registration = await navigator.serviceWorker.register(app_url + '/service-worker.js')

    // Genera la sottoscrizione
    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
    })

    // Determina il content_encoding basandoti sull'endpoint
    let content_encoding = 'aes128gcm' // Default moderno

    // FCM/Chrome potrebbe usare aesgcm per alcuni endpoint
    if (subscription.endpoint.includes('fcm.googleapis.com')) {
        content_encoding = 'aesgcm'
    }

    // Prepara i dati nel formato che si aspetta il controller
    const subscriptionData = {
        endpoint: subscription.endpoint,
        keys: {
            p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh')))),
            auth: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth'))))
        },
        content_encoding: content_encoding
    }

    if (notificationsEnabled.value) {

        const registration = await navigator.serviceWorker.getRegistration()
        const subscription = await registration.pushManager.getSubscription()
        const subscriptionData = { endpoint: subscription.endpoint };

        const successful = await subscription.unsubscribe();

        if (successful) {
            // Elimina sul backend
            await axios.post(app_url + '/api/push-destroy', subscriptionData, {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })

            notificationsEnabled.value = false;
        } else {
            console.error('Impossibile eliminare la subscription.');
        }

        notificationsEnabled.value = false

    } else {

        // Salva sul backend
        await axios.post(app_url + '/api/push-subscribe', subscriptionData, {
            headers: {
                Authorization: `Bearer ${token}`
            }
        })

        notificationsEnabled.value = true
    }
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4)
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
    const rawData = window.atob(base64)
    return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)))
}

onMounted(async () => {
    // Controlla se l’utente ha già una subscription
    const registration = await navigator.serviceWorker.getRegistration()
    if (!registration) {
        notificationsEnabled.value = false
        return
    }
    const subscription = await registration.pushManager.getSubscription()
    notificationsEnabled.value = subscription !== null
})

/*// Mostra le notifiche attive - utile per debug
navigator.serviceWorker.ready.then(registration => {
    registration.pushManager.getSubscription().then(subscription => {
        console.log(subscription ? subscription.toJSON() : 'Nessuna subscription attiva');
    });
});

navigator.serviceWorker.ready.then(registration => {
    registration.showNotification('Test dal client', {
        body: 'Funziona!',
        icon: app_url + '/faper3-logo.png'
    });
});*/

</script>

<template>
    <!-- Notification Bell new version -->
    <Popover class="relative">
        <!-- Bottone campanella -->
        <PopoverButton class="focus:outline-none" @click="handleClick">
            <div
                class="mt-2"
                :class="[
                    usePage().props.auth.user.notify_read_web === null && clicked === false ?
                        'text-sky-500 animate-ring' : 'text-gray-300 dark:text-gray-600',
                    /*clicked ? 'text-gray-300 dark:text-gray-600' : 'text-sky-500 animate-ring'*/
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
                class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg ring-1 ring-black/5 z-50"
            >
                <div class="flex w-full px-4 py-2 bg-gray-50 border-b rounded-t-lg items-center justify-between">

                    <!-- Titolo -->
                    <h3 class="text-sm font-semibold text-gray-700">
                        Novità di FaPer3
                    </h3>

                    <!-- Pulsante -->
                    <button @click="subscribeUser"
                            class="btn btn-sm"
                            :class="{
                              'btn-primary': notificationsEnabled,
                              'btn-secondary': !notificationsEnabled,
                            }">
                        <svg v-if="notificationsEnabled"
                             class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" />
                        </svg>

                        <svg v-if="!notificationsEnabled"
                             class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.143 17.082a24.248 24.248 0 0 0 3.844.148m-3.844-.148a23.856 23.856 0 0 1-5.455-1.31 8.964 8.964 0 0 0 2.3-5.542m3.155 6.852a3 3 0 0 0 5.667 1.97m1.965-2.277L21 21m-4.225-4.225a23.81 23.81 0 0 0 3.536-1.003A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6.53 6.53m10.245 10.245L6.53 6.53M3 3l3.53 3.53" />
                        </svg>
                    </button>

                </div>

                <!-- Lista notifiche -->
                <ul class="divide-y divide-gray-200 max-h-64 overflow-auto">
                    <li v-for="n in notifications" :key="n.id">
                        <a
                            :href="n.url ? n.url : '#'"
                            :target="n.url ? '_blank' : ''"
                            class="block px-4 py-3 hover:bg-gray-50 transition"
                        >
                            <p class="font-medium text-sm text-gray-800">{{ n.title }}</p>
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
