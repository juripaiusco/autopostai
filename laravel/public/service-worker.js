// Pagina offline: unica cosa in cache. Niente cache di dati o asset dell'app (no dati vecchi).
// Cambiando offline.html o la sua icona, alzare la versione.
const OFFLINE_CACHE = 'faper3-offline-v1';
const OFFLINE_URL = '/offline.html';
const OFFLINE_ASSETS = [OFFLINE_URL, '/pwa/icons/manifest-icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(OFFLINE_CACHE)
            .then((cache) => cache.addAll(OFFLINE_ASSETS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== OFFLINE_CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

// Sempre rete prima. Se fallisce: navigazioni (apertura/ricarica pagina) → pagina offline,
// file della pagina offline → copia in cache. Tutto il resto (Inertia/XHR, asset) non viene toccato.
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const isNavigation = request.mode === 'navigate';
    const isOfflineAsset = OFFLINE_ASSETS.includes(new URL(request.url).pathname);
    if (!isNavigation && !isOfflineAsset) return;

    const fallback = isNavigation ? OFFLINE_URL : request;
    event.respondWith(
        fetch(request).catch(() => caches.match(fallback, { cacheName: OFFLINE_CACHE })),
    );
});

self.addEventListener('push', (event) => {
    if (!event.data) return;

    const payload = event.data.json();
    const title = payload.title || 'FaPer3';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: payload.body || '',
            icon: '/pwa/icons/manifest-icon-192.png',
            data: payload.data || {},
            actions: payload.actions || [],
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data?.url || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if (client.url === url && 'focus' in client) return client.focus();
            }
            if (self.clients.openWindow) return self.clients.openWindow(url);
        }),
    );
});
