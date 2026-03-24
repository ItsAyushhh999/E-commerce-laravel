<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku'        => strtoupper(fake()->unique()->bothify('???-###')),
            'size'       => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'color'      => fake()->randomElement(['Red', 'Blue', 'Black', 'White']),
            'price'      => fake()->randomFloat(2, 5, 100),
            'stock'      => fake()->numberBetween(1, 50),
        ];
    }
}