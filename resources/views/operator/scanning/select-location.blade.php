@extends('layouts.app')

@section('title', 'Select Location')

@section('content')
<div class="card" style="max-width: 760px; margin: 50px auto;">
    <div class="card-header">
        <h1 class="card-title">Select Location for Scanning</h1>
    </div>

    <div class="card" style="margin: 0 0 20px 0; border: 1px solid #dbeafe;">
        <div class="card-header">
            <h2 class="card-title" style="font-size: 18px;">Pre-Event Offline Download</h2>
        </div>
        <div style="padding: 0 4px 8px;">
            <p style="font-size: 14px; color: #374151; margin-bottom: 12px;">
                Download attendee and scanning rules to this device before the event. Scanning works offline after download.
            </p>
            <div class="form-group">
                <label class="form-label">Event</label>
                <select id="offline-event-id" class="form-control">
                    <option value="">Loading events...</option>
                </select>
            </div>
            <button type="button" id="download-bootstrap-btn" class="btn btn-primary">Download Event Data Locally</button>
            <div id="bootstrap-status" style="margin-top: 10px; font-size: 13px; color: #6b7280;"></div>
        </div>
    </div>

    <form method="POST" action="{{ route('operator.scanning.store-location') }}" style="padding: 20px;">
        @csrf

        <div style="margin-bottom: 20px;">
            <label for="location_id" style="display: block; margin-bottom: 8px; font-weight: bold;">Location:</label>
            <select name="location_id" id="location_id" required class="form-control" style="width: 100%; padding: 10px; font-size: 16px;">
                <option value="">-- Select a Location --</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" data-event-id="{{ $location->event_id }}">{{ $location->name }}</option>
                @endforeach
            </select>
            @error('location_id')
                <div style="color: red; margin-top: 5px;">{{ $message }}</div>
            @enderror
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn btn-primary" style="flex: 1; padding: 12px;">Continue to Scanning</button>
            <a href="{{ route('operator.home') }}" class="btn btn-secondary" style="padding: 12px 20px;">Back</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/offline/indexed-db.js') }}"></script>
<script src="{{ asset('js/offline/pull-merge.js') }}"></script>
<script src="{{ asset('js/offline/bootstrap.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', async function () {
    const eventSelect = document.getElementById('offline-event-id');
    const locationSelect = document.getElementById('location_id');
    const statusEl = document.getElementById('bootstrap-status');
    const downloadBtn = document.getElementById('download-bootstrap-btn');

    try {
        const res = await fetch('{{ route('operator.offline.events') }}');
        const data = await res.json();
        eventSelect.innerHTML = '<option value="">-- Select Event --</option>';
        const events = data.events || [];
        events.forEach((ev) => {
            const opt = document.createElement('option');
            opt.value = ev.id;
            opt.textContent = ev.name;
            eventSelect.appendChild(opt);
        });
        if (events.length === 1) {
            eventSelect.value = events[0].id;
        }
    } catch (e) {
        eventSelect.innerHTML = '<option value="">Failed to load events</option>';
    }

    locationSelect.addEventListener('change', function () {
        const opt = locationSelect.options[locationSelect.selectedIndex];
        const eid = opt ? opt.getAttribute('data-event-id') : '';
        if (eid) {
            eventSelect.value = eid;
        }
    });

    downloadBtn.addEventListener('click', async function () {
        const eventId = eventSelect.value;
        if (!eventId) {
            statusEl.textContent = 'Please select an event first.';
            return;
        }
        statusEl.textContent = 'Downloading...';
        downloadBtn.disabled = true;
        try {
            const payload = await EventOfflineBootstrap.downloadBootstrap(
                eventId,
                '{{ route('operator.offline.bootstrap') }}',
                '{{ csrf_token() }}',
                {
                    registerDeviceUrl: '{{ route('operator.offline.register-device') }}',
                    syncToken: @json(config('offline.sync_token')),
                }
            );
            eventSelect.value = eventId;
            statusEl.textContent = 'Downloaded ' + (payload.counts?.attendees || 0) + ' attendees locally.';
        } catch (e) {
            statusEl.textContent = 'Download failed: ' + e.message;
        } finally {
            downloadBtn.disabled = false;
        }
    });
});
</script>
@endpush
