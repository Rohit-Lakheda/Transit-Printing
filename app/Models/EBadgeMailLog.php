<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class EBadgeMailLog extends Model
{
    use BelongsToEventScope;

    protected $table = 'e_badge_mail_logs';

    protected $fillable = [
        'event_id',
        'user_detail_id',
        'regid',
        'category',
        'email',
        'status',
        'message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
