<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\ProductVariant;

class CartService
{
    // get current user cart
    public function getCart(int $userId): array
    {
        $cart = Cart::where('user_id', $userId)
            ->with('productvariant.product')
            ->get();

        if ($cart->isEmpty()) {
            return ['empty' => true, 'cart' => [], 'total' => 0];
        }

        $total = $cart->sum(fn ($item) => $item->productvariant->price * $item->quantity);

        return [
            'empty' => false,
            'cart' => $cart,
            'total' => $total,
            'total_formatted' => format_price($total),
        ];
    }

    // add items to a cart
    public function addToCart(int $userId, int $variantId, int $quantity): array
    {
        $variant = ProductVariant::findOrFail($variantId);

        if ($variant->stock < $quantity) {
            return ['stock_error' => true];
        }

        $cartItem = Cart::where('user_id', $userId)
            ->where('product_variant_id', $variantId)
            ->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $quantity;

            if ($variant->stock < $newQuantity) {
                return ['stock_error' => true];
            }

            $cartItem->update(['quantity' => $newQuantity]);

            return [
                'updated' => true,
                'cart_item' => $cartItem->load('productvariant.product'),
            ];
        }

        $cartItem = Cart::create([
            'user_id' => $userId,
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
        ]);

        return [
            'updated' => false,
            'cart_item' => $cartItem->load('productvariant.product'),
        ];
    }

    // update the cart items quantity
    public function updateCart(int $userId, int $cartId, int $quantity): array
    {
        $cartItem = Cart::where('id', $cartId)
            ->where('user_id', $userId)
            ->first();

        if (! $cartItem) {
            return ['not_found' => true];
        }

        $variant = ProductVariant::find($cartItem->product_variant_id);
        $newQuantity = $cartItem->quantity + $quantity;

        if ($variant->stock < $quantity) {
            return ['stock_error' => true];
        }

        $cartItem->update(['quantity' => $newQuantity]);

        return [
            'cart_item' => $cartItem->load('productvariant.product'),
        ];
    }

    // remove item from cart
    public function removeItem(int $userId, int $cartId): bool
    {
        $cartItem = Cart::where('id', $cartId)
            ->where('user_id', $userId)
            ->first();

        if (! $cartItem) {
            return false;
        }

        $cartItem->delete();

        return true;
    }

    // decrease item quantity or remove if quantity becomes 0
    public function decreaseQuantity(int $userId, int $cartId, int $quantity): array
    {
        $cartItem = Cart::where('id', $cartId)
            ->where('user_id', $userId)
            ->first();

        if (! $cartItem) {
            return ['not_found' => true];
        }

        $newQuantity = $cartItem->quantity - $quantity;

        if ($newQuantity <= 0) {
            $cartItem->delete();

            return ['removed' => true];
        }

        $cartItem->update(['quantity' => $newQuantity]);

        return [
            'removed' => false,
            'cart_item' => $cartItem->load('productVariant.product'),
        ];
    }

    // clear the cart after order placed
    public function clearCart(int $userId): void
    {
        Cart::where('user_id', $userId)->delete();
    }
}
