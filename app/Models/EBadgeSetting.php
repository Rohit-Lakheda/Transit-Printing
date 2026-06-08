<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class EBadgeSetting extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'name',
        'email_subject',
        'email_body',
        'mail_configuration_id',
    ];

    public static function getDefault(): self
    {
        return static::firstOrCreate(
            ['name' => 'default'],
            [
                'email_subject' => 'Your E-Badge for {{Category}}',
                'email_body' => '<p>Dear {{Name}},</p><p>Please find your e-badge attached.</p><p>Category: {{Category}}<br>RegID: {{RegID}}</p><p>Regards,<br>Event Team</p>',
                'mail_configuration_id' => null,
            ]
        );
    }
}
