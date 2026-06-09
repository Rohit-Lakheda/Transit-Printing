@extends('layouts.app')

@section('title', 'Edit Registration — ' . $user->RegID)

@section('content')
<div class="container">
    <div class="card mb-4">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <h1 class="card-title" style="margin:0;">Edit Registration</h1>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="{{ route('admin.registered-users.index') }}" class="btn btn-secondary">Back to List</a>
                <a href="{{ route('admin.e-badge.send.preview', $user->id) }}" class="btn btn-secondary" target="_blank">Preview E-Badge</a>
            </div>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div style="margin-bottom:12px;padding:10px 12px;background:#ecfdf5;color:#047857;border-radius:8px;font-size:13px;">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div style="margin-bottom:12px;padding:10px 12px;background:#fef2f2;color:#b91c1c;border-radius:8px;font-size:13px;">
                    <ul style="margin:0;padding-left:18px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="margin-bottom:16px;padding:12px;background:#f8fafc;border-radius:8px;font-size:13px;color:#475569;">
                <strong>RegID:</strong> {{ $user->RegID }} &nbsp;|&nbsp;
                <strong>Received:</strong> {{ optional($user->Data_Received_At ?? $user->created_at)->format('Y-m-d H:i:s') }} &nbsp;|&nbsp;
                <strong>Source:</strong> {{ $user->DataFrom ?: '—' }}
                @if($user->Badge_Printed_At)
                    &nbsp;|&nbsp; <strong>Badge Printed:</strong> {{ $user->Badge_Printed_At->format('Y-m-d H:i:s') }}
                @endif
            </div>

            <form method="POST" action="{{ route('admin.registered-users.update', $user) }}">
                @csrf
                @method('PUT')

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;">
                    <div class="form-group">
                        <label class="form-label">RegID</label>
                        <input type="text" name="RegID" class="form-control" value="{{ old('RegID', $user->RegID) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="Category" class="form-control" required>
                            @foreach($categories as $category)
                                <option value="{{ $category->Category }}" {{ old('Category', $user->Category) === $category->Category ? 'selected' : '' }}>
                                    {{ $category->Category }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" name="Name" class="form-control" value="{{ old('Name', $user->Name) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Designation</label>
                        <input type="text" name="Designation" class="form-control" value="{{ old('Designation', $user->Designation) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Company</label>
                        <input type="text" name="Company" class="form-control" value="{{ old('Company', $user->Company) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="Email" class="form-control" value="{{ old('Email', $user->Email) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mobile</label>
                        <input type="text" name="Mobile" class="form-control" value="{{ old('Mobile', $user->Mobile) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input type="text" name="Country" class="form-control" value="{{ old('Country', $user->Country) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">State</label>
                        <input type="text" name="State" class="form-control" value="{{ old('State', $user->State) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="City" class="form-control" value="{{ old('City', $user->City) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Receipt Number</label>
                        <input type="text" name="ReceiptNumber" class="form-control" value="{{ old('ReceiptNumber', $user->ReceiptNumber) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Client Registration ID</label>
                        <input type="text" name="client_registration_id" class="form-control" value="{{ old('client_registration_id', $user->client_registration_id) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data Source</label>
                        <input type="text" name="DataFrom" class="form-control" value="{{ old('DataFrom', $user->DataFrom) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Additional 1</label>
                        <input type="text" name="Additional1" class="form-control" value="{{ old('Additional1', $user->Additional1) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Additional 2</label>
                        <input type="text" name="Additional2" class="form-control" value="{{ old('Additional2', $user->Additional2) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Additional 3</label>
                        <input type="text" name="Additional3" class="form-control" value="{{ old('Additional3', $user->Additional3) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Additional 4</label>
                        <input type="text" name="Additional4" class="form-control" value="{{ old('Additional4', $user->Additional4) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Additional 5</label>
                        <input type="text" name="Additional5" class="form-control" value="{{ old('Additional5', $user->Additional5) }}">
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;gap:8px;padding-top:24px;">
                        <input type="checkbox" name="IsLunchAllowed" value="1" id="IsLunchAllowed" {{ old('IsLunchAllowed', $user->IsLunchAllowed) ? 'checked' : '' }}>
                        <label for="IsLunchAllowed" class="form-label" style="margin:0;">Lunch Allowed</label>
                    </div>
                </div>

                <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>

            <div style="margin-top:24px;padding-top:20px;border-top:1px solid #e5e7eb;">
                <h3 style="font-size:16px;margin-bottom:10px;color:#1e40af;">Resend E-Badge Manually</h3>
                <p style="font-size:13px;color:#64748b;margin-bottom:12px;">Use these if you need to send the badge again after updating details.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <form method="POST" action="{{ route('admin.e-badge.send.user') }}">
                        @csrf
                        <input type="hidden" name="user_detail_id" value="{{ $user->id }}">
                        <button type="submit" class="btn btn-primary">Send Email</button>
                    </form>
                    <form method="POST" action="{{ route('admin.e-badge.send.whatsapp') }}">
                        @csrf
                        <input type="hidden" name="user_detail_id" value="{{ $user->id }}">
                        <button type="submit" class="btn btn-secondary">Send WhatsApp</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
