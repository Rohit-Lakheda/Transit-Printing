<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncDeadLetterLog extends Model
{
    protected $fillable = [
        'event_id',
        'device_id',
        'entity_type',
        'payload',
        'error_message',
        'retry_count',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'failed_at' => 'datetime',
    ];
}
