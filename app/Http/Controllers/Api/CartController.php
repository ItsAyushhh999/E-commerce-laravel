<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $service) {}

    // ==================================
    // View entire cart
    // ==================================

    public function index(Request $request)
    {
        $result = $this->service->getCart($request->user()->id);

        if ($result['empty']) {
            return response()->json([
                'message' => 'Your cart is empty',
                'cart' => [],
                'total' => 0,
            ]);
        }

        return response()->json($result);
    }

    // =======================
    // Add to cart
    // =======================

    public function add(Request $request)
    {
        $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $result = $this->service->addToCart(
            $request->user()->id,
            $request->product_variant_id,
            $request->quantity
        );

        if (isset($result['stock_error'])) {
            return response()->json(['message' => 'Not enough stock available'], 400);
        }

        return response()->json([
            'message' => $result['updated'] ? 'Cart updated successfully' : 'Item added to cart',
            'cart_item' => $result['cart_item'],
        ]);
    }

    // ================================
    // For Updating cart
    // ================================

    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $result = $this->service->updateCart(
            $request->user()->id,
            $id,
            $request->quantity
        );

        if (isset($result['not_found'])) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }

        if (isset($result['stock_error'])) {
            return response()->json(['message' => 'Not enough stock available'], 400);
        }

        return response()->json([
            'message' => 'Cart updated successfully',
            'cart_item' => $result['cart_item'],
        ]);
    }

    // =========================
    // Removing item
    // =========================

    public function remove(Request $request, $id)
    {
        $removed = $this->service->removeItem($request->user()->id, $id);

        if (! $removed) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }

        return response()->json(['message' => 'Item removed from cart']);
    }

    // =========================================
    // Decreasing an item by desired quantity
    // =========================================

    public function decreaseQuantity(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $result = $this->service->decreaseQuantity(
            $request->user()->id,
            $id,
            $request->quantity
        );

        if (isset($result['not_found'])) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }

        if ($result['removed']) {
            return response()->json(['message' => 'Item removed from cart']);
        }

        return response()->json([
            'message' => 'Quantity decreased',
            'cart_item' => $result['cart_item'],
        ]);
    }

    // ================================
    // Removing entire cart products
    // ================================

    public function clear(Request $request)
    {
        $this->service->clearCart($request->user()->id);

        return response()->json(['message' => 'Cart cleared']);
    }
}
