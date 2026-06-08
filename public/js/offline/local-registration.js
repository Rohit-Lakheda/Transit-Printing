/**
 * Offline onsite registration queue + local RegID generation.
 */
(function (global) {
    const DB = () => global.EventOfflineDB;

    function uuid() {
        if (crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'reg-' + Date.now() + '-' + Math.random().toString(36).slice(2);
    }

    async function generateLocalRegId(categoryName) {
        const category = await DB().get('categories', categoryName);
        const prefix = (category && category.Prefix) ? category.Prefix : '';
        const attendees = await DB().getAll('attendees');
        const pending = await DB().getAll('registrations_local');

        let maxNum = 0;
        const allRegIds = []
            .concat(attendees.filter((a) => a.Category === categoryName).map((a) => a.RegID))
            .concat(pending.filter((p) => p.Category === categoryName).map((p) => p.RegID));

        for (const regID of allRegIds) {
            if (!regID) continue;
            if (prefix && regID.indexOf(prefix) === 0) {
                const numPart = regID.slice(prefix.length);
                const m = numPart.match(/^(\d+)/);
                if (m) maxNum = Math.max(maxNum, parseInt(m[1], 10));
            } else {
                const m = String(regID).match(/(\d+)$/);
                if (m) maxNum = Math.max(maxNum, parseInt(m[1], 10));
            }
        }

        const next = maxNum + 1;
        return prefix + String(next).padStart(4, '0');
    }

    async function saveRegistration(formData) {
        const clientRegistrationId = uuid();
        const regId = await generateLocalRegId(formData.Category);
        const record = Object.assign({}, formData, {
            client_registration_id: clientRegistrationId,
            RegID: regId,
            DataFrom: 'Onsite Registration (offline)',
            Data_Received_At: new Date().toISOString(),
            sync_status: 'pending',
        });

        await DB().tx('registrations_local', 'readwrite', (store) => store.put(record));
        await DB().tx('attendees', 'readwrite', (store) => store.put(record));
        await DB().tx('sync_queue', 'readwrite', (store) => store.add({
            entity_type: 'registration',
            payload: record,
            status: 'pending',
            retry_count: 0,
            next_retry_at: new Date().toISOString(),
            created_at: new Date().toISOString(),
        }));

        return record;
    }

    global.EventOfflineRegistration = {
        saveRegistration,
        generateLocalRegId,
        uuid,
    };
})(window);
