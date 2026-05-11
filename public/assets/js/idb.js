/**
 * idb.js - IndexedDB helper for offline order queue
 * Exposed as window.IDB
 */
(function() {
    const DB_NAME    = 'cg-pos';
    const DB_VERSION = 1;
    const STORE      = 'orders';

    function open() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = e => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    db.createObjectStore(STORE, { keyPath: 'offline_id' });
                }
            };
            req.onsuccess = e => resolve(e.target.result);
            req.onerror   = e => reject(e.target.error);
        });
    }

    async function saveOrder(orderData) {
        const db    = await open();
        const entry = Object.assign({ synced: false, created_at: Date.now() }, orderData);
        return new Promise((resolve, reject) => {
            const tx  = db.transaction(STORE, 'readwrite');
            const req = tx.objectStore(STORE).put(entry);
            req.onsuccess = () => resolve(entry);
            req.onerror   = e => reject(e.target.error);
        });
    }

    async function getPendingOrders() {
        const db = await open();
        return new Promise((resolve, reject) => {
            const tx  = db.transaction(STORE, 'readonly');
            const req = tx.objectStore(STORE).getAll();
            req.onsuccess = e => resolve(e.target.result.filter(o => !o.synced));
            req.onerror   = e => reject(e.target.error);
        });
    }

    async function getAllOrders() {
        const db = await open();
        return new Promise((resolve, reject) => {
            const tx  = db.transaction(STORE, 'readonly');
            const req = tx.objectStore(STORE).getAll();
            req.onsuccess = e => resolve(e.target.result);
            req.onerror   = e => reject(e.target.error);
        });
    }

    async function clearSynced() {
        const db     = await open();
        const orders = await getAllOrders();
        return new Promise((resolve, reject) => {
            const tx    = db.transaction(STORE, 'readwrite');
            const store = tx.objectStore(STORE);
            orders.filter(o => o.synced).forEach(o => store.delete(o.offline_id));
            tx.oncomplete = () => resolve();
            tx.onerror    = e => reject(e.target.error);
        });
    }

    window.IDB = { saveOrder, getPendingOrders, getAllOrders, clearSynced };
})();
