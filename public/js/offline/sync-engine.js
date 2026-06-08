/**
 * Bidirectional background sync: push local → cloud, pull cloud → local.
 */
(function (global) {
    const DB = () => global.EventOfflineDB;
    const Connectivity = () => global.EventOfflineConnectivity;
    const PullMerge = () => global.EventOfflinePullMerge;

    let syncInProgress = false;

    function backoffSeconds(retryCount) {
        return Math.min(300, Math.pow(2, retryCount) * 5);
    }

    function apiUrl(base, path) {
        return (base ? base.replace(/\/$/, '') : '') + path;
    }

    function buildHeaders(csrfToken, deviceId, syncToken) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Offline-Device-Id': deviceId,
        };
        if (syncToken) headers['X-Offline-Sync-Token'] = syncToken;
        return headers;
    }

    async function getPendingQueue() {
        const all = await DB().getAll('sync_queue');
        const now = Date.now();
        return all.filter((item) => {
            if (item.status !== 'pending' && item.status !== 'retry') return false;
            if (!item.next_retry_at) return true;
            return new Date(item.next_retry_at).getTime() <= now;
        });
    }

    async function updateQueueItem(id, patch) {
        return DB().tx('sync_queue', 'readwrite', (store) => {
            return new Promise((resolve, reject) => {
                const getReq = store.get(id);
                getReq.onsuccess = () => {
                    const row = getReq.result;
                    if (!row) return resolve(false);
                    Object.assign(row, patch);
                    store.put(row);
                    resolve(true);
                };
                getReq.onerror = () => reject(getReq.error);
            });
        });
    }

    async function markEntitySynced(entityType, clientId) {
        const storeMap = {
            print: 'print_logs_local',
            scan: 'scan_logs_local',
            registration: 'registrations_local',
        };
        const storeName = storeMap[entityType];
        if (!storeName) return;
        const keyPath = entityType === 'registration' ? 'client_registration_id' : ('client_' + entityType + '_id');
        const row = await DB().get(storeName, clientId);
        if (row) {
            row.sync_status = 'synced';
            await DB().tx(storeName, 'readwrite', (s) => s.put(row));
        }
    }

    async function postJson(url, body, csrfToken, deviceId, syncToken) {
        const res = await fetch(url, {
            method: 'POST',
            headers: buildHeaders(csrfToken, deviceId, syncToken),
            body: JSON.stringify(body),
        });
        if (!res.ok) throw new Error('POST failed ' + res.status);
        return res.json();
    }

    async function getJson(url, csrfToken) {
        const res = await fetch(url, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
        if (!res.ok) throw new Error('GET failed ' + res.status);
        return res.json();
    }

    const TERMINAL_STATUSES = ['ok', 'duplicate', 'rejected_duplicate_print'];

    async function pushEntityBatch(entityType, items, eventId, deviceId, endpoints, csrfToken, syncToken) {
        const base = Connectivity().resolveApiBase();
        const batch = items.slice(0, 50).map((q) => q.payload);

        if (entityType === 'scan') {
            return postJson(apiUrl(base, endpoints.pushScans), { event_id: eventId, device_id: deviceId, scans: batch }, csrfToken, deviceId, syncToken);
        }
        if (entityType === 'registration') {
            return postJson(apiUrl(base, endpoints.pushRegistrations), { event_id: eventId, device_id: deviceId, registrations: batch }, csrfToken, deviceId, syncToken);
        }
        return postJson(apiUrl(base, endpoints.pushPrints), { event_id: eventId, device_id: deviceId, prints: batch }, csrfToken, deviceId, syncToken);
    }

    async function processPushQueue(config, eventId) {
        const pending = await getPendingQueue();
        const groups = [
            { type: 'scan', items: pending.filter((p) => p.entity_type === 'scan') },
            { type: 'print', items: pending.filter((p) => p.entity_type === 'print') },
            { type: 'registration', items: pending.filter((p) => p.entity_type === 'registration') },
        ];
        let pushed = 0;

        for (const group of groups) {
            if (!group.items.length) continue;
            try {
                const result = await pushEntityBatch(group.type, group.items, eventId, config.deviceId, config.endpoints, config.csrfToken, config.syncToken);
                const batchItems = group.items.slice(0, 50);
                for (const item of batchItems) {
                    const clientId = group.type === 'scan'
                        ? item.payload.client_scan_id
                        : (group.type === 'print' ? item.payload.client_print_id : item.payload.client_registration_id);
                    const serverResult = (result.results || []).find((r) => {
                        if (group.type === 'scan') return r.client_scan_id === clientId;
                        if (group.type === 'print') return r.client_print_id === clientId;
                        return r.client_registration_id === clientId;
                    });
                    if (serverResult && TERMINAL_STATUSES.includes(serverResult.status)) {
                        await updateQueueItem(item.id, { status: 'synced', synced_at: new Date().toISOString() });
                        await markEntitySynced(group.type, clientId);
                        if (group.type === 'registration' && serverResult.RegID && item.payload) {
                            const local = item.payload;
                            if (local.RegID !== serverResult.RegID) {
                                await DB().tx('attendees', 'readwrite', (s) => {
                                    s.delete(local.RegID);
                                    local.RegID = serverResult.RegID;
                                    s.put(local);
                                });
                            }
                        }
                        pushed++;
                    } else if (serverResult && serverResult.status === 'error') {
                        await handleRetry(item, serverResult.message, config.maxRetries);
                    }
                }
            } catch (e) {
                console.error('Push batch failed', group.type, e);
            }
        }
        return pushed;
    }

    async function handleRetry(item, message, maxRetries) {
        const retry = (item.retry_count || 0) + 1;
        if (retry >= maxRetries) {
            await updateQueueItem(item.id, { status: 'dead' });
            await DB().tx('failed_sync_logs', 'readwrite', (store) => store.add({
                entity_type: item.entity_type,
                payload: item.payload,
                error: message,
                failed_at: new Date().toISOString(),
            }));
        } else {
            const next = new Date(Date.now() + backoffSeconds(retry) * 1000).toISOString();
            await updateQueueItem(item.id, { status: 'retry', retry_count: retry, next_retry_at: next });
        }
    }

    async function pullFromServer(config, eventId) {
        const base = Connectivity().resolveApiBase();
        const locationId = await DB().getMeta('scanning_location_id');
        let since = await DB().getMeta('last_pull_at');
        let lastData = null;

        do {
            let url = apiUrl(base, config.endpoints.pull) + '?event_id=' + encodeURIComponent(eventId);
            if (since) url += '&since=' + encodeURIComponent(since);
            if (locationId) url += '&location_id=' + encodeURIComponent(locationId);

            const data = await getJson(url, config.csrfToken);
            await PullMerge().mergePullResponse(data);
            lastData = data;
            since = data.synced_at || since;
            if (data.has_more && data.synced_at) {
                await DB().setMeta('last_pull_at', data.synced_at);
            }
        } while (lastData && lastData.has_more);

        if (locationId) {
            const lanUrl = apiUrl(base, config.endpoints.pullLocationScans) +
                '?event_id=' + encodeURIComponent(eventId) +
                '&location_id=' + encodeURIComponent(locationId) +
                (since ? '&since=' + encodeURIComponent(since) : '');
            try {
                const lanData = await getJson(lanUrl, config.csrfToken);
                if (lanData.scans) {
                    PullMerge().mergeRemoteScans(lanData.scans, locationId);
                }
            } catch (e) {
                // optional LAN feed
            }
        }

        return lastData;
    }

    async function runSyncCycle(config) {
        if (syncInProgress) return { skipped: true, reason: 'sync_in_progress' };

        const health = Connectivity().getState();
        if (!health.online) return { skipped: true, reason: 'offline' };

        const eventId = await DB().getMeta('event_id');
        if (!eventId) return { skipped: true, reason: 'no_event' };

        syncInProgress = true;
        try {
            const pushed = await processPushQueue(config, eventId);
            let pulled = null;
            try {
                pulled = await pullFromServer(config, eventId);
            } catch (e) {
                console.error('Pull failed', e);
            }

            await DB().setMeta('last_sync_cycle_at', new Date().toISOString());
            return { pushed, pulled, ok: true };
        } finally {
            syncInProgress = false;
        }
    }

    function start(config) {
        const interval = (config.intervalSeconds || 20) * 1000;
        const tick = () => runSyncCycle(config);
        tick();
        const timer = setInterval(tick, interval);
        Connectivity().onChange((state) => {
            if (state.online) tick();
        });
        return {
            syncNow: tick,
            stop: () => clearInterval(timer),
        };
    }

    async function countPending() {
        const q = await getPendingQueue();
        return q.length;
    }

    global.EventOfflineSyncEngine = {
        start,
        runSyncCycle,
        countPending,
    };
})(window);
