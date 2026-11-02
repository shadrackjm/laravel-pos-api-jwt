<?php

use App\Models\Category;
use App\Models\Product;
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
});

it('lets a manager create a product', function () {
    $this->withHeaders(authHeader(manager()))
        ->postJson('/api/v1/products', [
            'category_id' => $this->category->id,
            'name' => 'Teapot',
            'sku' => 'POT-001',
            'price' => '19.99',
            'stock' => 5,
        ])
        ->assertCreated();
});

it('forbids a cashier from creating a product with 403, not 401', function () {
    // The distinction matters: 401 means "log in", which a cashier already has
    // done. 403 correctly says "you are known, and still not allowed".
    $this->withHeaders(authHeader(cashier()))
        ->postJson('/api/v1/products', [
            'category_id' => $this->category->id,
            'name' => 'Teapot',
            'sku' => 'POT-001',
            'price' => '19.99',
            'stock' => 5,
        ])
        ->assertStatus(403);
});

it('answers 401 rather than 403 when no token is sent at all', function () {
    $this->postJson('/api/v1/products', [])->assertStatus(401);
});

it('lets a cashier read products', function () {
    $this->withHeaders(authHeader(cashier()))
        ->getJson('/api/v1/products')
        ->assertOk();
});

it('hides one cashier\'s sales from another', function () {
    $jane = cashier();
    $bob = cashier();

    app(CheckoutService::class)->checkout($jane, [['product_id' => $this->mug->id, 'quantity' => 1]]);

    $this->withHeaders(authHeader($jane))->getJson('/api/v1/sales')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withHeaders(authHeader($bob))->getJson('/api/v1/sales')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('stops a cashier reading another cashier\'s sale by uuid (IDOR)', function () {
    $jane = cashier();
    $bob = cashier();

    $sale = app(CheckoutService::class)->checkout($jane, [['product_id' => $this->mug->id, 'quantity' => 1]]);

    // 404 rather than 403 — a 403 would confirm the sale exists.
    $this->withHeaders(authHeader($bob))
        ->getJson("/api/v1/sales/{$sale->uuid}")
        ->assertStatus(404);

    $this->withHeaders(authHeader($jane))
        ->getJson("/api/v1/sales/{$sale->uuid}")
        ->assertOk();
});

it('lets a manager see every sale', function () {
    $jane = cashier();
    app(CheckoutService::class)->checkout($jane, [['product_id' => $this->mug->id, 'quantity' => 1]]);

    $this->withHeaders(authHeader(manager()))
        ->getJson('/api/v1/sales')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('never exposes a sequential sale id', function () {
    $jane = cashier();
    $sale = app(CheckoutService::class)->checkout($jane, [['product_id' => $this->mug->id, 'quantity' => 1]]);

    $body = $this->withHeaders(authHeader($jane))->getJson("/api/v1/sales/{$sale->uuid}")->json('data');

    expect($body)->not->toHaveKey('id');
});

it('never leaks a password hash through the user resource', function () {
    $body = $this->withHeaders(authHeader(cashier()))->getJson('/api/v1/auth/me')->json('data');

    expect($body)->not->toHaveKey('password')
        ->and($body)->not->toHaveKey('remember_token');
});
