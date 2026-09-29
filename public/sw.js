// Budgeteer service worker: shows phone notifications and opens the right screen when one is tapped.
// It caches nothing, so the app always shows live data.

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let message = {};
    try {
        message = event.data ? event.data.json() : {};
    } catch (e) {
        message = { body: event.data ? event.data.text() : '' };
    }
    event.waitUntil(self.registration.showNotification(message.title || 'Budgeteer', {
        body: message.body || '',
        tag: message.tag || undefined,
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        data: { url: message.url || '/' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url || '/', self.location.origin);
    if (url.origin !== self.location.origin) {
        return;
    }
    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of windows) {
            if ('navigate' in client) {
                await client.navigate(url.href);
                return client.focus();
            }
        }
        return self.clients.openWindow(url.href);
    })());
});
