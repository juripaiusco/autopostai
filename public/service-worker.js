/*self.addEventListener('push', function(event) {
    console.log('Push event received:', event);
    event.waitUntil(
        (async () => {
            let data = {};

            try {
                if (event.data && event.data.text()) {
                    data = { endpoint: event.data.text() };
                }
            } catch (e) {
                console.error('Errore payload push:', e);
            }

            const subscription = await self.registration.pushManager.getSubscription();
            if (!subscription) {
                data = { title: 'Notifica fallback', body: 'Nessuna subscription trovata' };
            } else if (!data.title) {
                try {
                    const response = await fetch(`http://localhost/public/api/push-data?endpoint=${encodeURIComponent(subscription.endpoint)}`);
                    if (response.ok) {
                        data = await response.json();
                    } else {
                        data = { title: 'Notifica fallback', body: 'Impossibile recuperare dati' };
                    }
                } catch (e) {
                    data = { title: 'Notifica fallback', body: 'Errore di rete' };
                }
            }

            console.log('data:', data);
            console.log('url', data.url);

            const title = data.title || 'Notifica';
            const options = {
                body: data.body || '',
                icon: data.icon || '/faper3-logo.png',
                data: { url: data.url ? data.url : '/' },
                actions: data.actions || [],
            };

            await self.registration.showNotification(title, options);
        })()
    );
});*/

self.addEventListener('push', event => {
    event.waitUntil(
        (async () => {

            if (event.data) {

                const payload = event.data.json();

                const title = payload.title || 'Notifica';
                const options = {
                    body: payload.body || '',
                    icon: payload.icon || '/faper3-logo.png',
                    data: { url: payload.url ? payload.url : '/' },
                    actions: payload.actions || [],
                };

                await self.registration.showNotification(title, options);

            } else {
                console.log('No payload received');
            }
        }
        )()
    );
});

/*self.addEventListener('push', function(event) {
    event.waitUntil(
        (async () => {
            let data = {};

            try {
                if (event.data && event.data.text()) {
                    // Se c’è un payload semplice, lo usi come identificativo
                    data = { endpoint: event.data.text() };
                }
            } catch (e) {
                console.error('Errore payload push:', e);
            }

            // Se non hai dati completi, fai fetch a Laravel
            if (!data.title) {
                try {
                    const subscription = await self.registration.pushManager.getSubscription();

                    if (!subscription) {
                        data = { title: 'Notifica fallback', body: 'Nessuna subscription trovata' };

                    } else {

                        const response = await fetch(`http://localhost/public/api/push-data?endpoint=${encodeURIComponent(subscription.endpoint)}`);

                        if (response.ok) {
                            data = await response.json();
                        } else {
                            data = { title: 'Notifica fallback', body: 'Impossibile recuperare dati' };
                        }

                        console.log('Subscription endpoint:', subscription ? subscription.endpoint : 'null');
                        console.log('Response:', response);
                        console.log('Data ricevuti:', data);
                        console.log('url:', data.data.url);
                    }
                } catch (e) {
                    data = { title: 'Notifica fallback', body: 'Errore di rete' };
                }
            }

            const title = data.title || 'Notifica';
            const options = {
                title: data.title || '',
                icon: data.icon || null,
                body: data.body || null,
                data: data.data || null,
                // action: data.action || null,
            };

            await self.registration.showNotification(title, options);
        })()
    );
});*/

/*self.addEventListener('push', function (event) {
    console.log(event.data)
    let data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            console.error('Errore parsing payload:', e);
        }
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'No title', {
            body: data.body || '',
            icon: data.icon || '',
            data: data.data || {}
        })
    );
});*/

// Quello che usavo
/*self.addEventListener('push', function(event) {
    let data = {};

    try {
        if (event.data) {
            data = event.data.json();
            console.log('Payload JSON:', data);
        } else {
            console.warn('Push event senza dati', event);
        }
    } catch (e) {
        console.error('Errore parsing JSON:', e);
        if (event.data) {
            // Usa testo semplice come fallback
            data = { title: 'Notifica fallback', body: event.data.text() };
        } else {
            data = { title: 'Notifica fallback', body: 'Nessun contenuto' };
        }
    }

    const title = data.title || 'Notifica';
    const options = {
        body: data.body || 'Notifica senza contenuto',
        icon: data.icon || '/public/faper3-logo.png',
        data: data.url || '/'
    };

    event.waitUntil(self.registration.showNotification(title, options));
});*/

/*self.addEventListener('push', function(event) {
    if (event.data) {
        const data = event.data.json();
        event.waitUntil(
            self.registration.showNotification(data.title, {
                body: data.body,
                icon: data.icon,
                data: { url: data.data.url }
            })
        );
    }
});*/



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

    /*if (event.action === 'open') {
        event.waitUntil(clients.openWindow(event.notification.url));
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
            // Se la pagina è già aperta, portala in primo piano
            for (const client of clientList) {
                if (client.url === event.notification.url && 'focus' in client) {
                    return client.focus();
                }
            }
            // Altrimenti apri una nuova scheda
            if (clients.openWindow) {
                return clients.openWindow(event.notification.url);
            }
        })
    );*/
});

/*self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    event.waitUntil(
        clients.openWindow(event.notification.url)
    );
});*/
