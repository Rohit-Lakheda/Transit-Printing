/**
 * Pre-event bootstrap download into IndexedDB.
 */
(function (global) {
    const DB = () => global.EventOfflineDB;
    const PullMerge = () => global.EventOfflinePullMerge;

    function getDeviceId() {
        const key = 'event_offline_device_id';
        let id = localStorage.getItem(key);
        if (!id) {
            id = 'dev-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
            localStorage.setItem(key, id);
        }
        return id;
    }

    async function registerDevice(eventId, registerUrl, csrfToken, syncToken) {
        const headers = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Offline-Device-Id': getDeviceId(),
        };
        if (syncToken) headers['X-Offline-Sync-Token'] = syncToken;

        await fetch(registerUrl, {
            method: 'POST',
            headers,
            body: JSON.stringify({
                event_id: eventId,
                device_id: getDeviceId(),
                device_name: navigator.userAgent.slice(0, 120),
            }),
        });
    }

    async function downloadBootstrap(eventId, bootstrapUrl, csrfToken, options) {
        options = options || {};
        if (options.registerDeviceUrl) {
            await registerDevice(eventId, options.registerDeviceUrl, csrfToken, options.syncToken);
        }

        const url = bootstrapUrl + '?event_id=' + encodeURIComponent(eventId);
        const res = await fetch(url, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
        if (!res.ok) throw new Error('Bootstrap download failed: ' + res.status);
        const data = await res.json();
        await PullMerge().mergeBootstrap(data);
        return data;
    }

    async function incrementalPull(eventId, pullUrl, csrfToken, locationId) {
        let since = await DB().getMeta('last_pull_at');
        let lastData = null;

        do {
            let url = pullUrl + '?event_id=' + encodeURIComponent(eventId);
            if (since) url += '&since=' + encodeURIComponent(since);
            if (locationId) url += '&location_id=' + encodeURIComponent(locationId);
            const res = await fetch(url, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken } });
            if (!res.ok) throw new Error('Incremental pull failed');
            const data = await res.json();
            await PullMerge().mergePullResponse(data);
            lastData = data;
            since = data.synced_at || since;
            if (data.has_more && data.synced_at) {
                await DB().setMeta('last_pull_at', data.synced_at);
            }
        } while (lastData && lastData.has_more);

        return lastData;
    }

    global.EventOfflineBootstrap = {
        downloadBootstrap,
        incrementalPull,
        getDeviceId,
    };
})(window);
