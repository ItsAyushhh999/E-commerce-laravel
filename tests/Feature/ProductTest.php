<?php

use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $this->productData = [
        'name' => 'Basic Tshirt',
        'description' => 'A simple t-shirt',
        'variants' => [
            [
                'size' => 'S',
                'color' => 'Red',
                'price' => 9.99,
                'stock' => 10,
            ],
            [
                'size' => 'M',
                'color' => 'Blue',
                'price' => 11.99,
                'stock' => 5,
            ],
        ],
    ];
});

// ===========================
// View Products Tests
// ===========================

test('anyone can view all products', function () {
    $response = $this->getJson('/api/products');

    $response->assertStatus(200);
});

test('anyone can view single product', function () {
    $product = Product::factory()->create();

    $response = $this->getJson("/api/products/{$product->id}");

    $response->assertStatus(200);
    expect($response->json('id'))->toBe($product->id);
});

test('returns 404 for non existing product', function () {
    $response = $this->getJson('/api/products/99999');

    $response->assertStatus(404);
});

// ===========================
// Create Product Tests
// ===========================

test('admin can create product with variants', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/products', $this->productData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'product' => [
                'id',
                'name',
                'variants',
            ],
        ]);

    expect($response->json('product.name'))->toBe('Basic Tshirt');
    expect(count($response->json('product.variants')))->toBe(2);
});

test('admin cannot create product with missing fields', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/products', [
        'name' => 'Basic Tshirt',
        // missing variants
    ]);

    $response->assertStatus(422);
});

test('customer cannot create product', function () {
    Sanctum::actingAs($this->customer);

    $response = $this->postJson('/api/products', $this->productData);

    $response->assertStatus(403);
});

test('unauthenticated user cannot create product', function () {
    $response = $this->postJson('/api/products', $this->productData);

    $response->assertStatus(401);
});

// ===========================
// Update Product Tests
// ===========================

test('admin can update product', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::factory()->create();

    $response = $this->putJson("/api/products/{$product->id}", [
        'name' => 'Updated Tshirt',
    ]);

    $response->assertStatus(200);
    expect($response->json('product.name'))->toBe('Updated Tshirt');
});

test('customer cannot update product', function () {
    Sanctum::actingAs($this->customer);

    $product = Product::factory()->create();

    $response = $this->putJson("/api/products/{$product->id}", [
        'name' => 'Updated Tshirt',
    ]);

    $response->assertStatus(403);
});

// ===========================
// Delete Product Tests
// ===========================

test('admin can delete product', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::factory()->create();

    $response = $this->deleteJson("/api/products/{$product->id}");

    $response->assertStatus(200);
    expect(Product::find($product->id))->toBeNull();
});

test('customer cannot delete product', function () {
    Sanctum::actingAs($this->customer);

    $product = Product::factory()->create();

    $response = $this->deleteJson("/api/products/{$product->id}");

    $response->assertStatus(403);
});
