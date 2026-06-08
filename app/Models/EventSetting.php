<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class EventSetting extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'logo_path',
        'email_logo_path',
        'scanning_type',
        'print_scanning_type',
    ];

    /**
     * Get the current event settings (singleton pattern)
     */
    public static function getSettings()
    {
        return static::firstOrCreate(
            ['id' => 1],
            ['scanning_type' => 'camera', 'print_scanning_type' => 'camera']
        );
    }
}

