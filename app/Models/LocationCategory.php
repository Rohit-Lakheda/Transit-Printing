<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class LocationCategory extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'location_id',
        'category',
    ];

    /**
     * Get the location that owns this category mapping
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
