<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConfiguration;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WhatsappConfigurationController extends Controller
{
    public function save(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => ['required', Rule::in([
                WhatsappConfiguration::PROVIDER_AISENSY,
                WhatsappConfiguration::PROVIDER_INTERAKT,
            ])],
            'api_key' => 'nullable|string',
            'api_url' => 'nullable|string|max:500',
            'campaign_name' => 'nullable|string|max:255',
            'template_name' => 'nullable|string|max:255',
            'language_code' => 'nullable|string|max:20',
            'source' => 'nullable|string|max:255',
            'callback_data' => 'nullable|string|max:255',
            'default_country_code' => 'nullable|string|max:10',
            'template_params' => 'nullable|string',
            'header_params' => 'nullable|string',
            'body_params' => 'nullable|string',
            'media_url_param' => 'nullable|string|max:500',
            'media_filename' => 'nullable|string|max:255',
            'include_media' => 'nullable',
            'tags' => 'nullable|string',
            'attributes_json' => 'nullable|string',
            'ssl_verify' => 'nullable',
            'is_active' => 'nullable',
        ]);

        $config = WhatsappConfiguration::where('name', $data['name'])->first();
        if (!$config) {
            $config = new WhatsappConfiguration();
            $config->name = $data['name'];
        }

        if (empty($data['api_key'])) {
            if (!$config->exists) {
                return redirect()->back()->withInput()->with('error', 'API key is required for a new WhatsApp configuration.');
            }
            unset($data['api_key']);
        }

        if ($data['provider'] === WhatsappConfiguration::PROVIDER_AISENSY && empty(trim((string) ($data['campaign_name'] ?? '')))) {
            return redirect()->back()->withInput()->with('error', 'AiSensy campaign name is required.');
        }

        if ($data['provider'] === WhatsappConfiguration::PROVIDER_INTERAKT && empty(trim((string) ($data['template_name'] ?? '')))) {
            return redirect()->back()->withInput()->with('error', 'Interakt template name is required.');
        }

        $attributes = null;
        if (!empty($data['attributes_json'])) {
            $decoded = json_decode($data['attributes_json'], true);
            if (!is_array($decoded)) {
                return redirect()->back()->withInput()->with('error', 'Attributes must be valid JSON object.');
            }
            $attributes = $decoded;
        }

        $config->fill([
            'provider' => $data['provider'],
            'api_url' => $data['api_url'] ?? null,
            'campaign_name' => $data['campaign_name'] ?? null,
            'template_name' => $data['template_name'] ?? null,
            'language_code' => $data['language_code'] ?? 'en',
            'source' => $data['source'] ?? null,
            'callback_data' => $data['callback_data'] ?? null,
            'default_country_code' => $data['default_country_code'] ?: '+91',
            'template_params' => $data['template_params'] ?? null,
            'header_params' => $data['header_params'] ?? null,
            'body_params' => $data['body_params'] ?? null,
            'media_url_param' => $data['media_url_param'] ?? null,
            'media_filename' => $data['media_filename'] ?? null,
            'include_media' => $request->has('include_media'),
            'tags' => $data['tags'] ?? null,
            'attributes' => $attributes,
            'ssl_verify' => $request->has('ssl_verify'),
            'is_active' => $request->has('is_active'),
        ]);

        if (!empty($data['api_key'])) {
            $config->api_key = $data['api_key'];
        }

        $config->save();

        return redirect()->route('admin.e-badge.settings')
            ->with('success', 'WhatsApp configuration "' . $config->name . '" saved.');
    }
}
