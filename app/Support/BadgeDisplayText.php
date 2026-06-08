<?php

namespace App\Support;

class BadgeDisplayText
{
    public static function format(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        return mb_strtoupper($text, 'UTF-8');
    }
}
