<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\SyncDevice;
use App\Services\Offline\BadgePrintPayloadService;
use App\Services\Offline\EventBootstrapService;
use App\Services\Offline\PrintSyncService;
use App\Services\Offline\RegistrationSyncService;
use App\Services\Offline\ScanSyncService;
use Illuminate\Http\Request;

/**
 * Offline-first sync API for operator clients (browser IndexedDB).
 */
class OfflineSyncController extends Controller
{
    public function __construct(
        protected EventBootstrapService $bootstrap,
        protected ScanSyncService $scanSync,
        protected PrintSyncService $printSync,
        protected RegistrationSyncService $registrationSync,
        protected BadgePrintPayloadService $badgePrint
    ) {
    }

    public function health()
    {
        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'mode' => 'online',
        ]);
    }

    public function registerDevice(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'nullable|integer',
            'device_id' => 'required|string|max:120',
            'device_name' => 'nullable|string|max:255',
            'device_type' => 'nullable|string|max:50',
        ]);

        $eventId = (int) ($validated['event_id'] ?? 1);

        $device = SyncDevice::touchDevice(
            $eventId,
            $validated['device_id'],
            $validated['device_name'] ?? null,
            'registered'
        );

        return response()->json([
            'success' => true,
            'device' => $device,
        ]);
    }

    public function bootstrap(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'nullable|integer',
        ]);

        $eventId = (int) ($validated['event_id'] ?? 1);

        return response()->json($this->bootstrap->fullBootstrap($eventId));
    }

    public function pull(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'nullable|integer',
            'since' => 'nullable|string',
            'location_id' => 'nullable|integer',
        ]);
        $eventId = (int) ($validated['event_id'] ?? 1);

        return response()->json(
            $this->bootstrap->incremental(
                $eventId,
                $validated['since'] ?? null,
                isset($validated['location_id']) ? (int) $validated['location_id'] : null
            )
        );
    }

    public function printPayload(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'nullable|integer',
            'regid' => 'required|string',
        ]);
        $eventId = (int) ($validated['event_id'] ?? 1);

        $payload = $this->badgePrint->build($eventId, $validated['regid']);

        if (!$payload) {
            return response()->json(['success' => false, 'message' => 'Registration ID not found.'], 404);
        }

        return response()->json(['success' => true, 'payload' => $payload]);
    }

    public function pushScans(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'nullable|integer',
            'device_id' => 'required|string|max:120',
            'scans' => 'required|array|min:1|max:200',
            'scans.*.location_id' => 'required|integer',
            'scans.*.regid' => 'required|string',
            'scans.*.client_scan_id' => 'nullable|string|max:64',
            'scans.*.scan_time' => 'nullable|string',
            'scans.*.source' => 'nullable|string|max:30',
        ]);

        $eventId = (int) ($validated['event_id'] ?? 1);

        return response()->json(
            $this->scanSync->pushBatch(
                $eventId,
                $validated['device_id'],
                $validated['scans']
            )
        );
    }

    public function pushPrints(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'nullable|integer',
            'device_id' => 'required|string|max:120',
            'prints' => 'required|array|min:1|max:200',
            'prints.*.regid' => 'required|string',
            'prints.*.client_print_id' => 'nullable|string|max:64',
            'prints.*.printed_at' => 'nullable|string',
            'prints.*.print_type' => 'nullable|string|max:30',
        ]);

        $eventId = (int) ($validated['event_id'] ?? 1);

        return response()->json(
            $this->printSync->pushBatch(
                $eventId,
                $validated['device_id'],
                $validated['prints']
            )
        );
    }

    public function pushRegistrations(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'nullable|integer',
            'device_id' => 'required|string|max:120',
            'registrations' => 'required|array|min:1|max:100',
            'registrations.*.client_registration_id' => 'nullable|string|max:64',
            'registrations.*.Category' => 'required|string',
            'registrations.*.Name' => 'required|string|max:255',
            'registrations.*.RegID' => 'nullable|string|max:50',
            'registrations.*.ReceiptNumber' => 'nullable|string|max:255',
        ]);

        $eventId = (int) ($validated['event_id'] ?? 1);

        return response()->json(
            $this->registrationSync->pushBatch(
                $eventId,
                $validated['device_id'],
                $validated['registrations']
            )
        );
    }

    public function pullLocationScans(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'nullable|integer',
            'location_id' => 'required|integer',
            'since' => 'nullable|string',
        ]);
        $eventId = (int) ($validated['event_id'] ?? 1);

        return response()->json(
            $this->scanSync->pullLocationScans(
                $eventId,
                (int) $validated['location_id'],
                $validated['since'] ?? null
            )
        );
    }

    public function events()
    {
        return response()->json([
            'events' => [[
                'id' => 1,
                'name' => 'Default Event',
                'description' => 'Single event mode',
            ]],
        ]);
    }
}
