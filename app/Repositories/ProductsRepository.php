<?php

namespace App\Repositories;

use App\Contracts\ProductsRepositoryInterface;
use App\Models\Product;

class ProductsRepository implements ProductsRepositoryInterface
{
    public function all(int $perPage = 100)
    {
        return Product::with(['variants.attributeValues.attribute', 'images'])->paginate($perPage);
    }

    public function find(int $id): ?Product
    {
        return Product::with(['variants.attributeValues.attribute', 'images'])->find($id);
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->fresh(['variants', 'images']);
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
