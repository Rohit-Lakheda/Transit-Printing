@extends('layouts.app')

@section('title', 'E-Badge Settings')

@section('content')
<div class="container">
    <h1 class="mb-4">E-Badge Communication Settings</h1>

    @if(session('success'))
        <div style="margin-bottom:12px;padding:10px 12px;background:#ecfdf5;color:#047857;border-radius:8px;font-size:13px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="margin-bottom:12px;padding:10px 12px;background:#fef2f2;color:#b91c1c;border-radius:8px;font-size:13px;">{{ session('error') }}</div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;align-items:start;">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title" style="font-size:20px;">Email Settings</h2>
            </div>
            <div class="card-body" style="padding-top:0;">
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
                        <label class="form-label">WhatsApp Configuration for E-Badge sending</label>
                        <select name="whatsapp_configuration_id" class="form-control">
                            <option value="">Use active/latest WhatsApp configuration</option>
                            @foreach($whatsappConfigurations as $config)
                                <option value="{{ $config->id }}" {{ (string) old('whatsapp_configuration_id', $setting->whatsapp_configuration_id) === (string) $config->id ? 'selected' : '' }}>
                                    {{ $config->name }} ({{ $config->providerLabel() }}){{ $config->is_active ? ' - Active' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Subject</label>
                        <input type="text" name="email_subject" class="form-control" value="{{ old('email_subject', $setting->email_subject) }}" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Email Body (HTML)
                            <small style="display:block;margin-top:6px;font-size:12px;color:#64748b;">
                                Variables:
                                {{ '{' }}{Name}}, {{ '{' }}{RegID}}, {{ '{' }}{Category}}, {{ '{' }}{Company}}, {{ '{' }}{Email}},
                                {{ '{' }}{Mobile}}, {{ '{' }}{Designation}}, {{ '{' }}{Country}}, {{ '{' }}{State}}, {{ '{' }}{City}},
                                {{ '{' }}{Additional1}}–{{ '{' }}{Additional5}}, {{ '{' }}{BadgeDownloadLink}}, {{ '{' }}{BadgeBackgroundUrl}},
                                {{ '{' }}{EventLogoUrl}}, {{ '{' }}{EmailLogoUrl}}, {{ '{' }}{EmailLogoImage}}
                            </small>
                        </label>
                        <textarea name="email_body" rows="10" class="form-control" required>{{ old('email_body', $setting->email_body) }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Email &amp; Default WhatsApp Selection</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title" style="font-size:20px;">WhatsApp Provider Configuration</h2>
            </div>
            <div class="card-body" style="padding-top:0;">
                <p style="font-size:13px;color:#64748b;margin-bottom:14px;">
                    Manage AiSensy, Interakt, or other accounts without code changes. Create multiple named configurations and pick one above for e-badge sending.
                </p>

                <form method="POST" action="{{ route('admin.e-badge.whatsapp-config.save') }}" id="whatsappConfigForm">
                    @csrf

                    @php
                        $wa = $activeWhatsappConfig;
                        $waProvider = old('provider', optional($wa)->provider ?? 'aisensy');
                    @endphp

                    <div class="form-group">
                        <label class="form-label">Configuration Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', optional($wa)->name ?? 'Default') }}" required>
                        <small style="color:#64748b;font-size:12px;">Saving with an existing name updates that configuration.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Provider</label>
                        <select name="provider" id="wa-provider" class="form-control" required>
                            <option value="aisensy" {{ $waProvider === 'aisensy' ? 'selected' : '' }}>AiSensy (API Campaign)</option>
                            <option value="interakt" {{ $waProvider === 'interakt' ? 'selected' : '' }}>Interakt</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">API Key</label>
                        <input type="password" name="api_key" class="form-control" placeholder="{{ $wa ? 'Leave blank to keep existing API key' : 'Paste API key from provider dashboard' }}">
                    </div>

                    <div class="form-group">
                        <label class="form-label">API URL (optional)</label>
                        <input type="text" name="api_url" class="form-control" value="{{ old('api_url', optional($wa)->api_url) }}" placeholder="Leave blank for provider default">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Default Country Code</label>
                        <input type="text" name="default_country_code" class="form-control" value="{{ old('default_country_code', optional($wa)->default_country_code ?? '+91') }}">
                    </div>

                    <div class="wa-aisensy-fields">
                        <div class="form-group">
                            <label class="form-label">AiSensy Campaign Name</label>
                            <input type="text" name="campaign_name" class="form-control" value="{{ old('campaign_name', optional($wa)->campaign_name) }}" placeholder="Live API campaign name from AiSensy">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Source (optional)</label>
                            <input type="text" name="source" class="form-control" value="{{ old('source', optional($wa)->source) }}" placeholder="e.g. E-Badge Portal">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Template Params (one per line)</label>
                            <textarea name="template_params" rows="5" class="form-control" placeholder="{{ '{' }}{Name}}&#10;{{ '{' }}{RegID}}&#10;{{ '{' }}{BadgeDownloadLink}}">{{ old('template_params', optional($wa)->template_params) }}</textarea>
                            <small style="color:#64748b;font-size:12px;">Order must match your approved WhatsApp template variables.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-check-label" style="display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" name="include_media" value="1" {{ old('include_media', optional($wa)->include_media ?? true) ? 'checked' : '' }}>
                                Attach PDF media with message
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Media URL placeholder</label>
                            <input type="text" name="media_url_param" class="form-control" value="{{ old('media_url_param', optional($wa)->media_url_param ?? '{{BadgeDownloadLink}}') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Media filename</label>
                            <input type="text" name="media_filename" class="form-control" value="{{ old('media_filename', optional($wa)->media_filename ?? 'e_badge_{{RegID}}.pdf') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tags (comma-separated or JSON array)</label>
                            <input type="text" name="tags" class="form-control" value="{{ old('tags', optional($wa)->tags) }}" placeholder="e-badge,organiser">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Attributes (JSON object)</label>
                            <textarea name="attributes_json" rows="3" class="form-control" placeholder='{"regid": "{{RegID}}", "category": "{{Category}}"}'>{{ old('attributes_json', $wa && $wa->attributes ? json_encode($wa->attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
                        </div>
                    </div>

                    <div class="wa-interakt-fields">
                        <div class="form-group">
                            <label class="form-label">Interakt Template Name</label>
                            <input type="text" name="template_name" class="form-control" value="{{ old('template_name', optional($wa)->template_name) }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Language Code</label>
                            <input type="text" name="language_code" class="form-control" value="{{ old('language_code', optional($wa)->language_code ?? 'en') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Callback Data</label>
                            <input type="text" name="callback_data" class="form-control" value="{{ old('callback_data', optional($wa)->callback_data) }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Header Values (one per line)</label>
                            <textarea name="header_params" rows="3" class="form-control" placeholder="{{ '{' }}{BadgeDownloadLink}}">{{ old('header_params', optional($wa)->header_params) }}</textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Body Values (one per line)</label>
                            <textarea name="body_params" rows="5" class="form-control" placeholder="{{ '{' }}{Name}}&#10;{{ '{' }}{BadgeDownloadLink}}&#10;{{ '{' }}{Additional1}}">{{ old('body_params', optional($wa)->body_params) }}</textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-check-label" style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="ssl_verify" value="1" {{ old('ssl_verify', optional($wa)->ssl_verify ?? true) ? 'checked' : '' }}>
                            Verify SSL certificate
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-check-label" style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', optional($wa)->is_active ?? true) ? 'checked' : '' }}>
                            Active configuration
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">Save WhatsApp Configuration</button>
                </form>

                @if($whatsappConfigurations->isNotEmpty())
                    <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e5e7eb;">
                        <h3 style="font-size:15px;margin-bottom:8px;color:#1e40af;">Saved Configurations</h3>
                        <ul style="font-size:13px;color:#475569;padding-left:18px;margin:0;">
                            @foreach($whatsappConfigurations as $config)
                                <li>{{ $config->name }} — {{ $config->providerLabel() }}{{ $config->is_active ? ' (active)' : '' }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const providerSelect = document.getElementById('wa-provider');
    const aisensyFields = document.querySelectorAll('.wa-aisensy-fields');
    const interaktFields = document.querySelectorAll('.wa-interakt-fields');

    function toggleProviderFields() {
        const provider = providerSelect ? providerSelect.value : 'aisensy';
        aisensyFields.forEach((el) => { el.style.display = provider === 'aisensy' ? 'block' : 'none'; });
        interaktFields.forEach((el) => { el.style.display = provider === 'interakt' ? 'block' : 'none'; });
    }

    if (providerSelect) {
        providerSelect.addEventListener('change', toggleProviderFields);
        toggleProviderFields();
    }
});
</script>
@endpush
