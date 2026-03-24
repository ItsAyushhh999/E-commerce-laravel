<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    //view all prod
    public function index()
    {
        $products = Product::with('variants')->get();

        return response()->json($products);
    }
    
    //view only one
    public function show($id)
    {
        $product = Product::with('variants')->find($id);

        if(!$product){
            return response()->json([
                'message' => 'Product not found',
            ],404);
        }

        return response()->json($product);
    }

    //admin - creating product with variant
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'nullable|string',
            'variants' => 'required|array|min:1',
            'variants.*.size' => 'required|string',
            'variants.*.color' => 'required|string',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.stock' => 'required|integer|min:0',
        ]);

        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'image' => $request->image,
        ]);

        foreach($request->variants as $variant){
            //generating sku
            $sku = $this->generateSku($request->name, $variant['color'], $variant['size']);
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
            'product' => $product->load('variants'),
        ], 201);
    }

    //SKU genereate help
    private function generateSku($productName, $color, $size)
    {
        //get initials from product name
        $initials = collect(explode(' ', strtoupper($productName)))
                    ->map(fn($word)=>substr($word, 0, 1))
                    ->implode('');
        
        $colorCode =   strtoupper(substr($color, 0, 3));
        $sizeCode = strtoupper($size); 

        $base = "{$initials}-{$colorCode}-{$sizeCode}";

        //keeping sku unique by adding number 
        $sku = $base;
        $count =1;

        while(ProductVariant::where('sku', $sku)->exists()){
            $sku = "{$base}-{$count}";
            $count++;
        }

        return $sku;
    }

    //admin- update product
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if(!$product){
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
        ]);

        $product->update($request->only('name', 'description', 'image'));

        return response()->json([
            'message' => 'Product details updated',
            'product' => $product->load('variants'),
        ]);
    }

    //admin - delete product
    public function destroy($id)
    {
        $product = Product::find($id);

        if(!$product){
            return response()->json([
                'message' => 'Product not Found',
            ], 404);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }
}
