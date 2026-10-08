/**
 * Service Worker — PWA Demo
 * Provides offline support & caching for installable experience
 */

const CACHE_NAME = 'Sharan';
const ASSETS_TO_CACHE = [
    '/',
    '/index.php',
    '/manifest.json',
    '/icon-48.png',
    '/icon-72.png',
    '/icon-96.png',
    '/icon-144.png',
    '/icon-152.png',
    '/icon-167.png',
    '/icon-180.png',
    '/icon-192.png',
    '/icon-512.png'
];

// ==================== INSTALL ====================
self.addEventListener('install', (event) => {
    console.log('[SW] Installing...');
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[SW] Caching assets');
            return cache.addAll(ASSETS_TO_CACHE);
        }).then(() => {
            console.log('[SW] Skip waiting');
            return self.skipWaiting();
        })
    );
});

// ==================== ACTIVATE ====================
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        console.log('[SW] Deleting old cache:', cache);
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => {
            console.log('[SW] Claiming clients');
            return self.clients.claim();
        })
    );
});

// ==================== FETCH (Network-first with cache fallback) ====================
self.addEventListener('fetch', (event) => {
    // Only handle GET requests
    if (event.request.method !== 'GET') return;

    // Skip chrome-extension and other non-http(s) requests
    const url = new URL(event.request.url);
    if (!url.protocol.startsWith('http')) return;

    event.respondWith(
        fetch(event.request)
            .then((networkResponse) => {
                // Cache a clone of the successful response
                if (networkResponse && networkResponse.status === 200) {
                    const clonedResponse = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, clonedResponse);
                    });
                }
                return networkResponse;
            })
            .catch(() => {
                // Network failed — serve from cache
                return caches.match(event.request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // If the request was for a page, serve the index (App Shell)
                    if (event.request.mode === 'navigate') {
                        return caches.match('/index.php');
                    }
                    // Return a simple offline response
                    return new Response(
                        '<html><body style="background:#0f0f1a;color:#eaeaea;display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;text-align:center;"><div><h1>📡 Offline</h1><p>You\'re not connected to the internet.</p><p style="color:#a0a0b8;">The app will reload when you\'re back online.</p></div></body></html>',
                        { status: 503, headers: { 'Content-Type': 'text/html' } }
                    );
                });
            })
    );
});

// ==================== PUSH NOTIFICATIONS (Optional) ====================
self.addEventListener('push', (event) => {
    const options = {
        body: event.data ? event.data.text() : 'New update from PWA Demo!',
        icon: '/icon-192.png',
        badge: '/icon-72.png',
        vibrate: [200, 100, 200],
        tag: 'pwa-notification',
        requireInteraction: false
    };
    event.waitUntil(
        self.registration.showNotification('PWA Demo', options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            if (clientList.length > 0) {
                return clientList[0].focus();
            }
            return clients.openWindow('/');
        })
    );
});
