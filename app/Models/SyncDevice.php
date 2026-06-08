<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncDevice extends Model
{
    protected $fillable = [
        'event_id',
        'device_id',
        'device_name',
        'device_type',
        'last_seen_at',
        'last_sync_at',
        'last_sync_status',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    public static function touchDevice(int $eventId, string $deviceId, ?string $deviceName = null, string $status = 'ok'): self
    {
        return static::updateOrCreate(
            ['event_id' => $eventId, 'device_id' => $deviceId],
            [
                'device_name' => $deviceName,
                'last_seen_at' => now(),
                'last_sync_at' => now(),
                'last_sync_status' => $status,
            ]
        );
    }
}
