// Service Worker para SpacePark Mobile - Versión 2.0
const CACHE_NAME = 'spacepark-mobile-v2.1';
const ASSETS = [
    '/mobile/',
    '/mobile/index.html',
    '/mobile/report.html',
    '/mobile/css/mobile.css',
    '/mobile/js/auth.js?v=2.0',
    '/mobile/js/offline.js?v=2.0',
    '/mobile/js/sync.js?v=2.0',
    '/mobile/js/app.js?v=2.0',
    '/mobile/img/astronaut.png',
    '/mobile/manifest.json'
];

// Instalación
self.addEventListener('install', event => {
    console.log('[SW] Instalando versión:', CACHE_NAME);
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

// Activación
self.addEventListener('activate', event => {
    console.log('[SW] Activado. Purgando versiones viejas...');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Estrategia de Fetch
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // No cachear llamadas a la API
    if (url.pathname.includes('/api/')) {
        event.respondWith(
            fetch(event.request).catch(() => {
                return new Response(
                    JSON.stringify({ error: 'OFFLINE_MODE', success: false }),
                    { headers: { 'Content-Type': 'application/json' } }
                );
            })
        );
        return;
    }

    // Para el resto: Network First, Fallback to Cache
    event.respondWith(
        fetch(event.request)
            .then(res => {
                if (res.ok && event.request.method === 'GET') {
                    const resClone = res.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, resClone));
                }
                return res;
            })
            .catch(() => caches.match(event.request))
    );
});
