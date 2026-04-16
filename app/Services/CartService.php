<?php

namespace App\Services;

use App\Contracts\CartRepositoryInterface;
use App\Models\ProductVariant;

class CartService
{
    public function __construct(private CartRepositoryInterface $cartRepository)
    {
        //
    }

    // get current user cart
    public function getCart(int $userId): array
    {
        $cart = $this->cartRepository->getCartByUser($userId);

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

        $cartItem = $this->cartRepository->findItem($userId, $variantId);

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $quantity;

            if ($variant->stock < $newQuantity) {
                return ['stock_error' => true];
            }

            $this->cartRepository->updateItemQuantity($cartItem, $newQuantity);

            return [
                'updated' => true,
                'cart_item' => $cartItem->load(['productvariant.product', 'productvariant.attributeValues.attribute']),
            ];
        }

        $cartItem = $this->cartRepository->addItem($userId, $variantId, $quantity);

        return [
            'updated' => false,
            'cart_item' => $cartItem->load(['productvariant.product', 'productvariant.attributeValues.attribute']),
        ];
    }

    // update the cart items quantity
    public function updateCart(int $userId, int $cartId, int $quantity): array
    {
        $cartItem = $this->cartRepository->findItemById($userId, $cartId);

        if (! $cartItem) {
            return ['not_found' => true];
        }

        $variant = ProductVariant::find($cartItem->product_variant_id);
        $newQuantity = $cartItem->quantity + $quantity;

        if ($variant->stock < $quantity) {
            return ['stock_error' => true];
        }

        $this->cartRepository->updateItemQuantity($cartItem, $newQuantity);

        return [
            'cart_item' => $cartItem->load(['productvariant.product', 'productvariant.attributeValues.attribute']),
        ];
    }

    // remove item from cart
    public function removeItem(int $userId, int $cartId): bool
    {
        $cartItem = $this->cartRepository->findItemById($userId, $cartId);

        if (! $cartItem) {
            return false;
        }

        $this->cartRepository->removeItem($cartItem);

        return true;
    }

    // decrease item quantity or remove if quantity becomes 0
    public function decreaseQuantity(int $userId, int $cartId, int $quantity): array
    {
        $cartItem = $this->cartRepository->findItemById($userId, $cartId);

        if (! $cartItem) {
            return ['not_found' => true];
        }

        $newQuantity = $cartItem->quantity - $quantity;

        if ($newQuantity <= 0) {
            $this->cartRepository->removeItem($cartItem);

            return ['removed' => true];
        }

        $this->cartRepository->updateItemQuantity($cartItem, $newQuantity);

        return [
            'removed' => false,
            'cart_item' => $cartItem->load(['productVariant.product', 'productVariant.attributeValues.attribute']),
        ];
    }

    // clear the cart after order placed
    public function clearCart(int $userId): void
    {
        $this->cartRepository->clearCart($userId);
    }
}
