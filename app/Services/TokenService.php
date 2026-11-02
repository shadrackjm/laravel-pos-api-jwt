<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
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

    private int $refreshTokenTtlDays = 30;

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
            $claims = JWT::decode($token, new Key($this->secret, $this->algo));
        } catch (ExpiredException) {
            return null;
        } catch (\Throwable) {
            // Bad signature, malformed, wrong algorithm — all equally "no".
            return null;
        }

        // Episode 12: a JWT is valid until it expires, so "logout" needs a denylist.
        if ($this->isDenied($claims->jti ?? null)) {
            return null;
        }

        return $claims;
    }

    public function userFromToken(string $token): ?User
    {
        $claims = $this->decode($token);

        return $claims ? User::find($claims->sub) : null;
    }

    /**
     * Revoke one access token by remembering its `jti` until it would have expired.
     *
     * We only need to store it for its remaining lifetime — after that the token
     * fails the expiry check anyway, so the denylist stays small on its own.
     */
    public function deny(object $claims): void
    {
        $secondsLeft = ($claims->exp ?? time()) - time();

        if ($secondsLeft > 0 && isset($claims->jti)) {
            Cache::put("jwt:denied:{$claims->jti}", true, $secondsLeft);
        }
    }

    public function isDenied(?string $jti): bool
    {
        return $jti !== null && Cache::has("jwt:denied:{$jti}");
    }

    /**
     * Issue a refresh token. We hand the caller the raw value ONCE and store only
     * its hash — the same reasoning as passwords.
     */
    public function issueRefreshToken(User $user): string
    {
        $raw = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => now()->addDays($this->refreshTokenTtlDays),
        ]);

        return $raw;
    }

    /**
     * Exchange a refresh token for a new pair, rotating the refresh token.
     *
     * Rotation matters: if a refresh token is stolen and used, the legitimate
     * client's next refresh fails, which is how you find out.
     */
    public function rotateRefreshToken(string $raw): ?array
    {
        $record = RefreshToken::where('token_hash', hash('sha256', $raw))->first();

        if (! $record || ! $record->isUsable()) {
            return null;
        }

        $record->update(['revoked_at' => now()]);

        $user = $record->user;

        return [
            'access_token' => $this->issue($user),
            'refresh_token' => $this->issueRefreshToken($user),
            'user' => $user,
        ];
    }

    public function revokeAllRefreshTokens(User $user): void
    {
        $user->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public function accessTokenTtl(): int
    {
        return $this->accessTokenTtl;
    }
}
