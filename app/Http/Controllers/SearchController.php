<?php

namespace App\Http\Controllers;

use App\Models\AttributeValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['required', 'max:255'],
            'type' => ['required', 'in:products,variants,attributes,orders'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $search = $request->search;
        $likeSearch = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';

        $search = $request->search;

        if ($request->type === 'products') {
            $results = Product::where(function ($q) use ($search, $likeSearch) {
                $q->where('id', $search)
                    ->orWhere('name', 'like', "$likeSearch");
            })
                ->select('id', 'name', 'created_at')
                ->paginate(250);

        } elseif ($request->type === 'variants') {
            $results = ProductVariant::with('product:id,name')
                ->where(function ($q) use ($search, $likeSearch) {
                    $q->where('id', $search)
                        ->orWhere('sku', 'like', "$likeSearch");
                })
                ->select('id', 'product_id', 'sku', 'price', 'stock')
                ->paginate(250);

        } elseif ($request->type === 'attributes') {
            $results = AttributeValue::with('attribute:id,name')
                ->where(function ($q) use ($search, $likeSearch) {
                    $q->where('id', $search)
                        ->orWhere('value', 'like', "$likeSearch")
                        ->orWhereHas('attribute', function ($aq) use ($likeSearch) {
                            $aq->where('name', 'like', "$likeSearch");
                        });
                })
                ->select('id', 'attribute_id', 'value')
                ->paginate(250);

        } else { // orders
            $results = Order::with(['user:id,name,email', 'items:id,order_id,product_variant_id,quantity,price'])
                ->where(function ($q) use ($search, $likeSearch) {
                    $q->where('id', $search)
                        ->orWhere('status', 'like', "$likeSearch")
                        ->orWhere('shipping_address', 'like', "$likeSearch")
                        ->orWhereHas('user', function ($uq) use ($likeSearch) {
                            $uq->where('name', 'like', "$likeSearch")
                                ->orWhere('email', 'like', "$likeSearch");
                        })
                        ->orwhereHas('items', function ($iq) use ($search) {
                            $iq->where('product_variant_id', $search)
                                ->orWhere('price', $search);
                        });
                })
                ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->from))
                ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->to))
                ->select('id', 'user_id', 'status', 'shipping_address', 'total_price', 'created_at')
                ->paginate(250);
        }

        return response()->json([
            'results' => $results,
            'type' => $request->type,
            'search' => $search,
        ]);
    }

    public function searchAll(Request $request)
    {
        $request->validate([
            'search' => ['required', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $search = $request->search;
        $likeSearch = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';

        $products = Product::where(function ($q) use ($search, $likeSearch) {
            $q->where('id', $search)
                ->orWhere('name', 'like', "$likeSearch");
        })
            ->select('id', 'name', 'created_at')
            ->paginate(250);

        $variants = ProductVariant::with('product:id,name')
            ->where(function ($q) use ($search, $likeSearch) {
                $q->where('id', $search)
                    ->orWhere('sku', 'like', "$likeSearch");
            })
            ->select('id', 'product_id', 'sku', 'price', 'stock')
            ->paginate(250);

        $attributes = AttributeValue::with('attribute:id,name')
            ->where(function ($q) use ($search, $likeSearch) {
                $q->where('id', $search)
                    ->orWhere('value', 'like', "$likeSearch")
                    ->orWhereHas('attribute', function ($aq) use ($likeSearch) {
                        $aq->where('name', 'like', "$likeSearch");
                    });
            })
            ->select('id', 'attribute_id', 'value')
            ->paginate(250);

        $orders = Order::with(['user:id,name,email', 'items'])
            ->where(function ($q) use ($search, $likeSearch) {
                $q->where('id', $search)
                    ->orWhere('status', 'like', "$likeSearch")
                    ->orWhere('shipping_address', 'like', "$likeSearch")
                    ->orWhereHas('user', function ($uq) use ($likeSearch) {
                        $uq->where('name', 'like', "$likeSearch")
                            ->orWhere('email', 'like', "$likeSearch");
                    });
            })
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->to))
            ->select('id', 'user_id', 'status', 'shipping_address', 'total_price', 'created_at')
            ->paginate(250);

        return response()->json([
            'search' => $search,
            'results' => [
                'products' => $products,
                'variants' => $variants,
                'attributes' => $attributes,
                'orders' => $orders,
            ],
        ]);
    }
}
