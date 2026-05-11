/**
 * pos.js - POS offline/online manager
 * Registers service worker, manages online status banner,
 * intercepts checkout to save offline when needed
 */
(function() {
    // ---- Register Service Worker ----
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').then(reg => {
            console.log('SW registered:', reg.scope);

            // Listen for sync complete message from SW
            navigator.serviceWorker.addEventListener('message', e => {
                if (e.data && e.data.type === 'SYNC_COMPLETE') {
                    showBanner('&#10003; ' + e.data.synced + ' offline order(s) synced to database!', 'success', 5000);
                    updatePendingBadge();
                }
            });
        }).catch(err => console.warn('SW registration failed:', err));
    }

    // ---- Online/Offline Banner ----
    const banner = document.createElement('div');
    banner.id = 'pos-banner';
    banner.style.cssText = [
        'position:fixed', 'top:0', 'left:0', 'right:0', 'z-index:9999',
        'padding:8px 16px', 'text-align:center', 'font-size:13px',
        'font-weight:600', 'transition:transform .3s ease', 'transform:translateY(-100%)',
    ].join(';');
    document.body.appendChild(banner);

    function showBanner(msg, type, autohide) {
        banner.textContent = '';
        banner.innerHTML = msg;
        banner.style.background = type === 'success' ? '#1D9E75' :
                                  type === 'warn'    ? '#f59e0b' : '#e53e3e';
        banner.style.color = '#fff';
        banner.style.transform = 'translateY(0)';
        if (autohide) setTimeout(hideBanner, autohide);
    }

    function hideBanner() {
        banner.style.transform = 'translateY(-100%)';
    }

    window.addEventListener('online', () => {
        showBanner('&#127760; Back online — syncing orders…', 'success', 0);
        triggerSync();
    });

    window.addEventListener('offline', () => {
        showBanner('&#128683; Offline — orders will be saved locally and synced when connection returns', 'warn', 0);
    });

    // Show initial state
    if (!navigator.onLine) {
        showBanner('&#128683; Currently offline — POS running in offline mode', 'warn', 0);
    }

    // ---- Trigger Background Sync ----
    async function triggerSync() {
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            const reg = await navigator.serviceWorker.ready;
            await reg.sync.register('sync-orders');
        } else {
            // Fallback: manual sync
            manualSync();
        }
    }

    async function manualSync() {
        const pending = await window.IDB.getPendingOrders();
        if (!pending.length) return;
        try {
            const res  = await fetch('/api/sync_orders.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ orders: pending }),
            });
            const data = await res.json();
            if (data.ok) {
                const synced = data.results.filter(r => r.synced).length;
                showBanner('&#10003; ' + synced + ' offline order(s) synced!', 'success', 5000);
                updatePendingBadge();
            }
        } catch(e) {
            console.warn('Manual sync failed:', e);
        }
    }

    // ---- Pending orders badge ----
    async function updatePendingBadge() {
        if (!window.IDB) return;
        const pending = await window.IDB.getPendingOrders();
        let badge = document.getElementById('offline-badge');
        if (pending.length > 0) {
            if (!badge) {
                badge = document.createElement('div');
                badge.id = 'offline-badge';
                badge.style.cssText = [
                    'position:fixed','bottom:16px','right:16px','z-index:9998',
                    'background:#f59e0b','color:#fff','padding:8px 14px',
                    'border-radius:20px','font-size:12px','font-weight:600',
                    'box-shadow:0 2px 8px rgba(0,0,0,.2)',
                ].join(';');
                document.body.appendChild(badge);
            }
            badge.innerHTML = '&#128683; ' + pending.length + ' order(s) pending sync';
        } else if (badge) {
            badge.remove();
        }
    }

    // Check for pending orders on load
    document.addEventListener('DOMContentLoaded', () => {
        if (window.IDB) {
            updatePendingBadge();
            // If we come back online and there are pending orders, sync
            if (navigator.onLine) triggerSync();
        }
    });

    window.POS = { triggerSync, manualSync, updatePendingBadge };
})();
