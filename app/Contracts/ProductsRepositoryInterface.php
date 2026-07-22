<?php

namespace App\Contracts;

use App\Models\Product;

// use Illuminate\Database\Eloquent\Collection;

interface ProductsRepositoryInterface
{
    public function all(int $perPage = 100);

    public function find(int $id): ?Product;

    public function create(array $data): Product;

    public function update(Product $product, array $data): Product;

    public function delete(Product $product): void;
}
