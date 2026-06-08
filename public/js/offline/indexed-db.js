/**
 * IndexedDB layer for offline-first event operations.
 * DB: event_offline_db (v3)
 */
(function (global) {
    const DB_NAME = 'event_offline_db';
    const DB_VERSION = 4;

    const STORES = [
        { name: 'sync_meta', keyPath: 'key' },
        { name: 'attendees', keyPath: 'RegID' },
        { name: 'locations', keyPath: 'id' },
        { name: 'categories', keyPath: 'Category' },
        { name: 'blocked_regids', keyPath: 'regid' },
        { name: 'master_badges', keyPath: 'regid' },
        { name: 'bypassed_regids', keyPath: 'regid' },
        { name: 'badge_display_settings', keyPath: 'cache_key' },
        { name: 'badge_layout_groups', keyPath: 'cache_key' },
        { name: 'print_index', keyPath: 'key' },
        { name: 'scan_logs_local', keyPath: 'client_scan_id' },
        { name: 'scan_logs_remote', keyPath: 'remote_key' },
        { name: 'print_logs_local', keyPath: 'client_print_id' },
        { name: 'registrations_local', keyPath: 'client_registration_id' },
        { name: 'sync_queue', keyPath: 'id', autoIncrement: true },
        { name: 'failed_sync_logs', keyPath: 'id', autoIncrement: true },
        { name: 'devices', keyPath: 'device_id' },
    ];

    let dbInstance = null;

    function openDb() {
        if (dbInstance) {
            return Promise.resolve(dbInstance);
        }
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                dbInstance = request.result;
                resolve(dbInstance);
            };
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                STORES.forEach((store) => {
                    if (!db.objectStoreNames.contains(store.name)) {
                        const opts = {};
                        if (store.keyPath) opts.keyPath = store.keyPath;
                        if (store.autoIncrement) opts.autoIncrement = true;
                        const os = db.createObjectStore(store.name, opts);
                        if (store.name === 'sync_queue') {
                            os.createIndex('status', 'status', { unique: false });
                            os.createIndex('entity_type', 'entity_type', { unique: false });
                        }
                        if (store.name === 'scan_logs_local') {
                            os.createIndex('sync_status', 'sync_status', { unique: false });
                            os.createIndex('location_id', 'location_id', { unique: false });
                            os.createIndex('regid', 'regid', { unique: false });
                        }
                        if (store.name === 'print_index') {
                            os.createIndex('regid', 'regid', { unique: false });
                        }
                    }
                });
            };
        });
    }

    async function tx(storeName, mode, fn) {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const transaction = db.transaction(storeName, mode);
            const store = transaction.objectStore(storeName);
            let result;
            try {
                result = fn(store, transaction);
            } catch (e) {
                reject(e);
                return;
            }
            transaction.oncomplete = () => resolve(result);
            transaction.onerror = () => reject(transaction.error);
        });
    }

    async function putAll(storeName, rows) {
        if (!rows || !rows.length) return true;
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const transaction = db.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            rows.forEach((row) => store.put(row));
            transaction.oncomplete = () => resolve(true);
            transaction.onerror = () => reject(transaction.error);
        });
    }

    async function get(storeName, key) {
        return tx(storeName, 'readonly', (store) => {
            return new Promise((resolve, reject) => {
                const req = store.get(key);
                req.onsuccess = () => resolve(req.result);
                req.onerror = () => reject(req.error);
            });
        });
    }

    async function getAll(storeName, indexName, query) {
        return tx(storeName, 'readonly', (store) => {
            return new Promise((resolve, reject) => {
                const source = indexName ? store.index(indexName) : store;
                const req = query !== undefined ? source.getAll(query) : source.getAll();
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => reject(req.error);
            });
        });
    }

    async function setMeta(key, value) {
        return tx('sync_meta', 'readwrite', (store) => store.put({ key, value }));
    }

    async function getMeta(key) {
        const row = await get('sync_meta', key);
        return row ? row.value : null;
    }

    async function clearStore(storeName) {
        return tx(storeName, 'readwrite', (store) => store.clear());
    }

    global.EventOfflineDB = {
        openDb,
        putAll,
        get,
        getAll,
        setMeta,
        getMeta,
        tx,
        clearStore,
    };
})(window);
