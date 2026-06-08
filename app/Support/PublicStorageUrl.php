<?php

namespace App\Support;

class PublicStorageUrl
{
    public static function make(?string $path): string
    {
        if (!$path) {
            return '';
        }

        $relative = ltrim($path, '/');
        $relative = preg_replace('#^storage/app/public/#', '', $relative) ?? $relative;
        $relative = preg_replace('#^public/#', '', $relative) ?? $relative;

        return url('files/' . $relative);
    }
}
