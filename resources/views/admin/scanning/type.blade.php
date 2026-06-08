@extends('layouts.app')

@section('title', 'Scanning Type')

@section('content')
<div class="card" style="max-width: 720px; margin: 0 auto;">
    <div class="card-header">
        <h1 class="card-title">Scanning Type</h1>
    </div>

    <form method="POST" action="{{ route('admin.scanning.type.update') }}">
        @csrf
        @method('PUT')

        <div class="form-group" style="margin-bottom: 28px;">
            <label class="form-label">Entry Scanning (Scan &amp; Verify)</label>
            <p style="font-size: 13px; color: #6b7280; margin: 6px 0 10px;">
                Used on the Scanning page when verifying attendee access at a location.
            </p>
            <div style="display: grid; gap: 10px;">
                <label class="lead-field-checkbox" style="padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                    <input type="radio"
                           name="scanning_type"
                           value="camera"
                           {{ ($eventSettings->scanning_type ?? 'camera') === 'camera' ? 'checked' : '' }}>
                    <span><strong>Camera Scanning</strong> — show camera QR scanner</span>
                </label>
                <label class="lead-field-checkbox" style="padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                    <input type="radio"
                           name="scanning_type"
                           value="device"
                           {{ ($eventSettings->scanning_type ?? 'camera') === 'device' ? 'checked' : '' }}>
                    <span><strong>Scanner Device</strong> — text input only (USB/Bluetooth barcode scanner)</span>
                </label>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Print Scanning (Scan &amp; Print Badge)</label>
            <p style="font-size: 13px; color: #6b7280; margin: 6px 0 10px;">
                Used on the Scan &amp; Print Badge page. Independent from entry scanning above.
            </p>
            <div style="display: grid; gap: 10px;">
                <label class="lead-field-checkbox" style="padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                    <input type="radio"
                           name="print_scanning_type"
                           value="camera"
                           {{ ($eventSettings->print_scanning_type ?? 'camera') === 'camera' ? 'checked' : '' }}>
                    <span><strong>Camera Scanning</strong> — scan badge QR with device camera, then print</span>
                </label>
                <label class="lead-field-checkbox" style="padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                    <input type="radio"
                           name="print_scanning_type"
                           value="device"
                           {{ ($eventSettings->print_scanning_type ?? 'camera') === 'device' ? 'checked' : '' }}>
                    <span><strong>Scanner Device</strong> — type or scan RegID with barcode scanner, then print</span>
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top: 20px;">Save Scanning Types</button>
    </form>
</div>
@endsection


