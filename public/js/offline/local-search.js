/**
 * Offline attendee search from IndexedDB cache.
 */
(function (global) {
    const DB = () => global.EventOfflineDB;

    function matchesSearch(attendee, term) {
        if (!term) return true;
        const q = term.toLowerCase();
        const fields = [
            'RegID', 'Name', 'Email', 'Mobile', 'Company', 'Designation',
            'Country', 'State', 'City', 'Category',
            'Additional1', 'Additional2', 'Additional3', 'Additional4', 'Additional5',
            'ReceiptNumber',
        ];
        return fields.some((f) => {
            const val = attendee[f];
            return val && String(val).toLowerCase().includes(q);
        });
    }

    async function search({ category, term, limit = 50 }) {
        const all = await DB().getAll('attendees');
        let rows = all.filter((a) => matchesSearch(a, term));
        if (category) {
            rows = rows.filter((a) => a.Category === category);
        }
        rows.sort((a, b) => String(a.RegID).localeCompare(String(b.RegID)));
        return rows.slice(0, limit);
    }

    async function getCategories() {
        const cats = await DB().getAll('categories');
        return cats.map((c) => c.Category).sort();
    }

    global.EventOfflineSearch = {
        search,
        getCategories,
    };
})(window);
