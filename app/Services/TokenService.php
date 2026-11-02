<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;

/**
 * Everything this app knows about making and checking tokens, in one place.
 *
 * Episodes 10 (issuing), 11 (verifying) and 12 (refresh + logout denylist).
 */
class TokenService
{
    private string $secret;

    private string $algo = 'HS256';

    /** Access tokens are deliberately short-lived — see episode 9. */
    private int $accessTokenTtl = 900;          // 15 minutes

    public function __construct()
    {
        $secret = config('services.jwt.secret');

        // Fail loudly at boot rather than silently signing every token with "".
        // An empty signing key means anyone can forge any token.
        if (blank($secret)) {
            throw new \RuntimeException('JWT_SECRET is not set. Run: php artisan jwt:secret');
        }

        $this->secret = $secret;
    }

    /**
     * Mint a signed access token for a user.
     */
    public function issue(User $user): string
    {
        $now = time();

        $payload = [
            'sub' => (string) $user->id,
            'name' => $user->name,
            'role' => $user->role,
            // A unique ID for THIS token, so a single token can be revoked
            // on logout without revoking every token the user holds (episode 12).
            'jti' => (string) Str::uuid(),
            'iat' => $now,
            'exp' => $now + $this->accessTokenTtl,
        ];

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Verify a token and return its claims, or null if it is in any way not good.
     */
    public function decode(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secret, $this->algo));
        } catch (ExpiredException) {
            return null;
        } catch (\Throwable) {
            // Bad signature, malformed, wrong algorithm — all equally "no".
            return null;
        }
    }

    public function userFromToken(string $token): ?User
    {
        $claims = $this->decode($token);

        return $claims ? User::find($claims->sub) : null;
    }

    public function accessTokenTtl(): int
    {
        return $this->accessTokenTtl;
    }
}
