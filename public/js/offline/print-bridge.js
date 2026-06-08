/**
 * Offline-first printing for operator scan-print page.
 */
(function (global) {
    const DB = () => global.EventOfflineDB;
    const LocalPrint = () => global.EventOfflineLocalPrint;
    const Renderer = () => global.EventOfflinePrintRenderer;
    const Connectivity = () => global.EventOfflineConnectivity;

    let config = null;

    function getDeviceId() {
        const key = 'event_offline_device_id';
        let id = localStorage.getItem(key);
        if (!id) {
            id = 'dev-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
            localStorage.setItem(key, id);
        }
        return id;
    }

    async function fetchServerPayload(regid, eventId) {
        const base = Connectivity().resolveApiBase();
        const url = base + config.endpoints.printPayload + '?event_id=' + encodeURIComponent(eventId) + '&regid=' + encodeURIComponent(regid);
        const headers = {
            Accept: 'application/json',
            'X-CSRF-TOKEN': config.csrfToken,
        };
        if (config.syncToken) {
            headers['X-Offline-Sync-Token'] = config.syncToken;
        }
        const res = await fetch(url, { headers });
        if (!res.ok) return null;
        const data = await res.json();
        return data.payload || null;
    }

    async function printFromPayload(payload, regid, recordLocally) {
        payload = await Renderer().enrichPayloadWithQr(payload);
        const html = Renderer().buildFromPayload(payload);
        if (!html) {
            return { ok: false, message: 'Could not build badge layout.' };
        }

        if (recordLocally) {
            const category = payload.category?.Category || payload.user?.Category;
            await LocalPrint().recordLocalPrint(regid, category, getDeviceId(), 'single');
            if (global.EventOfflineSyncEngine && config.syncNow) {
                config.syncNow();
            }
        }

        const opened = Renderer().openPrintWindow(html);
        if (!opened) {
            return { ok: false, message: 'Print dialog blocked by browser.' };
        }

        return { ok: true, message: 'Print dialog opened' };
    }

    async function printRegid(regid) {
        if (!regid) {
            return { ok: false, message: 'Registration ID is required.' };
        }

        const eventId = (await DB().getMeta('event_id')) || 1;
        const health = Connectivity().getState();

        if (health.online) {
            const payload = await fetchServerPayload(regid, eventId);
            if (payload) {
                const hasLocal = await DB().get('attendees', regid);
                return printFromPayload(payload, regid, !!hasLocal);
            }
        }

        const validation = await LocalPrint().validatePrint(regid);
        if (!validation.ok) {
            return validation;
        }

        const html = await Renderer().buildFromLocalCache(regid);
        if (!html) {
            return { ok: false, message: 'Badge layout not available offline. Download event data or connect to hub/internet.' };
        }

        await LocalPrint().recordLocalPrint(regid, validation.attendee.Category, getDeviceId(), 'single');

        if (global.EventOfflineSyncEngine && config.syncNow) {
            config.syncNow();
        }

        const opened = Renderer().openPrintWindow(html);
        if (!opened) {
            return { ok: false, message: 'Print dialog blocked by browser.' };
        }

        return { ok: true, message: 'Print dialog opened' };
    }

    function init(cfg) {
        config = cfg;
    }

    global.EventOfflinePrintBridge = {
        init,
        printRegid,
        getDeviceId,
    };
})(window);
