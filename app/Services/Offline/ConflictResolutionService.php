<?php

namespace App\Services\Offline;

/**
 * Configurable conflict policies for multi-device offline sync.
 */
class ConflictResolutionService
{
    public const POLICY_FIRST_SCAN_WINS = 'first_scan_wins';
    public const POLICY_LATEST_WINS = 'latest_wins';

    public function policy(): string
    {
        return (string) config('offline.conflict_policy', self::POLICY_FIRST_SCAN_WINS);
    }

    /**
     * Whether an incoming allowed scan should be rejected due to prior scan at location.
     */
    public function shouldRejectDuplicateAllowedScan(
        bool $uniqueScanningEnabled,
        ?\DateTimeInterface $existingAllowedAt,
        ?\DateTimeInterface $incomingScannedAt
    ): bool {
        if (!$uniqueScanningEnabled || !$existingAllowedAt) {
            return false;
        }

        if ($this->policy() === self::POLICY_LATEST_WINS) {
            return $incomingScannedAt && $incomingScannedAt <= $existingAllowedAt;
        }

        // first_scan_wins (default): any prior allowed scan blocks new allowed scan
        return true;
    }
}
