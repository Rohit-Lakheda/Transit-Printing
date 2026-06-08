<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class LeadScan extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'user_detail_id',
        'regid',
        'scanned_at',
        'device_id',
        'scanned_by_user_id',
        'source',
        'location_name',
        'lead_type',
        'lead_comments',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function userDetail()
    {
        return $this->belongsTo(UserDetail::class);
    }
}

