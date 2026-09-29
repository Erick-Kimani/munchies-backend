<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return response()->json([
                'error' => 'Unauthorized. Please log in.'
            ], 401);
        }

        // 403, not 401: the user IS authenticated, they just don't have
        // permission. The frontend's axios interceptor treats any 401 as
        // "session expired" and force-logs the user out -- so a
        // logged-in non-admin who hits an admin-only route (e.g. a stale
        // bookmark) was previously being incorrectly logged out, rather
        // than just shown "you don't have permission."
        if (! $request->user()->isAdmin()) {
            return response()->json([
                'error' => 'Forbidden. Admin only.'
            ], 403);
        }

        return $next($request);
    }
}
