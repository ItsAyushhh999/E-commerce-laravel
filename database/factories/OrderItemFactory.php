<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $variant = ProductVariant::inRandomOrder()->first();

        return [
            'order_id' => Order::inRandomOrder()->value('id'),
            'product_variant_id' => $variant->id,
            'quantity' => fake()->numberBetween(1, 5),
            'price' => $variant->price,
        ];
    }
}
