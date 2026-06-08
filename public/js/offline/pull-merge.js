/**
 * Merge server pull responses into local IndexedDB (cloud → local).
 */
(function (global) {
    const DB = () => global.EventOfflineDB;

    function printKey(regid, category) {
        return regid + '::' + category;
    }

    async function mergePrintIndex(rows) {
        if (!rows || !rows.length) return;
        const mapped = rows.map((row) => ({
            key: printKey(row.regid, row.category),
            regid: row.regid,
            category: row.category,
            printed_at: row.printed_at,
            source: row.source || 'server',
        }));
        await DB().putAll('print_index', mapped);
    }

    async function mergeRemoteScans(rows, locationId) {
        if (!rows || !rows.length) return;
        const mapped = rows.map((row) => ({
            remote_key: (row.client_scan_id || row.regid + '-' + row.scanned_at),
            regid: row.regid,
            location_id: row.location_id || locationId,
            is_allowed: row.is_allowed,
            scanned_at: row.scanned_at,
            device_id: row.device_id,
            source: 'server',
        }));
        await DB().putAll('scan_logs_remote', mapped);
    }

    /**
     * Apply incremental pull payload from server.
     */
    async function mergePullResponse(data) {
        if (data.attendees && data.attendees.length) {
            await DB().putAll('attendees', data.attendees);
        }
        if (data.categories && data.categories.length) {
            await DB().putAll('categories', data.categories);
        }
        if (data.printing_logs && data.printing_logs.length) {
            await mergePrintIndex(data.printing_logs);
            for (const row of data.printing_logs) {
                const attendee = await DB().get('attendees', row.regid);
                if (attendee && !attendee.Badge_Printed_At) {
                    attendee.Badge_Printed_At = row.printed_at;
                    await DB().tx('attendees', 'readwrite', (s) => s.put(attendee));
                }
            }
        }
        if (data.scanning_logs && data.scanning_logs.length) {
            await mergeRemoteScans(data.scanning_logs);
        }
        if (data.synced_at) {
            await DB().setMeta('last_pull_at', data.synced_at);
        }
    }

    /**
     * Apply full bootstrap payload.
     */
    async function mergeBootstrap(data) {
        if (data.attendees) await DB().putAll('attendees', data.attendees);
        if (data.locations) await DB().putAll('locations', data.locations);
        if (data.categories) await DB().putAll('categories', data.categories);
        if (data.blocked_regids) await DB().putAll('blocked_regids', data.blocked_regids);
        if (data.master_badges) await DB().putAll('master_badges', data.master_badges);
        if (data.bypassed_regids) await DB().putAll('bypassed_regids', data.bypassed_regids);
        if (data.badge_display_settings) await DB().putAll('badge_display_settings', data.badge_display_settings);
        if (data.badge_layout_groups) await DB().putAll('badge_layout_groups', data.badge_layout_groups);
        if (data.print_index) {
            const mapped = data.print_index.map((row) => ({
                key: printKey(row.regid, row.category),
                regid: row.regid,
                category: row.category,
                printed_at: row.printed_at,
                source: 'server',
            }));
            await DB().clearStore('print_index');
            await DB().putAll('print_index', mapped);
        }
        await DB().setMeta('event_id', data.event_id);
        await DB().setMeta('last_bootstrap_at', data.synced_at || new Date().toISOString());
        await DB().setMeta('last_pull_at', data.synced_at || new Date().toISOString());
        await DB().setMeta('event_settings', data.event_settings || {});
    }

    global.EventOfflinePullMerge = {
        mergePullResponse,
        mergeBootstrap,
        mergeRemoteScans,
        printKey,
    };
})(window);
