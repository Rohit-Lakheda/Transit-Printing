<?php

namespace App\Services\Offline;

use App\Models\Category;
use App\Models\PrintingLog;
use App\Models\ScanningLog;
use App\Models\UserDetail;
use Illuminate\Support\Carbon;

/**
 * Pull server changes down to offline clients (vice versa of push sync).
 */
class IncrementalPullService
{
    public function __construct(
        protected BadgePrintPayloadService $badgePrint
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function pull(int $eventId, ?string $since, ?int $locationId = null): array
    {
        $sinceAt = $since ? Carbon::parse($since) : Carbon::createFromTimestamp(0);
        $chunk = (int) config('offline.bootstrap_chunk_size', 2000);

        $attendees = UserDetail::withoutGlobalScopes()
            ->where('updated_at', '>', $sinceAt)
            ->select([
                'id', 'RegID', 'Category', 'Name', 'Email', 'Mobile',
                'Designation', 'Company', 'Country', 'State', 'City',
                'Additional1', 'Additional2', 'Additional3', 'Additional4', 'Additional5',
                'Badge_Printed_At', 'updated_at',
            ])
            ->orderBy('updated_at')
            ->limit($chunk)
            ->get();

        $prints = PrintingLog::withoutGlobalScopes()
            ->where('printed_at', '>', $sinceAt)
            ->select(['regid', 'category', 'printed_at', 'print_type'])
            ->orderBy('printed_at')
            ->limit($chunk)
            ->get()
            ->map(fn ($row) => [
                'regid' => $row->regid,
                'category' => $row->category,
                'printed_at' => $row->printed_at?->toIso8601String(),
                'print_type' => $row->print_type,
            ]);

        $scansQuery = ScanningLog::withoutGlobalScopes()
            ->where('scanned_at', '>', $sinceAt);

        if ($locationId) {
            $scansQuery->where('location_id', $locationId);
        }

        $scans = $scansQuery
            ->select(['regid', 'location_id', 'is_allowed', 'scanned_at', 'device_id', 'client_scan_id'])
            ->orderBy('scanned_at')
            ->limit($chunk)
            ->get()
            ->map(fn ($row) => [
                'regid' => $row->regid,
                'location_id' => $row->location_id,
                'is_allowed' => (bool) $row->is_allowed,
                'scanned_at' => $row->scanned_at?->toIso8601String(),
                'device_id' => $row->device_id,
                'client_scan_id' => $row->client_scan_id,
                'source' => 'server',
            ]);

        $categories = Category::withoutGlobalScopes()
            ->where('updated_at', '>', $sinceAt)
            ->get(['Category', 'unique_printing', 'badge_width', 'badge_height', 'updated_at']);

        return [
            'event_id' => $eventId,
            'since' => $sinceAt->toIso8601String(),
            'synced_at' => now()->toIso8601String(),
            'attendees' => $attendees,
            'printing_logs' => $prints,
            'scanning_logs' => $scans,
            'categories' => $categories,
            'has_more' => $attendees->count() >= $chunk,
        ];
    }
}
