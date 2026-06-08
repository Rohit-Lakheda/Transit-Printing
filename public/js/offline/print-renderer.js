/**
 * Client-side badge HTML renderer for offline printing.
 */
(function (global) {
    const MM_TO_PX = 3.779527559;
    let printFrame = null;

    function formatBadgeText(text) {
        if (text == null) {
            return '';
        }
        return String(text).trim().toLocaleUpperCase();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = formatBadgeText(text);
        return div.innerHTML;
    }

    function generateQrSvg(regid) {
        if (typeof global.qrcode !== 'function' || !regid) {
            return null;
        }
        try {
            const qr = global.qrcode(0, 'M');
            qr.addData(String(regid));
            qr.make();
            return qr.createSvgTag(4, 0);
        } catch (e) {
            console.warn('QR generation failed', e);
            return null;
        }
    }

    function isQrEnabled(display) {
        if (!display) return false;
        const flag = display.QRcode;
        return flag === true || flag === 1 || flag === '1';
    }

    async function resolveQrCode(regid, display, existingQr, existingType) {
        if (existingQr) {
            return { qr_code: existingQr, qr_code_type: existingType || 'svg' };
        }
        if (!isQrEnabled(display)) {
            return { qr_code: null, qr_code_type: null };
        }
        const svg = generateQrSvg(regid);
        if (svg) {
            return { qr_code: svg, qr_code_type: 'svg' };
        }
        return { qr_code: null, qr_code_type: null };
    }

    function buildFieldsHtml(payload) {
        const user = payload.user;
        const category = payload.category;
        const layouts = payload.layout_settings || [];
        const visibleFields = payload.visible_fields || [];
        const layoutMap = {};
        layouts.forEach((l) => { layoutMap[l.field_name] = l; });

        const sorted = visibleFields
            .filter((f) => layoutMap[f])
            .map((f) => ({ field: f, layout: layoutMap[f], sequence: layoutMap[f].sequence || 999 }))
            .sort((a, b) => a.sequence - b.sequence);

        let fieldsHtml = '';
        sorted.forEach((item, index) => {
            const field = item.field;
            const layout = item.layout;
            let value = '';

            if (field === 'Category') {
                value = user.Category || '';
            } else if (layout.static_text_value) {
                value = layout.static_text_value;
            } else if (field !== 'QRcode') {
                value = user[field] || '';
            }

            const marginTopPx = (layout.margin_top ?? (index > 0 ? 2 : 0)) * MM_TO_PX;
            const fontSizePx = field !== 'QRcode' && layout.font_size ? layout.font_size * MM_TO_PX : null;

            if (field === 'QRcode' && payload.qr_code) {
                const qrWidth = (layout.width || 20) * MM_TO_PX;
                const qrHeight = (layout.height || 20) * MM_TO_PX;
                const align = layout.text_align || 'left';
                let qrInner = '';
                if (payload.qr_code_type === 'svg') {
                    qrInner = String(payload.qr_code)
                        .replace(/width="[^"]*"/, 'width="' + qrWidth + '"')
                        .replace(/height="[^"]*"/, 'height="' + qrHeight + '"');
                } else if (payload.qr_code_type === 'png') {
                    qrInner = '<img src="data:image/png;base64,' + payload.qr_code + '" style="width:' + qrWidth + 'px;height:' + qrHeight + 'px;" alt="QR" />';
                }
                fieldsHtml += '<div class="badge-field" style="margin-top:' + marginTopPx + 'px;display:flex;justify-content:' +
                    (align === 'center' ? 'center' : align === 'right' ? 'flex-end' : 'flex-start') + '">' + qrInner + '</div>';
            } else if (value) {
                fieldsHtml += '<div class="badge-field" style="margin-top:' + marginTopPx + 'px;font-size:' + fontSizePx + 'px;font-family:' +
                    (layout.font_family || 'Comfortaa') + ';font-weight:' + (layout.font_weight || 'normal') + ';text-align:' +
                    (layout.text_align || 'left') + ';color:' + (layout.color || '#000') + ';">' + escapeHtml(value) + '</div>';
            }
        });

        return { fieldsHtml, category };
    }

    function buildDocumentHtml(payload) {
        const { fieldsHtml, category } = buildFieldsHtml(payload);
        const badgeWidthPx = category.badge_width * MM_TO_PX;
        const badgeHeightPx = category.badge_height * MM_TO_PX;

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@400;600&display=swap" rel="stylesheet">' +
            '<style>*{box-sizing:border-box}body{margin:0;font-family:Comfortaa,sans-serif}' +
            '.badge-print{width:' + badgeWidthPx + 'px;min-height:' + badgeHeightPx + 'px;position:relative;background:#fff;border:2px solid #3b82f6;border-radius:12px;padding:5mm}' +
            '@media print{html,body{width:' + category.badge_width + 'mm;height:' + category.badge_height + 'mm;margin:0}' +
            '.badge-print{width:' + category.badge_width + 'mm!important;height:' + category.badge_height + 'mm!important;border:none}' +
            '@page{size:' + category.badge_width + 'mm ' + category.badge_height + 'mm;margin:0}}</style></head><body>' +
            '<div class="badge-print">' + fieldsHtml + '</div></body></html>';
    }

    function buildFromPayload(payload) {
        return buildDocumentHtml(payload);
    }

    async function buildFromLocalCache(regid) {
        const attendee = await global.EventOfflineDB.get('attendees', regid);
        if (!attendee) return null;

        const cacheKey = attendee.Category + '::normal';
        const display = await global.EventOfflineDB.get('badge_display_settings', cacheKey);
        const layoutGroup = await global.EventOfflineDB.get('badge_layout_groups', cacheKey);
        if (!layoutGroup) return null;

        const category = await global.EventOfflineDB.get('categories', attendee.Category);
        const visibleFields = [];
        if (display) {
            ['Category', 'RegID', 'Name', 'Email', 'Mobile', 'Designation', 'Company', 'Country', 'State', 'City',
                'Additional1', 'Additional2', 'Additional3', 'Additional4', 'Additional5', 'QRcode'].forEach((field) => {
                const check = field === 'Category' ? 'ShowCategory' : field;
                if (display[check]) visibleFields.push(field);
            });
        }
        (layoutGroup.layouts || []).forEach((l) => {
            if (l.static_text_value && !visibleFields.includes(l.field_name)) {
                visibleFields.push(l.field_name);
            }
        });

        const qr = await resolveQrCode(regid, display, null, null);

        return buildFromPayload({
            user: attendee,
            category: category || { badge_width: 90, badge_height: 54 },
            layout_settings: layoutGroup.layouts || [],
            visible_fields: visibleFields,
            qr_code: qr.qr_code,
            qr_code_type: qr.qr_code_type,
        });
    }

    async function enrichPayloadWithQr(payload) {
        if (!payload || !payload.user) return payload;
        let display = payload.display_settings;
        if (!display && payload.user.Category) {
            const cacheKey = payload.user.Category + '::normal';
            display = await global.EventOfflineDB.get('badge_display_settings', cacheKey);
        }
        const qr = await resolveQrCode(
            payload.user.RegID,
            display,
            payload.qr_code,
            payload.qr_code_type
        );
        payload.qr_code = qr.qr_code;
        payload.qr_code_type = qr.qr_code_type;
        return payload;
    }

    function openPrintWindow(html) {
        if (!printFrame) {
            printFrame = document.createElement('iframe');
            printFrame.id = 'offline-print-frame';
            printFrame.setAttribute('aria-hidden', 'true');
            printFrame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
            document.body.appendChild(printFrame);
        }

        const frameWindow = printFrame.contentWindow;
        const frameDoc = frameWindow.document;
        frameDoc.open();
        frameDoc.write(html);
        frameDoc.close();

        const triggerPrint = () => {
            try {
                frameWindow.focus();
                frameWindow.print();
            } catch (e) {
                console.error('Print failed', e);
                alert('Could not open print dialog. Please allow printing for this site.');
            }
        };

        if (frameDoc.readyState === 'complete') {
            setTimeout(triggerPrint, 250);
        } else {
            printFrame.onload = function () {
                setTimeout(triggerPrint, 250);
            };
        }

        return true;
    }

    global.EventOfflinePrintRenderer = {
        buildFromPayload,
        buildFromLocalCache,
        enrichPayloadWithQr,
        openPrintWindow,
    };
})(window);
