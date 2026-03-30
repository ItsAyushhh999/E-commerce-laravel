<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    // =============================
    // View all products
    // =============================

    public function index()
    {
        $products = Product::with(['variants', 'images'])->get();

        return response()->json($products);
    }

    // ==================================
    // View only one product by their id
    // ==================================

    public function show($id)
    {
        $product = Product::with(['variants', 'images'])->find($id);

        if (! $product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json($product);
    }

    // =============================================================
    // [Admin] - creating product with variants and multiple images
    // =============================================================

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
            'primary_index' => 'nullable|integer|min:0',
            'variants' => 'required|array|min:1',
            'variants.*.size' => 'required|string',
            'variants.*.color' => 'required|string',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.stock' => 'required|integer|min:0',
        ]);

        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        // Handle multiple image uploads
        if ($request->hasFile('images')) {
            $primaryIndex = (int) $request->input('primary_index', 0);

            foreach ($request->file('images') as $index => $image) {
                $product->images()->create([
                    'path' => $image->store('products', 'public'),
                    'is_primary' => $index === $primaryIndex,
                    'sort_order' => $index,
                ]);
            }
        }

        foreach ($request->variants as $variant) {
            $sku = generate_sku($request->name, $variant['color'], $variant['size']);
            $product->variants()->create([
                'sku' => $sku,
                'size' => $variant['size'],
                'color' => $variant['color'],
                'price' => $variant['price'],
                'stock' => $variant['stock'],
            ]);
        }

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product->load(['variants', 'images']),
        ], 201);
    }

    // =============================================================
    // [Admin] - update product details
    // =============================================================

    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
            'primary_image_id' => 'nullable|integer|exists:product_images,id',
        ]);

        $product->update($request->only('name', 'description'));

        // Append new images if uploaded
        if ($request->hasFile('images')) {
            $lastOrder = $product->images()->max('sort_order') ?? -1;

            foreach ($request->file('images') as $image) {
                $product->images()->create([
                    'path' => $image->store('products', 'public'),
                    'is_primary' => false,
                    'sort_order' => ++$lastOrder,
                ]);
            }
        }

        // Change which image is primary
        if ($request->filled('primary_image_id')) {
            $product->images()->update(['is_primary' => false]);
            $product->images()
                ->where('id', $request->primary_image_id)
                ->update(['is_primary' => true]);
        }

        return response()->json([
            'message' => 'Product details updated',
            'product' => $product->load(['variants', 'images']),
        ]);
    }

    // ===============================================
    // [Admin] - update variants stock and price
    // ===============================================

    public function updateVariant(Request $request, $id)
    {
        $variant = ProductVariant::findOrFail($id);

        $request->validate([
            'stock' => 'sometimes|integer|min:0',
            'price' => 'sometimes|numeric|min:0',
        ]);

        $variant->update($request->only('stock', 'price'));

        return response()->json([
            'message' => 'Variant updated successfully',
            'variant' => $variant->fresh(),
        ]);
    }

    // ==================================
    // [Admin] - delete product
    // ==================================

    public function destroy($id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'Product not Found'], 404);
        }

        // Delete all image files from storage before deleting product
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->getRawOriginal('path'));
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    // ===============================================
    // [Admin] - delete a single image from a product
    // ===============================================
    public function destroyImage($productId, $imageId)
    {
        $product = Product::find($productId);

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $image = $product->images()->find($imageId);

        if (! $image) {
            return response()->json(['message' => 'Image not found'], 404);
        }

        $wasPrimary = $image->is_primary;

        Storage::disk('public')->delete($image->getRawOriginal('path'));
        $image->delete();

        // Auto-promote the next image to primary if deleted image was primary
        if ($wasPrimary) {
            $product->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }

        return response()->json(['message' => 'Image deleted successfully']);
    }
}
