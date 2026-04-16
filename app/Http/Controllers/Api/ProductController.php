<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\UpdateVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Reranking;

class ProductController extends Controller
{
    public function __construct(ProductService $service)
    {
        $this->service = $service;
    }

    // =============================
    // View all products
    // =============================

    public function index()
    {
        return response()->json($this->service->getAllProducts());
    }

    // ==================================
    // View only one product by their id
    // ==================================

    public function show(int $id)
    {
        $product = $this->service->getProduct($id);

        if (! $product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'images' => $product->images,
            'variants' => ProductVariantResource::collection($product->variants),
        ]);
    }

    // =============================================================
    // [Admin] - creating product with variants and multiple images
    // =============================================================

    public function store(StoreProductRequest $request)
    {
        $product = $this->service->createProduct(
            $request->validated(),
            $request->file('images', []),
            $request->input('variants', [])
        );

        return response()->json([
            'message' => 'Product created successfully',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'images' => $product->images,
                'variants' => ProductVariantResource::collection($product->variants),
            ],
        ], 201);
    }

    // =============================================================
    // [Admin] - update product details
    // =============================================================

    public function update(UpdateProductRequest $request, int $id)
    {
        $product = $this->service->getProduct($id);

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $updated = $this->service->updateProduct(
            $product,
            $request->validated(),
            $request->file('images', [])
        );

        return response()->json([
            'message' => 'Product details updated',
            'product' => $updated,
        ]);
    }

    // ===============================================
    // [Admin] - update variants stock and price
    // ===============================================

    public function updateVariant(UpdateVariantRequest $request, int $id)
    {
        $variant = ProductVariant::findOrFail($id);

        $variant->update($request->only('stock', 'price'));

        return response()->json([
            'message' => 'Variant updated successfully',
            'variant' => new ProductVariantResource($variant->fresh()->load('attributeValues.attribute')),
        ]);
    }

    // ==================================
    // [Admin] - delete product
    // ==================================

    public function destroy(int $id)
    {
        $product = $this->service->getProduct($id);

        if (! $product) {
            return response()->json(['message' => 'Product not Found'], 404);
        }

        $this->service->deleteProduct($product);

        return response()->json(['message' => 'Product deleted successfully']);
    }

    // ===============================================
    // [Admin] - delete a single image from a product
    // ===============================================

    public function destroyImage(int $productId, int $imageId)
    {
        $product = $this->service->getProduct($productId);

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $this->service->deleteImage($product, $imageId);

        return response()->json(['message' => 'Image deleted successfully']);
    }

    public function showDetails()
    {
        $products = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('product_variants_attribute_values as pvav', 'pvav.product_variant_id', '=', 'pv.id')
            ->join('attribute_values as av', 'av.id', '=', 'pvav.attribute_value_id')
            ->join('attributes as a', 'a.id', '=', 'av.attribute_id')
            ->select(
                'p.name  as product_name',
                'pv.id as variant_id',
                'pv.sku',
                'pv.price',
                'pv.stock',
                'a.name  as attribute',
                'av.value as attribute_value'
            )
            ->orderBy('pv.id')
            ->orderBy('a.name')
            ->get();

        return response()->json($products);
    }

    // GET - /api/counts
    public function countProductsAndVariants()
    {
        [$productCount, $variantCount, $totalStock, $totalPrice] = Concurrency::run([
            fn () => DB::table('products')->count(),
            fn () => DB::table('product_variants')->count(),
            fn () => DB::table('product_variants')->sum('stock'),
            fn () => DB::table('product_variants')->sum('price'),
        ]);

        /*$orders = Concurrency::defer([
            fn() => DB::table('orders')->count(),
        ]);
        */

        return response()->json([
            'product_count' => $productCount,
            'variant_count' => $variantCount,
            'total_stock' => $totalStock,
            'total_price' => $totalPrice,
        ]);
    }

    // GET - /api/search
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $products = Product::query()
            ->whereFullText(['name', 'description'], $request->input('query'))
            ->limit(20)
            ->get()
            ->rerank('description', $request->input('query'), limit: 10);

        return response()->json($products);
    }

    // GET - /api/rerank
    public function reranking()
    {
        $response = Reranking::of([
            'Django is a Python web framework.',
            'Laravel is a PHP web application framework.',
            'React is a JavaScript library for building user interfaces.',
        ])->rerank('PHP frameworks');

        return response()->json($response);
    }

    // GET - /api/countswoc
    public function noconcurrency()
    {
        $productCount = DB::table('products')->count();

        $variantCount = DB::table('product_variants')->count();

        $totalStock = DB::table('product_variants')->sum('stock');

        $totalPrice = DB::table('product_variants')->sum('price');

        return response()->json([
            'product_count' => $productCount,
            'variant_count' => $variantCount,
            'total_stock' => $totalStock,
            'total_price' => $totalPrice,
        ]);
    }
}
