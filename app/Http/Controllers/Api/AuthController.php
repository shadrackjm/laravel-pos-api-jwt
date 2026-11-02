<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends ApiController
{
    public function __construct(private TokenService $tokens) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Note: no Hash::make(). Laravel 13's User model casts `password` to
        // 'hashed', so assigning the plain value hashes it exactly once.
        // Calling Hash::make() here as well is not a double-hash (the cast
        // checks first) but it IS dead code — see episode 10.
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            // The role is NOT taken from the request. Letting a client pick its
            // own role means anyone can register as a manager.
            'role' => 'cashier',
        ]);

        return $this->success($this->tokenPayload($user), 'Registration successful.', 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        // One identical answer for "no such email" and "wrong password".
        // Distinguishing them turns this endpoint into a way to discover which
        // email addresses have accounts.
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->error('Invalid credentials.', 401);
        }

        return $this->success($this->tokenPayload($user), 'Login successful.');
    }

    public function me(): JsonResponse
    {
        return $this->success(new UserResource(auth()->user()));
    }

    /**
     * Episode 12 — trade a refresh token for a fresh pair.
     */
    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $result = $this->tokens->rotateRefreshToken($validated['refresh_token']);

        if (! $result) {
            return $this->error('Refresh token is invalid, expired or already used.', 401);
        }

        return $this->success([
            'user' => new UserResource($result['user']),
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $this->tokens->accessTokenTtl(),
        ], 'Token refreshed.');
    }

    /**
     * Episode 12 — real logout.
     *
     * A JWT is valid until it expires; there is nothing on the server to delete.
     * So logout does two things: deny THIS access token by its `jti` until it
     * would have expired anyway, and revoke the user's refresh tokens so no new
     * access token can be minted.
     */
    public function logout(Request $request): JsonResponse
    {
        $claims = $request->attributes->get('jwt_claims');

        if ($claims) {
            $this->tokens->deny($claims);
        }

        $this->tokens->revokeAllRefreshTokens(auth()->user());

        return $this->success(null, 'Logged out successfully.');
    }

    private function tokenPayload(User $user): array
    {
        return [
            'user' => new UserResource($user),
            'access_token' => $this->tokens->issue($user),
            'refresh_token' => $this->tokens->issueRefreshToken($user),
            'token_type' => 'Bearer',
            'expires_in' => $this->tokens->accessTokenTtl(),
        ];
    }
}
