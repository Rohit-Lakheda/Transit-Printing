@extends('layouts.app')

@section('title', 'Manage Lead Users')

@section('content')
<div class="container">
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Manage Lead Users</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.leads.users') }}">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                    <div>
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->Category }}" {{ ($selectedCategory ?? '') === $category->Category ? 'selected' : '' }}>
                                    {{ $category->Category }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Credential Status</label>
                        <select name="credential_status" class="form-select">
                            <option value="all" {{ ($credentialStatus ?? 'all') === 'all' ? 'selected' : '' }}>All Users</option>
                            <option value="with" {{ ($credentialStatus ?? '') === 'with' ? 'selected' : '' }}>With Credentials</option>
                            <option value="without" {{ ($credentialStatus ?? '') === 'without' ? 'selected' : '' }}>Without Credentials</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" value="{{ $search ?? '' }}" placeholder="RegID / Name / Company / Email / Mobile">
                    </div>
                </div>
                <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="{{ route('admin.leads.users') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
            <h5 class="card-title mb-0">Lead Users</h5>
            <small class="text-muted">Use row actions to send credentials and update lead limit.</small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                    <tr>
                        <th>RegID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Lead Limit</th>
                        <th>Update Lead Limit</th>
                        <th>Send Credentials</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->RegID }}</td>
                            <td>{{ $user->Name }}</td>
                            <td>{{ $user->Category }}</td>
                            <td>{{ $user->Email ?: '-' }}</td>
                            <td>{{ $user->credential_username ?: '-' }}</td>
                            <td>
                                @if($user->has_credential)
                                    {{ is_null($user->current_max_leads) ? 'Unlimited' : $user->current_max_leads }}
                                @else
                                    <span class="text-muted">No credentials</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.leads.user-limit.update') }}" style="display:flex;gap:6px;align-items:center;">
                                    @csrf
                                    <input type="hidden" name="user_detail_id" value="{{ $user->id }}">
                                    <input type="hidden" name="category" value="{{ $selectedCategory }}">
                                    <input type="hidden" name="search" value="{{ $search ?? '' }}">
                                    <input type="hidden" name="credential_status" value="{{ $credentialStatus ?? 'all' }}">
                                    <input type="hidden" name="context" value="users">
                                    <input type="number"
                                           name="max_leads"
                                           class="form-control"
                                           min="1"
                                           value="{{ old('max_leads', $user->current_max_leads) }}"
                                           placeholder="Unlimited"
                                           style="min-width:110px;padding:4px 8px;font-size:12px;">
                                    <button type="submit" class="btn btn-secondary" style="padding:6px 10px;font-size:12px;">Save</button>
                                </form>
                                <div class="small text-muted" style="font-size:11px;">Empty = Unlimited</div>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.leads.user.send-credentials') }}">
                                    @csrf
                                    <input type="hidden" name="user_detail_id" value="{{ $user->id }}">
                                    <input type="hidden" name="category" value="{{ $selectedCategory }}">
                                    <input type="hidden" name="search" value="{{ $search ?? '' }}">
                                    <input type="hidden" name="credential_status" value="{{ $credentialStatus ?? 'all' }}">
                                    <input type="hidden" name="context" value="users">
                                    <button type="submit" class="btn btn-primary" style="padding:6px 10px;font-size:12px;">Send Credentials</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;">No users found for selected filters.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($users, 'links'))
                <div style="margin-top:12px;">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
