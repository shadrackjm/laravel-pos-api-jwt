<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Services\CheckoutService;

beforeEach(function () {
    $this->category = Category::create(['name' => 'Drinks']);

    $this->mug = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Coffee Mug',
        'sku' => 'MUG-001',
        'price' => 12.50,
        'stock' => 40,
    ]);

    $this->tea = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Green Tea Box',
        'sku' => 'TEA-001',
        'price' => 8.25,
        'stock' => 15,
    ]);
});

it('records a sale, reduces stock and totals correctly', function () {
    $cashier = cashier();

    $response = $this->withHeaders(authHeader($cashier) + ['Idempotency-Key' => 'test-key-1'])
        ->postJson('/api/v1/sales', [
            'items' => [
                ['product_id' => $this->mug->id, 'quantity' => 3],
                ['product_id' => $this->tea->id, 'quantity' => 2],
            ],
        ]);

    // 3 × 12.50 = 37.50, 2 × 8.25 = 16.50, total 54.00
    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total', '54.00')
        ->assertJsonPath('data.status', 'completed');

    expect($this->mug->fresh()->stock)->toBe(37)
        ->and($this->tea->fresh()->stock)->toBe(13);
});

it('refuses to oversell and leaves stock untouched', function () {
    $cashier = cashier();

    $this->withHeaders(authHeader($cashier) + ['Idempotency-Key' => 'test-key-3'])
        ->postJson('/api/v1/sales', [
            'items' => [['product_id' => $this->tea->id, 'quantity' => 999]],
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    // The whole transaction rolled back — nothing was half-applied.
    expect($this->tea->fresh()->stock)->toBe(15)
        ->and(Sale::count())->toBe(0);
});

it('rolls the entire sale back when one line fails', function () {
    $cashier = cashier();

    // First line is fine, second is impossible. Neither must be applied.
    $this->withHeaders(authHeader($cashier) + ['Idempotency-Key' => 'test-key-4'])
        ->postJson('/api/v1/sales', [
            'items' => [
                ['product_id' => $this->mug->id, 'quantity' => 1],
                ['product_id' => $this->tea->id, 'quantity' => 999],
            ],
        ])
        ->assertStatus(422);

    expect($this->mug->fresh()->stock)->toBe(40)
        ->and($this->tea->fresh()->stock)->toBe(15)
        ->and(Sale::count())->toBe(0);
});

it('never trusts a client-supplied total', function () {
    $cashier = cashier();

    $response = $this->withHeaders(authHeader($cashier) + ['Idempotency-Key' => 'test-key-5'])
        ->postJson('/api/v1/sales', [
            'items' => [['product_id' => $this->mug->id, 'quantity' => 2]],
            'total' => '0.01',          // a tampered client trying to pay a penny
            'user_id' => 9999,          // and trying to blame someone else
        ]);

    $response->assertCreated()->assertJsonPath('data.total', '25.00');

    expect(Sale::first()->user_id)->toBe($cashier->id);
});

it('freezes the product name onto the receipt', function () {
    $sale = app(CheckoutService::class)->checkout(
        cashier(),
        [['product_id' => $this->mug->id, 'quantity' => 1]],
    );

    $this->mug->update(['name' => 'Renamed Mug']);

    expect($sale->items->first()->product_name)->toBe('Coffee Mug');
});
