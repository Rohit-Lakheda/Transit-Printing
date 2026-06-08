<?php

namespace App\Services\Offline;

use App\Models\Category;
use App\Models\PrintingLog;
use App\Models\SyncDeadLetterLog;
use App\Models\SyncDevice;
use App\Models\UserDetail;
use Carbon\Carbon;

class PrintSyncService
{
    /**
     * @param  array<int, array<string, mixed>>  $prints
     * @return array<string, mixed>
     */
    public function pushBatch(int $eventId, string $deviceId, array $prints): array
    {
        SyncDevice::touchDevice($eventId, $deviceId);

        $results = [];
        $accepted = 0;
        $failed = 0;

        foreach ($prints as $print) {
            try {
                $regid = (string) ($print['regid'] ?? '');
                $clientPrintId = $print['client_print_id'] ?? null;

                if ($regid === '') {
                    throw new \InvalidArgumentException('regid is required.');
                }

                if ($clientPrintId) {
                    $existing = PrintingLog::withoutGlobalScopes()
                        ->where('client_print_id', $clientPrintId)
                        ->first();
                    if ($existing) {
                        $results[] = ['client_print_id' => $clientPrintId, 'status' => 'duplicate', 'id' => $existing->id];
                        $accepted++;
                        continue;
                    }
                }

                $user = UserDetail::withoutGlobalScopes()
                    ->where('RegID', $regid)
                    ->first();

                if (!$user) {
                    throw new \RuntimeException('User not found for RegID ' . $regid);
                }

                $category = Category::withoutGlobalScopes()
                    ->where('Category', $user->Category)
                    ->first();

                if ($category && $category->unique_printing) {
                    $exists = PrintingLog::withoutGlobalScopes()
                        ->where('regid', $regid)
                        ->where('category', $user->Category)
                        ->exists();
                    if ($exists) {
                        $results[] = [
                            'client_print_id' => $clientPrintId,
                            'status' => 'rejected_duplicate_print',
                            'regid' => $regid,
                        ];
                        $failed++;
                        continue;
                    }
                }

                $printedAt = isset($print['printed_at'])
                    ? Carbon::parse($print['printed_at'])
                    : now();

                $log = PrintingLog::create([
                    'regid' => $regid,
                    'user_name' => $user->Name,
                    'category' => $user->Category,
                    'print_type' => $print['print_type'] ?? 'single',
                    'printed_at' => $printedAt,
                    'client_print_id' => $clientPrintId,
                    'device_id' => $deviceId,
                    'source' => $print['source'] ?? 'offline_sync',
                ]);

                if (!$user->Badge_Printed_At) {
                    $user->Badge_Printed_At = $printedAt;
                    $user->save();
                }

                $results[] = [
                    'client_print_id' => $clientPrintId,
                    'status' => 'ok',
                    'id' => $log->id,
                ];
                $accepted++;
            } catch (\Throwable $e) {
                $failed++;
                SyncDeadLetterLog::create([
                    'event_id' => $eventId,
                    'device_id' => $deviceId,
                    'entity_type' => 'print',
                    'payload' => $print,
                    'error_message' => $e->getMessage(),
                    'retry_count' => (int) ($print['retry_count'] ?? 0),
                    'failed_at' => now(),
                ]);
                $results[] = [
                    'client_print_id' => $print['client_print_id'] ?? null,
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
}
