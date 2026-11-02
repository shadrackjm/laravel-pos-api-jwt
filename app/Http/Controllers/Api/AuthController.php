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

    private function tokenPayload(User $user): array
    {
        return [
            'user' => new UserResource($user),
            'access_token' => $this->tokens->issue($user),
            'token_type' => 'Bearer',
            'expires_in' => $this->tokens->accessTokenTtl(),
        ];
    }
}
