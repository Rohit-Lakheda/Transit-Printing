<?php

namespace App\Services\Offline;

use App\Models\Category;
use App\Models\SyncDeadLetterLog;
use App\Models\SyncDevice;
use App\Models\UserDetail;
use App\Services\RegIdGenerator;
use Carbon\Carbon;

class RegistrationSyncService
{
    /**
     * @param  array<int, array<string, mixed>>  $registrations
     * @return array<string, mixed>
     */
    public function pushBatch(int $eventId, string $deviceId, array $registrations): array
    {
        SyncDevice::touchDevice($eventId, $deviceId);

        $results = [];
        $accepted = 0;
        $failed = 0;

        foreach ($registrations as $registration) {
            try {
                $clientId = $registration['client_registration_id'] ?? null;

                if ($clientId) {
                    $existing = UserDetail::withoutGlobalScopes()
                        ->where('client_registration_id', $clientId)
                        ->first();
                    if ($existing) {
                        $results[] = [
                            'client_registration_id' => $clientId,
                            'status' => 'duplicate',
                            'RegID' => $existing->RegID,
                            'id' => $existing->id,
                        ];
                        $accepted++;
                        continue;
                    }
                }

                $categoryName = (string) ($registration['Category'] ?? '');
                if ($categoryName === '') {
                    throw new \InvalidArgumentException('Category is required.');
                }

                $category = Category::withoutGlobalScopes()
                    ->where('Category', $categoryName)
                    ->first();

                if (!$category) {
                    throw new \RuntimeException('Category not found for this event.');
                }

                $name = (string) ($registration['Name'] ?? '');
                if ($name === '') {
                    throw new \InvalidArgumentException('Name is required.');
                }

                if ($category->receipt_number_required && empty($registration['ReceiptNumber'])) {
                    throw new \RuntimeException('Receipt number is required for this category.');
                }

                $regId = RegIdGenerator::generateForCategory($categoryName);

                if (!empty($registration['RegID'])) {
                    $proposed = (string) $registration['RegID'];
                    $exists = UserDetail::withoutGlobalScopes()
                        ->where('RegID', $proposed)
                        ->exists();
                    if (!$exists) {
                        $regId = $proposed;
                    }
                }

                $receivedAt = isset($registration['Data_Received_At'])
                    ? Carbon::parse($registration['Data_Received_At'])
                    : now();

                $user = UserDetail::create([
                    'client_registration_id' => $clientId,
                    'RegID' => $regId,
                    'Category' => $categoryName,
                    'Name' => $name,
                    'Designation' => $registration['Designation'] ?? null,
                    'Company' => $registration['Company'] ?? null,
                    'Country' => $registration['Country'] ?? null,
                    'State' => $registration['State'] ?? null,
                    'City' => $registration['City'] ?? null,
                    'Email' => $registration['Email'] ?? null,
                    'Mobile' => $registration['Mobile'] ?? null,
                    'Additional1' => $registration['Additional1'] ?? null,
                    'Additional2' => $registration['Additional2'] ?? null,
                    'Additional3' => $registration['Additional3'] ?? null,
                    'Additional4' => $registration['Additional4'] ?? null,
                    'Additional5' => $registration['Additional5'] ?? null,
                    'ReceiptNumber' => $registration['ReceiptNumber'] ?? null,
                    'DataFrom' => $registration['DataFrom'] ?? 'Onsite Registration (offline sync)',
                    'Data_Received_At' => $receivedAt,
                ]);

                $results[] = [
                    'client_registration_id' => $clientId,
                    'status' => 'ok',
                    'RegID' => $user->RegID,
                    'id' => $user->id,
                ];
                $accepted++;
            } catch (\Throwable $e) {
                $failed++;
                SyncDeadLetterLog::create([
                    'event_id' => $eventId,
                    'device_id' => $deviceId,
                    'entity_type' => 'registration',
                    'payload' => $registration,
                    'error_message' => $e->getMessage(),
                    'retry_count' => (int) ($registration['retry_count'] ?? 0),
                    'failed_at' => now(),
                ]);
                $results[] = [
                    'client_registration_id' => $registration['client_registration_id'] ?? null,
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
