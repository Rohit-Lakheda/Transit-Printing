/**
 * Internet + LAN health monitor with automatic mode switching.
 */
(function (global) {
    const listeners = new Set();
    let lanBaseUrl = null;
    let preferLan = false;
    let lastHealth = { online: false, lan: false, cloud: false, mode: 'offline' };

    function normalizeBaseUrl(url) {
        if (!url || typeof url !== 'string') return null;
        const trimmed = url.trim();
        if (!trimmed) return null;
        const withProtocol = /^https?:\/\//i.test(trimmed) ? trimmed : ('http://' + trimmed);
        try {
            const parsed = new URL(withProtocol);
            return parsed.origin;
        } catch (e) {
            return null;
        }
    }

    function notify() {
        listeners.forEach((fn) => {
            try { fn(lastHealth); } catch (e) { console.error(e); }
        });
    }

    async function ping(url, timeoutMs = 4000) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeoutMs);
        try {
            const res = await fetch(url, { method: 'GET', cache: 'no-store', signal: controller.signal });
            clearTimeout(timer);
            return res.ok;
        } catch (e) {
            clearTimeout(timer);
            return false;
        }
    }

    async function checkHealth(cloudHealthUrl) {
        const browserOnline = navigator.onLine;
        let cloudOk = false;
        let lanOk = false;

        if (browserOnline) {
            cloudOk = await ping(cloudHealthUrl);
        }

        if (lanBaseUrl) {
            lanOk = await ping(lanBaseUrl.replace(/\/$/, '') + '/operator/offline/health');
        }

        const online = cloudOk || lanOk;
        const mode = cloudOk ? 'cloud' : (lanOk ? 'lan' : 'offline');

        lastHealth = { online, lan: lanOk, cloud: cloudOk, mode, checked_at: new Date().toISOString() };
        notify();
        return lastHealth;
    }

    function startMonitor(cloudHealthUrl, intervalSeconds = 20) {
        checkHealth(cloudHealthUrl);
        setInterval(() => checkHealth(cloudHealthUrl), intervalSeconds * 1000);
        window.addEventListener('online', () => checkHealth(cloudHealthUrl));
        window.addEventListener('offline', () => checkHealth(cloudHealthUrl));
    }

    function onChange(fn) {
        listeners.add(fn);
        fn(lastHealth);
        return () => listeners.delete(fn);
    }

    function setLanBaseUrl(url) {
        lanBaseUrl = normalizeBaseUrl(url);
    }

    function setPreferLan(value) {
        preferLan = !!value;
    }

    function getState() {
        return lastHealth;
    }

    function resolveApiBase() {
        if (preferLan && lastHealth.lan && lanBaseUrl) return lanBaseUrl.replace(/\/$/, '');
        if (lastHealth.cloud) return '';
        if (lastHealth.lan && lanBaseUrl) return lanBaseUrl.replace(/\/$/, '');
        return '';
    }

    global.EventOfflineConnectivity = {
        startMonitor,
        onChange,
        setLanBaseUrl,
        setPreferLan,
        getState,
        resolveApiBase,
        checkHealth,
    };
})(window);
