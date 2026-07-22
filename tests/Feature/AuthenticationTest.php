<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->customer = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
        ]);
    }

    // ===========================
    // Register Tests
    // ===========================

    public function test_customer_can_register(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user',
                'access_token',
                'token_type',
            ]);

        $this->assertSame('jane@example.com', $response->json('user.email'));
    }

    public function test_customer_cannot_register_with_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Jane Doe',
            // missing email, password
        ]);

        $response->assertStatus(422);
    }

    public function test_customer_cannot_register_with_mismatched_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertStatus(422);
    }

    // ===========================
    // Login Tests
    // ===========================

    public function test_user_can_login_with_correct_credentials(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('access_token'));
        $this->assertSame('Bearer', $response->json('token_type'));
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_cannot_login_with_wrong_email(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'notexist@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    // ===========================
    // Logout Tests
    // ===========================

    public function test_customer_can_logout(): void
    {
        Sanctum::actingAs($this->customer);

        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }

    // ===========================
    // Me Tests
    // ===========================

    public function test_customer_can_view_their_profile(): void
    {
        Sanctum::actingAs($this->customer);

        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(200);
        $this->assertSame('john@example.com', $response->json('email'));
        $this->assertSame('customer', $response->json('role'));
    }

    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
    }
}
