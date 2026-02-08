self.addEventListener('push', function(event) {
    let data = {title: 'Aufgaben', body: 'Du hast eine neue Nachricht'};

    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body || '',
        icon: 'MetroUI-Other-Task-icon.png',
        badge: 'MetroUI-Other-Task-icon.png',
        data: {url: data.url || '/'},
        vibrate: [200, 100, 200]
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Aufgaben', options)
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    const url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if ('focus' in client) {
                    return client.focus();
                }
            }
            return clients.openWindow(url);
        })
    );
});
