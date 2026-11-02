<?php

use App\Models\Category;
use App\Models\Product;

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

it('never leaks a password hash through the user resource', function () {
    $body = $this->withHeaders(authHeader(cashier()))->getJson('/api/v1/auth/me')->json('data');

    expect($body)->not->toHaveKey('password')
        ->and($body)->not->toHaveKey('remember_token');
});
