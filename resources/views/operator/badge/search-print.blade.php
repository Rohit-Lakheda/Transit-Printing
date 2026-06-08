@extends('layouts.app')

@section('title', 'Search & Print Badge')

@section('content')
<div class="card">
    <div class="card-header">
        <h1 class="card-title">Search &amp; Print Badge</h1>
    </div>

    <div id="offline-search-status" style="padding: 0 24px 12px; font-size: 13px; color: #6b7280;">
        Checking offline cache...
    </div>

    <div id="offline-search-panel" style="display: none; padding: 0 24px 20px;">
        <div style="display: grid; grid-template-columns: auto 1fr; gap: 15px; margin-bottom: 15px; align-items: start;">
            <div class="form-group" style="min-width: 200px;">
                <label class="form-label">Category</label>
                <select id="offlineCategory" class="form-control">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Search (offline)</label>
                <input type="text" id="offlineSearchTerm" class="form-control" placeholder="Search cached attendees...">
            </div>
        </div>
        <button type="button" id="offlineSearchBtn" class="btn btn-primary">Search Offline</button>
        <div id="offlineResults" style="margin-top: 20px;"></div>
    </div>

    <hr style="margin: 0 24px 20px; border: none; border-top: 1px solid #e5e7eb;">

    <p style="padding: 0 24px; font-size: 13px; color: #6b7280;">Online search (requires connection):</p>

    <form method="GET" action="{{ route('operator.badge.search-print') }}" id="searchForm">
        <div style="display: grid; grid-template-columns: auto 1fr; gap: 15px; margin-bottom: 20px; align-items: start;">
            <div class="form-group" style="min-width: 200px;">
                <label class="form-label">Category</label>
                <select name="category" class="form-control">
                    <option value="" {{ !request()->filled('category') ? 'selected' : '' }}>All Categories</option>
                    @foreach(\App\Models\Category::all() as $cat)
                        <option value="{{ $cat->Category }}" {{ request('category') === $cat->Category ? 'selected' : '' }}>
                            {{ $cat->Category }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Search</label>
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       value="{{ request('search') }}" 
                       placeholder="Search by RegID, Name, Email, Mobile, Company, Country, State, City, or any field..."
                       autofocus>
                <small style="color: #6b7280; font-size: 12px; margin-top: 5px; display: block;">
                    Searches across all fields including RegID, Name, Email, Mobile, Company, Country, State, City, Designation, and Additional fields
                </small>
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="{{ route('operator.badge.search-print') }}" class="btn btn-secondary">Clear</a>
            <a href="{{ route('operator.badge.menu') }}" class="btn btn-secondary">Back to Menu</a>
        </div>
    </form>

    @if(request()->filled('search') || request()->filled('category'))
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>RegID</th>
                        <th>Category</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Company</th>
                        <th>Country</th>
                        <th>State</th>
                        <th>City</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->RegID }}</td>
                            <td>{{ $user->Category }}</td>
                            <td>{{ $user->Name }}</td>
                            <td>{{ $user->Designation ?? '-' }}</td>
                            <td>{{ $user->Company ?? '-' }}</td>
                            <td>{{ $user->Country ?? '-' }}</td>
                            <td>{{ $user->State ?? '-' }}</td>
                            <td>{{ $user->City ?? '-' }}</td>
                            <td>
                                <form action="{{ route('operator.badge.print') }}" method="POST" style="display: inline;">
                                    @csrf
                                    <input type="hidden" name="regid" value="{{ $user->RegID }}">
                                    <button type="submit" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">Print</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px;">No users found matching your search criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="table-card">
            @forelse($users as $user)
                <div class="table-card-item">
                    <div class="card-row">
                        <span class="card-label">RegID:</span>
                        <span class="card-value"><strong>{{ $user->RegID }}</strong></span>
                    </div>
                    <div class="card-row">
                        <span class="card-label">Category:</span>
                        <span class="card-value">{{ $user->Category }}</span>
                    </div>
                    <div class="card-row">
                        <span class="card-label">Name:</span>
                        <span class="card-value">{{ $user->Name }}</span>
                    </div>
                    @if($user->Designation)
                    <div class="card-row">
                        <span class="card-label">Designation:</span>
                        <span class="card-value">{{ $user->Designation }}</span>
                    </div>
                    @endif
                    @if($user->Company)
                    <div class="card-row">
                        <span class="card-label">Company:</span>
                        <span class="card-value">{{ $user->Company }}</span>
                    </div>
                    @endif
                    <div class="card-actions">
                        <form action="{{ route('operator.badge.print') }}" method="POST" style="width: 100%;">
                            @csrf
                            <input type="hidden" name="regid" value="{{ $user->RegID }}">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Print Badge</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="table-card-item" style="text-align: center; padding: 40px;">
                    No users found matching your search criteria.
                </div>
            @endforelse
        </div>

        @if($users->hasPages())
            <div style="margin-top: 20px; display: flex; justify-content: center; gap: 10px;">
                @if($users->onFirstPage())
                    <span class="btn btn-secondary" style="opacity: 0.5; cursor: not-allowed;">Previous</span>
                @else
                    <a href="{{ $users->previousPageUrl() }}" class="btn btn-secondary">Previous</a>
                @endif

                <span style="padding: 8px 16px; display: inline-flex; align-items: center;">
                    Page {{ $users->currentPage() }} of {{ $users->lastPage() }}
                </span>

                @if($users->hasMorePages())
                    <a href="{{ $users->nextPageUrl() }}" class="btn btn-secondary">Next</a>
                @else
                    <span class="btn btn-secondary" style="opacity: 0.5; cursor: not-allowed;">Next</span>
                @endif
            </div>
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/vendor/qrcode-generator.js') }}"></script>
<script src="{{ asset('js/offline/indexed-db.js') }}"></script>
<script src="{{ asset('js/offline/local-search.js') }}"></script>
<script src="{{ asset('js/offline/connectivity.js') }}"></script>
<script src="{{ asset('js/offline/local-print.js') }}"></script>
<script src="{{ asset('js/offline/print-renderer.js') }}"></script>
<script src="{{ asset('js/offline/print-bridge.js') }}"></script>
<script src="{{ asset('js/offline/sync-engine.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', async function () {
    const statusEl = document.getElementById('offline-search-status');
    const panel = document.getElementById('offline-search-panel');
    const categoryEl = document.getElementById('offlineCategory');
    const termEl = document.getElementById('offlineSearchTerm');
    const resultsEl = document.getElementById('offlineResults');
    const searchBtn = document.getElementById('offlineSearchBtn');

    if (@json(config('offline.lan_base_url'))) {
        EventOfflineConnectivity.setLanBaseUrl(@json(config('offline.lan_base_url')));
    }
    EventOfflineConnectivity.setPreferLan(@json((bool) config('offline.prefer_lan') || config('offline.mode') === 'lan_first'));
    EventOfflineConnectivity.startMonitor('/operator/offline/health', 20);

    let syncNow = function () {};
    const eventId = await EventOfflineDB.getMeta('event_id');
    if (eventId) {
        const syncHandle = EventOfflineSyncEngine.start({
            deviceId: localStorage.getItem('event_offline_device_id') || 'dev-search-print',
            csrfToken: '{{ csrf_token() }}',
            syncToken: @json(config('offline.sync_token')),
            intervalSeconds: {{ (int) config('offline.sync_interval_seconds', 20) }},
            maxRetries: {{ (int) config('offline.max_sync_retries', 8) }},
            endpoints: {
                health: '/operator/offline/health',
                pushScans: '/operator/offline/push-scans',
                pushPrints: '/operator/offline/push-prints',
                pushRegistrations: '/operator/offline/push-registrations',
                pull: '/operator/offline/pull',
                pullLocationScans: '/operator/offline/pull-location-scans',
            },
        });
        syncNow = syncHandle.syncNow;
    }

    EventOfflinePrintBridge.init({
        csrfToken: '{{ csrf_token() }}',
        syncToken: @json(config('offline.sync_token')),
        syncNow: syncNow,
        endpoints: { printPayload: '/operator/offline/print-payload' },
    });

    document.querySelectorAll('form[action*="badge/print"]').forEach(function (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const regidInput = form.querySelector('input[name="regid"]');
            const regid = regidInput ? regidInput.value.trim() : '';
            if (!regid) {
                alert('Registration ID is missing.');
                return;
            }
            const result = await EventOfflinePrintBridge.printRegid(regid);
            if (!result.ok) {
                alert(result.message || 'Print failed');
            }
        });
    });

    if (eventId) {
        panel.style.display = 'block';
        statusEl.textContent = 'Offline search available for event #' + eventId + '.';
        const cats = await EventOfflineSearch.getCategories();
        cats.forEach((c) => {
            const opt = document.createElement('option');
            opt.value = c;
            opt.textContent = c;
            categoryEl.appendChild(opt);
        });

        async function renderOfflineResults() {
            const rows = await EventOfflineSearch.search({
                category: categoryEl.value,
                term: termEl.value.trim(),
            });
            if (!rows.length) {
                resultsEl.innerHTML = '<p style="color:#6b7280;">No matches in local cache.</p>';
                return;
            }
            let html = '<table class="table"><thead><tr><th>RegID</th><th>Name</th><th>Category</th><th></th></tr></thead><tbody>';
            rows.forEach((u) => {
                html += '<tr><td>' + u.RegID + '</td><td>' + (u.Name || '') + '</td><td>' + (u.Category || '') + '</td>';
                html += '<td><button type="button" class="btn btn-primary offline-print-btn" data-regid="' + u.RegID + '" style="padding:6px 12px;font-size:12px;">Print</button></td></tr>';
            });
            html += '</tbody></table>';
            resultsEl.innerHTML = html;
            resultsEl.querySelectorAll('.offline-print-btn').forEach((btn) => {
                btn.addEventListener('click', async function () {
                    const regid = btn.getAttribute('data-regid');
                    const result = await EventOfflinePrintBridge.printRegid(regid);
                    if (!result.ok) alert(result.message || 'Print failed');
                });
            });
        }

        searchBtn.addEventListener('click', renderOfflineResults);
        termEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); renderOfflineResults(); } });
    } else {
        statusEl.textContent = 'No offline cache — download event data from Scanning → Select Location first.';
    }
});
</script>
@endpush
