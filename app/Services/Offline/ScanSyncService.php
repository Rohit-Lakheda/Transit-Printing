<?php

namespace App\Services\Offline;

use App\Models\Location;
use App\Models\ScanningLog;
use App\Models\SyncDeadLetterLog;
use App\Models\SyncDevice;

class ScanSyncService
{
    public function __construct(
        protected ScanValidationService $validator
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $scans
     * @return array<string, mixed>
     */
    public function pushBatch(int $eventId, string $deviceId, array $scans): array
    {
        SyncDevice::touchDevice($eventId, $deviceId);

        $results = [];
        $accepted = 0;
        $failed = 0;

        foreach ($scans as $scan) {
            try {
                $locationId = (int) ($scan['location_id'] ?? 0);
                $regid = (string) ($scan['regid'] ?? '');
                $clientScanId = $scan['client_scan_id'] ?? null;

                if (!$locationId || $regid === '') {
                    throw new \InvalidArgumentException('location_id and regid are required.');
                }

                $location = Location::withoutGlobalScopes()->where('id', $locationId)->firstOrFail();

                $payload = $this->validator->processScan(
                    $location,
                    $regid,
                    $clientScanId,
                    $deviceId,
                    $scan['scan_time'] ?? null,
                    $scan['source'] ?? 'offline_sync'
                );

                $results[] = [
                    'client_scan_id' => $clientScanId,
                    'status' => 'ok',
                    'payload' => $payload,
                ];
                $accepted++;
            } catch (\Throwable $e) {
                $failed++;
                SyncDeadLetterLog::create([
                    'event_id' => $eventId,
                    'device_id' => $deviceId,
                    'entity_type' => 'scan',
                    'payload' => $scan,
                    'error_message' => $e->getMessage(),
                    'retry_count' => (int) ($scan['retry_count'] ?? 0),
                    'failed_at' => now(),
                ]);

                $results[] = [
                    'client_scan_id' => $scan['client_scan_id'] ?? null,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => true,
            'accepted' => $accepted,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * LAN / multi-device: recent scans for a location since timestamp.
     *
     * @return array<string, mixed>
     */
    public function pullLocationScans(int $eventId, int $locationId, ?string $since): array
    {
        $query = ScanningLog::withoutGlobalScopes()
            ->where('location_id', $locationId)
            ->orderBy('scanned_at');

        if ($since) {
            $query->where('scanned_at', '>', $since);
        }

        $logs = $query->limit(500)->get([
            'regid', 'is_allowed', 'scanned_at', 'device_id', 'client_scan_id', 'location_id',
        ]);

        return [
            'event_id' => $eventId,
            'location_id' => $locationId,
            'scans' => $logs,
            'synced_at' => now()->toIso8601String(),
        ];
    }
}
