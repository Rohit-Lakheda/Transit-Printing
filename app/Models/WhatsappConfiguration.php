<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class WhatsappConfiguration extends Model
{
    use BelongsToEventScope;

    public const PROVIDER_AISENSY = 'aisensy';

    public const PROVIDER_INTERAKT = 'interakt';

    protected $fillable = [
        'event_id',
        'name',
        'provider',
        'api_key',
        'api_url',
        'campaign_name',
        'template_name',
        'language_code',
        'source',
        'callback_data',
        'default_country_code',
        'template_params',
        'header_params',
        'body_params',
        'media_url_param',
        'media_filename',
        'include_media',
        'tags',
        'attributes',
        'ssl_verify',
        'is_active',
    ];

    protected $casts = [
        'include_media' => 'boolean',
        'ssl_verify' => 'boolean',
        'is_active' => 'boolean',
        'attributes' => 'array',
    ];

    public function defaultApiUrl(): string
    {
        if ($this->api_url) {
            return $this->api_url;
        }

        return match ($this->provider) {
            self::PROVIDER_AISENSY => 'https://backend.aisensy.com/campaign/t1/api/v2',
            self::PROVIDER_INTERAKT => 'https://api.interakt.ai/v1/public/message/',
            default => '',
        };
    }

    public function providerLabel(): string
    {
        return match ($this->provider) {
            self::PROVIDER_AISENSY => 'AiSensy',
            self::PROVIDER_INTERAKT => 'Interakt',
            default => ucfirst((string) $this->provider),
        };
    }
}
