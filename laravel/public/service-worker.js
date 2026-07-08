self.addEventListener('push', (event) => {
    if (!event.data) return;

    const payload = event.data.json();
    const title = payload.title || 'FaPer3';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: payload.body || '',
            icon: '/images/faper3-logo.png',
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
