<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class BypassedRegidUsageLog extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'bypassed_regid_id',
        'location_id',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    /**
     * Get the bypassed RegID
     */
    public function bypassedRegid()
    {
        return $this->belongsTo(BypassedRegid::class);
    }

    /**
     * Get the location
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
