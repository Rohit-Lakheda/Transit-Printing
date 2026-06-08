<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class MailConfiguration extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'name',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
        'use_auth',
        'is_active',
    ];

    protected $casts = [
        'use_auth' => 'boolean',
        'is_active' => 'boolean',
    ];
}

