<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class UserCredential extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'user_detail_id',
        'username',
        'password',
        'remember_token',
        'max_devices',
        'max_leads',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_devices' => 'integer',
        'max_leads' => 'integer',
    ];

    public function userDetail()
    {
        return $this->belongsTo(UserDetail::class);
    }

    public function deviceLogins()
    {
        return $this->hasMany(UserDeviceLogin::class);
    }
}

