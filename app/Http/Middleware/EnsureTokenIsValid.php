<?php

namespace App\Http\Middleware;

use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gate. Episode 11.
 */
class EnsureTokenIsValid
{
    public function __construct(private TokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication token not provided.',
            ], 401);
        }

        $claims = $this->tokens->decode($token);

        if (! $claims) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication token is invalid or expired.',
            ], 401);
        }

        $user = \App\Models\User::find($claims->sub);

        if (! $user) {
            // The token is cryptographically fine but the user is gone.
            return response()->json([
                'success' => false,
                'message' => 'Authentication token is invalid or expired.',
            ], 401);
        }

        auth()->setUser($user);

        // Stash the claims so the logout endpoint can deny this exact token.
        $request->attributes->set('jwt_claims', $claims);

        return $next($request);
    }
}
