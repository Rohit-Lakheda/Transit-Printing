<?php

namespace App\Models\Concerns;

trait BelongsToEventScope
{
    protected static function bootBelongsToEventScope(): void
    {
        // Multi-event scoping disabled (single-event mode).
    }
}
