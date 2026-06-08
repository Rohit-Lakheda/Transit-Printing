<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\EBadgeLayoutSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EBadgeLayoutController extends Controller
{
    /**
     * @var array<int,string>
     */
    protected array $supportedPdfFonts = [
        'Helvetica',
        'Times-Roman',
        'Courier',
    ];

    protected function storeBackgroundAsPng(Request $request): string
    {
        $file = $request->file('background_image');
        if (!$file) {
            throw new \RuntimeException('Background file is missing.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());

        // PNG is safest with current DomPDF/GD setup.
        if ($extension === 'png') {
            return $file->store('e-badge-backgrounds', 'public');
        }

        // Build image resource from supported formats.
        $imageResource = null;
        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            if (!function_exists('imagecreatefromjpeg')) {
                throw new \RuntimeException('JPEG backgrounds are not supported on this server right now. Please upload PNG.');
            }
            $imageResource = @imagecreatefromjpeg($file->getRealPath());
        } elseif ($extension === 'gif') {
            if (!function_exists('imagecreatefromgif')) {
                throw new \RuntimeException('GIF backgrounds are not supported on this server right now. Please upload PNG.');
            }
            $imageResource = @imagecreatefromgif($file->getRealPath());
        } elseif ($extension === 'webp') {
            if (!function_exists('imagecreatefromwebp')) {
                throw new \RuntimeException('WEBP backgrounds are not supported on this server right now. Please upload PNG.');
            }
            $imageResource = @imagecreatefromwebp($file->getRealPath());
        }

        if (!$imageResource) {
            throw new \RuntimeException('Unable to process this background image. Please upload PNG.');
        }

        $dir = storage_path('app/public/e-badge-backgrounds');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $filename = Str::random(40) . '.png';
        $targetPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!imagepng($imageResource, $targetPath)) {
            imagedestroy($imageResource);
            throw new \RuntimeException('Failed to save PNG background. Please try again.');
        }
        imagedestroy($imageResource);

        return 'e-badge-backgrounds/' . $filename;
    }

    public function index()
    {
        $categories = Category::orderBy('Category')->get();
        return view('admin.e-badge.layouts.index', compact('categories'));
    }

    /**
     * @return array{width_px:int,height_px:int,width_mm:float,height_mm:float}
     */
    protected function resolveBadgeDimensions(Category $category): array
    {
        $fallbackWidthMm = (float) $category->badge_width;
        $fallbackHeightMm = (float) $category->badge_height;
        $fallbackWidthPx = (int) round($fallbackWidthMm * 3.779527559);
        $fallbackHeightPx = (int) round($fallbackHeightMm * 3.779527559);

        if (!$category->e_badge_background_path) {
            return [
                'width_px' => $fallbackWidthPx,
                'height_px' => $fallbackHeightPx,
                'width_mm' => $fallbackWidthMm,
                'height_mm' => $fallbackHeightMm,
            ];
        }

        $bgPath = storage_path('app/public/' . $category->e_badge_background_path);
        $bgSize = is_file($bgPath) ? @getimagesize($bgPath) : false;
        if (!$bgSize || empty($bgSize[0]) || empty($bgSize[1])) {
            return [
                'width_px' => $fallbackWidthPx,
                'height_px' => $fallbackHeightPx,
                'width_mm' => $fallbackWidthMm,
                'height_mm' => $fallbackHeightMm,
            ];
        }

        $widthPx = (int) $bgSize[0];
        $heightPx = (int) $bgSize[1];

        return [
            'width_px' => $widthPx,
            'height_px' => $heightPx,
            'width_mm' => round($widthPx * 25.4 / 96, 2),
            'height_mm' => round($heightPx * 25.4 / 96, 2),
        ];
    }

    public function edit(string $category)
    {
        $categoryModel = Category::where('Category', $category)->firstOrFail();
        $layoutSettings = EBadgeLayoutSetting::where('Category', $categoryModel->Category)
            ->orderBy('sequence')
            ->get()
            ->keyBy('field_name');

        $visibleFields = $layoutSettings->keys()->values()->all();
        if (empty($visibleFields)) {
            $visibleFields = ['Category', 'RegID', 'Name', 'QRcode'];
        }

        $badgeDimensions = $this->resolveBadgeDimensions($categoryModel);

        return view('admin.e-badge.layouts.edit', compact('categoryModel', 'layoutSettings', 'visibleFields', 'badgeDimensions'));
    }

    public function update(Request $request, string $category)
    {
        $categoryModel = Category::where('Category', $category)->firstOrFail();

        $layoutsInput = $request->input('layouts');
        if (is_string($layoutsInput)) {
            $layoutsInput = json_decode($layoutsInput, true);
        }
        if (!is_array($layoutsInput)) {
            return redirect()->back()->withInput()->with('error', 'Invalid layout data format.');
        }

        $request->merge(['layouts' => $layoutsInput]);

        $badgeDimensions = $this->resolveBadgeDimensions($categoryModel);
        $maxElementWidth = max(50, (int) ceil($badgeDimensions['width_mm']));
        $maxElementHeight = max(20, (int) ceil($badgeDimensions['height_mm']));

        $validated = $request->validate([
            'layouts' => 'required|array',
            'layouts.*.field_name' => 'required|string',
            'layouts.*.static_text_key' => 'nullable|string',
            'layouts.*.static_text_value' => 'nullable|string',
            'layouts.*.margin_top' => 'nullable|numeric|min:0|max:' . $maxElementHeight,
            'layouts.*.margin_left' => 'nullable|numeric|min:0|max:' . $maxElementWidth,
            'layouts.*.margin_right' => 'nullable|numeric|min:0|max:' . $maxElementWidth,
            'layouts.*.sequence' => 'required|integer|min:0',
            'layouts.*.text_align' => 'required|string|in:left,center,right',
            'layouts.*.font_family' => 'required|string|in:Helvetica,Times-Roman,Courier',
            'layouts.*.font_weight' => 'required|string|in:normal,bold',
            'layouts.*.color' => 'required|string',
            'layouts.*.font_size' => 'nullable|numeric|min:1|max:50',
            'layouts.*.width' => 'nullable|numeric|min:5|max:' . $maxElementWidth,
            'layouts.*.height' => 'nullable|numeric|min:5|max:' . $maxElementHeight,
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'remove_background' => 'nullable|boolean',
        ]);

        if ($request->boolean('remove_background')) {
            if ($categoryModel->e_badge_background_path && Storage::disk('public')->exists($categoryModel->e_badge_background_path)) {
                Storage::disk('public')->delete($categoryModel->e_badge_background_path);
            }
            $categoryModel->e_badge_background_path = null;
            $categoryModel->save();
        }

        if ($request->hasFile('background_image')) {
            if ($categoryModel->e_badge_background_path && Storage::disk('public')->exists($categoryModel->e_badge_background_path)) {
                Storage::disk('public')->delete($categoryModel->e_badge_background_path);
            }
            try {
                $path = $this->storeBackgroundAsPng($request);
                $categoryModel->e_badge_background_path = $path;
                $categoryModel->save();
            } catch (\RuntimeException $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        EBadgeLayoutSetting::where('Category', $categoryModel->Category)->delete();
        foreach ($validated['layouts'] as $layoutData) {
            $fontFamily = in_array($layoutData['font_family'], $this->supportedPdfFonts, true)
                ? $layoutData['font_family']
                : 'Helvetica';

            EBadgeLayoutSetting::create([
                'Category' => $categoryModel->Category,
                'field_name' => $layoutData['field_name'],
                'static_text_key' => $layoutData['static_text_key'] ?? null,
                'static_text_value' => $layoutData['static_text_value'] ?? null,
                'margin_top' => $layoutData['margin_top'] ?? 0,
                'margin_left' => $layoutData['margin_left'] ?? 0,
                'margin_right' => $layoutData['margin_right'] ?? 0,
                'sequence' => $layoutData['sequence'] ?? 0,
                'text_align' => $layoutData['text_align'],
                'font_family' => $fontFamily,
                'font_weight' => $layoutData['font_weight'],
                'color' => $layoutData['color'],
                'font_size' => $layoutData['font_size'] ?? null,
                'width' => $layoutData['width'] ?? null,
                'height' => $layoutData['height'] ?? null,
            ]);
        }

        return redirect()->route('admin.e-badge.layouts.edit', ['category' => $categoryModel->Category])
            ->with('success', 'E-badge layout saved successfully.');
    }
}
