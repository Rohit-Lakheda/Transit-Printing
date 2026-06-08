<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyOfflineSyncToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('offline.sync_token');
        if (empty($expected)) {
            return $next($request);
        }

        $provided = $request->header('X-Offline-Sync-Token')
            ?? $request->input('sync_token');

        if (!hash_equals((string) $expected, (string) $provided)) {
            return response()->json(['success' => false, 'message' => 'Invalid sync token.'], 403);
        }

        return $next($request);
    }
}
