<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)
            ->with('productvariant.product')
            ->get();

        $total = $cart->sum(function ($item) {
            return $item->productvariant->price * $item->quantity;
        });

        return response()->json([
            'cart' => $cart,
            'total' => $total,
        ]);
    }

    // add to cart
    public function add(Request $request)
    {
        $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $variant = ProductVariant::find($request->product_variant_id);

        // checking for enough stock
        if ($variant->stock < $request->quantity) {
            return response()->json([
                'message' => 'Not enough stock available',
            ], 400);
        }

        $cartItem = Cart::where('user_id', $request->user()->id)
            ->where('product_variant_id', $request->product_variant_id)
            ->first();

        // for adding item and quantity if already there
        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $request->quantity;

            if ($variant->stock < $newQuantity) {
                return response()->json([
                    'message' => 'Not enough stock available',
                ], 400);
            }

            $cartItem->update(['quantity' => $newQuantity]);

            return response()->json([
                'message' => 'Cart updated successfully',
                'cart_item' => $cartItem->load('productvariant.product'),
            ]);
        }

        // for new item
        $cartItem = Cart::create([
            'user_id' => $request->user()->id,
            'product_variant_id' => $request->product_variant_id,
            'quantity' => $request->quantity,
        ]);

        return response()->json([
            'message' => 'Item added to cart',
            'cart' => $cartItem->load('productvariant.product'),
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $cartItem = Cart::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $cartItem) {
            return response()->json([
                'message' => 'Cart item not found',
            ], 404);
        }

        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $variant = ProductVariant::find($cartItem->product_variant_id);
        $newQuantity = $cartItem->quantity + $request->quantity;

        if ($variant->stock < $request->quantity) {
            return response()->json([
                'message' => 'Not enough stock available',
            ], 404);
        }

        $cartItem->update(['quantity' => $newQuantity]);

        return response()->json([
            'message' => 'Cart Updated Successfully',
            'cart_item' => $cartItem->load('productvariant.product'),
        ]);
    }

    // removing item
    public function remove(Request $request, $id)
    {
        $cartItem = Cart::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $cartItem) {
            return response()->json([
                'message' => 'Cart item not found',
            ], 404);
        }

        $cartItem->delete();

        return response()->json([
            'message' => 'Item removed from cart',
        ]);
    }

    // removing 1 item by 1 quantity
    public function decreaseQuantity(Request $request, $id)
    {
        $cartItem = Cart::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $cartItem) {
            return response()->json([
                'message' => 'Cart item not found',
            ], 404);
        }

        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $newQuantity = $cartItem->quantity - $request->quantity;

        if ($newQuantity <= 0) {
            // if result is 0 or less, delete the item completely
            $cartItem->delete();

            return response()->json([
                'message' => 'Item removed from cart',
            ]);
        }

        $cartItem->update(['quantity' => $newQuantity]);

        return response()->json([
            'message' => 'Quantity decreased',
            'cart_item' => $cartItem->load('productVariant.product'),
        ]);
    }

    public function clear(Request $request)
    {
        Cart::where('user_id', $request->user()->id)
            ->delete();

        return response()->json([
            'message' => 'Cart cleared',
        ]);
    }
}
