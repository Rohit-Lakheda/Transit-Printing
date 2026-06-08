<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class EBadgeLayoutSetting extends Model
{
    use BelongsToEventScope;

    protected $table = 'e_badge_layout_settings';

    protected $fillable = [
        'event_id',
        'Category',
        'field_name',
        'static_text_key',
        'static_text_value',
        'margin_top',
        'margin_left',
        'margin_right',
        'sequence',
        'text_align',
        'font_family',
        'font_weight',
        'color',
        'font_size',
        'width',
        'height',
    ];

    protected $casts = [
        'margin_top' => 'decimal:2',
        'margin_left' => 'decimal:2',
        'margin_right' => 'decimal:2',
        'sequence' => 'integer',
        'font_size' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
    ];
}
