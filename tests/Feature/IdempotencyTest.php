<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;

beforeEach(function () {
    $this->cashier = cashier();

    $this->mug = Product::create([
        'category_id' => Category::create(['name' => 'Drinks'])->id,
        'name' => 'Coffee Mug',
        'sku' => 'MUG-001',
        'price' => 12.50,
        'stock' => 40,
    ]);

    $this->body = ['items' => [['product_id' => $this->mug->id, 'quantity' => 2]]];
});

it('charges once when the same checkout is retried', function () {
    $headers = authHeader($this->cashier) + ['Idempotency-Key' => 'checkout-abc-123'];

    $first = $this->withHeaders($headers)->postJson('/api/v1/sales', $this->body)->assertCreated();

    // The tablet lost Wi-Fi and the cashier tapped Pay again.
    $second = $this->withHeaders($headers)->postJson('/api/v1/sales', $this->body)->assertCreated();

    // Same receipt returned, and only one sale exists.
    expect($second->json('data.uuid'))->toBe($first->json('data.uuid'))
        ->and(Sale::count())->toBe(1)
        ->and($this->mug->fresh()->stock)->toBe(38);

    $second->assertHeader('Idempotent-Replay', 'true');
});

it('rejects a checkout with no idempotency key', function () {
    $this->withHeaders(authHeader($this->cashier))
        ->postJson('/api/v1/sales', $this->body)
        ->assertStatus(400);

    expect(Sale::count())->toBe(0);
});

it('returns 409 when a key is reused with a different basket', function () {
    $headers = authHeader($this->cashier) + ['Idempotency-Key' => 'checkout-abc-123'];

    $this->withHeaders($headers)->postJson('/api/v1/sales', $this->body)->assertCreated();

    // Same key, different items — replaying would return the wrong receipt.
    $this->withHeaders($headers)
        ->postJson('/api/v1/sales', ['items' => [['product_id' => $this->mug->id, 'quantity' => 9]]])
        ->assertStatus(409);

    expect(Sale::count())->toBe(1);
});

it('does not let one cashier replay another cashier\'s key', function () {
    $headers = authHeader($this->cashier) + ['Idempotency-Key' => 'shared-key'];
    $this->withHeaders($headers)->postJson('/api/v1/sales', $this->body)->assertCreated();

    $other = authHeader(cashier()) + ['Idempotency-Key' => 'shared-key'];
    $this->withHeaders($other)->postJson('/api/v1/sales', $this->body)->assertStatus(409);
});

it('allows a retry with the same key after a failure', function () {
    $headers = authHeader($this->cashier) + ['Idempotency-Key' => 'retry-key'];

    // Fails: not enough stock.
    $this->withHeaders($headers)
        ->postJson('/api/v1/sales', ['items' => [['product_id' => $this->mug->id, 'quantity' => 999]]])
        ->assertStatus(422);

    // A failed attempt must not burn the key — the cashier fixes the basket
    // and tries again on the same attempt.
    $this->withHeaders($headers)
        ->postJson('/api/v1/sales', ['items' => [['product_id' => $this->mug->id, 'quantity' => 1]]])
        ->assertCreated();

    expect(Sale::count())->toBe(1);
});

it('treats different keys as different sales', function () {
    $this->withHeaders(authHeader($this->cashier) + ['Idempotency-Key' => 'key-1'])
        ->postJson('/api/v1/sales', $this->body)->assertCreated();

    $this->withHeaders(authHeader($this->cashier) + ['Idempotency-Key' => 'key-2'])
        ->postJson('/api/v1/sales', $this->body)->assertCreated();

    expect(Sale::count())->toBe(2)
        ->and($this->mug->fresh()->stock)->toBe(36);
});
