<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class MasterBadge extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'regid',
        'reason',
    ];

    /**
     * Get all locations where this master badge is allowed
     */
    public function locations()
    {
        return $this->belongsToMany(Location::class, 'master_badge_locations')
            ->withTimestamps();
    }

    /**
     * Check if master badge is allowed at a specific location
     */
    public function isAllowedAt($locationId)
    {
        return $this->locations()->where('location_id', $locationId)->exists();
    }
}
