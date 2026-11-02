<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Episode 18 — the duplicate checkout problem.
 *
 * The story: a cashier taps Pay. The tablet sends the request, the sale is
 * recorded, and then the Wi-Fi drops before the response gets back. The tablet
 * shows an error. The cashier taps Pay again. Without this middleware the
 * customer is charged twice and stock drops twice.
 *
 * The fix: the client generates a unique key per checkout ATTEMPT (not per
 * retry) and sends it as `Idempotency-Key`. We remember the first response for
 * that key and replay it on any retry.
 */
class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        // No key sent — nothing we can do. We require it on money endpoints
        // rather than silently allowing double charges.
        if (blank($key)) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint requires an Idempotency-Key header.',
            ], 400);
        }

        $userId = auth()->id();
        $requestHash = hash('sha256', $request->getContent());

        if ($replay = $this->replay($key, $userId, $requestHash)) {
            return $replay;
        }

        // An atomic lock, NOT a "check then write". Two retries can arrive
        // milliseconds apart; a boolean flag in the cache loses that race.
        $lock = Cache::lock("idempotency:{$key}", 30);

        if (! $lock->get()) {
            // A first attempt with this key is still in flight. Telling the
            // client to retry is safer than running the checkout a second time.
            return response()->json([
                'success' => false,
                'message' => 'A request with this Idempotency-Key is already in progress.',
            ], 409);
        }

        try {
            // Re-check inside the lock: the in-flight request may have finished
            // between our first look and acquiring the lock.
            if ($replay = $this->replay($key, $userId, $requestHash)) {
                return $replay;
            }

            $response = $next($request);

            // Only remember successful outcomes. A failed checkout SHOULD be
            // retryable with the same key.
            if ($response->getStatusCode() < 400) {
                IdempotencyKey::create([
                    'key' => $key,
                    'user_id' => $userId,
                    'request_hash' => $requestHash,
                    'response_status' => $response->getStatusCode(),
                    'response_body' => json_decode($response->getContent(), true),
                ]);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * Replay a stored response, or 409 if the same key arrives with a different body.
     */
    private function replay(string $key, ?int $userId, string $requestHash): ?Response
    {
        $existing = IdempotencyKey::where('key', $key)->first();

        if (! $existing) {
            return null;
        }

        // Same key, different payload = the client reused a key by mistake.
        // Replaying here would hand back a receipt for a DIFFERENT basket.
        if ($existing->request_hash !== $requestHash || $existing->user_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'This Idempotency-Key was already used with a different request.',
            ], 409);
        }

        return response()->json($existing->response_body, $existing->response_status)
            ->header('Idempotent-Replay', 'true');
    }
}
