/**
 * Sharan Foundation — Service Worker
 *
 * Strategies:
 *   • App shell + static assets        → Cache First (instant load)
 *   • HTML pages                       → Network First w/ offline fallback
 *   • Images                           → Cache First w/ network update
 *   • API submissions (POST)           → Network Only + Background Sync queue
 *   • Anything else                    → Network with cache fallback
 *
 * Bump CACHE_VERSION whenever you ship a new release so users get fresh files.
 */

const CACHE_VERSION = 'sharan-v1.0.0';
const STATIC_CACHE  = `${CACHE_VERSION}-static`;
const PAGES_CACHE   = `${CACHE_VERSION}-pages`;
const IMAGES_CACHE  = `${CACHE_VERSION}-images`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;

// Files cached immediately on install (the "app shell")
const PRECACHE_URLS = [
  '/',
  '/index.php',
  '/css/style.css',
  '/js/main.js',
  '/manifest.webmanifest',
  '/pwa/offline.html',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/icons/apple-touch-icon.png',
  '/images/logo.png'
];

// File extensions that match each cache type
const STATIC_EXTS = /\.(css|js|woff2?|ttf|eot|otf|svg|ico)$/i;
const IMAGE_EXTS  = /\.(png|jpe?g|gif|webp|avif|bmp)$/i;
const HTML_PHP    = /\.(html|php)$|\/$/i;

/* ==========================================================
   INSTALL — precache the app shell
   ========================================================== */
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => cache.addAll(PRECACHE_URLS).catch(err => {
        // Don't let one missing URL break the whole install
        console.warn('[SW] Some precache URLs failed:', err);
        return Promise.all(
          PRECACHE_URLS.map(url => cache.add(url).catch(() => null))
        );
      }))
      .then(() => self.skipWaiting())
  );
});

/* ==========================================================
   ACTIVATE — clean up old caches from previous versions
   ========================================================== */
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys.filter(k => !k.startsWith(CACHE_VERSION)).map(k => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

/* ==========================================================
   FETCH — main routing logic
   ========================================================== */
self.addEventListener('fetch', event => {
  const req = event.request;
  const url = new URL(req.url);

  // Skip non-GET (POST/PUT/DELETE go through normally — except donations, handled below)
  if (req.method !== 'GET') {
    // Background-sync donation form submissions when offline
    if (/\/api\/(submit_|payment\/)/i.test(url.pathname)) {
      event.respondWith(handlePostWithRetry(req));
    }
    return;
  }

  // Skip cross-origin requests (let the browser handle them)
  if (url.origin !== location.origin) return;

  // Skip admin pages — they should always be fresh
  if (url.pathname.includes('/admin/')) return;

  // Route to the right strategy
  if (HTML_PHP.test(url.pathname)) {
    event.respondWith(networkFirst(req, PAGES_CACHE));
  } else if (IMAGE_EXTS.test(url.pathname)) {
    event.respondWith(cacheFirst(req, IMAGES_CACHE));
  } else if (STATIC_EXTS.test(url.pathname)) {
    event.respondWith(cacheFirst(req, STATIC_CACHE));
  } else {
    event.respondWith(networkWithCacheFallback(req, RUNTIME_CACHE));
  }
});

/* ==========================================================
   STRATEGY: Cache First (fast, falls back to network)
   ========================================================== */
async function cacheFirst(req, cacheName) {
  const cached = await caches.match(req);
  if (cached) {
    // Refresh in background ("stale-while-revalidate")
    fetch(req).then(res => {
      if (res && res.ok) caches.open(cacheName).then(c => c.put(req, res));
    }).catch(() => {});
    return cached;
  }
  try {
    const res = await fetch(req);
    if (res && res.ok) {
      const cache = await caches.open(cacheName);
      cache.put(req, res.clone());
    }
    return res;
  } catch (err) {
    return makeErrorResponse('Network unavailable.');
  }
}

/* ==========================================================
   STRATEGY: Network First (always fresh, cache as fallback)
   ========================================================== */
async function networkFirst(req, cacheName) {
  try {
    const res = await fetch(req);
    if (res && res.ok) {
      const cache = await caches.open(cacheName);
      cache.put(req, res.clone());
    }
    return res;
  } catch (err) {
    const cached = await caches.match(req);
    if (cached) return cached;
    // Last resort: show the offline page
    const offline = await caches.match('/pwa/offline.html');
    return offline || makeErrorResponse('You are offline and this page is not cached yet.');
  }
}

/* ==========================================================
   STRATEGY: Network with cache fallback
   ========================================================== */
async function networkWithCacheFallback(req, cacheName) {
  try {
    const res = await fetch(req);
    if (res && res.ok) {
      const cache = await caches.open(cacheName);
      cache.put(req, res.clone());
    }
    return res;
  } catch (err) {
    const cached = await caches.match(req);
    return cached || makeErrorResponse('Not available offline.');
  }
}

/* ==========================================================
   POST queueing (Background Sync) — saves donations made offline
   ========================================================== */
async function handlePostWithRetry(req) {
  try {
    return await fetch(req.clone());
  } catch (err) {
    // Network failed — save to IndexedDB and replay on reconnect
    if ('sync' in self.registration) {
      try {
        const body = await req.clone().text();
        await queuePostRequest({
          url: req.url,
          method: req.method,
          headers: [...req.headers],
          body,
          timestamp: Date.now()
        });
        await self.registration.sync.register('replay-queued-posts');
      } catch (e) { console.warn('[SW] Queue failed:', e); }
    }
    return new Response(JSON.stringify({
      ok: false,
      offline: true,
      error: 'You are offline. Your submission has been saved and will be sent automatically when you reconnect.'
    }), { headers: { 'Content-Type': 'application/json' }, status: 503 });
  }
}

/* ==========================================================
   BACKGROUND SYNC — replay queued POSTs when back online
   ========================================================== */
self.addEventListener('sync', event => {
  if (event.tag === 'replay-queued-posts') {
    event.waitUntil(replayQueuedPosts());
  }
});

async function replayQueuedPosts() {
  const queue = await readQueue();
  for (const item of queue) {
    try {
      await fetch(item.url, {
        method: item.method,
        headers: new Headers(item.headers),
        body: item.body
      });
      await removeFromQueue(item.timestamp);
    } catch (err) {
      // Will retry on next sync event
    }
  }
}

// Minimal IndexedDB helpers for the offline POST queue
function openDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open('sharan-sw-queue', 1);
    req.onupgradeneeded = e => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains('posts')) {
        db.createObjectStore('posts', { keyPath: 'timestamp' });
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror   = () => reject(req.error);
  });
}
async function queuePostRequest(item) {
  const db = await openDB();
  return new Promise((res, rej) => {
    const tx = db.transaction('posts','readwrite');
    tx.objectStore('posts').put(item);
    tx.oncomplete = res; tx.onerror = rej;
  });
}
async function readQueue() {
  const db = await openDB();
  return new Promise((res, rej) => {
    const tx = db.transaction('posts','readonly');
    const req = tx.objectStore('posts').getAll();
    req.onsuccess = () => res(req.result || []);
    req.onerror   = () => rej(req.error);
  });
}
async function removeFromQueue(ts) {
  const db = await openDB();
  return new Promise((res, rej) => {
    const tx = db.transaction('posts','readwrite');
    tx.objectStore('posts').delete(ts);
    tx.oncomplete = res; tx.onerror = rej;
  });
}

/* ==========================================================
   PUSH NOTIFICATIONS (optional — fires when your backend posts to a push service)
   ========================================================== */
self.addEventListener('push', event => {
  let data = { title: 'Sharan Foundation', body: 'You have a new update.' };
  try { data = event.data ? event.data.json() : data; } catch (e) {}
  const options = {
    body: data.body,
    icon: '/icons/icon-192.png',
    badge: '/icons/icon-96.png',
    vibrate: [120, 60, 120],
    tag: data.tag || 'sharan-notification',
    data: { url: data.url || '/' },
    actions: data.actions || [
      { action: 'open', title: 'Open' },
      { action: 'dismiss', title: 'Dismiss' }
    ]
  };
  event.waitUntil(self.registration.showNotification(data.title, options));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  if (event.action === 'dismiss') return;
  const url = event.notification.data?.url || '/';
  event.waitUntil(
    self.clients.matchAll({ type: 'window' }).then(clientList => {
      for (const client of clientList) {
        if (client.url === url && 'focus' in client) return client.focus();
      }
      if (self.clients.openWindow) return self.clients.openWindow(url);
    })
  );
});

/* ==========================================================
   MESSAGE handler — lets the page tell the SW to update / clear cache
   ========================================================== */
self.addEventListener('message', event => {
  if (event.data === 'SKIP_WAITING') self.skipWaiting();
  if (event.data === 'CLEAR_CACHE') {
    caches.keys().then(keys => Promise.all(keys.map(k => caches.delete(k))));
  }
});

/* ==========================================================
   HELPERS
   ========================================================== */
function makeErrorResponse(msg) {
  return new Response(
    `<!DOCTYPE html><html><head><meta charset="utf-8"><title>Offline — Sharan Foundation</title>
     <style>body{font-family:system-ui,sans-serif;text-align:center;padding:3rem;background:#0d2940;color:#fff}
     h1{color:#f4a261}p{color:#cfd8e3}</style></head>
     <body><h1>📡 You're offline</h1><p>${msg}</p></body></html>`,
    { headers: { 'Content-Type': 'text/html' }, status: 503 }
  );
}
