<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::inRandomOrder()->value('id'),
            'total_price' => 0, // will be updated after items are created
            'status' => fake()->randomElement(['pending', 'processing', 'completed', 'cancelled']),
            'shipping_address' => fake()->address(),
            'created_at' => fake()->dateTimeBetween('-12 months', 'now'),
            'updated_at' => now(),
        ];
    }
}
