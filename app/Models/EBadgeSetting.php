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
        'whatsapp_configuration_id',
        'auto_send_email_on_api_registration',
        'auto_send_whatsapp_on_api_registration',
    ];

    protected $casts = [
        'auto_send_email_on_api_registration' => 'boolean',
        'auto_send_whatsapp_on_api_registration' => 'boolean',
    ];

    public static function getDefault(): self
    {
        return static::firstOrCreate(
            ['name' => 'default'],
            [
                'email_subject' => 'Your E-Badge for {{Category}}',
                'email_body' => '<p>Dear {{Name}},</p><p>Please find your e-badge attached.</p><p>Category: {{Category}}<br>RegID: {{RegID}}</p><p>Regards,<br>Event Team</p>',
                'mail_configuration_id' => null,
                'auto_send_email_on_api_registration' => true,
                'auto_send_whatsapp_on_api_registration' => true,
            ]
        );
    }
}
