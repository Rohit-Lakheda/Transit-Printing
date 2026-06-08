@extends('layouts.app')

@section('title', 'Import Data')

@section('content')
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">Import Data (Excel)</h1>
        </div>

        <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
            <div class="card" style="margin-bottom: 0;">
                <div class="card-header">
                    <h2 class="card-title" style="font-size: 18px;">1) Download Excel Template</h2>
                </div>

                <div style="color: #374151; margin-bottom: 12px; font-size: 14px;">
                    This template is common for all categories and contains all printable columns from <b>user_details</b>.
                </div>

                <a href="{{ route('admin.import-data.template') }}" class="btn btn-primary">Download Template</a>
            </div>

            <div class="card" style="margin-bottom: 0;">
                <div class="card-header">
                    <h2 class="card-title" style="font-size: 18px;">2) Upload Filled Excel</h2>
                </div>

                <form method="POST" action="{{ route('admin.import-data.import') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->Category }}">{{ $cat->Category }}</option>
                            @endforeach
                        </select>
                        @error('category')
                            <div style="color:#991b1b; margin-top: 8px; font-size: 13px;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Excel File</label>
                        <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                        @error('file')
                            <div style="color:#991b1b; margin-top: 8px; font-size: 13px;">{{ $message }}</div>
                        @enderror
                        <small style="color: #6b7280; font-size: 12px; margin-top: 8px; display: block;">
                            Leave any field blank to save it blank in database. If <b>RegID</b> is blank, it will be generated automatically.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary">Upload & Import</button>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary" style="margin-left: 10px;">Back</a>
                </form>
            </div>

            <div class="card" style="margin-bottom: 0;">
                <div class="card-header">
                    <h2 class="card-title" style="font-size: 18px;">3) Download Registered Users Data</h2>
                </div>

                <div style="color: #374151; margin-bottom: 12px; font-size: 14px;">
                    Download all registered user details (RegID, Name, Category, Email, Mobile, all additional fields, and date/time columns).
                </div>

                <form method="GET" action="{{ route('admin.import-data.export') }}">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Category (optional)</label>
                            <select name="category" class="form-control">
                                <option value="">All Categories</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->Category }}">{{ $cat->Category }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">From Date (optional)</label>
                            <input type="date" name="date_from" class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">To Date (optional)</label>
                            <input type="date" name="date_to" class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <button type="submit" class="btn btn-primary" style="width:100%;">Download Registered Data</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

