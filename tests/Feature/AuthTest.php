<?php

use App\Models\User;
use App\Services\TokenService;
use Firebase\JWT\JWT;

it('registers a cashier and returns a token pair', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Cashier',
        'email' => 'jane@pos.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['user', 'access_token', 'refresh_token', 'expires_in']]);
});

it('never lets a client choose its own role', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Sneaky',
        'email' => 'sneaky@pos.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'manager',
    ])->assertCreated();

    expect(User::where('email', 'sneaky@pos.test')->first()->role)->toBe('cashier');
});

it('stores the password hashed, never in plain text', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane',
        'email' => 'jane@pos.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    expect(User::first()->password)->not->toBe('password123')->toStartWith('$');
});

it('gives the same answer for a wrong password and an unknown email', function () {
    User::factory()->create(['email' => 'jane@pos.test']);

    $wrongPassword = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@pos.test',
        'password' => 'not-the-password',
    ])->assertStatus(401);

    $unknownEmail = $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@pos.test',
        'password' => 'not-the-password',
    ])->assertStatus(401);

    // Identical wording — otherwise this endpoint reveals which emails exist.
    expect($wrongPassword->json('message'))->toBe($unknownEmail->json('message'));
});

it('rejects a request with no token', function () {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('rejects a token signed with the wrong secret', function () {
    $user = cashier();

    // Note the length: firebase/php-jwt 7.x refuses to sign HS256 with a key
    // shorter than 256 bits, so even an attacker's fake secret has to be long.
    $forged = JWT::encode(
        ['sub' => (string) $user->id, 'jti' => 'x', 'iat' => time(), 'exp' => time() + 900],
        str_repeat('wrong-secret-', 8),
        'HS256'
    );

    $this->withHeaders(['Authorization' => "Bearer {$forged}"])
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401);
});

it('rejects an expired token', function () {
    $user = cashier();

    $expired = JWT::encode(
        ['sub' => (string) $user->id, 'jti' => 'x', 'iat' => time() - 4000, 'exp' => time() - 60],
        config('services.jwt.secret'),
        'HS256'
    );

    $this->withHeaders(['Authorization' => "Bearer {$expired}"])
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401);
});

it('rejects a tampered payload', function () {
    $user = cashier();
    $token = app(TokenService::class)->issue($user);

    // Flip a character in the payload segment; the signature no longer matches.
    [$header, $payload, $signature] = explode('.', $token);
    $tampered = $header.'.'.strrev($payload).'.'.$signature;

    $this->withHeaders(['Authorization' => "Bearer {$tampered}"])
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401);
});

it('stops accepting an access token after logout', function () {
    $user = cashier();
    $headers = authHeader($user);

    $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertOk();

    $this->withHeaders($headers)->postJson('/api/v1/auth/logout')->assertOk();

    // This is the bit most JWT tutorials skip: without a denylist the token
    // below would still work for the rest of its lifetime.
    $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('exchanges a refresh token for a new pair and rotates it', function () {
    $tokens = app(TokenService::class);
    $user = cashier();
    $refresh = $tokens->issueRefreshToken($user);

    $response = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh])
        ->assertOk()
        ->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);

    expect($response->json('data.refresh_token'))->not->toBe($refresh);

    // The old one is now dead — that rotation is what reveals a stolen token.
    $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh])->assertStatus(401);
});

it('stores only a hash of the refresh token', function () {
    $user = cashier();
    $raw = app(TokenService::class)->issueRefreshToken($user);

    expect($user->refreshTokens()->first()->token_hash)
        ->not->toBe($raw)
        ->toBe(hash('sha256', $raw));
});

it('does not let normal API traffic use up the login limit', function () {
    $user = cashier();
    $token = app(TokenService::class)->issue($user);

    // A busy till makes far more than five calls a minute...
    foreach (range(1, 6) as $_) {
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
    }

    // ...and the next login must still get through. Each throttle needs its
    // own name, or they all count against one bucket per IP address.
    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();
});

it('still stops a sixth login attempt in a minute', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/login', ['email' => 'jane@pos.test', 'password' => 'guess']);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'jane@pos.test', 'password' => 'guess'])
        ->assertStatus(429);
});
