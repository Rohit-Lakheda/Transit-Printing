/**
 * Operator scanning bridge: offline-first scan handling for scan.blade.php
 */
(function (global) {
    const DB = () => global.EventOfflineDB;
    const Validator = () => global.EventOfflineScanValidator;
    const Connectivity = () => global.EventOfflineConnectivity;

    let config = null;
    let syncHandle = null;

    function getDeviceId() {
        const key = 'event_offline_device_id';
        let id = localStorage.getItem(key);
        if (!id) {
            id = 'dev-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
            localStorage.setItem(key, id);
        }
        return id;
    }

    function syncHeaders() {
        const headers = {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': config.csrfToken,
            'X-Offline-Device-Id': getDeviceId(),
        };
        if (config.syncToken) {
            headers['X-Offline-Sync-Token'] = config.syncToken;
        }
        return headers;
    }

    async function markScanQueueSynced(clientScanId) {
        const queue = await DB().getAll('sync_queue');
        for (const item of queue) {
            if (item.entity_type === 'scan' && item.payload && item.payload.client_scan_id === clientScanId) {
                await DB().tx('sync_queue', 'readwrite', (store) => {
                    item.status = 'synced';
                    item.synced_at = new Date().toISOString();
                    store.put(item);
                });
            }
        }
    }

    async function tryOnlineScan(regid, locationId, clientScanId) {
        const base = Connectivity().resolveApiBase();
        const url = (base ? base.replace(/\/$/, '') : '') + config.endpoints.checkUser;

        const res = await fetch(url, {
            method: 'POST',
            headers: syncHeaders(),
            body: JSON.stringify({
                regid,
                client_scan_id: clientScanId,
                scan_time: new Date().toISOString(),
                device_id: getDeviceId(),
            }),
        });

        if (!res.ok) throw new Error('check-user failed');
        return res.json();
    }

    async function handleScan(regid, locationId) {
        await DB().setMeta('scanning_location_id', locationId);

        const bootstrapAt = await DB().getMeta('last_bootstrap_at');
        const cachedEventId = await DB().getMeta('event_id');
        if (!bootstrapAt || !cachedEventId) {
            return {
                success: false,
                allowed: false,
                message: 'Event data not downloaded. Go to Select Location and download event data first.',
                offline: true,
            };
        }

        const validation = await Validator().validateScan(regid, locationId);
        const saved = await Validator().saveLocalScan(regid, locationId, validation, getDeviceId());

        const health = Connectivity().getState();
        if (health.online) {
            try {
                const online = await tryOnlineScan(regid, locationId, saved.client_scan_id);
                if (online && online.success !== false) {
                    saved.record.sync_status = 'synced';
                    await DB().tx('scan_logs_local', 'readwrite', (store) => store.put(saved.record));
                    await markScanQueueSynced(saved.client_scan_id);
                    if (syncHandle) syncHandle.syncNow();
                    return online;
                }
            } catch (e) {
                console.warn('Online scan failed, local result kept', e);
            }
        }

        if (syncHandle) syncHandle.syncNow();

        return {
            success: validation.success,
            allowed: validation.allowed,
            already_scanned: validation.already_scanned,
            name: validation.name,
            category: validation.category,
            reason: validation.reason,
            offline: !health.online,
            message: health.online ? 'Saved locally; sync pending' : 'Offline mode - saved locally',
        };
    }

    async function countPending() {
        if (global.EventOfflineSyncEngine && global.EventOfflineSyncEngine.countPending) {
            return global.EventOfflineSyncEngine.countPending();
        }
        const queue = await DB().getAll('sync_queue');
        return queue.filter((q) => q.status === 'pending' || q.status === 'retry').length;
    }

    function init(cfg) {
        config = cfg;
        if (cfg.lanBaseUrl) Connectivity().setLanBaseUrl(cfg.lanBaseUrl);
        if (typeof cfg.preferLan !== 'undefined') Connectivity().setPreferLan(cfg.preferLan);
        Connectivity().startMonitor(cfg.endpoints.health, cfg.intervalSeconds || 20);
        syncHandle = global.EventOfflineSyncEngine.start({
            deviceId: getDeviceId(),
            csrfToken: cfg.csrfToken,
            syncToken: cfg.syncToken || null,
            endpoints: cfg.endpoints,
            intervalSeconds: cfg.intervalSeconds || 20,
            maxRetries: cfg.maxRetries || 8,
        });
    }

    global.EventOfflineOperator = {
        init,
        handleScan,
        syncNow: async () => syncHandle && syncHandle.syncNow(),
        countPending,
        getDeviceId,
    };
})(window);
