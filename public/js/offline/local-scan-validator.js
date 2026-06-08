/**
 * Offline scan validation using locally cached bootstrap rules.
 */
(function (global) {
    const DB = () => global.EventOfflineDB;

    function uuid() {
        if (crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'scan-' + Date.now() + '-' + Math.random().toString(36).slice(2);
    }

    async function getBypassUsage(regid, locationId) {
        const logs = await DB().getAll('scan_logs_local');
        return logs.filter((l) =>
            l.regid === regid &&
            l.location_id === locationId &&
            l.is_allowed === true &&
            l.bypass_applied === true
        ).length;
    }

    async function getConflictPolicy() {
        const settings = await DB().getMeta('event_settings');
        return (settings && settings.conflict_policy) ? settings.conflict_policy : 'first_scan_wins';
    }

    async function hasAllowedScanAtLocation(regid, locationId) {
        const local = await DB().getAll('scan_logs_local');
        if (local.some((l) => l.regid === regid && l.location_id === locationId && l.is_allowed === true)) {
            return true;
        }
        const remote = await DB().getAll('scan_logs_remote');
        return remote.some((l) => l.regid === regid && l.location_id === locationId && l.is_allowed === true);
    }

    async function validateScan(regid, locationId) {
        const attendee = await DB().get('attendees', regid);
        const location = await DB().get('locations', locationId);

        if (!location) {
            return { success: false, allowed: false, reason: 'Location not found in local cache', name: '', category: '' };
        }

        if (!attendee) {
            return {
                success: false,
                allowed: false,
                reason: 'User not found in local database',
                name: '',
                category: '',
                regid,
            };
        }

        const userName = attendee.Name || '';
        const userCategory = attendee.Category || '';
        let isAllowed = false;
        let reason = '';
        let alreadyScanned = false;

        const bypassed = await DB().get('bypassed_regids', regid);
        let isBypassed = false;
        if (bypassed && (bypassed.location_ids || []).includes(locationId)) {
            const maxUses = bypassed.max_uses == null ? 1 : Number(bypassed.max_uses);
            const usage = await getBypassUsage(regid, locationId);
            if (usage < maxUses) {
                isBypassed = true;
                isAllowed = true;
                reason = 'Bypassed RegID (offline) - ' + (bypassed.reason || 'Access granted');
            }
        }

        const master = await DB().get('master_badges', regid);
        const isMaster = master && (master.location_ids || []).includes(locationId);

        if (!isBypassed) {
            if (isMaster) {
                isAllowed = true;
                reason = 'Master RegID - allowed at all selected locations';
            } else {
                const blocked = await DB().get('blocked_regids', regid);
                if (blocked && (blocked.location_ids || []).includes(locationId)) {
                    isAllowed = false;
                    reason = 'RegID is blocked at this location';
                } else {
                    const allowedCategories = location.allowed_categories || [];
                    isAllowed = allowedCategories.includes(userCategory);
                    reason = isAllowed
                        ? `Category '${userCategory}' is allowed at location '${location.name}'`
                        : `Category '${userCategory}' is not allowed at location '${location.name}'`;
                }
            }
        }

        if (location.unique_scanning && isAllowed && !isMaster && !isBypassed) {
            const policy = await getConflictPolicy();
            const already = await hasAllowedScanAtLocation(regid, locationId);
            if (already) {
                if (policy === 'latest_wins') {
                    // Allow rescan locally; server resolves timestamp on sync.
                } else {
                    alreadyScanned = true;
                    isAllowed = false;
                    reason = 'Already scanned at this location (offline cache)';
                }
            }
        }

        return {
            success: true,
            allowed: isAllowed && !alreadyScanned,
            already_scanned: alreadyScanned,
            name: userName,
            category: userCategory,
            regid,
            reason,
            bypass_applied: isBypassed,
        };
    }

    async function saveLocalScan(regid, locationId, validation, deviceId) {
        const clientScanId = uuid();
        const record = {
            client_scan_id: clientScanId,
            regid,
            location_id: locationId,
            scan_time: new Date().toISOString(),
            is_allowed: validation.allowed,
            reason: validation.reason,
            name: validation.name,
            category: validation.category,
            bypass_applied: !!validation.bypass_applied,
            device_id: deviceId,
            sync_status: 'pending',
        };

        await DB().tx('scan_logs_local', 'readwrite', (store) => store.put(record));

        await DB().tx('sync_queue', 'readwrite', (store) => store.add({
            entity_type: 'scan',
            payload: record,
            status: 'pending',
            retry_count: 0,
            next_retry_at: new Date().toISOString(),
            created_at: new Date().toISOString(),
        }));

        return { client_scan_id: clientScanId, record };
    }

    global.EventOfflineScanValidator = {
        validateScan,
        saveLocalScan,
        uuid,
    };
})(window);
