<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: {{ $pageWidthMm }}mm {{ $pageHeightMm }}mm;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            margin: 0;
            padding: 0;
            width: {{ $pageWidthMm }}mm;
            height: {{ $pageHeightMm }}mm;
            overflow: hidden;
        }
        .badge {
            width: {{ $pageWidthMm }}mm;
            height: {{ $pageHeightMm }}mm;
            position: relative;
            overflow: hidden;
            page-break-inside: avoid;
            page-break-after: avoid;
            break-inside: avoid;
        }
        .content {
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
            width: {{ $pageWidthMm }}mm;
            height: {{ $pageHeightMm }}mm;
        }
        .item {
            position: absolute;
            padding: 0 1mm;
            word-break: break-word;
            overflow-wrap: break-word;
            line-height: 1.15;
            box-sizing: border-box;
        }
        .item-qr {
            padding: 0;
            display: block;
        }
        .item-qr-table {
            width: 100%;
            border-collapse: collapse;
        }
        .item-qr-table td {
            padding: 0;
            vertical-align: top;
        }
    </style>
</head>
<body>
@php
    $bgPath = $category->e_badge_background_path ? storage_path('app/public/' . $category->e_badge_background_path) : null;
    $bgDataUri = null;
    if ($bgPath && file_exists($bgPath)) {
        $ext = strtolower((string) pathinfo($bgPath, PATHINFO_EXTENSION));
        $isRenderable = true;
        if (in_array($ext, ['jpg', 'jpeg'], true) && !function_exists('imagecreatefromjpeg')) {
            $isRenderable = false;
        }
        if ($ext === 'png' && !function_exists('imagecreatefrompng')) {
            $isRenderable = false;
        }
        if ($ext === 'gif' && !function_exists('imagecreatefromgif')) {
            $isRenderable = false;
        }
        if ($ext === 'webp' && !function_exists('imagecreatefromwebp')) {
            $isRenderable = false;
        }

        if ($isRenderable) {
            $mime = null;
            $imgInfo = @getimagesize($bgPath);
            if ($imgInfo && !empty($imgInfo['mime'])) {
                $mime = $imgInfo['mime'];
            }
            if (!$mime) {
                $mime = match ($ext) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    default => null,
                };
            }

            $raw = @file_get_contents($bgPath);
            if ($raw !== false && $mime) {
                $bgDataUri = 'data:' . $mime . ';base64,' . base64_encode($raw);
            }
        }
    }
@endphp
<div class="badge">
    @if($bgDataUri)
        <img
            src="{{ $bgDataUri }}"
            alt="Background"
            style="position:absolute;top:0;left:0;z-index:0;display:block;width:{{ $pageWidthMm }}mm;height:{{ $pageHeightMm }}mm;"
        >
    @endif
    <div class="content">
        @foreach($renderedElements as $element)
            @if(($element['type'] ?? '') === 'qr' && $qrCode)
                @php
                    $align = $element['text_align'] ?? 'left';
                    $qrCellAlign = match ($align) {
                        'center' => 'center',
                        'right' => 'right',
                        default => 'left',
                    };
                @endphp
                <div class="item item-qr"
                     style="top: {{ $element['top_mm'] }}mm; left: {{ $element['left_mm'] }}mm; width: {{ $element['zone_width_mm'] }}mm; height: {{ $element['qr_height_mm'] }}mm;">
                    <table class="item-qr-table" style="width: {{ $element['zone_width_mm'] }}mm;">
                        <tr>
                            <td align="{{ $qrCellAlign }}">
                                <img
                                    src="{{ $qrCode }}"
                                    alt="QR"
                                    style="width: {{ $element['qr_width_mm'] }}mm; height: {{ $element['qr_height_mm'] }}mm; display: inline-block;"
                                >
                            </td>
                        </tr>
                    </table>
                </div>
            @elseif(($element['type'] ?? '') === 'text')
                <div class="item"
                     style="
                        top: {{ $element['top_mm'] }}mm;
                        left: {{ $element['left_mm'] }}mm;
                        width: {{ $element['width_mm'] }}mm;
                        text-align: {{ $element['text_align'] ?? 'left' }};
                        font-family: '{{ $element['font_family'] ?? 'Helvetica' }}', sans-serif;
                        font-weight: {{ $element['font_weight'] ?? 'normal' }};
                        color: {{ $element['color'] ?? '#000000' }};
                        font-size: {{ $element['font_size_mm'] }}mm;
                     ">
                    {{ $element['value'] }}
                </div>
            @endif
        @endforeach
    </div>
</div>
</body>
</html>
