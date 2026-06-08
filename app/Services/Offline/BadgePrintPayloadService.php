<?php

namespace App\Services\Offline;

use App\Models\BadgeDisplaySetting;
use App\Models\BadgeLayoutSetting;
use App\Models\Category;
use App\Models\PrintingLog;
use App\Models\UserDetail;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Builds printable badge payload for online API and offline client cache.
 */
class BadgePrintPayloadService
{
    /**
     * @return array<string, mixed>|null
     */
    public function build(int $eventId, string $regid): ?array
    {
        $user = UserDetail::withoutGlobalScopes()
            ->where('RegID', $regid)
            ->first();

        if (!$user) {
            return null;
        }

        $category = Category::withoutGlobalScopes()
            ->where('Category', $user->Category)
            ->first();

        if (!$category) {
            return null;
        }

        $displaySettings = BadgeDisplaySetting::withoutGlobalScopes()
            ->where('Category', $user->Category)
            ->where('layout_type', 'normal')
            ->first();

        $layoutSettings = BadgeLayoutSetting::withoutGlobalScopes()
            ->where('Category', $user->Category)
            ->where('layout_type', 'normal')
            ->orderBy('sequence')
            ->get();

        $visibleFields = $this->visibleFields($displaySettings, $user->Category, 'normal');
        $qr = $this->generateQr($displaySettings, $user->RegID);

        return [
            'user' => $user->only([
                'RegID', 'Category', 'Name', 'Email', 'Mobile', 'Designation', 'Company',
                'Country', 'State', 'City', 'Additional1', 'Additional2', 'Additional3', 'Additional4', 'Additional5',
                'Badge_Printed_At',
            ]),
            'category' => $category->only(['Category', 'badge_width', 'badge_height', 'unique_printing']),
            'display_settings' => $displaySettings?->toArray(),
            'layout_settings' => $layoutSettings->map->toArray()->values(),
            'visible_fields' => $visibleFields,
            'qr_code' => $qr['code'],
            'qr_code_type' => $qr['type'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function visibleFields(?BadgeDisplaySetting $displaySettings, string $categoryName, string $layoutType): array
    {
        $visibleFields = [];
        if (!$displaySettings) {
            return $visibleFields;
        }

        $fields = ['Category', 'RegID', 'Name', 'Email', 'Mobile', 'Designation', 'Company',
            'Country', 'State', 'City', 'Additional1', 'Additional2',
            'Additional3', 'Additional4', 'Additional5', 'QRcode'];

        foreach ($fields as $field) {
            $checkField = ($field === 'Category') ? 'ShowCategory' : $field;
            if (isset($displaySettings->$checkField) && $displaySettings->$checkField) {
                $visibleFields[] = $field;
            }
        }

        $staticLayouts = BadgeLayoutSetting::withoutGlobalScopes()
            ->where('Category', $categoryName)
            ->where('layout_type', $layoutType)
            ->whereNotNull('static_text_value')
            ->where('static_text_value', '!=', '')
            ->get();

        foreach ($staticLayouts as $staticLayout) {
            if (!in_array($staticLayout->field_name, $visibleFields, true)) {
                $visibleFields[] = $staticLayout->field_name;
            }
        }

        return $visibleFields;
    }

    /**
     * @return array{code: ?string, type: ?string}
     */
    protected function generateQr(?BadgeDisplaySetting $displaySettings, string $regid): array
    {
        if (!$displaySettings || !$displaySettings->QRcode) {
            return ['code' => null, 'type' => null];
        }

        try {
            return ['code' => QrCode::format('svg')->size(200)->generate($regid), 'type' => 'svg'];
        } catch (\Throwable $e) {
            try {
                return ['code' => base64_encode(QrCode::format('png')->size(200)->generate($regid)), 'type' => 'png'];
            } catch (\Throwable $e2) {
                return ['code' => null, 'type' => null];
            }
        }
    }

    /**
     * Compact print index for offline unique-print checks.
     *
     * @return array<int, array{regid: string, category: string, printed_at: string}>
     */
    public function printIndexForEvent(int $eventId): array
    {
        return PrintingLog::withoutGlobalScopes()
            ->select(['regid', 'category', 'printed_at'])
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'regid' => $row->regid,
                'category' => $row->category,
                'printed_at' => $row->printed_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
