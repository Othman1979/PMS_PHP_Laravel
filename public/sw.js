self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('install', event => {
    event.waitUntil(caches.open(STATIC_CACHE).then(cache => cache.add(OFFLINE_URL)).catch(() => {}));
    self.skipWaiting();
});

self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));

self.addEventListener('push', event => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch { data = { body: event.data && event.data.text() }; }
    const title = data.title || 'CMMS';
    event.waitUntil(self.registration.showNotification(title, {
        body: data.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/badge-96.png',
        tag: data.tag || undefined,
        renotify: !!data.tag,
        dir: 'auto',
        vibrate: [200, 100, 200],
        data: { url: data.url || '/' }
    }));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const url = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin).href;
    event.waitUntil((async () => {
        const all = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const c of all) {
            if (c.url === url && 'focus' in c) return c.focus();
        }
        if (all.length && 'navigate' in all[0]) {
            await all[0].navigate(url);
            return all[0].focus();
        }
        return self.clients.openWindow(url);
    })());
});

// Static assets (Bootstrap, fonts, scripts, icons) are served cache-first so pages and
// dialogs open instantly on slow connections. CSS/JS URLs carry a ?v= version, so a changed
// file gets a new URL and is never stale.
const STATIC_CACHE = 'cmms-static-v2';
const OFFLINE_URL = '/offline.html';
const STATIC_PREFIXES = ['/lib/', '/css/', '/js/', '/fonts/', '/icons/'];

self.addEventListener('install', event => {
    event.waitUntil(caches.open(STATIC_CACHE).then(cache => cache.add(OFFLINE_URL)).catch(() => {}));
    self.skipWaiting();
});

self.addEventListener('activate', event => event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== STATIC_CACHE).map(k => caches.delete(k))))
));

self.addEventListener('fetch', event => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;
    if (req.mode === 'navigate') {
        // Pages are always network-first; when the network is down show the offline page.
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL).then(hit => hit || Response.error())));
        return;
    }
    if (!STATIC_PREFIXES.some(p => url.pathname.startsWith(p))) return;

    event.respondWith(caches.open(STATIC_CACHE).then(async cache => {
        const hit = await cache.match(req);
        if (hit) return hit;
        const res = await fetch(req);
        if (res.ok) cache.put(req, res.clone());
        return res;
    }));
});
