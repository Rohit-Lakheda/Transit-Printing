@extends('layouts.app')

@section('title', 'E-Badge Layouts')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">E-Badge Layouts</h1>
        </div>
        <div class="card-body">
            <p style="margin-bottom:16px; color:#6b7280;">
                Configure per-category background image and e-badge field layout.
            </p>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Category</th>
                        <th>Badge Size (mm)</th>
                        <th>Background</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td>{{ $category->Category }}</td>
                            <td>{{ number_format((float) $category->badge_width, 2) }} x {{ number_format((float) $category->badge_height, 2) }}</td>
                            <td>
                                @if($category->e_badge_background_path)
                                    <a href="{{ \App\Support\PublicStorageUrl::make($category->e_badge_background_path) }}" target="_blank">View Background</a>
                                @else
                                    <span class="text-muted">Not uploaded</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.e-badge.layouts.edit', $category->Category) }}" class="btn btn-primary">Edit Layout</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;">No categories found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
