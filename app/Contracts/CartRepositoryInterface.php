<?php

namespace App\Contracts;

use App\Models\Cart;
use Illuminate\Support\Collection;

interface CartRepositoryInterface
{
    public function getCartByUser(int $userId): Collection;

    public function findItem(int $userId, int $variantId): ?Cart;

    public function findItemById(int $userId, int $cartId): ?Cart;

    public function addItem(int $userId, int $variantId, int $quantity): Cart;

    public function updateItemQuantity(Cart $cartItem, int $quantity): Cart;

    public function removeItem(Cart $cartItem): void;

    public function clearCart(int $userId): void;
}
