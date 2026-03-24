<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->customer = User::factory()->create([
        'role' => 'customer',
    ]);

    $this->product = Product::factory()->create();

    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'price'      => 9.99,
        'stock'      => 10,
        'size'       => 'S',
        'color'      => 'Red',
    ]);
});

test('customer can place order from cart', function () {
    Sanctum::actingAs($this->customer);

    // Add to cart first
    $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity'           => 2,
    ]);

    $response = $this->postJson('/api/orders');

    $response->assertStatus(201)
             ->assertJson(['message' => 'Order placed successfully.'])
             ->assertJsonStructure([
                 'order' => [
                     'id',
                     'total_price',
                     'status',
                     'items',
                 ],
             ]);

    expect($response->json('order.status'))->toBe('pending');
    expect($response->json('order.total_price'))->toBe(19.98);
});

test('stock is deducted after order placed', function () {
    Sanctum::actingAs($this->customer);

    $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity'           => 3,
    ]);

    $this->postJson('/api/orders');

    $this->variant->refresh();
    expect($this->variant->stock)->toBe(7);
});

test('cart is cleared after order placed', function () {
    Sanctum::actingAs($this->customer);

    $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity'           => 2,
    ]);

    $this->postJson('/api/orders');

    $response = $this->getJson('/api/cart');
    expect(count($response->json('cart')))->toBe(0);
});

test('customer cannot place order with empty cart', function () {
    $freshCustomer = User::factory()->create(['role' => 'customer']);
    Sanctum::actingAs($freshCustomer);

    $response = $this->postJson('/api/orders');

    $response->assertStatus(400)
             ->assertJson(['message' => 'Your cart is empty']);
});

test('customer can view their orders', function () {
    Sanctum::actingAs($this->customer);

    $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity'           => 1,
    ]);
    $this->postJson('/api/orders');

    $response = $this->getJson('/api/orders');

    $response->assertStatus(200);
    expect(count($response->json()))->toBe(1);
});

test('customer can view single order', function () {
    Sanctum::actingAs($this->customer);

    $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity'           => 1,
    ]);
    $this->postJson('/api/orders');

    $orderId = $this->getJson('/api/orders')->json('0.id');

    $response = $this->getJson("/api/orders/{$orderId}");

    $response->assertStatus(200);
    expect($response->json('id'))->toBe($orderId);
});

test('admin can view all orders', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson('/api/admin/orders');

    $response->assertStatus(200);
});

test('admin can update order status', function () {
    // Place order as customer
    Sanctum::actingAs($this->customer);
    $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity'           => 1,
    ]);
    $this->postJson('/api/orders');
    $orderId = $this->getJson('/api/orders')->json('0.id');

    // Switch to admin - use actingAs with guard specified
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->putJson("/api/admin/orders/{$orderId}", [
        'status' => 'processing',
    ]);

    $response->assertStatus(200);
    expect($response->json('order.status'))->toBe('processing');
});

test('admin cannot set invalid order status', function () {
    Sanctum::actingAs($this->customer);
    $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity'           => 1,
    ]);
    $this->postJson('/api/orders');
    $orderId = $this->getJson('/api/orders')->json('0.id');

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->putJson("/api/admin/orders/{$orderId}", [
        'status' => 'invalid_status',
    ]);

    $response->assertStatus(422);
});

test('customer cannot view another customers order', function () {
    Sanctum::actingAs($this->customer);

    $this->postJson('/api/cart', [
        'product_variant_id' => $this->variant->id,
        'quantity'           => 1,
    ]);
    $this->postJson('/api/orders');
    $orderId = $this->getJson('/api/orders')->json('0.id');

    // login as different customer
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    Sanctum::actingAs($otherCustomer);

    $response = $this->getJson("/api/orders/{$orderId}");

    $response->assertStatus(404);
});

test('unauthenticated user cannot place order', function () {
    $response = $this->postJson('/api/orders');

    $response->assertStatus(401);
});