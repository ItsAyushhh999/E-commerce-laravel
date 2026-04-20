<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $service) {}

    //
    // Returns the cart items for the authenticated user along with total price
    //

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

    //
    // Adds a product variant to the cart or updates quantity if it already exists in the cart
    //

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

    //
    // Updates the quantity of a cart item
    //

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

    //
    // Removes an item from the cart
    //

    public function remove(Request $request, $id)
    {
        $removed = $this->service->removeItem($request->user()->id, $id);

        if (! $removed) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }

        return response()->json(['message' => 'Item removed from cart']);
    }

    //
    // Decreases the quantity of a cart item or removes it if quantity becomes 0
    //

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

    //
    // Clears the entire cart for the authenticated user
    //

    public function clear(Request $request)
    {
        $this->service->clearCart($request->user()->id);

        return response()->json(['message' => 'Cart cleared']);
    }
}
