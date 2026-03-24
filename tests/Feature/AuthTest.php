<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'email'    => 'admin@example.com',
        'password' => Hash::make('password'),
        'role'     => 'admin',
    ]);

    $this->customer = User::factory()->create([
        'email'    => 'john@example.com',
        'password' => Hash::make('password123'),
        'role'     => 'customer',
    ]);
});

// ===========================
// Register Tests
// ===========================

test('customer can register', function () {
    $response = $this->postJson('/api/register', [
        'name'                  => 'Jane Doe',
        'email'                 => 'jane@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
             ->assertJsonStructure([
                 'user',
                 'access_token',
                 'token_type',
             ]);

    expect($response->json('user.email'))->toBe('jane@example.com');
});

test('customer cannot register with missing fields', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        // missing email, password
    ]);

    $response->assertStatus(422);
});

test('customer cannot register with mismatched password confirmation', function () {
    $response = $this->postJson('/api/register', [
        'name'                  => 'Jane Doe',
        'email'                 => 'jane@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'differentpassword',
    ]);

    $response->assertStatus(422);
});

// ===========================
// Login Tests
// ===========================

test('user can login with correct credentials', function () {
    // use the customer already created in beforeEach
    $response = $this->postJson('/api/login', [
        'email'    => 'john@example.com',
        'password' => 'password123',
    ]);
    $response->assertStatus(200);
    expect($response->json('access_token'))->not->toBeNull();
    expect($response->json('token_type'))->toBe('Bearer');
});

test('user cannot login with wrong password', function () {
    $response = $this->postJson('/api/login', [
        'email'    => 'john@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(422);
});

test('user cannot login with wrong email', function () {
    $response = $this->postJson('/api/login', [
        'email'    => 'notexist@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(422);
});

// ===========================
// Logout Tests
// ===========================

test('customer can logout', function () {
    Sanctum::actingAs($this->customer);

    $response = $this->postJson('/api/logout');

    $response->assertStatus(200)
             ->assertJson(['message' => 'Logged out successfully']);
});

test('unauthenticated user cannot logout', function () {
    $response = $this->postJson('/api/logout');

    $response->assertStatus(401);
});

// ===========================
// Me Tests
// ===========================

test('customer can view their profile', function () {
    Sanctum::actingAs($this->customer);

    $response = $this->getJson('/api/me');

    $response->assertStatus(200);
    expect($response->json('email'))->toBe('john@example.com');
    expect($response->json('role'))->toBe('customer');
});

test('unauthenticated user cannot view profile', function () {
    $response = $this->getJson('/api/me');

    $response->assertStatus(401);
});