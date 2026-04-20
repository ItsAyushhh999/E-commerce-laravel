<?php

namespace App\Repositories;

use App\Contracts\CartRepositoryInterface;
use App\Models\Cart;
use Illuminate\Support\Collection;

class CartRepository implements CartRepositoryInterface
{
    public function getCartByUser(int $userId): Collection
    {
        return Cart::where('user_id', $userId)
            ->with(['productVariant.product', 'productVariant.attributeValues.attribute'])
            ->get();
    }

    public function findItem(int $userId, int $variantId): ?Cart
    {
        return Cart::where('user_id', $userId)
            ->where('product_variant_id', $variantId)
            ->first();
    }

    public function findItemById(int $userId, int $cartId): ?Cart
    {
        return Cart::where('id', $cartId)
            ->where('user_id', $userId)
            ->first();
    }

    public function addItem(int $userId, int $variantId, int $quantity): Cart
    {
        return Cart::create([
            'user_id' => $userId,
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
        ]);
    }

    public function updateItemQuantity(Cart $cartItem, int $quantity): Cart
    {
        $cartItem->update(['quantity' => $quantity]);

        return $cartItem->load(['productVariant.product', 'productVariant.attributeValues.attribute']);
    }

    public function removeItem(Cart $cartItem): void
    {
        $cartItem->delete();
    }

    public function clearCart(int $userId): void
    {
        Cart::where('user_id', $userId)->delete();
    }
}
