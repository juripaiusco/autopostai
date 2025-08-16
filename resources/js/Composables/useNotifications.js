import { onMounted, onUnmounted, ref } from 'vue'
import { usePage } from "@inertiajs/vue3"
import axios from 'axios'

const app_url = import.meta.env.VITE_APP_URL
const notify_read_web = ref(1)
const notify_clicked = ref(false)
const notifications = ref([])
let initialized = false
let evtSource = null

export const useNotifications = () => {
    const VAPID_PUBLIC_KEY = document.querySelector('meta[name="vapid-public-key"]').content
    const notificationsEnabled = ref(false)
    let intervalId = null
    let token = usePage().props.auth.token
    notifications.value = usePage().props.notifications

    const checkNotification = async () => {
        try {
            await axios.get(`${app_url}/api/notify-web-check`, {
                headers: { Authorization: `Bearer ${token}` }
            }).then(response => {
                notify_read_web.value = response.data.notify
                notify_clicked.value = false
            })
        } catch (error) {
            console.error("Errore durante il controllo delle notifiche:", error)
        }
    }

    const handleClick = async () => {
        notify_clicked.value = true
        await axios.get(`${app_url}/api/notify-read-web`, {
            headers: { Authorization: `Bearer ${token}` }
        }).then(response => {
            notifications.value = response.data.notifications
        })
    }

    async function subscribeUser() {
        if (!token) {
            alert('Devi essere loggato per abilitare le notifiche. Esci e rientra nel nell\'applicazione.')
            return
        }

        const permission = await Notification.requestPermission()
        if (permission !== 'granted') return

        let registration = await navigator.serviceWorker.getRegistration()
        if (!registration) {
            registration = await navigator.serviceWorker.register(`${app_url}/service-worker.js`)
        }

        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
        })

        let content_encoding = subscription.endpoint.includes('fcm.googleapis.com')
            ? 'aesgcm'
            : 'aes128gcm'

        const subscriptionData = {
            endpoint: subscription.endpoint,
            keys: {
                p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh')))),
                auth: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth'))))
            },
            content_encoding
        }

        await axios.post(`${app_url}/api/push-subscribe`, subscriptionData, {
            headers: { Authorization: `Bearer ${token}` }
        })

        notificationsEnabled.value = true
    }

    async function unsubscribeUser() {
        const registration = await navigator.serviceWorker.getRegistration()
        if (!registration) return

        const subscription = await registration.pushManager.getSubscription()
        if (!subscription) return

        const endpoint = subscription.endpoint
        const successful = await subscription.unsubscribe()

        if (successful) {
            await axios.post(`${app_url}/api/push-destroy`, { endpoint }, {
                headers: { Authorization: `Bearer ${token}` }
            })

            notificationsEnabled.value = false
        }
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4)
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
        const rawData = window.atob(base64)
        return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)))
    }

    onMounted(async () => {
        // Stato iniziale push
        const registration = await navigator.serviceWorker.getRegistration()
        notificationsEnabled.value = registration && await registration.pushManager.getSubscription() !== null

        // SSE solo la prima volta
        if (!initialized) {

            initialized = true
            await checkNotification()
            intervalId = setInterval(checkNotification, 3000)

            /*evtSource = new EventSource(app_url + '/sse/notification')

            evtSource.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data)
                    notify_read_web.value = data.active
                    notify_clicked.value = false
                } catch (e) {
                    console.error("Errore parsing SSE:", e)
                }
            }*/

            /*evtSource.onerror = (err) => {
                console.error("Errore SSE:", err)
            }*/
        }
    })

    onUnmounted(() => {
        if (intervalId) {
            clearInterval(intervalId)
            intervalId = null
            initialized = false
        }
        // Chiudi SSE quando non serve più
        /*if (evtSource) {
            evtSource.close()
            evtSource = null
            initialized = false
        }*/
    })

    return {
        notifications,
        notificationsEnabled,
        notify_read_web,
        notify_clicked,
        handleClick,
        subscribeUser,
        unsubscribeUser
    }
}
