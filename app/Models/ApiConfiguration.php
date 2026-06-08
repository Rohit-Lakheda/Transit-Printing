<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class ApiConfiguration extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'name',
        'api_key',
        'is_active',
        'field_mappings',
        'description',
        'endpoint_url',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'field_mappings' => 'array',
    ];

    /**
     * Generate a unique API key
     */
    public static function generateApiKey()
    {
        do {
            $key = 'api_' . bin2hex(random_bytes(16));
        } while (self::where('api_key', $key)->exists());
        
        return $key;
    }

    /**
     * Get the API endpoint URL
     */
    public function getEndpointUrlAttribute()
    {
        return url('/api/user-registration/' . $this->api_key);
    }
}
