<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorization, not authentication. Episode 14.
 *
 * 401 says "I don't know who you are". 403 says "I know exactly who you are,
 * and you're still not allowed". Conflating them is one of the most common
 * API mistakes — and it leaks information either way if you get it backwards.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = auth()->user();

        // No user at all is a 401 — this middleware must run AFTER jwt.auth.
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication token not provided.',
            ], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
