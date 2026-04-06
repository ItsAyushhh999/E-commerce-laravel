<?php

namespace App\Repositories;

use App\Contracts\ProductsRepositoryInterface;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductsRepository implements ProductsRepositoryInterface
{
    public function all(): Collection
    {
        return Product::with(['variants.attributeValues.attribute', 'images'])->get();
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
