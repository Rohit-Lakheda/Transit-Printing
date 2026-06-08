<?php

namespace App\Services;

use App\Models\Category;
use App\Models\EBadgeLayoutSetting;
use App\Models\UserDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EBadgePdfService
{
    /**
     * Resolve e-badge page size from uploaded background image.
     *
     * @return array{width_pt:float,height_pt:float,width_px:int,height_px:int,width_mm:float,height_mm:float}
     */
    protected function resolvePageSize(Category $category): array
    {
        $fallbackWidthPt = (float) $category->badge_width * 2.83465;
        $fallbackHeightPt = (float) $category->badge_height * 2.83465;
        $fallbackWidthPx = (int) round($fallbackWidthPt * (96 / 72));
        $fallbackHeightPx = (int) round($fallbackHeightPt * (96 / 72));
        $fallbackWidthMm = (float) $category->badge_width;
        $fallbackHeightMm = (float) $category->badge_height;

        if (!$category->e_badge_background_path) {
            return [
                'width_pt' => $fallbackWidthPt,
                'height_pt' => $fallbackHeightPt,
                'width_px' => $fallbackWidthPx,
                'height_px' => $fallbackHeightPx,
                'width_mm' => $fallbackWidthMm,
                'height_mm' => $fallbackHeightMm,
            ];
        }

        $bgPath = storage_path('app/public/' . $category->e_badge_background_path);
        if (!is_file($bgPath)) {
            return [
                'width_pt' => $fallbackWidthPt,
                'height_pt' => $fallbackHeightPt,
                'width_px' => $fallbackWidthPx,
                'height_px' => $fallbackHeightPx,
                'width_mm' => $fallbackWidthMm,
                'height_mm' => $fallbackHeightMm,
            ];
        }

        $imageSize = @getimagesize($bgPath);
        if (!$imageSize || empty($imageSize[0]) || empty($imageSize[1])) {
            return [
                'width_pt' => $fallbackWidthPt,
                'height_pt' => $fallbackHeightPt,
                'width_px' => $fallbackWidthPx,
                'height_px' => $fallbackHeightPx,
                'width_mm' => $fallbackWidthMm,
                'height_mm' => $fallbackHeightMm,
            ];
        }

        $widthPx = (int) $imageSize[0];
        $heightPx = (int) $imageSize[1];
        $widthMm = $widthPx * 25.4 / 96;
        $heightMm = $heightPx * 25.4 / 96;

        // DomPDF default is 96 DPI CSS pixels to 72 DPI PDF points.
        return [
            'width_pt' => $widthPx * (72 / 96),
            'height_pt' => $heightPx * (72 / 96),
            'width_px' => $widthPx,
            'height_px' => $heightPx,
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
        ];
    }

    /**
     * @return array{content:string,filename:string}
     */
    public function generateForUser(UserDetail $user): array
    {
        $category = Category::where('Category', $user->Category)->firstOrFail();

        $layoutSettings = EBadgeLayoutSetting::where('Category', $category->Category)
            ->orderBy('sequence')
            ->get()
            ->keyBy('field_name');

        if ($layoutSettings->isEmpty()) {
            throw new \RuntimeException('E-badge layout is not configured for category: ' . $category->Category);
        }

        $qrCodeDataUri = null;
        try {
            $qrPng = QrCode::format('png')
                ->size(220)
                ->errorCorrection('H')
                ->generate((string) $user->RegID);
            $qrCodeDataUri = 'data:image/png;base64,' . base64_encode($qrPng);
        } catch (\Throwable $e) {
            try {
                $qrSvg = QrCode::format('svg')
                    ->size(220)
                    ->errorCorrection('H')
                    ->generate((string) $user->RegID);
                $qrCodeDataUri = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
            } catch (\Throwable $e2) {
                $qrCodeDataUri = null;
            }
        }

        $pageSize = $this->resolvePageSize($category);

        $pdf = Pdf::loadView('admin.e-badge.pdf.single', [
            'userDetail' => $user,
            'category' => $category,
            'layoutSettings' => $layoutSettings,
            'qrCode' => $qrCodeDataUri,
            'pageWidthPt' => $pageSize['width_pt'],
            'pageHeightPt' => $pageSize['height_pt'],
            'pageWidthPx' => $pageSize['width_px'],
            'pageHeightPx' => $pageSize['height_px'],
            'pageWidthMm' => $pageSize['width_mm'],
            'pageHeightMm' => $pageSize['height_mm'],
        ])->setPaper([
            0,
            0,
            $pageSize['width_pt'],
            $pageSize['height_pt'],
        ], 'portrait')
            ->setOption('margin-top', 0)
            ->setOption('margin-right', 0)
            ->setOption('margin-bottom', 0)
            ->setOption('margin-left', 0)
            ->setOption('dpi', 96)
            ->setOption('enable-local-file-access', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('fontDir', storage_path('fonts'))
            ->setOption('fontCache', storage_path('fonts'))
            ->setOption('enable-font-subsetting', true)
            ->setOption('defaultFont', 'Helvetica');

        return [
            'content' => $pdf->output(),
            'filename' => 'e_badge_' . ($user->RegID ?: $user->id) . '.pdf',
        ];
    }
}
