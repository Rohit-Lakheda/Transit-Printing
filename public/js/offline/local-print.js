/**
 * Offline print validation + local print log queue.
 */
(function (global) {
    const DB = () => global.EventOfflineDB;
    const Merge = () => global.EventOfflinePullMerge;

    function uuid() {
        if (crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'print-' + Date.now() + '-' + Math.random().toString(36).slice(2);
    }

    async function isAlreadyPrinted(regid, category) {
        const key = Merge().printKey(regid, category);
        const idx = await DB().get('print_index', key);
        if (idx) return true;

        const localLogs = await DB().getAll('print_logs_local');
        return localLogs.some((l) => l.regid === regid && l.category === category && l.sync_status !== 'failed');
    }

    async function validatePrint(regid) {
        const attendee = await DB().get('attendees', regid);
        if (!attendee) {
            return { ok: false, message: 'Registration ID not found in local database. Download event data first.' };
        }

        const category = await DB().get('categories', attendee.Category);
        if (category && category.unique_printing) {
            if (await isAlreadyPrinted(regid, attendee.Category)) {
                return {
                    ok: false,
                    message: 'This badge is already printed for this category (local/server index).',
                };
            }
        }

        return { ok: true, attendee, category };
    }

    async function recordLocalPrint(regid, category, deviceId, printType = 'single') {
        const clientPrintId = uuid();
        const printedAt = new Date().toISOString();

        const record = {
            client_print_id: clientPrintId,
            regid,
            category,
            printed_at: printedAt,
            print_type: printType,
            device_id: deviceId,
            sync_status: 'pending',
        };

        await DB().tx('print_logs_local', 'readwrite', (s) => s.put(record));
        await DB().tx('print_index', 'readwrite', (s) => s.put({
            key: Merge().printKey(regid, category),
            regid,
            category,
            printed_at: printedAt,
            source: 'local',
        }));

        const attendee = await DB().get('attendees', regid);
        if (attendee) {
            attendee.Badge_Printed_At = printedAt;
            await DB().tx('attendees', 'readwrite', (s) => s.put(attendee));
        }

        await DB().tx('sync_queue', 'readwrite', (store) => store.add({
            entity_type: 'print',
            payload: record,
            status: 'pending',
            retry_count: 0,
            next_retry_at: printedAt,
            created_at: printedAt,
        }));

        return record;
    }

    global.EventOfflineLocalPrint = {
        validatePrint,
        recordLocalPrint,
        isAlreadyPrinted,
        uuid,
    };
})(window);
