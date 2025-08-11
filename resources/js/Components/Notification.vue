<script setup>

import {onMounted, ref} from 'vue'
import {usePage} from "@inertiajs/vue3";
import axios from 'axios'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'

const notifications = [
    /*{
        id: 1,
        title: 'Nuova versione disponibile',
        description: 'Scopri le nuove funzionalità della release ' + usePage().props.app.version,
        time: '2 min fa',
        url: '/versioni/ultima'
    },
    {
        id: 2,
        title: 'Aggiornamento completato',
        description: 'Il tuo sistema è stato aggiornato con successo.',
        time: '10 min fa',
        url: '/aggiornamenti'
    },
    {
        id: 3,
        title: 'Messaggio privato',
        description: 'Hai ricevuto un nuovo messaggio da Marco.',
        time: '1 ora fa',
        url: '/messaggi/456'
    },*/
]

const app_url = import.meta.env.VITE_APP_URL;
const VAPID_PUBLIC_KEY = import.meta.env.VITE_VAPID_PUBLIC_KEY;

// Stato per sapere se la campanella è stata cliccata
const clicked = ref(false)
const notificationsEnabled = ref(false)
const token = usePage().props.auth.token_notification

const handleClick = () => {
    clicked.value = true
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

    // Salva sul backend
    await axios.post(app_url + '/api/push-subscribe', subscriptionData, {
        headers: {
            Authorization: `Bearer ${token}`
        }
    })

    notificationsEnabled.value = true
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
    <Popover v-if="!usePage().props.auth.user.parent_id"
             class="relative">
        <!-- Bottone campanella -->
        <PopoverButton class="focus:outline-none" @click="handleClick">
            <div
                class="mt-2"
                :class="[
                    clicked ? 'text-gray-300 dark:text-gray-600' : 'text-sky-500 animate-ring'
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
                        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                    </button>

                </div>

                <!-- Lista notifiche -->
                <ul class="divide-y divide-gray-200 max-h-64 overflow-auto">
                    <li v-for="n in notifications" :key="n.id">
                        <a
                            :href="n.url"
                            class="block px-4 py-3 hover:bg-gray-50 transition"
                        >
                            <p class="font-medium text-sm text-gray-800">{{ n.title }}</p>
                            <p class="text-gray-600 text-xs mb-1">{{ n.description }}</p>
                            <p class="text-gray-400 text-xs">{{ n.time }}</p>
                        </a>
                    </li>
                </ul>

                <!-- Pulsante "Vedi tutte" -->
                <div class="px-4 py-2 border-t">
                    <a
                        href="/notifiche"
                        class="block text-sm text-center text-sky-600 hover:underline"
                    >
                        Vedi tutte
                    </a>
                </div>
            </PopoverPanel>
        </transition>
    </Popover>
</template>

<style scoped>

</style>
