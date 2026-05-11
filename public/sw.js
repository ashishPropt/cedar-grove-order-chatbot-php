/**
 * Cedar Grove POS - Service Worker
 * Handles offline caching and background sync
 */

const CACHE_VERSION = 'cg-v1';
const STATIC_CACHE  = CACHE_VERSION + '-static';
const MENU_CACHE    = CACHE_VERSION + '-menu';
const SYNC_TAG      = 'sync-orders';

// Static assets to cache on install
const STATIC_ASSETS = [
    '/',
    '/index.php',
    '/basket.php',
    '/checkout.php',
    '/offline.html',
    '/assets/css/app.css',
    '/assets/js/menu.js',
    '/assets/js/idb.js',
    '/assets/js/pos.js',
];

// ---- Install: cache static assets ----
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => cache.addAll(STATIC_ASSETS.filter(u => !u.endsWith('.php'))))
            .then(() => self.skipWaiting())
    );
});

// ---- Activate: clean old caches ----
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys.filter(k => k.startsWith('cg-') && k !== STATIC_CACHE && k !== MENU_CACHE)
                    .map(k => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

// ---- Fetch: serve from cache or network ----
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Never intercept POST requests (basket updates, sync)
    if (event.request.method !== 'GET') return;

    // Menu API: network first, cache fallback
    if (url.pathname === '/api/menu.php') {
        event.respondWith(
            fetch(event.request)
                .then(res => {
                    const clone = res.clone();
                    caches.open(MENU_CACHE).then(c => c.put(event.request, clone));
                    return res;
                })
                .catch(() => caches.match(event.request))
        );
        return;
    }

    // item.php: network first (needs live Supabase data), cache fallback
    if (url.pathname === '/item.php') {
        event.respondWith(
            fetch(event.request)
                .then(res => {
                    const clone = res.clone();
                    caches.open(STATIC_CACHE).then(c => c.put(event.request, clone));
                    return res;
                })
                .catch(() => caches.match(event.request)
                    || caches.match('/offline.html'))
        );
        return;
    }

    // Static assets: cache first
    if (
        url.pathname.startsWith('/assets/') ||
        url.pathname === '/offline.html'
    ) {
        event.respondWith(
            caches.match(event.request).then(cached => cached || fetch(event.request))
        );
        return;
    }

    // PHP pages: network first, cache fallback
    event.respondWith(
        fetch(event.request)
            .then(res => {
                if (res.ok) {
                    const clone = res.clone();
                    caches.open(STATIC_CACHE).then(c => c.put(event.request, clone));
                }
                return res;
            })
            .catch(() =>
                caches.match(event.request).then(cached => cached || caches.match('/offline.html'))
            )
    );
});

// ---- Background Sync ----
self.addEventListener('sync', event => {
    if (event.tag === SYNC_TAG) {
        event.waitUntil(syncOfflineOrders());
    }
});

async function syncOfflineOrders() {
    // Open IndexedDB and get all pending orders
    const db     = await openDB();
    const orders = await getAllPending(db);
    if (!orders.length) return;

    try {
        const res = await fetch('/api/sync_orders.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ orders }),
        });
        const data = await res.json();
        if (data.ok) {
            // Remove successfully synced orders from IDB
            const synced = data.results.filter(r => r.synced).map(r => r.offline_id);
            await markSynced(db, synced);
            // Notify all open clients
            const clients = await self.clients.matchAll();
            clients.forEach(c => c.postMessage({ type: 'SYNC_COMPLETE', synced: synced.length }));
        }
    } catch (e) {
        // Will retry on next sync event
        console.error('Sync failed, will retry:', e);
    }
}

// ---- IndexedDB helpers ----
function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open('cg-pos', 1);
        req.onupgradeneeded = e => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains('orders')) {
                db.createObjectStore('orders', { keyPath: 'offline_id' });
            }
        };
        req.onsuccess = e => resolve(e.target.result);
        req.onerror   = e => reject(e.target.error);
    });
}

function getAllPending(db) {
    return new Promise((resolve, reject) => {
        const tx  = db.transaction('orders', 'readonly');
        const req = tx.objectStore('orders').getAll();
        req.onsuccess = e => resolve(e.target.result.filter(o => !o.synced));
        req.onerror   = e => reject(e.target.error);
    });
}

function markSynced(db, offline_ids) {
    return new Promise((resolve, reject) => {
        const tx    = db.transaction('orders', 'readwrite');
        const store = tx.objectStore('orders');
        const ids   = new Set(offline_ids);
        const req   = store.getAll();
        req.onsuccess = e => {
            e.target.result.forEach(o => {
                if (ids.has(o.offline_id)) {
                    o.synced = true;
                    store.put(o);
                }
            });
        };
        tx.oncomplete = () => resolve();
        tx.onerror    = e => reject(e.target.error);
    });
}
