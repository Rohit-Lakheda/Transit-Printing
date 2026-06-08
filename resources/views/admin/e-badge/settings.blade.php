@extends('layouts.app')

@section('title', 'E-Badge Settings')

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">E-Badge Email Settings</h1>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.e-badge.settings.update') }}">
                @csrf

                <div class="form-group">
                    <label class="form-label">Mail Configuration (optional)</label>
                    <select name="mail_configuration_id" class="form-control">
                        <option value="">Use active/latest mail configuration</option>
                        @foreach($mailConfigurations as $config)
                            <option value="{{ $config->id }}" {{ (string) old('mail_configuration_id', $setting->mail_configuration_id) === (string) $config->id ? 'selected' : '' }}>
                                {{ $config->name }} ({{ $config->host }}:{{ $config->port }}){{ $config->is_active ? ' - Active' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Subject</label>
                    <input
                        type="text"
                        name="email_subject"
                        class="form-control"
                        value="{{ old('email_subject', $setting->email_subject) }}"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Email Body (HTML)
                        <small class="text-muted d-block" style="margin-top:6px; font-size:12px;">
                            Variables:
                            {{ '{' }}{Name}}, {{ '{' }}{RegID}}, {{ '{' }}{Category}}, {{ '{' }}{Company}}, {{ '{' }}{Email}},
                            {{ '{' }}{Mobile}}, {{ '{' }}{Designation}}, {{ '{' }}{Country}}, {{ '{' }}{State}}, {{ '{' }}{City}},
                            {{ '{' }}{Additional1}}, {{ '{' }}{Additional2}}, {{ '{' }}{Additional3}}, {{ '{' }}{Additional4}}, {{ '{' }}{Additional5}},
                            {{ '{' }}{EventLogoUrl}}, {{ '{' }}{EmailLogoUrl}}, {{ '{' }}{EmailLogoImage}}, {{ '{' }}{BadgeDownloadLink}}, {{ '{' }}{BadgeBackgroundUrl}}
                        </small>
                    </label>
                    <textarea
                        name="email_body"
                        rows="12"
                        class="form-control"
                        required
                    >{{ old('email_body', $setting->email_body) }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary">Save E-Badge Settings</button>
            </form>
        </div>
    </div>
</div>
@endsection
