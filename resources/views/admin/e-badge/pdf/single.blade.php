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
            font-family: 'Comfortaa', sans-serif;
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
            width: 100%;
            height: 100%;
        }
        .item {
            width: 100%;
            max-width: 100%;
            padding: 0 1mm;
            word-break: break-word;
            overflow-wrap: break-word;
            line-height: 1;
        }
    </style>
</head>
<body>
@php
    $mmToPx = 3.779527559;
    $sorted = $layoutSettings->sortBy('sequence')->values();
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
            width="{{ $pageWidthPx }}"
            height="{{ $pageHeightPx }}"
            style="position:absolute;top:0;left:0;z-index:0;display:block;width:{{ $pageWidthMm }}mm;height:{{ $pageHeightMm }}mm;"
        >
    @endif
    <div class="content">
        @foreach($sorted as $layout)
            @php
                $field = $layout->field_name;
                $marginTopPx = ((float) ($layout->margin_top ?? 0)) * $mmToPx;
                $marginLeftPx = ((float) ($layout->margin_left ?? 0)) * $mmToPx;
                $marginRightPx = ((float) ($layout->margin_right ?? 0)) * $mmToPx;
                $align = $layout->text_align ?? 'left';
                $fontFamily = $layout->font_family ?? 'Helvetica';
                $supportedFontFamilies = ['Helvetica', 'Times-Roman', 'Courier'];
                if (!in_array($fontFamily, $supportedFontFamilies, true)) {
                    $fontFamily = 'Helvetica';
                }
                $fontWeight = $layout->font_weight ?? 'normal';
                $color = $layout->color ?? '#000000';
                $fontSizePx = ((float) ($layout->font_size ?? 3.7)) * $mmToPx;
                $elementWidthMm = (float) ($layout->width ?? 0);
                $hasCustomWidth = $elementWidthMm > 0;
                $elementWidthPx = $hasCustomWidth ? ($elementWidthMm * $mmToPx) : null;

                $value = '';
                if ($field === 'QRcode') {
                    $value = '';
                } elseif ($field === 'Category') {
                    $value = $userDetail->Category ?? '';
                } elseif (str_starts_with($field, 'Instruction')) {
                    $value = $layout->static_text_value ?? '';
                } else {
                    $value = $userDetail->{$field} ?? '';
                }
            @endphp

            @if($field === 'QRcode')
                @php
                    $qrWidthPx = ((float) ($layout->width ?? 20)) * $mmToPx;
                    $qrHeightPx = ((float) ($layout->height ?? 20)) * $mmToPx;
                @endphp
                @if($qrCode)
                    <div class="item" style="margin-top: {{ $marginTopPx }}px; margin-left: {{ $marginLeftPx }}px; margin-right: {{ $marginRightPx }}px; text-align: {{ $align }};">
                        <img
                            src="{{ $qrCode }}"
                            alt="QR"
                            width="{{ round($qrWidthPx) }}"
                            height="{{ round($qrHeightPx) }}"
                            style="width: {{ $qrWidthPx }}px; height: {{ $qrHeightPx }}px; object-fit: contain; display: inline-block;"
                        >
                    </div>
                @endif
            @elseif($value !== '')
                <div class="item"
                     style="
                        margin-top: {{ $marginTopPx }}px;
                        margin-left: {{ $marginLeftPx }}px;
                        margin-right: {{ $marginRightPx }}px;
                        text-align: {{ $align }};
                        font-family: '{{ $fontFamily }}', sans-serif;
                        font-weight: {{ $fontWeight }};
                        color: {{ $color }};
                        font-size: {{ $fontSizePx }}px;
                        line-height: 1;
                        @if($hasCustomWidth)
                        width: {{ $elementWidthPx }}px;
                        max-width: {{ $pageWidthPx }}px;
                        @endif
                     ">
                    {{ $value }}
                </div>
            @endif
        @endforeach
    </div>
</div>
</body>
</html>
