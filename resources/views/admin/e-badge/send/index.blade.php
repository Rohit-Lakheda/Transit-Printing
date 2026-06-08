@extends('layouts.app')

@section('title', 'Send E-Badges')

@section('content')
<div class="container">
    <div class="card mb-4">
        <div class="card-header">
            <h1 class="card-title">Send E-Badges</h1>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.e-badge.send.index') }}">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                    <div>
                        <label class="form-label">Category</label>
                        <select name="category" class="form-control">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->Category }}" {{ ($selectedCategory ?? '') === $category->Category ? 'selected' : '' }}>
                                    {{ $category->Category }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" value="{{ $search ?? '' }}" placeholder="RegID / Name / Email / Company / Mobile">
                    </div>
                </div>
                <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="{{ route('admin.e-badge.send.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
            @if(!empty($selectedCategory) && !empty($selectedBadgeSize))
                <div style="margin-top:10px;font-size:12px;color:#475569;">
                    Resolved e-badge PDF size:
                    <strong>{{ $selectedBadgeSize['width_px'] }}px × {{ $selectedBadgeSize['height_px'] }}px</strong>
                    (source:
                    <strong>{{ $selectedBadgeSize['source'] === 'background_image' ? 'uploaded background image' : 'category fallback size' }}</strong>)
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Users</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.e-badge.send.bulk') }}">
                @csrf
                <input type="hidden" name="category" value="{{ $selectedCategory }}">
                <input type="hidden" name="search" value="{{ $search }}">

                <div style="margin-bottom:10px;">
                    <button type="submit" class="btn btn-primary">Send Email To Selected</button>
                    <button type="submit" formaction="{{ route('admin.e-badge.send.bulk-whatsapp') }}" class="btn btn-secondary">Send WhatsApp To Selected</button>
                    @if(!empty($selectedCategory))
                        <small style="display:block;margin-top:6px;color:#6b7280;">If none selected, all filtered users of selected category will be processed.</small>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all-users"></th>
                            <th>RegID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Email</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td><input type="checkbox" name="selected_user_ids[]" class="row-user-checkbox" value="{{ $user->id }}"></td>
                                <td>{{ $user->RegID }}</td>
                                <td>{{ $user->Name }}</td>
                                <td>{{ $user->Category }}</td>
                                <td>{{ $user->Email ?: '-' }}</td>
                                <td>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                        <a
                                            href="{{ route('admin.e-badge.send.preview', $user->id) }}"
                                            target="_blank"
                                            class="btn btn-secondary"
                                            style="padding:6px 10px;font-size:12px;text-decoration:none;"
                                        >
                                            View PDF
                                        </a>
                                        <button
                                            type="submit"
                                            name="user_detail_id"
                                            value="{{ $user->id }}"
                                            formaction="{{ route('admin.e-badge.send.whatsapp') }}"
                                            class="btn btn-secondary"
                                            style="padding:6px 10px;font-size:12px;"
                                        >
                                            Send WhatsApp
                                        </button>
                                        <button
                                            type="submit"
                                            name="user_detail_id"
                                            value="{{ $user->id }}"
                                            formaction="{{ route('admin.e-badge.send.user') }}"
                                            class="btn btn-primary"
                                            style="padding:6px 10px;font-size:12px;"
                                        >
                                            Send Email
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align:center;">No users found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </form>

            <div style="margin-top:12px;">
                {{ $users->links() }}
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Recent Send Logs</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                    <tr>
                        <th>RegID</th>
                        <th>Email</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Message</th>
                        <th>Sent At</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($recentLogs as $log)
                        <tr>
                            <td>{{ $log->regid }}</td>
                            <td>{{ $log->email }}</td>
                            <td>{{ $log->category }}</td>
                            <td>{{ $log->status }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($log->message, 80) }}</td>
                            <td>{{ optional($log->sent_at)->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center;">No e-badge logs yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('select-all-users');
    if (!selectAll) return;

    selectAll.addEventListener('change', function () {
        document.querySelectorAll('.row-user-checkbox').forEach(function (cb) {
            cb.checked = selectAll.checked;
        });
    });
});
</script>
@endpush
