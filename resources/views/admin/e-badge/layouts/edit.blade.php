@extends('layouts.app')

@section('title', 'Edit E-Badge Layout - ' . $categoryModel->Category)

@php
    $previewWidthPx = (int) $badgeDimensions['width_px'];
    $previewHeightPx = (int) $badgeDimensions['height_px'];
    $badgeWidthMm = (float) $badgeDimensions['width_mm'];
    $badgeHeightMm = (float) $badgeDimensions['height_mm'];
    $previewSizeLabel = $previewWidthPx . 'px × ' . $previewHeightPx . 'px (' . number_format($badgeWidthMm, 1) . 'mm × ' . number_format($badgeHeightMm, 1) . 'mm)';
    $sizeSource = $categoryModel->e_badge_background_path ? 'Uploaded background image' : 'Category print size';

    $sampleQrCode = null;
    try {
        $sampleSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->generate('PREVIEW123');
        $sampleQrCode = 'data:image/svg+xml;base64,' . base64_encode($sampleSvg);
    } catch (\Throwable $e) {
        $sampleQrCode = null;
    }
@endphp

@push('styles')
<style>
    .e-badge-editor-workspace {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        align-items: flex-start;
        margin-top: 16px;
    }

    .e-badge-preview-panel {
        flex: 1 1 420px;
        min-width: 0;
    }

    .e-badge-preview-panel .card {
        margin-bottom: 0;
    }

    .e-badge-preview-scaler {
        overflow: auto;
        max-width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: #f8fafc;
        padding: 12px;
    }

    .e-badge-preview-inner {
        position: relative;
        margin: 0 auto;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
    }

    #badgePreview {
        position: relative;
        overflow: hidden;
        background: #fff;
        border: 1px solid #94a3b8;
        border-radius: 4px;
        user-select: none;
    }

    .e-badge-element {
        position: absolute;
        box-sizing: border-box;
        padding: 0 1mm;
        line-height: 1.15;
        overflow-wrap: break-word;
        word-break: break-word;
        cursor: move;
        border: 1px dashed transparent;
        min-height: 4px;
    }

    .e-badge-element:hover {
        border-color: rgba(37, 99, 235, 0.45);
        background: rgba(59, 130, 246, 0.06);
    }

    .e-badge-element.selected {
        border-color: #2563eb;
        background: rgba(59, 130, 246, 0.12);
        z-index: 5;
    }

    .e-badge-element.is-qr {
        display: flex;
        align-items: flex-start;
        padding: 0;
    }

    .e-badge-element.is-qr.align-left { justify-content: flex-start; }
    .e-badge-element.is-qr.align-center { justify-content: center; }
    .e-badge-element.is-qr.align-right { justify-content: flex-end; }

    .e-badge-element.is-qr img,
    .e-badge-element.is-qr .qr-placeholder {
        flex-shrink: 0;
        pointer-events: none;
    }

    .e-badge-controls-panel {
        flex: 0 0 360px;
        width: 360px;
        max-width: 100%;
        position: sticky;
        top: 16px;
        align-self: flex-start;
    }

    .e-badge-controls-panel .card {
        margin-bottom: 0;
        max-height: calc(100vh - 120px);
        overflow-y: auto;
    }

    .e-badge-hint {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 10px;
        line-height: 1.5;
    }

    #elementButtons {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 12px;
    }

    #elementButtons .btn {
        padding: 6px 10px;
        font-size: 12px;
    }

    #controlsContainer label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 4px;
    }

    #controlsContainer .form-control {
        font-size: 13px;
    }

    .editor-grid-top {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px;
        align-items: start;
    }
</style>
@endpush

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">Edit E-Badge Layout: {{ $categoryModel->Category }}</h1>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div style="margin-bottom:12px;padding:10px 12px;background:#ecfdf5;color:#047857;border-radius:8px;font-size:13px;">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div style="margin-bottom:12px;padding:10px 12px;background:#fef2f2;color:#b91c1c;border-radius:8px;font-size:13px;">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div style="margin-bottom:12px;padding:10px 12px;background:#fef2f2;color:#b91c1c;border-radius:8px;font-size:13px;">
                    <ul style="margin:0;padding-left:18px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.e-badge.layouts.update', $categoryModel->Category) }}" enctype="multipart/form-data" id="layoutForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="layouts" id="layoutsInput">
                <input type="hidden" name="canvas_width_mm" id="canvasWidthMm" value="{{ $badgeWidthMm }}">
                <input type="hidden" name="canvas_height_mm" id="canvasHeightMm" value="{{ $badgeHeightMm }}">

                <div class="editor-grid-top">
                    <div class="card" style="margin-bottom:0;">
                        <h3 style="margin-bottom:10px;">Background Image</h3>
                        @php
                            $bgExt = $categoryModel->e_badge_background_path ? strtolower(pathinfo($categoryModel->e_badge_background_path, PATHINFO_EXTENSION)) : null;
                            $bgSupported =
                                !$bgExt ? true :
                                (($bgExt === 'png' && function_exists('imagecreatefrompng'))
                                || (in_array($bgExt, ['jpg', 'jpeg'], true) && function_exists('imagecreatefromjpeg'))
                                || ($bgExt === 'gif' && function_exists('imagecreatefromgif'))
                                || ($bgExt === 'webp' && function_exists('imagecreatefromwebp')));
                        @endphp
                        @if($categoryModel->e_badge_background_path && !$bgSupported)
                            <div style="margin-bottom:10px;padding:10px;border:1px solid #fecaca;background:#fff1f2;color:#9f1239;border-radius:8px;font-size:12px;">
                                Current background format <strong>.{{ $bgExt }}</strong> is not supported by this server for PDF rendering.
                                Please upload a <strong>PNG</strong> background.
                            </div>
                        @endif
                        @if($categoryModel->e_badge_background_path)
                            <div style="margin-bottom:10px;">
                                <img src="{{ \App\Support\PublicStorageUrl::make($categoryModel->e_badge_background_path) }}" alt="Background"
                                     style="max-width:100%;max-height:160px;border:1px solid #e5e7eb;border-radius:8px;">
                            </div>
                        @endif
                        <div class="form-group">
                            <label class="form-label">Upload / Replace Background</label>
                            <input type="file" name="background_image" class="form-control" accept="image/*">
                            <small style="display:block;margin-top:5px;color:#64748b;font-size:12px;">
                                Recommended: PNG at your final e-badge size. Portrait badges around <strong>1150×1700 px</strong> work well.
                                The editor scales the preview to fit your screen — actual PDF uses full image size.
                            </small>
                        </div>
                        @if($categoryModel->e_badge_background_path)
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-top:8px;">
                                <input type="checkbox" name="remove_background" value="1">
                                Remove existing background
                            </label>
                        @endif
                    </div>

                    <div class="card" style="margin-bottom:0;">
                        <h3 style="margin-bottom:10px;">Add / Remove Fields</h3>
                        <div id="field-checkboxes" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;"></div>
                    </div>
                </div>

                <div class="e-badge-editor-workspace">
                    <div class="e-badge-preview-panel">
                        <div class="card">
                            <h3 style="margin-bottom:8px;">Preview</h3>
                            <div id="preview-size-label" class="e-badge-hint">
                                Size: {{ $previewSizeLabel }} (Source: {{ $sizeSource }})
                            </div>
                            <p class="e-badge-hint">
                                <strong>Drag elements</strong> anywhere on the badge, or use Position / Width fields on the right.
                                Text alignment controls how content sits inside each element box.
                            </p>
                            <div class="e-badge-preview-scaler">
                                <div class="e-badge-preview-inner" id="previewScaler">
                                    <div id="badgePreview"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="e-badge-controls-panel">
                        <div class="card">
                            <h3 style="margin-bottom:10px;">Element Properties</h3>
                            <div id="elementButtons"></div>
                            <div id="controlsContainer" style="font-size:13px;color:#64748b;">Select an element to edit.</div>
                        </div>
                    </div>
                </div>

                <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary">Save E-Badge Layout</button>
                    <a href="{{ route('admin.e-badge.layouts.index') }}" class="btn btn-secondary">Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const mmToPx = 3.779527559;
let badgeWidthPx = {{ $previewWidthPx }};
let badgeHeightPx = {{ $previewHeightPx }};
let badgeWidthMm = {{ $badgeWidthMm }};
let badgeHeightMm = {{ $badgeHeightMm }};
let displayScale = 1;
const categoryBackgroundUrl = @json($categoryModel->e_badge_background_path ? \App\Support\PublicStorageUrl::make($categoryModel->e_badge_background_path) : null);
const sampleQrCode = @json($sampleQrCode);
const previewFontMap = {
    'Helvetica': 'Helvetica, Arial, sans-serif',
    'Times-Roman': '"Times New Roman", Times, serif',
    'Courier': '"Courier New", Courier, monospace',
};
const supportedPdfFonts = ['Helvetica', 'Times-Roman', 'Courier'];

const availableFields = [
    'Category','RegID','Name','Email','Mobile','Designation','Company','Country','State','City',
    'Additional1','Additional2','Additional3','Additional4','Additional5','QRcode',
    'Instruction1','Instruction2','Instruction3','Instruction4','Instruction5'
];

const defaultPreviewText = {
    Category: @json($categoryModel->Category),
    RegID: 'REG123',
    Name: 'John Doe',
    Email: 'john@example.com',
    Mobile: '+91XXXXXXXXXX',
    Designation: 'Manager',
    Company: 'ABC Corp',
    Country: 'India',
    State: 'Gujarat',
    City: 'Gandhinagar',
    Additional1: 'Additional 1',
    Additional2: 'Additional 2',
    Additional3: 'Additional 3',
    Additional4: 'Additional 4',
    Additional5: 'Additional 5',
    Instruction1: 'Instruction 1',
    Instruction2: 'Instruction 2',
    Instruction3: 'Instruction 3',
    Instruction4: 'Instruction 4',
    Instruction5: 'Instruction 5',
};

let elements = [];
let selectedIndex = null;
let dragState = null;

function normalizeFontFamily(font) {
    return supportedPdfFonts.includes(font) ? font : 'Helvetica';
}

function syncCanvasDimensionFields() {
    const widthInput = document.getElementById('canvasWidthMm');
    const heightInput = document.getElementById('canvasHeightMm');
    if (widthInput) widthInput.value = badgeWidthMm;
    if (heightInput) heightInput.value = badgeHeightMm;
}

function updateDisplayScale() {
    const scaler = document.getElementById('previewScaler');
    const available = Math.min(
        (scaler?.parentElement?.clientWidth || 700) - 24,
        window.innerWidth - 420
    );
    displayScale = Math.min(1, available / badgeWidthPx);
    if (displayScale < 0.15) displayScale = 0.15;

    const preview = document.getElementById('badgePreview');
    if (!preview || !scaler) return;

    preview.style.width = badgeWidthPx + 'px';
    preview.style.height = badgeHeightPx + 'px';
    preview.style.transform = 'scale(' + displayScale + ')';
    preview.style.transformOrigin = 'top left';
    scaler.style.width = Math.round(badgeWidthPx * displayScale) + 'px';
    scaler.style.height = Math.round(badgeHeightPx * displayScale) + 'px';
}

function defaultElementWidthMm() {
    return Math.round(badgeWidthMm * 0.85 * 10) / 10;
}

const layoutSettings = @json(($layoutSettings ?? collect())->values()->toArray());
if (layoutSettings.length > 0) {
    elements = layoutSettings.map((item, i) => ({
        field_name: item.field_name,
        static_text_key: item.static_text_key || null,
        static_text_value: item.static_text_value || '',
        margin_top: parseFloat(item.margin_top || 0),
        margin_left: parseFloat(item.margin_left || 0),
        margin_right: parseFloat(item.margin_right || 0),
        sequence: parseInt(item.sequence || i, 10),
        text_align: item.text_align || 'left',
        font_family: normalizeFontFamily(item.font_family || 'Helvetica'),
        font_weight: item.font_weight || 'normal',
        color: item.color || '#000000',
        font_size: item.font_size ? parseFloat(item.font_size) : 3.7,
        width: item.width ? parseFloat(item.width) : null,
        height: item.height ? parseFloat(item.height) : 20,
    }));
} else {
    const defaults = [
        { field: 'Category', top: 8, left: 10, width: 80 },
        { field: 'Name', top: 20, left: 10, width: 120 },
        { field: 'RegID', top: 32, left: 10, width: 80 },
        { field: 'QRcode', top: badgeHeightMm - 45, left: 0, right: 0, width: 30, height: 30 },
    ];
    elements = defaults.map((d, i) => ({
        field_name: d.field,
        static_text_key: null,
        static_text_value: '',
        margin_top: d.top,
        margin_left: d.left,
        margin_right: 0,
        sequence: i,
        text_align: 'center',
        font_family: 'Helvetica',
        font_weight: 'normal',
        color: '#000000',
        font_size: 3.7,
        width: d.width,
        height: d.height || 20,
    }));
}
elements.sort((a, b) => a.sequence - b.sequence);
if (elements.length > 0) selectedIndex = 0;

function getElement(fieldName) {
    return elements.find((el) => el.field_name === fieldName);
}

function elementBoxWidthMm(el) {
    if (el.width && Number(el.width) > 0) return Number(el.width);
    return defaultElementWidthMm();
}

function qrZoneWidthPx(el) {
    const leftPx = (el.margin_left || 0) * mmToPx;
    const rightPx = (el.margin_right || 0) * mmToPx;
    return Math.max(0, badgeWidthPx - leftPx - rightPx);
}

function rebuildFieldCheckboxes() {
    const wrap = document.getElementById('field-checkboxes');
    wrap.innerHTML = '';
    availableFields.forEach((field) => {
        const label = document.createElement('label');
        label.style.display = 'flex';
        label.style.alignItems = 'center';
        label.style.gap = '6px';
        label.style.fontSize = '13px';

        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.checked = !!getElement(field);
        cb.addEventListener('change', () => toggleField(field, cb.checked));

        const text = document.createElement('span');
        text.textContent = field;
        label.appendChild(cb);
        label.appendChild(text);
        wrap.appendChild(label);
    });
}

function toggleField(field, checked) {
    if (checked) {
        if (!getElement(field)) {
            elements.push({
                field_name: field,
                static_text_key: field.startsWith('Instruction') ? field : null,
                static_text_value: field.startsWith('Instruction') ? defaultPreviewText[field] : '',
                margin_top: field === 'QRcode' ? Math.max(0, badgeHeightMm - 45) : 10,
                margin_left: field === 'QRcode' ? 0 : 10,
                margin_right: 0,
                sequence: elements.length,
                text_align: field === 'QRcode' ? 'center' : 'left',
                font_family: 'Helvetica',
                font_weight: 'normal',
                color: '#000000',
                font_size: 3.7,
                width: field === 'QRcode' ? 30 : defaultElementWidthMm(),
                height: field === 'QRcode' ? 30 : 20,
            });
        }
    } else {
        elements = elements.filter((el) => el.field_name !== field);
        if (selectedIndex !== null && !elements[selectedIndex]) selectedIndex = null;
    }
    elements.sort((a, b) => a.sequence - b.sequence);
    renderPreview();
    renderButtons();
    renderControls();
}

function startDrag(e, idx) {
    e.preventDefault();
    e.stopPropagation();
    selectedIndex = idx;
    dragState = {
        idx,
        startX: e.clientX,
        startY: e.clientY,
        origLeft: elements[idx].margin_left || 0,
        origTop: elements[idx].margin_top || 0,
    };
    renderPreview();
    renderButtons();
    renderControls();
}

function onDragMove(e) {
    if (!dragState) return;
    const el = elements[dragState.idx];
    const dxMm = (e.clientX - dragState.startX) / displayScale / mmToPx;
    const dyMm = (e.clientY - dragState.startY) / displayScale / mmToPx;
    const boxW = el.field_name === 'QRcode'
        ? (qrZoneWidthPx(el) / mmToPx)
        : elementBoxWidthMm(el);
    const boxH = el.field_name === 'QRcode' ? (el.height || 20) : ((el.font_size || 3.7) * 1.5);
    el.margin_left = Math.max(0, Math.min(badgeWidthMm - Math.min(boxW, badgeWidthMm), dragState.origLeft + dxMm));
    el.margin_top = Math.max(0, Math.min(badgeHeightMm - boxH, dragState.origTop + dyMm));
    renderPreview();
}

function endDrag() {
    if (!dragState) return;
    dragState = null;
    renderControls();
}

function renderPreview() {
    const preview = document.getElementById('badgePreview');
    preview.innerHTML = '';
    preview.style.backgroundImage = categoryBackgroundUrl ? `url(${categoryBackgroundUrl})` : 'none';
    preview.style.backgroundSize = 'cover';
    preview.style.backgroundPosition = 'center';
    preview.style.backgroundRepeat = 'no-repeat';

    [...elements].sort((a, b) => a.sequence - b.sequence).forEach((el, idx) => {
        const div = document.createElement('div');
        div.className = 'e-badge-element' + (selectedIndex === idx ? ' selected' : '');
        const topPx = (el.margin_top || 0) * mmToPx;
        const leftPx = (el.margin_left || 0) * mmToPx;
        const boxWidthPx = elementBoxWidthMm(el) * mmToPx;
        div.style.top = topPx + 'px';
        div.style.left = leftPx + 'px';
        div.style.width = boxWidthPx + 'px';
        div.style.maxWidth = (badgeWidthPx - leftPx) + 'px';
        div.style.textAlign = el.text_align || 'left';
        div.style.color = el.color || '#000';
        div.style.fontFamily = previewFontMap[el.font_family] || 'Helvetica, Arial, sans-serif';
        div.style.fontWeight = el.font_weight || 'normal';

        div.addEventListener('mousedown', (e) => startDrag(e, idx));
        div.addEventListener('click', (e) => {
            e.stopPropagation();
            selectedIndex = idx;
            renderPreview();
            renderButtons();
            renderControls();
        });

        if (el.field_name === 'QRcode') {
            const qrW = (el.width || 20) * mmToPx;
            const qrH = (el.height || 20) * mmToPx;
            const zoneWidthPx = qrZoneWidthPx(el);
            div.className += ' is-qr align-' + (el.text_align || 'left');
            div.style.width = zoneWidthPx + 'px';
            div.style.maxWidth = zoneWidthPx + 'px';
            div.style.height = qrH + 'px';
            if (sampleQrCode) {
                const qr = document.createElement('img');
                qr.src = sampleQrCode;
                qr.alt = 'QR';
                qr.style.width = qrW + 'px';
                qr.style.height = qrH + 'px';
                qr.style.objectFit = 'contain';
                div.appendChild(qr);
            } else {
                const qr = document.createElement('div');
                qr.className = 'qr-placeholder';
                qr.style.width = qrW + 'px';
                qr.style.height = qrH + 'px';
                qr.style.border = '1px dashed #334155';
                qr.style.display = 'flex';
                qr.style.alignItems = 'center';
                qr.style.justifyContent = 'center';
                qr.style.fontSize = '10px';
                qr.textContent = 'QR';
                div.appendChild(qr);
            }
        } else {
            div.style.fontSize = ((el.font_size || 3.7) * mmToPx) + 'px';
            const previewValue = el.field_name.startsWith('Instruction')
                ? (el.static_text_value || defaultPreviewText[el.field_name] || el.field_name)
                : (defaultPreviewText[el.field_name] || el.field_name);
            div.textContent = String(previewValue).toLocaleUpperCase();
        }
        preview.appendChild(div);
    });

    updateDisplayScale();
}

function renderButtons() {
    const wrap = document.getElementById('elementButtons');
    wrap.innerHTML = '';
    [...elements].sort((a, b) => a.sequence - b.sequence).forEach((el, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = selectedIndex === idx ? 'btn btn-primary' : 'btn btn-secondary';
        btn.textContent = el.field_name;
        btn.addEventListener('click', () => {
            selectedIndex = idx;
            renderPreview();
            renderButtons();
            renderControls();
        });
        wrap.appendChild(btn);
    });
}

function renderControls() {
    const wrap = document.getElementById('controlsContainer');
    if (selectedIndex === null || !elements[selectedIndex]) {
        wrap.innerHTML = 'Select an element to edit.';
        return;
    }

    const el = elements[selectedIndex];
    const isQR = el.field_name === 'QRcode';
    const isInstruction = el.field_name.startsWith('Instruction');
    const maxWidth = Math.round(badgeWidthMm * 10) / 10;
    const maxHeight = Math.round(badgeHeightMm * 10) / 10;
    const currentWidth = el.width ?? defaultElementWidthMm();

    wrap.innerHTML = `
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
            <div style="grid-column:1/-1;"><label>Field</label><input class="form-control" disabled value="${el.field_name}"></div>
            <div><label>Layer (sequence)</label><input id="ctrl-seq" class="form-control" type="number" min="0" value="${el.sequence ?? 0}"></div>
            <div><label>${isQR ? 'QR horizontal align' : 'Text align (inside box)'}</label>
                <select id="ctrl-align" class="form-control">
                    <option value="left" ${el.text_align === 'left' ? 'selected' : ''}>Left</option>
                    <option value="center" ${el.text_align === 'center' ? 'selected' : ''}>Center</option>
                    <option value="right" ${el.text_align === 'right' ? 'selected' : ''}>Right</option>
                </select>
            </div>
            <div><label>Position top (mm)</label><input id="ctrl-margin" class="form-control" type="number" min="0" max="${maxHeight}" step="0.1" value="${el.margin_top ?? 0}"></div>
            <div><label>Zone inset left (mm)</label><input id="ctrl-margin-left" class="form-control" type="number" min="0" max="${maxWidth}" step="0.1" value="${el.margin_left ?? 0}"></div>
            ${isQR ? `<div><label>Zone inset right (mm)</label><input id="ctrl-margin-right" class="form-control" type="number" min="0" max="${maxWidth}" step="0.1" value="${el.margin_right ?? 0}"><small style="display:block;color:#64748b;font-size:11px;margin-top:4px;">QR aligns within the zone between left and right insets. Use 0 on both sides for full badge width.</small></div>` : ''}
            ${!isQR ? `<div style="grid-column:1/-1;"><label>Element width (mm) — max ${maxWidth}</label><input id="ctrl-width" class="form-control" type="number" min="5" max="${maxWidth}" step="0.1" value="${Number(currentWidth).toFixed(1)}"></div>` : ''}
            ${!isQR ? `<div><label>Font size (mm)</label><input id="ctrl-fontsize" class="form-control" type="number" min="1" max="50" step="0.1" value="${el.font_size ?? 3.7}"></div>` : ''}
            ${!isQR ? `<div><label>Font family</label>
                <select id="ctrl-family" class="form-control">
                    ${supportedPdfFonts.map((font) => `<option value="${font}" ${el.font_family === font ? 'selected' : ''}>${font}</option>`).join('')}
                </select></div>` : ''}
            ${!isQR ? `<div><label>Font weight</label>
                <select id="ctrl-weight" class="form-control">
                    <option value="normal" ${el.font_weight === 'normal' ? 'selected' : ''}>Normal</option>
                    <option value="bold" ${el.font_weight === 'bold' ? 'selected' : ''}>Bold</option>
                </select></div>` : ''}
            ${!isQR ? `<div><label>Color</label><input id="ctrl-color" class="form-control" type="color" value="${el.color || '#000000'}"></div>` : ''}
            ${isQR ? `<div><label>QR width (mm)</label><input id="ctrl-width" class="form-control" type="number" min="5" max="${maxWidth}" step="0.1" value="${el.width ?? 20}"></div>` : ''}
            ${isQR ? `<div><label>QR height (mm)</label><input id="ctrl-height" class="form-control" type="number" min="5" max="${maxHeight}" step="0.1" value="${el.height ?? 20}"></div>` : ''}
            ${isInstruction ? `<div style="grid-column:1/-1;"><label>Static text</label><input id="ctrl-static" class="form-control" value="${(el.static_text_value || '').replace(/"/g,'&quot;')}"></div>` : ''}
        </div>
    `;

    const bind = (id, fn, ev = 'input') => {
        const node = document.getElementById(id);
        if (!node) return;
        node.addEventListener(ev, () => {
            fn(node.value);
            renderPreview();
            renderButtons();
        });
    };

    bind('ctrl-seq', (v) => {
        el.sequence = parseInt(v || '0', 10);
        elements.sort((a, b) => a.sequence - b.sequence);
        selectedIndex = elements.findIndex((x) => x.field_name === el.field_name);
    });
    bind('ctrl-margin', (v) => { el.margin_top = parseFloat(v || '0'); });
    bind('ctrl-margin-left', (v) => { el.margin_left = parseFloat(v || '0'); });
    bind('ctrl-margin-right', (v) => { el.margin_right = parseFloat(v || '0'); });
    bind('ctrl-align', (v) => { el.text_align = v; }, 'change');
    bind('ctrl-family', (v) => { el.font_family = normalizeFontFamily(v); }, 'change');
    bind('ctrl-weight', (v) => { el.font_weight = v; }, 'change');
    bind('ctrl-color', (v) => { el.color = v; }, 'input');
    bind('ctrl-fontsize', (v) => { el.font_size = parseFloat(v || '3.7'); });
    bind('ctrl-width', (v) => { el.width = parseFloat(v || '20'); });
    bind('ctrl-height', (v) => { el.height = parseFloat(v || '20'); });
    bind('ctrl-static', (v) => { el.static_text_value = v; });
}

document.getElementById('layoutForm').addEventListener('submit', () => {
    syncCanvasDimensionFields();
    const payload = [...elements].sort((a, b) => a.sequence - b.sequence).map((el, idx) => ({
        field_name: el.field_name,
        static_text_key: el.field_name.startsWith('Instruction') ? el.field_name : null,
        static_text_value: el.field_name.startsWith('Instruction') ? (el.static_text_value || '') : null,
        margin_top: parseFloat(el.margin_top || 0),
        margin_left: parseFloat(el.margin_left || 0),
        margin_right: parseFloat(el.margin_right || 0),
        sequence: parseInt((el.sequence ?? idx), 10),
        text_align: el.text_align || 'left',
        font_family: normalizeFontFamily(el.font_family || 'Helvetica'),
        font_weight: el.font_weight || 'normal',
        color: el.color || '#000000',
        font_size: el.field_name === 'QRcode' ? null : parseFloat(el.font_size || 3.7),
        width: el.width ? parseFloat(el.width) : null,
        height: el.field_name === 'QRcode' ? parseFloat(el.height || 20) : null,
    }));
    document.getElementById('layoutsInput').value = JSON.stringify(payload);
});

const backgroundInput = document.querySelector('input[name="background_image"]');
const previewSizeLabelEl = document.getElementById('preview-size-label');
if (backgroundInput) {
    backgroundInput.addEventListener('change', function () {
        const file = backgroundInput.files && backgroundInput.files[0] ? backgroundInput.files[0] : null;
        if (!file) return;
        const objectUrl = URL.createObjectURL(file);
        const img = new Image();
        img.onload = function () {
            badgeWidthPx = img.width;
            badgeHeightPx = img.height;
            badgeWidthMm = Math.round(badgeWidthPx * 25.4 / 96 * 10) / 10;
            badgeHeightMm = Math.round(badgeHeightPx * 25.4 / 96 * 10) / 10;
            syncCanvasDimensionFields();
            if (previewSizeLabelEl) {
                previewSizeLabelEl.textContent = 'Size: ' + img.width + 'px × ' + img.height + 'px (' + badgeWidthMm + 'mm × ' + badgeHeightMm + 'mm) (Source: selected upload)';
            }
            renderPreview();
            renderControls();
            URL.revokeObjectURL(objectUrl);
        };
        img.src = objectUrl;
    });
}

document.addEventListener('mousemove', onDragMove);
document.addEventListener('mouseup', endDrag);
window.addEventListener('resize', () => { updateDisplayScale(); });

syncCanvasDimensionFields();
rebuildFieldCheckboxes();
renderPreview();
renderButtons();
renderControls();
</script>
@endpush
