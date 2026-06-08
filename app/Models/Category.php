<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEventScope;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use BelongsToEventScope;

    protected $fillable = [
        'event_id',
        'Prefix',
        'Category',
        'badge_width',
        'badge_height',
        'unique_printing',
        'receipt_number_required',
        'e_badge_background_path',
    ];

    protected $casts = [
        'badge_width' => 'decimal:2',
        'badge_height' => 'decimal:2',
        'unique_printing' => 'boolean',
        'receipt_number_required' => 'boolean',
    ];
}
