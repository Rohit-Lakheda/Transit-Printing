<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class LeadScanAttemptLog extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'scanned_by_user_id',
        'lead_scan_id',
        'regid',
        'status',
        'message',
        'scanned_at',
        'source',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];
}

