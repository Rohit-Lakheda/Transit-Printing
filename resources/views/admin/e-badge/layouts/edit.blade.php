@extends('layouts.app')

@section('title', 'Edit E-Badge Layout - ' . $categoryModel->Category)

@php
    use Illuminate\Support\Facades\Storage;
    $mmToPx = 3.779527559;
    $fallbackWidthPx = (float) $categoryModel->badge_width * $mmToPx;
    $fallbackHeightPx = (float) $categoryModel->badge_height * $mmToPx;
    $previewWidthPx = $fallbackWidthPx;
    $previewHeightPx = $fallbackHeightPx;
    $previewSizeLabel = number_format((float) $categoryModel->badge_width, 2) . 'mm × ' . number_format((float) $categoryModel->badge_height, 2) . 'mm (fallback)';
    $sizeSource = 'Category print size';

    if ($categoryModel->e_badge_background_path) {
        $bgStoragePath = storage_path('app/public/' . $categoryModel->e_badge_background_path);
        $bgSize = is_file($bgStoragePath) ? @getimagesize($bgStoragePath) : false;
        if ($bgSize && !empty($bgSize[0]) && !empty($bgSize[1])) {
            $previewWidthPx = (float) $bgSize[0];
            $previewHeightPx = (float) $bgSize[1];
            $previewSizeLabel = $bgSize[0] . 'px × ' . $bgSize[1] . 'px';
            $sizeSource = 'Uploaded background image';
        }
    }

    $sampleQrCode = null;
    try {
        $sampleSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->generate('PREVIEW123');
        $sampleQrCode = 'data:image/svg+xml;base64,' . base64_encode($sampleSvg);
    } catch (\Throwable $e) {
        $sampleQrCode = null;
    }
@endphp

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">Edit E-Badge Layout: {{ $categoryModel->Category }}</h1>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.e-badge.layouts.update', $categoryModel->Category) }}" enctype="multipart/form-data" id="layoutForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="layouts" id="layoutsInput">

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;align-items:start;">
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
                                     style="max-width:100%;max-height:180px;border:1px solid #e5e7eb;border-radius:8px;">
                            </div>
                        @endif
                        <div class="form-group">
                            <label class="form-label">Upload / Replace Background</label>
                            <input type="file" name="background_image" class="form-control" accept="image/*">
                            <small style="display:block;margin-top:5px;color:#64748b;font-size:12px;">
                                Recommended: PNG. On this server, JPEG may not render in PDF if JPEG GD support is unavailable.
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
                        <h3 style="margin-bottom:10px;">Add/Remove Fields</h3>
                        <div id="field-checkboxes" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;"></div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:minmax(320px,{{ $previewWidthPx + 40 }}px) 1fr;gap:16px;margin-top:16px;">
                    <div class="card" style="margin-bottom:0;">
                        <h3 style="margin-bottom:8px;">Preview</h3>
                        <div id="preview-size-label" style="font-size:12px;color:#6b7280;margin-bottom:10px;">
                            Size: {{ $previewSizeLabel }} (Source: {{ $sizeSource }})
                        </div>
                        <div id="badgePreview"
                             style="width:{{ $previewWidthPx }}px;height:{{ $previewHeightPx }}px;border:1px solid #94a3b8;border-radius:8px;overflow:hidden;background:#fff;display:flex;flex-direction:column;padding:0;"></div>
                    </div>

                    <div class="card" style="margin-bottom:0;">
                        <h3 style="margin-bottom:10px;">Element Properties</h3>
                        <div id="elementButtons" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;"></div>
                        <div id="controlsContainer" style="font-size:13px;color:#64748b;">Select an element to edit.</div>
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

function normalizeFontFamily(font) {
    return supportedPdfFonts.includes(font) ? font : 'Helvetica';
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
    elements = ['Category','RegID','Name','QRcode'].map((field, i) => ({
        field_name: field,
        static_text_key: field.startsWith('Instruction') ? field : null,
        static_text_value: field.startsWith('Instruction') ? defaultPreviewText[field] : '',
        margin_top: i === 0 ? 0 : 2,
        margin_left: 0,
        margin_right: 0,
        sequence: i,
        text_align: 'left',
        font_family: 'Helvetica',
        font_weight: 'normal',
        color: '#000000',
        font_size: 3.7,
        width: null,
        height: 20,
    }));
}
elements.sort((a, b) => a.sequence - b.sequence);
if (elements.length > 0) {
    selectedIndex = 0;
}

function getElement(fieldName) {
    return elements.find((el) => el.field_name === fieldName);
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
                margin_top: 2,
                margin_left: 0,
                margin_right: 0,
                sequence: elements.length,
                text_align: 'left',
                font_family: 'Helvetica',
                font_weight: 'normal',
                color: '#000000',
                font_size: 3.7,
                width: null,
                height: 20,
            });
        }
    } else {
        elements = elements.filter((el) => el.field_name !== field);
        if (selectedIndex !== null && !elements[selectedIndex]) {
            selectedIndex = null;
        }
    }
    elements.sort((a, b) => a.sequence - b.sequence);
    renderPreview();
    renderButtons();
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
        div.style.marginTop = `${(el.margin_top || 0) * mmToPx}px`;
        div.style.marginLeft = `${(el.margin_left || 0) * mmToPx}px`;
        div.style.marginRight = `${(el.margin_right || 0) * mmToPx}px`;
        div.style.textAlign = el.text_align || 'left';
        div.style.color = el.color || '#000';
        div.style.fontFamily = previewFontMap[el.font_family] || (el.font_family || 'Helvetica, Arial, sans-serif');
        div.style.fontWeight = el.font_weight || 'normal';
        div.style.lineHeight = '1';
        const elementWidthPx = (el.width && Number(el.width) > 0)
            ? (Number(el.width) * mmToPx)
            : badgeWidthPx;
        div.style.width = `${elementWidthPx}px`;
        div.style.maxWidth = `${badgeWidthPx}px`;
        div.style.boxSizing = 'border-box';
        div.style.padding = '0 1mm';
        div.style.overflowWrap = 'break-word';
        div.style.wordBreak = 'break-word';
        div.style.fontSize = `${(el.font_size || 3.7) * mmToPx}px`;
        div.style.border = selectedIndex === idx ? '1px dashed #2563eb' : '1px dashed transparent';
        div.style.cursor = 'pointer';
        div.addEventListener('click', () => {
            selectedIndex = idx;
            renderPreview();
            renderButtons();
            renderControls();
        });

        if (el.field_name === 'QRcode') {
            if (sampleQrCode) {
                const qr = document.createElement('img');
                qr.src = sampleQrCode;
                qr.alt = 'QR';
                qr.style.width = `${(el.width || 20) * mmToPx}px`;
                qr.style.height = `${(el.height || 20) * mmToPx}px`;
                qr.style.objectFit = 'contain';
                qr.style.display = 'inline-block';
                div.appendChild(qr);
            } else {
                const qr = document.createElement('div');
                qr.style.width = `${(el.width || 20) * mmToPx}px`;
                qr.style.height = `${(el.height || 20) * mmToPx}px`;
                qr.style.border = '1px dashed #334155';
                qr.style.display = 'inline-flex';
                qr.style.alignItems = 'center';
                qr.style.justifyContent = 'center';
                qr.style.fontSize = '10px';
                qr.textContent = 'QR';
                div.appendChild(qr);
            }
        } else {
            div.textContent = el.field_name.startsWith('Instruction')
                ? (el.static_text_value || defaultPreviewText[el.field_name] || el.field_name)
                : (defaultPreviewText[el.field_name] || el.field_name);
        }
        preview.appendChild(div);
    });
}

function renderButtons() {
    const wrap = document.getElementById('elementButtons');
    wrap.innerHTML = '';
    [...elements].sort((a, b) => a.sequence - b.sequence).forEach((el, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = selectedIndex === idx ? 'btn btn-primary' : 'btn btn-secondary';
        btn.style.padding = '6px 10px';
        btn.style.fontSize = '12px';
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

    wrap.innerHTML = `
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;">
            <div><label>Field</label><input class="form-control" disabled value="${el.field_name}"></div>
            <div><label>Sequence</label><input id="ctrl-seq" class="form-control" type="number" min="0" value="${el.sequence ?? 0}"></div>
            <div><label>Margin Top (mm)</label><input id="ctrl-margin" class="form-control" type="number" min="0" step="0.1" value="${el.margin_top ?? 0}"></div>
            <div><label>Margin Left (mm)</label><input id="ctrl-margin-left" class="form-control" type="number" min="0" step="0.1" value="${el.margin_left ?? 0}"></div>
            <div><label>Margin Right (mm)</label><input id="ctrl-margin-right" class="form-control" type="number" min="0" step="0.1" value="${el.margin_right ?? 0}"></div>
            <div><label>Align</label>
                <select id="ctrl-align" class="form-control">
                    <option value="left" ${el.text_align === 'left' ? 'selected' : ''}>Left</option>
                    <option value="center" ${el.text_align === 'center' ? 'selected' : ''}>Center</option>
                    <option value="right" ${el.text_align === 'right' ? 'selected' : ''}>Right</option>
                </select>
            </div>
            <div><label>Font Family</label>
                <select id="ctrl-family" class="form-control">
                    ${supportedPdfFonts.map((font) => `<option value="${font}" ${el.font_family === font ? 'selected' : ''}>${font}</option>`).join('')}
                </select>
            </div>
            <div><label>Font Weight</label>
                <select id="ctrl-weight" class="form-control">
                    <option value="normal" ${el.font_weight === 'normal' ? 'selected' : ''}>Normal</option>
                    <option value="bold" ${el.font_weight === 'bold' ? 'selected' : ''}>Bold</option>
                </select>
            </div>
            <div><label>Color</label><input id="ctrl-color" class="form-control" type="color" value="${el.color || '#000000'}"></div>
            ${!isQR ? `<div><label>Font Size (mm)</label><input id="ctrl-fontsize" class="form-control" type="number" min="1" max="50" step="0.1" value="${el.font_size ?? 3.7}"></div>` : ''}
            ${!isQR ? `<div><label>Element Width (mm)</label><input id="ctrl-width" class="form-control" type="number" min="5" max="200" step="0.1" value="${el.width ?? (badgeWidthPx / mmToPx).toFixed(1)}"></div>` : ''}
            ${isQR ? `<div><label>QR Width (mm)</label><input id="ctrl-width" class="form-control" type="number" min="5" max="100" step="0.1" value="${el.width ?? 20}"></div>` : ''}
            ${isQR ? `<div><label>QR Height (mm)</label><input id="ctrl-height" class="form-control" type="number" min="5" max="100" step="0.1" value="${el.height ?? 20}"></div>` : ''}
            ${isInstruction ? `<div style="grid-column:1/-1;"><label>Static Text</label><input id="ctrl-static" class="form-control" value="${(el.static_text_value || '').replace(/"/g,'&quot;')}"></div>` : ''}
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

    bind('ctrl-seq', (v) => { el.sequence = parseInt(v || '0', 10); elements.sort((a,b)=>a.sequence-b.sequence); selectedIndex = elements.findIndex((x)=>x.field_name===el.field_name); }, 'input');
    bind('ctrl-margin', (v) => { el.margin_top = parseFloat(v || '0'); }, 'input');
    bind('ctrl-margin-left', (v) => { el.margin_left = parseFloat(v || '0'); }, 'input');
    bind('ctrl-margin-right', (v) => { el.margin_right = parseFloat(v || '0'); }, 'input');
    bind('ctrl-align', (v) => { el.text_align = v; }, 'change');
    bind('ctrl-family', (v) => { el.font_family = normalizeFontFamily(v); }, 'change');
    bind('ctrl-weight', (v) => { el.font_weight = v; }, 'change');
    bind('ctrl-color', (v) => { el.color = v; }, 'input');
    bind('ctrl-fontsize', (v) => { el.font_size = parseFloat(v || '3.7'); }, 'input');
    bind('ctrl-width', (v) => { el.width = parseFloat(v || '20'); }, 'input');
    bind('ctrl-height', (v) => { el.height = parseFloat(v || '20'); }, 'input');
    bind('ctrl-static', (v) => { el.static_text_value = v; }, 'input');
}

document.getElementById('layoutForm').addEventListener('submit', (e) => {
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
            const preview = document.getElementById('badgePreview');
            if (preview) {
                preview.style.width = badgeWidthPx + 'px';
                preview.style.height = badgeHeightPx + 'px';
            }
            if (previewSizeLabelEl) {
                previewSizeLabelEl.textContent = 'Size: ' + img.width + 'px × ' + img.height + 'px (Source: selected upload)';
            }
            renderPreview();
            URL.revokeObjectURL(objectUrl);
        };
        img.src = objectUrl;
    });
}

rebuildFieldCheckboxes();
renderPreview();
renderButtons();
renderControls();
</script>
@endpush
