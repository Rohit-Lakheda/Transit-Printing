<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class PrintingLog extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'client_print_id',
        'device_id',
        'source',
        'regid',
        'user_name',
        'category',
        'print_type',
        'printed_at',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
    ];
}
