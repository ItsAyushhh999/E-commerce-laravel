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

    // fetches the cart items of authenticated users and calculates the total price of the cart. It returns an array containing the cart items, total price, and a flag indicating if the cart is empty.

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

    // Adds a product variant to the cart. It checks if the requested quantity is available in stock and either updates the existing cart item or creates a new one. It returns an array indicating whether the item was updated or added, along with the cart item details. If there is a stock error, it returns an array with a 'stock_error' flag set to true.

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

    // Updates the quantity of a specific cart item. It checks if the cart item exists and if the requested quantity is available in stock. If the cart item is not found, it returns an array with a 'not_found' flag set to true. If there is a stock error, it returns an array with a 'stock_error' flag set to true. Otherwise, it updates the cart item quantity and returns the updated cart item details.

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

    // Removes a specific item from the cart. It returns true if the item was successfully removed, or false if the cart item was not found.

    public function removeItem(int $userId, int $cartId): bool
    {
        $cartItem = $this->cartRepository->findItemById($userId, $cartId);

        if (! $cartItem) {
            return false;
        }

        $this->cartRepository->removeItem($cartItem);

        return true;
    }

    // Decreases the quantity of a specific cart item or removes it if the quantity becomes 0

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

    // Clears all items from the user's cart. It does not return any value.

    public function clearCart(int $userId): void
    {
        $this->cartRepository->clearCart($userId);
    }
}
