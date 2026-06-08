<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class LeadPasswordReset extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'user_credential_id',
        'email',
        'token_hash',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function credential()
    {
        return $this->belongsTo(UserCredential::class, 'user_credential_id');
    }
}

