<?php

namespace App\Services;

use App\Contracts\ProductsRepositoryInterface;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function __construct(
        private ProductsRepositoryInterface $repository
    ) {}

    // ======================
    // Public Methods
    // ======================

    public function getAllProducts()
    {
        return $this->repository->all();
    }

    public function getProduct(int $id): ?Product
    {
        return $this->repository->find($id);
    }

    // Creating a product with variants and multiple images
    public function createProduct(array $data, array $images, array $variants): Product
    {
        $product = $this->repository->create([
            'name' => $data['name'],
            'description' => $data['description'],
        ]);

        $this->attachImages($product, $images, (int) ($data['primary_index'] ?? 0));
        $this->attachVariants($product, $variants);

        return $product->load(['variants', 'images']);
    }

    // Update product details
    public function updateProduct(Product $product, array $data, array $images): Product
    {
        $updated = $this->repository->update($product, array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
        ]));

        if (! empty($images)) {
            $this->appendImages($updated, $images);
        }

        if (! empty($data['primary_image_id'])) {
            $this->changePrimaryImage($updated, (int) $data['primary_image_id']);
        }

        return $updated;
    }

    // Update variant stock and price
    public function updateVariant(ProductVariant $variant, array $data): ProductVariant
    {
        $variant->update(array_filter([
            'stock' => $data['stock'] ?? null,
            'price' => $data['price'] ?? null,
        ]));

        return $variant->fresh();
    }

    // Delete product details
    public function deleteProduct(Product $product): void
    {
        $this->deleteProductImages($product);
        $this->repository->delete($product);
    }

    // Delete a specific product image
    public function deleteImage(Product $product, int $imageId): void
    {
        $image = $product->images()->findOrFail($imageId);
        $wasPrimary = $image->is_primary;

        Storage::disk('public')->delete($image->getRawOriginal('path'));
        $image->delete();

        if ($wasPrimary) {
            $product->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }
    }

    // ==================
    // Private Helpers
    // ==================

    private function attachImages(Product $product, array $images, int $primaryIndex): void
    {
        foreach ($images as $index => $image) {
            $product->images()->create([
                'path' => $image->store('products', 'public'),
                'is_primary' => $index === $primaryIndex,
                'sort_order' => $index,
            ]);
        }
    }

    private function appendImages(Product $product, array $images): void
    {
        $lastOrder = $product->images()->max('sort_order') ?? -1;

        foreach ($images as $image) {
            $product->images()->create([
                'path' => $image->store('products', 'public'),
                'is_primary' => false,
                'sort_order' => ++$lastOrder,
            ]);
        }
    }

    private function attachVariants(Product $product, array $variants): void
    {
        foreach ($variants as $variant) {
            $product->variants()->create([
                'sku' => generate_sku($product->name, $variant['color'], $variant['size']),
                'size' => $variant['size'],
                'color' => $variant['color'],
                'price' => $variant['price'],
                'stock' => $variant['stock'],
            ]);
        }
    }

    private function changePrimaryImage(Product $product, int $imageId): void
    {
        $product->images()->update(['is_primary' => false]);
        $product->images()->where('id', $imageId)->update(['is_primary' => true]);
    }

    private function deleteProductImages(Product $product): void
    {
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->getRawOriginal('path'));
        }
    }
}
