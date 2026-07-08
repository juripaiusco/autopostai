import { ref, onMounted } from 'vue';
import { csrfHeader } from '@/lib/csrf';

// Converte la VAPID public key (base64url) nel formato richiesto da
// pushManager.subscribe(). Standard per l'integrazione Web Push.
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

export function usePushSubscription(vapidPublicKey) {
    const supported = 'serviceWorker' in navigator && 'PushManager' in window;
    const subscribed = ref(false);
    const loading = ref(false);
    const error = ref(null);

    async function refreshState() {
        if (!supported) return;
        const registration = await navigator.serviceWorker.getRegistration('/service-worker.js');
        const sub = await registration?.pushManager.getSubscription();
        subscribed.value = !!sub;
    }

    async function subscribe() {
        if (!supported) {
            error.value = 'Il browser non supporta le notifiche push.';
            return;
        }
        loading.value = true;
        error.value = null;
        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                error.value = 'Permesso negato per le notifiche.';
                return;
            }

            const registration = await navigator.serviceWorker.register('/service-worker.js');
            const sub = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
            });

            const json = sub.toJSON();
            await fetch(route('push.subscribe'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeader() },
                body: JSON.stringify({ endpoint: json.endpoint, keys: json.keys }),
            });

            subscribed.value = true;
        } catch (e) {
            error.value = 'Attivazione non riuscita. Riprova.';
        } finally {
            loading.value = false;
        }
    }

    async function unsubscribe() {
        loading.value = true;
        try {
            const registration = await navigator.serviceWorker.getRegistration('/service-worker.js');
            const sub = await registration?.pushManager.getSubscription();
            if (sub) {
                await fetch(route('push.unsubscribe'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeader() },
                    body: JSON.stringify({ endpoint: sub.endpoint }),
                });
                await sub.unsubscribe();
            }
            subscribed.value = false;
        } finally {
            loading.value = false;
        }
    }

    onMounted(refreshState);

    return { supported, subscribed, loading, error, subscribe, unsubscribe };
}
