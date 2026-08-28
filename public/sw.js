const CACHE_VERSION = 'finacourt-v2';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const PUBLIC_CACHE = `${CACHE_VERSION}-public`;
const OFFLINE_URL = '/offline.html';
const PUBLIC_MAX_AGE_MS = 5 * 60 * 1000;

const PRECACHE = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/icons/finacourt-logo-192.png',
    '/icons/finacourt-logo-512.png',
    '/icons/finacourt-logo-maskable-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => ![STATIC_CACHE, PUBLIC_CACHE].includes(key)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

function isStaticAsset(url) {
    return url.origin === self.location.origin
        && (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/manifest.webmanifest');
}

function isSensitiveOrVolatile(request, url) {
    if (request.method !== 'GET' || url.origin !== self.location.origin) return true;
    if (url.search) return true;

    return [
        '/owner', '/platform', '/player', '/booking', '/login', '/register', '/webhooks', '/venues',
    ].some((prefix) => url.pathname === prefix || url.pathname.startsWith(`${prefix}/`));
}

function isSafePublicPage(request, url) {
    if (request.mode !== 'navigate' || isSensitiveOrVolatile(request, url)) return false;

    return url.pathname === '/' || url.pathname === '/courts' || url.pathname === '/deals'
        || /^\/courts\/[a-z0-9-]+$/.test(url.pathname)
        || /^\/[a-z0-9-]+\/[a-z0-9-]+$/.test(url.pathname);
}

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    const response = await fetch(request);
    if (response.ok) {
        const cache = await caches.open(STATIC_CACHE);
        await cache.put(request, response.clone());
    }

    return response;
}

async function stamped(response) {
    const headers = new Headers(response.headers);
    headers.set('x-sw-cached-at', Date.now().toString());

    return new Response(await response.blob(), {
        status: response.status,
        statusText: response.statusText,
        headers,
    });
}

async function safePublicPage(request) {
    const cache = await caches.open(PUBLIC_CACHE);
    const cached = await cache.match(request);

    try {
        const response = await fetch(request);
        if (response.ok && response.headers.get('x-pwa-cache') === 'public-short') {
            await cache.put(request, await stamped(response.clone()));
        }

        return response;
    } catch (error) {
        if (cached) {
            const cachedAt = Number(cached.headers.get('x-sw-cached-at') || 0);
            if (Date.now() - cachedAt <= PUBLIC_MAX_AGE_MS) return cached;
        }

        return caches.match(OFFLINE_URL);
    }
}

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (isStaticAsset(url)) {
        event.respondWith(cacheFirst(event.request));
        return;
    }

    if (isSafePublicPage(event.request, url)) {
        event.respondWith(safePublicPage(event.request));
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
    }
});

self.addEventListener('push', (event) => {
    if (!event.data) return;
    const payload = event.data.json();
    event.waitUntil(self.registration.showNotification(payload.title || 'FinACourt', {
        body: payload.message,
        icon: '/icons/finacourt-logo-192.png',
        badge: '/icons/finacourt-logo-192.png',
        data: { url: payload.url || '/player/bookings' },
        tag: payload.tag,
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const destination = new URL(event.notification.data?.url || '/player/bookings', self.location.origin).href;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
        const client = clients.find((item) => item.url === destination);
        return client ? client.focus() : self.clients.openWindow(destination);
    }));
});
