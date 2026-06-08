@extends('layouts.app')

@section('title', 'Operator Home')

@section('content')
<div class="card" style="max-width: 800px; margin: 50px auto; text-align: center;">
    <div class="card-header">
        <h1 class="card-title">Welcome to Badge System</h1>
    </div>

    <div id="offline-home-status" style="margin: 12px 24px; padding: 12px; background: #f0f9ff; border-radius: 8px; font-size: 13px; color: #1e40af;">
        Loading offline status...
    </div>

    <p style="margin-top: 20px; color: #6b7280; font-size: 18px;">
        Please select an option to continue
    </p>

    <div style="margin-top: 50px; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; padding: 20px;">
        <a href="{{ route('operator.badge.menu') }}" class="btn btn-primary" style="padding: 40px 30px; font-size: 20px; text-decoration: none; display: block;">
            <div style="font-weight: bold; margin-bottom: 10px;">Printing</div>
            <div style="font-size: 14px; opacity: 0.9;">Print badges for users</div>
        </a>
        
        <a href="{{ route('operator.scanning.select-location') }}" class="btn btn-secondary" style="padding: 40px 30px; font-size: 20px; text-decoration: none; display: block;">
            <div style="font-weight: bold; margin-bottom: 10px;">Scanning</div>
            <div style="font-size: 14px; opacity: 0.9;">Scan and verify user access</div>
        </a>
        
        <a href="{{ route('operator.registration.create') }}" class="btn" style="background-color: #10b981; color: white; padding: 40px 30px; font-size: 20px; text-decoration: none; display: block;">
            <div style="font-weight: bold; margin-bottom: 10px;">Onsite Registration</div>
            <div style="font-size: 14px; opacity: 0.9;">Register and print badge</div>
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/offline/indexed-db.js') }}"></script>
<script src="{{ asset('js/offline/connectivity.js') }}"></script>
<script src="{{ asset('js/offline/sync-engine.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', async function () {
    const el = document.getElementById('offline-home-status');
    if (@json(config('offline.lan_base_url'))) {
        EventOfflineConnectivity.setLanBaseUrl(@json(config('offline.lan_base_url')));
    }
    EventOfflineConnectivity.setPreferLan(@json((bool) config('offline.prefer_lan') || config('offline.mode') === 'lan_first'));
    EventOfflineConnectivity.startMonitor('/operator/offline/health', 20);
    EventOfflineConnectivity.onChange(async (state) => {
        const eventId = await EventOfflineDB.getMeta('event_id');
        const pending = EventOfflineSyncEngine.countPending ? await EventOfflineSyncEngine.countPending() : 0;
        el.textContent = (eventId ? 'Event #' + eventId + ' ready offline. ' : 'Download event data from Scanning → Select Location. ') +
            (state.online ? 'Connected (' + state.mode + ').' : 'Working offline.') +
            ' Pending sync items: ' + pending + '.';
    });
});
</script>
@endpush
