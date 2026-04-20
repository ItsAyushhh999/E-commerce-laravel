<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $this->product = Product::factory()->create();

    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'price' => 9.99,
        'stock' => 10,
    ]);
});

// ========================================================
// Customer Tests
// ========================================================
test('customer can add item to cart', function () {
    Sanctum::actingAs($this->customer);

    $response = $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity' => 2,
    ]);

    $response->assertSuccessful()
        ->assertJson(['message' => 'Item added to cart']);

    expect($response->json('cart_item.quantity'))->toBe(2);
});

test('adding same item increases quantity', function () {
    Sanctum::actingAs($this->customer);

    $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity' => 2,
    ]);

    $response = $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity' => 3,
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Cart updated successfully']);

    expect($response->json('cart_item.quantity'))->toBe(5);
});

test('customer cannot add more than available stock', function () {
    Sanctum::actingAs($this->customer);

    $response = $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity' => 9999,
    ]);

    $response->assertStatus(400)
        ->assertJson(['message' => 'Not enough stock available']);
});

test('customer can view their cart', function () {
    Sanctum::actingAs($this->customer);

    $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity' => 2,
    ]);

    $response = $this->getJson('/api/cart');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'cart',
            'total',
        ]);

    expect(count($response->json('cart')))->toBe(1);
    expect($response->json('total'))->toBe(22.58);
});

test('customer can remove item from cart', function () {
    Sanctum::actingAs($this->customer);

    $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity' => 2,
    ]);

    $cartItemId = $this->getJson('/api/cart')->json('cart.0.id');

    $response = $this->deleteJson("/api/cart/{$cartItemId}");

    $response->assertStatus(200)
        ->assertJson(['message' => 'Item removed from cart']);
});

test('customer can clear entire cart', function () {
    Sanctum::actingAs($this->customer);

    $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity' => 2,
    ]);

    $response = $this->deleteJson('/api/cart');

    $response->assertStatus(200)
        ->assertJson(['message' => 'Cart cleared']);
});

// =============================================================
// Cart authentication test
// =============================================================

test('unauthenticated user cannot access cart', function () {
    $response = $this->getJson('/api/cart');

    $response->assertStatus(401);
});
