/* Service worker de JAM Sport: primero la red y, si no hay conexión, la última
   copia guardada. La API nunca se guarda en caché (precios y existencias
   siempre vienen del servidor). */
const CACHE = 'jam-sport-v1';
const SHELL = ['./', 'index.html', 'index.css', 'config.js', 'img/iconos.svg', 'img/favicon.svg'];

self.addEventListener('install', function (event) {
    event.waitUntil(caches.open(CACHE).then(function (cache) { return cache.addAll(SHELL); }));
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(caches.keys().then(function (keys) {
        return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (event) {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.indexOf('/api/') >= 0) {
        return;
    }
    event.respondWith(fetch(request).then(function (response) {
        if (response.ok) {
            const copy = response.clone();
            caches.open(CACHE).then(function (cache) { cache.put(request, copy); });
        }
        return response;
    }).catch(function () {
        return caches.match(request).then(function (cached) {
            return cached || (request.mode === 'navigate' ? caches.match('index.html') : Response.error());
        });
    }));
});
