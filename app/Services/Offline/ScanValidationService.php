<?php

namespace App\Services\Offline;

use App\Models\BlockedRegid;
use App\Models\BypassedRegid;
use App\Models\Location;
use App\Models\MasterBadge;
use App\Models\ScanningLog;
use App\Models\UserDetail;
use Carbon\Carbon;

/**
 * Core scan validation + logging (online API and offline batch replay).
 */
class ScanValidationService
{
    public function __construct(
        protected ConflictResolutionService $conflicts
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function processScan(
        Location $location,
        string $regid,
        ?string $clientScanId = null,
        ?string $deviceId = null,
        ?string $scanTime = null,
        string $source = 'online'
    ): array {
        if ($clientScanId) {
            $existing = ScanningLog::withoutGlobalScopes()
                ->where('client_scan_id', $clientScanId)
                ->first();

            if ($existing) {
                return $this->formatFromLog($existing, true);
            }
        }

        $scannedAt = $scanTime ? Carbon::parse($scanTime) : now();

        $user = UserDetail::withoutGlobalScopes()
            ->where('RegID', $regid)
            ->first();

        if (!$user) {
            $log = ScanningLog::create([
                'location_id' => $location->id,
                'location_name' => $location->name,
                'regid' => $regid,
                'user_name' => null,
                'category' => null,
                'is_allowed' => false,
                'reason' => 'User not found in database',
                'scanned_at' => $scannedAt,
                'client_scan_id' => $clientScanId,
                'device_id' => $deviceId,
                'source' => $source,
            ]);

            return $this->formatFromLog($log, false);
        }

        $userName = $user->Name ?? '';
        $userCategory = $user->Category ?? '';
        $isAllowed = false;
        $reason = '';
        $isBypassed = false;
        $bypassedUsed = false;
        $bypassedUsageCount = 0;
        $bypassedRegid = null;
        $isMasterBadge = false;
        $blockedRegid = null;
        $alreadyScanned = false;
        $previousScanTime = null;

        $bypassedRegid = BypassedRegid::withoutGlobalScopes()
            ->where('regid', $regid)
            ->first();

        if ($bypassedRegid && $bypassedRegid->isBypassedAt($location->id)) {
            $bypassedUsageCount = $bypassedRegid->getUsageCountAt($location->id);
            if ($bypassedRegid->canBeBypassedAt($location->id)) {
                $isBypassed = true;
                $bypassedRegid->markAsUsedAt($location->id);
                $isAllowed = true;
            } else {
                $bypassedUsed = true;
            }
        }

        $masterBadge = MasterBadge::withoutGlobalScopes()
            ->where('regid', $regid)
            ->first();

        if (!$isBypassed || $bypassedUsed) {
            if ($masterBadge && $masterBadge->isAllowedAt($location->id)) {
                $isMasterBadge = true;
                $isAllowed = true;
            } else {
                $blockedRegid = BlockedRegid::withoutGlobalScopes()
                    ->where('regid', $regid)
                    ->first();

                if ($blockedRegid && $blockedRegid->isBlockedAt($location->id)) {
                    $isAllowed = false;
                } else {
                    $isAllowed = $location->isCategoryAllowed($userCategory);
                }
            }
        }

        if ($location->unique_scanning && $isAllowed && !$isMasterBadge && !$isBypassed) {
            $previousScan = ScanningLog::withoutGlobalScopes()
                ->where('location_id', $location->id)
                ->where('regid', $regid)
                ->where('is_allowed', true)
                ->orderBy('scanned_at')
                ->first();

            if ($previousScan) {
                $previousScanTime = $previousScan->scanned_at;
                if ($this->conflicts->shouldRejectDuplicateAllowedScan(
                    true,
                    $previousScanTime,
                    $scannedAt
                )) {
                    $alreadyScanned = true;
                    $isAllowed = false;
                }
            }
        }

        if ($isBypassed && !$bypassedUsed) {
            $displayCount = $bypassedUsageCount + 1;
            $effectiveMaxUses = $bypassedRegid->getEffectiveMaxUses();
            $reason = "Bypassed RegID ({$displayCount}/{$effectiveMaxUses} uses) - " . ($bypassedRegid->reason ?? 'Access granted');
        } elseif ($isMasterBadge) {
            $reason = 'Master RegID - allowed at all selected locations';
        } elseif ($blockedRegid && $blockedRegid->isBlockedAt($location->id)) {
            $reason = 'RegID is blocked at this location' . ($blockedRegid->reason ? ' - ' . $blockedRegid->reason : '');
        } elseif ($alreadyScanned) {
            $formattedTime = $previousScanTime->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
            $reason = 'Already scanned at this location on ' . $formattedTime;
        } elseif ($isAllowed) {
            $reason = "Category '{$userCategory}' is allowed at location '{$location->name}'";
        } else {
            $reason = "Category '{$userCategory}' is not allowed at location '{$location->name}'";
        }

        $log = ScanningLog::create([
            'location_id' => $location->id,
            'location_name' => $location->name,
            'regid' => $regid,
            'user_name' => $userName,
            'category' => $userCategory,
            'is_allowed' => $isAllowed && !$alreadyScanned,
            'reason' => $reason,
            'scanned_at' => $scannedAt,
            'client_scan_id' => $clientScanId,
            'device_id' => $deviceId,
            'source' => $source,
        ]);

        return $this->formatFromLog($log, true, [
            'name' => $userName,
            'category' => $userCategory,
            'regid' => $user->RegID ?? '',
            'already_scanned' => $alreadyScanned,
            'previous_scan_time' => $previousScanTime
                ? $previousScanTime->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s')
                : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function formatFromLog(ScanningLog $log, bool $success, array $extra = []): array
    {
        $todayStart = now(config('app.timezone'))->startOfDay();
        $todayEnd = now(config('app.timezone'))->endOfDay();

        $baseQuery = ScanningLog::withoutGlobalScopes()
            ->where('location_id', $log->location_id)
            ->whereBetween('scanned_at', [$todayStart, $todayEnd]);

        return array_merge([
            'success' => $success,
            'allowed' => (bool) $log->is_allowed,
            'message' => $log->reason,
            'reason' => $log->reason,
            'name' => $log->user_name ?? '',
            'category' => $log->category ?? '',
            'regid' => $log->regid,
            'client_scan_id' => $log->client_scan_id,
            'today_scan_count' => (clone $baseQuery)->count(),
            'today_approved_count' => (clone $baseQuery)->where('is_allowed', true)->count(),
            'today_rejected_count' => (clone $baseQuery)->where('is_allowed', false)->count(),
        ], $extra);
    }
}
