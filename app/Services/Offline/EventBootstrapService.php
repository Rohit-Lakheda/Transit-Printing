<?php

namespace App\Services\Offline;

use App\Models\BadgeDisplaySetting;
use App\Models\BadgeLayoutSetting;
use App\Models\BlockedRegid;
use App\Models\BypassedRegid;
use App\Models\Category;
use App\Models\EventSetting;
use App\Models\Location;
use App\Models\MasterBadge;
use App\Models\UserDetail;

/**
 * Builds offline bootstrap payloads for client IndexedDB seeding.
 */
class EventBootstrapService
{
    public function __construct(
        protected BadgePrintPayloadService $badgePrint,
        protected IncrementalPullService $incrementalPull
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function fullBootstrap(int $eventId): array
    {
        $now = now()->toIso8601String();

        $attendees = UserDetail::withoutGlobalScopes()
            ->select([
                'id', 'RegID', 'Category', 'Name', 'Email', 'Mobile',
                'Designation', 'Company', 'Country', 'State', 'City',
                'Additional1', 'Additional2', 'Additional3', 'Additional4', 'Additional5',
                'Badge_Printed_At', 'updated_at',
            ])
            ->orderBy('id')
            ->get();

        $locations = Location::withoutGlobalScopes()
            ->where('is_active', true)
            ->with(['allowedCategories'])
            ->get()
            ->map(function (Location $location) {
                return [
                    'id' => $location->id,
                    'name' => $location->name,
                    'unique_scanning' => (bool) $location->unique_scanning,
                    'allowed_categories' => $location->allowedCategories->pluck('category')->values(),
                ];
            });

        $blocked = BlockedRegid::withoutGlobalScopes()
            ->with('locations:id')
            ->get()
            ->map(fn ($row) => [
                'regid' => $row->regid,
                'location_ids' => $row->locations->pluck('id')->values(),
                'reason' => $row->reason,
            ]);

        $master = MasterBadge::withoutGlobalScopes()
            ->with('locations:id')
            ->get()
            ->map(fn ($row) => [
                'regid' => $row->regid,
                'location_ids' => $row->locations->pluck('id')->values(),
            ]);

        $bypassed = BypassedRegid::withoutGlobalScopes()
            ->with('locations:id')
            ->get()
            ->map(fn ($row) => [
                'regid' => $row->regid,
                'location_ids' => $row->locations->pluck('id')->values(),
                'max_uses' => $row->max_uses,
                'reason' => $row->reason,
            ]);

        $categories = Category::withoutGlobalScopes()
            ->get(['Category', 'Prefix', 'unique_printing', 'receipt_number_required', 'badge_width', 'badge_height', 'updated_at']);

        $displaySettings = BadgeDisplaySetting::withoutGlobalScopes()
            ->where('layout_type', 'normal')
            ->get()
            ->map(fn ($row) => array_merge($row->toArray(), [
                'cache_key' => $row->Category . '::normal',
            ]));

        $layoutSettings = BadgeLayoutSetting::withoutGlobalScopes()
            ->where('layout_type', 'normal')
            ->orderBy('sequence')
            ->get()
            ->groupBy('Category')
            ->map(fn ($group, $category) => [
                'cache_key' => $category . '::normal',
                'Category' => $category,
                'layouts' => $group->map->toArray()->values(),
            ])
            ->values();

        $settings = EventSetting::withoutGlobalScopes()
            ->where('id', 1)
            ->first();

        $printIndex = $this->badgePrint->printIndexForEvent($eventId);

        return [
            'event_id' => $eventId,
            'synced_at' => $now,
            'server_time' => $now,
            'attendees' => $attendees,
            'locations' => $locations,
            'categories' => $categories,
            'blocked_regids' => $blocked,
            'master_badges' => $master,
            'bypassed_regids' => $bypassed,
            'badge_display_settings' => $displaySettings,
            'badge_layout_groups' => $layoutSettings,
            'print_index' => $printIndex,
            'event_settings' => $settings ? [
                'scanning_type' => $settings->scanning_type ?: 'camera',
                'logo_path' => $settings->logo_path ? \App\Support\PublicStorageUrl::make($settings->logo_path) : null,
                'conflict_policy' => config('offline.conflict_policy', 'first_scan_wins'),
            ] : [
                'scanning_type' => 'camera',
                'conflict_policy' => config('offline.conflict_policy', 'first_scan_wins'),
            ],
            'counts' => [
                'attendees' => $attendees->count(),
                'locations' => $locations->count(),
                'print_index' => count($printIndex),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function incremental(int $eventId, ?string $since, ?int $locationId = null): array
    {
        return $this->incrementalPull->pull($eventId, $since, $locationId);
    }
}
