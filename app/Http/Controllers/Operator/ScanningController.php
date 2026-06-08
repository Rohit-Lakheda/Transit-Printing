<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\ScanningLog;
use App\Models\EventSetting;
use App\Services\Offline\ScanValidationService;
use Illuminate\Http\Request;

class ScanningController extends Controller
{
    public function __construct(
        protected ScanValidationService $scanValidator
    ) {
    }

    public function selectLocation()
    {
        $locations = Location::withoutGlobalScopes()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('operator.scanning.select-location', compact('locations'));
    }

    public function storeLocation(Request $request)
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
        ]);

        $location = Location::withoutGlobalScopes()->findOrFail($validated['location_id']);

        session([
            'scanning_location_id' => $location->id,
            'scanning_location_name' => $location->name,
        ]);

        return redirect()->route('operator.scanning.scan');
    }

    public function scan()
    {
        $locationId = session('scanning_location_id');

        if (!$locationId) {
            return redirect()->route('operator.scanning.select-location')
                ->with('error', 'Please select a location first.');
        }

        $location = Location::withoutGlobalScopes()->findOrFail($locationId);

        $todayStart = now(config('app.timezone'))->startOfDay();
        $todayEnd = now(config('app.timezone'))->endOfDay();

        $base = ScanningLog::withoutGlobalScopes()
            ->where('location_id', $locationId)
            ->whereBetween('scanned_at', [$todayStart, $todayEnd]);

        $todayScanCount = (clone $base)->count();
        $todayApprovedCount = (clone $base)->where('is_allowed', true)->count();
        $todayRejectedCount = (clone $base)->where('is_allowed', false)->count();

        $eventSettings = EventSetting::getSettings();

        $scanningType = $eventSettings?->scanning_type ?: 'camera';

        return view('operator.scanning.scan', compact(
            'location',
            'todayScanCount',
            'todayApprovedCount',
            'todayRejectedCount',
            'scanningType'
        ));
    }

    public function checkUser(Request $request)
    {
        $validated = $request->validate([
            'regid' => 'required|string',
            'client_scan_id' => 'nullable|string|max:64',
            'scan_time' => 'nullable|string',
            'device_id' => 'nullable|string|max:120',
        ]);

        $locationId = session('scanning_location_id');

        if (!$locationId) {
            return response()->json([
                'success' => false,
                'message' => 'Location not selected',
            ], 400);
        }

        $location = Location::withoutGlobalScopes()->findOrFail($locationId);

        $result = $this->scanValidator->processScan(
            $location,
            $validated['regid'],
            $validated['client_scan_id'] ?? null,
            $validated['device_id'] ?? $request->header(config('offline.device_token_header')),
            $validated['scan_time'] ?? null,
            'online'
        );

        if (!$result['success'] && empty($result['name'])) {
            return response()->json([
                'success' => false,
                'allowed' => false,
                'message' => 'User not found',
                'name' => '',
                'category' => '',
                'reason' => $result['reason'] ?? 'User not found',
            ]);
        }

        return response()->json($result);
    }

    public function clearLocation()
    {
        session()->forget(['scanning_location_id', 'scanning_location_name']);
        return redirect()->route('operator.scanning.select-location');
    }
}
