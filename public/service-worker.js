self.addEventListener('push', event => {
    event.waitUntil(
        (async () => {

            if (event.data) {

                const payload = event.data.json();

                const title = payload.title || null;
                const options = {
                    body: payload.body || null,
                    icon: payload.icon || '/faper3-logo.png',
                    data: { url: payload.data.url ? payload.data.url : '/' },
                    actions: payload.actions || [],
                };

                if (title) {
                    await self.registration.showNotification(title, options);
                }

            } else {
                console.log('No payload received');
            }
        }
        )()
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    const urlToOpen = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(windowClients => {
                // Se la finestra è già aperta, la metti in focus
                for (let client of windowClients) {
                    if (client.url.includes(urlToOpen) && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Altrimenti apri una nuova finestra
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});
