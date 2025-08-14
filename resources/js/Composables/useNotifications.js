import {onMounted, ref} from 'vue'
import {usePage} from "@inertiajs/vue3";
import axios from 'axios'

let initializedUseNotification = false

export const useNotifications = () => {
    const notifications = usePage().props.notifications;
    const notify_read_web = usePage().props.auth.user.notify_read_web;
    const app_url = import.meta.env.VITE_APP_URL;
    const VAPID_PUBLIC_KEY = document.querySelector('meta[name="vapid-public-key"]').content;
    const token = usePage().props.auth.token_notification
    const notificationsEnabled = ref(false)

    if (!initializedUseNotification) {
        console.log('token:', token)
        initializedUseNotification = true
    }
    
// Stato per sapere se la campanella è stata cliccata
    const clicked = ref(false)

    const handleClick = async () => {
        clicked.value = true

        await axios.get(app_url + '/api/notify-read-web', {
            headers: {
                Authorization: `Bearer ${token}`
            }
        })
    }

    async function subscribeUser() {
        const reg = await navigator.serviceWorker.getRegistration();
        if (reg) {
            await reg.update(); // forza update
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') return;

        let registration = await navigator.serviceWorker.getRegistration();
        if (!registration) {
            registration = await navigator.serviceWorker.register(app_url + '/service-worker.js');
        }

        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
        });

        let content_encoding = subscription.endpoint.includes('fcm.googleapis.com')
            ? 'aesgcm'
            : 'aes128gcm';

        const subscriptionData = {
            endpoint: subscription.endpoint,
            keys: {
                p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh')))),
                auth: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth'))))
            },
            content_encoding
        };

        await axios.post(`${app_url}/api/push-subscribe`, subscriptionData, {
            headers: { Authorization: `Bearer ${token}` }
        });

        notificationsEnabled.value = true;
    }

    async function unsubscribeUser() {
        const reg = await navigator.serviceWorker.getRegistration();
        if (reg) {
            await reg.update(); // forza update
        }

        const registration = await navigator.serviceWorker.getRegistration();
        if (!registration) return;

        const subscription = await registration.pushManager.getSubscription();
        if (!subscription) return;

        const endpoint = subscription.endpoint;

        const successful = await subscription.unsubscribe();
        if (successful) {
            await axios.post(`${app_url}/api/push-destroy`, { endpoint }, {
                headers: { Authorization: `Bearer ${token}` }
            });

            const check = await registration.pushManager.getSubscription();
            if (check) {
                console.warn("Subscription non rimossa completamente dal browser.");
            }

            notificationsEnabled.value = false;
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

    return {
        notifications: notifications,
        notify_read_web: notify_read_web,
        notificationsEnabled: notificationsEnabled,
        clicked: clicked,
        handleClick,
        subscribeUser,
        unsubscribeUser,
    }
}
