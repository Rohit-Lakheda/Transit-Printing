<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class BlockedRegid extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'regid',
        'reason',
    ];

    /**
     * Get all locations where this RegID is blocked
     */
    public function locations()
    {
        return $this->belongsToMany(Location::class, 'blocked_regid_locations')
            ->withTimestamps();
    }

    /**
     * Check if RegID is blocked at a specific location
     */
    public function isBlockedAt($locationId)
    {
        return $this->locations()->where('location_id', $locationId)->exists();
    }
}
