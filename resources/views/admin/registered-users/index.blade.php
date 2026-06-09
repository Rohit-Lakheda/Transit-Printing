@extends('layouts.app')

@section('title', 'Registered Users')

@section('content')
<div class="container">
    <div class="card mb-4">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <h1 class="card-title" style="margin:0;">Registered Users</h1>
            <a href="{{ route('admin.import-data.export') }}" class="btn btn-secondary">Export Data</a>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div style="margin-bottom:12px;padding:10px 12px;background:#ecfdf5;color:#047857;border-radius:8px;font-size:13px;">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div style="margin-bottom:12px;padding:10px 12px;background:#fef2f2;color:#b91c1c;border-radius:8px;font-size:13px;">{{ session('error') }}</div>
            @endif

            <form method="GET" action="{{ route('admin.registered-users.index') }}">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;">
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
                        <label class="form-label">Data Source</label>
                        <select name="data_from" class="form-control">
                            <option value="">All Sources</option>
                            @foreach($dataFromOptions as $source)
                                <option value="{{ $source }}" {{ ($dataFrom ?? '') === $source ? 'selected' : '' }}>{{ $source }}</option>
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
                    <a href="{{ route('admin.registered-users.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="padding-top:0;">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                    <tr>
                        <th>RegID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Company</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Source</th>
                        <th>Received</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->RegID }}</td>
                            <td>{{ $user->Name }}</td>
                            <td>{{ $user->Category }}</td>
                            <td>{{ $user->Company }}</td>
                            <td>{{ $user->Email }}</td>
                            <td>{{ $user->Mobile }}</td>
                            <td>{{ $user->DataFrom }}</td>
                            <td>{{ optional($user->Data_Received_At ?? $user->created_at)->format('Y-m-d H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.registered-users.edit', $user) }}" class="btn btn-primary" style="padding:4px 10px;font-size:12px;">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align:center;color:#6b7280;">No registered users found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="app-pagination">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
