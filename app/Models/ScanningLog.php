<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class ScanningLog extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'client_scan_id',
        'device_id',
        'source',
        'location_id',
        'location_name',
        'regid',
        'user_name',
        'category',
        'is_allowed',
        'reason',
        'scanned_at',
    ];

    protected $casts = [
        'is_allowed' => 'boolean',
        'scanned_at' => 'datetime',
    ];

    /**
     * Get the location that this log belongs to
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
